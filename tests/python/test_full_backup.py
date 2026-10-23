import importlib.util
from contextlib import closing
import io
import json
from pathlib import Path
import sqlite3
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('full_backup', Path(__file__).parents[2] / 'scripts/full-backup.py')
backup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(backup)


class FullBackupTest(unittest.TestCase):
    def fixture(self, root):
        payload = root / 'payload'
        payload.mkdir()
        with closing(sqlite3.connect(payload / 'database.sqlite')) as db, db:
            for table in backup.TABLES:
                db.execute(f'CREATE TABLE {table} (id INTEGER PRIMARY KEY)')
                db.execute(f'INSERT INTO {table} VALUES (1)')
        for name in ('project', 'storage', 'host'):
            with tarfile.open(payload / f'{name}.tar', 'w') as archive:
                info = tarfile.TarInfo('.env.staging' if name == 'project' else 'file.txt')
                data = b'APP_KEY=synthetic-only\n' if name == 'project' else b'content'
                info.size = len(data)
                archive.addfile(info, io.BytesIO(data))
        (payload / 'images.tar').write_bytes(b'synthetic-docker-archive')
        manifest = {'format': 1, 'counts': {t: 1 for t in backup.TABLES},
                    'files': {p.name: backup.sha256(p) for p in payload.iterdir()}}
        (payload / 'manifest.json').write_text(json.dumps(manifest))
        bundle = root / 'backup.tar'
        backup.pack(payload, bundle)
        return bundle, payload

    def test_restores_verified_database_files_and_env_without_overwriting(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            bundle, _ = self.fixture(root)
            destination = root / 'restored'
            result = backup.verify(bundle, destination)
            self.assertEqual(1, result['counts']['users'])
            self.assertTrue((destination / 'project/.env.staging').is_file())
            self.assertEqual(b'content', (destination / 'storage/file.txt').read_bytes())
            with self.assertRaises(FileExistsError):
                backup.verify(bundle, destination)

    def test_modified_payload_is_rejected_before_destination_is_created(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            bundle, payload = self.fixture(root)
            (payload / 'storage.tar').write_bytes(b'corrupted')
            backup.pack(payload, bundle)
            with self.assertRaisesRegex(ValueError, 'Checksum mismatch'):
                backup.verify(bundle, root / 'restored')
            self.assertFalse((root / 'restored').exists())

    def test_path_traversal_is_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            archive = root / 'bad.tar'
            with tarfile.open(archive, 'w') as stream:
                stream.addfile(tarfile.TarInfo('../escape'))
            with self.assertRaises(tarfile.FilterError):
                backup.unpack(archive, root / 'destination')
            self.assertFalse((root / 'escape').exists())

    def test_foreign_key_corruption_is_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            bundle, payload = self.fixture(root)
            with closing(sqlite3.connect(payload / 'database.sqlite')) as db, db:
                db.execute('CREATE TABLE child (parent_id REFERENCES users(id))')
                db.execute('INSERT INTO child VALUES (99)')
            manifest = json.loads((payload / 'manifest.json').read_text())
            manifest['files']['database.sqlite'] = backup.sha256(payload / 'database.sqlite')
            (payload / 'manifest.json').write_text(json.dumps(manifest))
            backup.pack(payload, bundle)
            with self.assertRaisesRegex(ValueError, 'foreign key'):
                backup.verify(bundle)


if __name__ == '__main__':
    unittest.main()
