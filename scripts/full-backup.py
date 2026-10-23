#!/usr/bin/env python3
"""Local disaster recovery bundle for the recovered panel, never a live restore."""
import argparse
from contextlib import closing
import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import signal
import sqlite3
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[1]
TABLES = ('users', 'empresas', 'servidores', 'listas', 'dominios', 'jobs', 'failed_jobs')


def run(*args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)


def output(*args):
    return subprocess.check_output(args).decode().strip()


def compose(*args):
    return ['docker', 'compose', '-f', str(ROOT / 'compose.staging.yml'), *args]


def sha256(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def database_info(path):
    with closing(sqlite3.connect(path.resolve().as_uri() + '?mode=ro', uri=True)) as db:
        if db.execute('PRAGMA integrity_check').fetchall() != [('ok',)]:
            raise ValueError('SQLite integrity check failed')
        if db.execute('PRAGMA foreign_key_check').fetchone():
            raise ValueError('SQLite foreign key check failed')
        return {table: db.execute(f'SELECT count(*) FROM {table}').fetchone()[0] for table in TABLES}


def pack(directory, target):
    with tarfile.open(target, 'w') as archive:
        for path in sorted(directory.iterdir()):
            archive.add(path, arcname=path.name)


def unpack(archive, destination):
    # The standard data filter rejects traversal, devices and escaping links.
    with tarfile.open(archive) as source:
        source.extractall(destination, filter='data')


def verify(bundle, destination=None):
    """Verify all payload hashes, DB integrity and extraction before publishing."""
    with tempfile.TemporaryDirectory(prefix='.verify-', dir=bundle.parent) as temp:
        root = Path(temp)
        unpack(bundle, root)
        manifest = json.loads((root / 'manifest.json').read_text())
        if manifest['format'] != 1:
            raise ValueError('Unsupported backup format')
        expected = {'database.sqlite', 'project.tar', 'storage.tar', 'host.tar', 'images.tar'}
        if set(manifest['files']) != expected:
            raise ValueError('Unexpected backup payload')
        for name, digest in manifest['files'].items():
            if sha256(root / name) != digest:
                raise ValueError(f'Checksum mismatch: {name}')
        if database_info(root / 'database.sqlite') != manifest['counts']:
            raise ValueError('Database counts differ')
        for name in ('project', 'storage', 'host'):
            unpack(root / f'{name}.tar', root / name)
        if not (root / 'project/.env.staging').is_file():
            raise ValueError('Runtime environment missing')
        if destination:
            # mkdir is exclusive: never overwrite a previous restore or live path.
            destination.mkdir(mode=0o700, parents=False, exist_ok=False)
            for path in root.iterdir():
                shutil.move(str(path), destination / path.name)
        return manifest


def create(directory):
    if os.geteuid() != 0:
        raise PermissionError('Run creation as root to read runtime volumes and secrets')
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    if directory.stat().st_mode & 0o077:
        raise PermissionError('Backup directory must be private (0700)')
    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    final = directory / f'panel-full-{timestamp}.tar'
    if final.exists():
        raise FileExistsError(final)
    with tempfile.TemporaryDirectory(prefix='.capture-', dir=directory) as temp:
        work = Path(temp)
        app_id = output(*compose('ps', '-q', 'app'))
        containers = json.loads(output('docker', 'inspect', app_id))
        mounts = {m['Destination']: Path(m['Source']) for m in containers[0]['Mounts']}
        services = output(*compose('ps', '--status', 'running', '--services')).splitlines()
        if not {'app', 'nginx'}.issubset(services):
            raise ValueError('Expected app and nginx running before capture')
        ids = output(*compose('ps', '-q')).splitlines()
        gateway_id = output('docker', 'compose', '-f', str(ROOT / 'compose.preview.yml'), 'ps', '-q', 'gateway')
        images = sorted({json.loads(output('docker', 'inspect', cid))[0]['Config']['Image']
                         for cid in [*ids, gateway_id]})
        print('Pausing panel services for consistent capture', flush=True)
        try:
            run(*compose('stop', *services))
            with closing(sqlite3.connect((mounts['/data'] / 'database.sqlite').as_uri() + '?mode=ro', uri=True)) as source:
                with closing(sqlite3.connect(work / 'database.sqlite')) as snapshot:
                    source.backup(snapshot)
            counts = database_info(work / 'database.sqlite')
            pack(mounts['/var/www/html/storage'], work / 'storage.tar')
            tracked = subprocess.check_output(['git', '-C', str(ROOT), 'ls-files', '-z']).decode().split('\0')
            with tarfile.open(work / 'project.tar', 'w') as archive:
                for name in filter(None, tracked):
                    archive.add(ROOT / name, arcname=name, recursive=False)
                archive.add(ROOT / '.env.staging', arcname='.env.staging')
                for name in ('preview-tls', 'letsencrypt', 'acme'):
                    path = ROOT / '.recovery' / name
                    if path.exists():
                        archive.add(path, arcname=f'.recovery/{name}')
            with tarfile.open(work / 'host.tar', 'w') as archive:
                for base in ('/etc/systemd/system', '/usr/local/sbin', '/etc/logrotate.d'):
                    for path in sorted(Path(base).glob('dns-panel-rpz*')):
                        archive.add(path, arcname=str(path).lstrip('/'))
                for base in ('/etc/fail2ban/jail.d', '/etc/fail2ban/filter.d', '/etc/fail2ban/action.d'):
                    for path in sorted(Path(base).glob('*rpz*')):
                        archive.add(path, arcname=str(path).lstrip('/'))
        finally:
            print('Resuming original panel services', flush=True)
            run(*compose('start', '--wait', *services))
        print('Saving Docker images after service recovery', flush=True)
        run('docker', 'image', 'save', '-o', str(work / 'images.tar'), *images)
        manifest = {
            'format': 1, 'created_at': timestamp,
            'commit': output('git', '-C', str(ROOT), 'rev-parse', 'HEAD'),
            'counts': counts, 'images': images,
            'image_ids': {name: output('docker', 'image', 'inspect', name, '--format', '{{.Id}}') for name in images},
            'files': {path.name: sha256(path) for path in work.iterdir()},
            'scope': 'Recovered panel; excludes old backups, historical recovery DB and unrelated host services',
        }
        (work / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
        partial = directory / (final.name + '.partial')
        try:
            pack(work, partial)
            verify(partial)
            partial.rename(final)
        finally:
            partial.unlink(missing_ok=True)
    print(json.dumps({'backup': str(final), 'bytes': final.stat().st_size, 'sha256': sha256(final), 'counts': counts}))


def smoke_test(directory):
    """Boot restored application without a network or any production mount."""
    manifest = json.loads((directory / 'manifest.json').read_text())
    project = directory / 'project'
    config = json.loads(output('docker', 'compose', '-f', str(project / 'compose.staging.yml'),
                               'config', '--format', 'json'))
    environment = config['services']['app']['environment']
    environment.update(DB_DATABASE='/data/database.sqlite', DB_URL='', CACHE_STORE='array',
                       SESSION_DRIVER='array', QUEUE_CONNECTION='sync', LOG_CHANNEL='stderr',
                       VIEW_COMPILED_PATH='/tmp/views', RPZ_PRIMARY_ENABLED='false',
                       RPZ_NOTIFY_ENABLED='false', EXTERNAL_SYNC_ENABLED='false')
    with tempfile.TemporaryDirectory(prefix='.smoke-', dir=directory) as temp:
        env = Path(temp) / 'runtime.env'
        env.write_text(''.join(f'{key}={value}\n' for key, value in environment.items()))
        image = config['services']['app']['image']
        # Pin to the exact captured image, not a mutable current tag.
        image_id = manifest['image_ids'][image]
        code = '''require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);
        $response = $kernel->handle(Illuminate\\Http\\Request::create("https://rpz.trevizamnetwork.com.br/login"));
        if ($response->getStatusCode() !== 200) { throw new RuntimeException("Login restore failed"); }
        echo json_encode(["login_status" => 200, "users" => App\\Models\\User::count(), "php" => PHP_VERSION]), PHP_EOL;
        '''
        result = json.loads(output('docker', 'run', '--rm', '--network', 'none', '--read-only',
                                   '--user', '0:0', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
                                   '--tmpfs', '/tmp', '--tmpfs', '/var/www/html/bootstrap/cache',
                                   '--env-file', str(env), '-v', f'{directory}:/data',
                                   '-v', f'{directory / "storage"}:/var/www/html/storage',
                                   image_id, 'php', '-r', code))
        if result['users'] != manifest['counts']['users']:
            raise ValueError('Restored application count differs')
        print(json.dumps(result))


def main():
    os.umask(0o077)
    def interrupted(signum, frame):
        raise InterruptedError(f'Interrupted by signal {signum}')
    signal.signal(signal.SIGTERM, interrupted)
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    commands.add_parser('create').add_argument('--directory', type=Path, required=True)
    validation = commands.add_parser('verify')
    validation.add_argument('bundle', type=Path)
    validation.add_argument('--restore-to', type=Path)
    commands.add_parser('smoke-test').add_argument('directory', type=Path)
    args = parser.parse_args()
    if args.command == 'create':
        # A host lock prevents overlapping captures and restart races.
        import fcntl
        with (ROOT / '.recovery/full-backup.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            create(args.directory.resolve())
    elif args.command == 'verify':
        print(json.dumps(verify(args.bundle.resolve(), args.restore_to)))
    else:
        smoke_test(args.directory.resolve())


if __name__ == '__main__':
    main()
