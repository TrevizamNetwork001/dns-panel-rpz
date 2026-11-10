"""Company exception contract checked with actual resolvers and a negative control.

Uses generated fixtures, actual BIND/Unbound, and a loopback-only fake upstream.
No live endpoints, transfers, NOTIFY, or public DNS are used.
"""
import json
import os
import re
from pathlib import Path
import shutil
import socket
import struct
import subprocess
import tempfile
import threading
import time
import unittest

OUTPUT = Path(os.environ.get('RPZ_SAFETY_OUTPUT', '/review'))
MANIFEST_PATH = OUTPUT / 'manifest.json'
ALLOWED = ['exemplo.com.br', 'www.exemplo.com.br', 'a.b.exemplo.com.br',
           'ads.exemplo.com.br', 'allow.deep.example.com',
           'www.allow.deep.example.com', 'x.y.allow.deep.example.com']
BLOCKED = ['com.br', 'outroexemplo.com.br', 'exemplo.com.br.com.br',
           'example.com', 'random.example.com', 'deep.example.com',
           'other.deep.example.com', 'x.other.deep.example.com']


class FakeUpstream:
    def __init__(self):
        self.socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.socket.bind(('127.0.0.1', 15300))
        threading.Thread(target=self.serve, daemon=True).start()

    def serve(self):
        while True:
            data, address = self.socket.recvfrom(4096)
            end = 12
            while data[end]:
                end += data[end] + 1
            end += 5
            qtype = struct.unpack('!H', data[end - 4:end - 2])[0]
            address_data = socket.inet_aton('192.0.2.42') if qtype == 1 else socket.inet_pton(socket.AF_INET6, '2001:db8::42') if qtype == 28 else None
            answer = b'\xc0\x0c' + struct.pack('!HHIH', qtype, 1, 60, len(address_data)) + address_data if address_data else b''
            self.socket.sendto(data[:2] + struct.pack('!HHHHH', 0x8180, 1, bool(answer), 0, 0)
                               + data[12:end] + answer, address)


class Resolver:
    def __init__(self, engine, zone, diagnostics=False):
        self.engine = engine
        self.temp = tempfile.TemporaryDirectory(prefix='rpz-semantics-')
        self.root = Path(self.temp.name)
        shutil.copyfile(zone, self.root / 'policy.zone')
        self.port = 15301 if engine == 'unbound' else 15302
        if engine == 'unbound':
            config = f'''server:
 interface: 127.0.0.1@{self.port}
 username: ""
 chroot: ""
 directory: "{self.root}"
 pidfile: "{self.root}/unbound.pid"
 use-syslog: no
 do-not-query-localhost: no
 so-sndbuf: 0
 module-config: "respip iterator"
forward-zone:
 name: "."
 forward-addr: 127.0.0.1@15300
rpz:
 name: "policy.test"
 zonefile: "{self.root}/policy.zone"
'''
            if diagnostics:
                config = config.replace('server:\n', 'server:\n extended-statistics: yes\n statistics-inhibit-zero: no\n local-zone: "test." transparent\n', 1)
                config += f''' rpz-log: yes
 rpz-log-name: "safety-synthetic"
remote-control:
 control-enable: yes
 control-interface: "{self.root}/control.sock"
 control-use-cert: no
'''
            command = ['unbound', '-d', '-c']
        else:
            config = f'''options {{ directory "{self.root}";
 listen-on port {self.port} {{127.0.0.1;}}; listen-on-v6 {{none;}};
 pid-file "{self.root}/named.pid"; session-keyfile "{self.root}/session.key";
 recursion yes; dnssec-validation no; empty-zones-enable no;
 forward only; forwarders {{127.0.0.1 port 15300;}};
 response-policy {{zone "policy.test";}} qname-wait-recurse no;
}};
controls {{}};
zone "policy.test" {{type primary; file "policy.zone"; notify no;}};
'''
            command = ['named', '-g', '-c']
        path = self.root / 'resolver.conf'
        path.write_text(config)
        self.log = open(self.root / 'resolver.log', 'w')
        self.process = subprocess.Popen(command + [str(path)], stdout=self.log, stderr=subprocess.STDOUT)
        for _ in range(40):
            if self.process.poll() is not None:
                raise RuntimeError((self.root / 'resolver.log').read_text())
            if 'status: NOERROR' in self.query('ready.safety.net'):
                break
            time.sleep(0.05)
        else:
            self.close()
            raise RuntimeError('Resolver did not become ready')

    def query(self, name, qtype='A'):
        return subprocess.run(['dig', '@127.0.0.1', '-p', str(self.port), name, qtype,
                               '+time=1', '+tries=1', '+noall', '+comments', '+answer'],
                              capture_output=True, text=True, timeout=3).stdout

    def close(self):
        self.process.terminate()
        self.process.wait(timeout=5)
        self.log.close()
        self.temp.cleanup()


class CompanyExceptionSafety(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.manifest = json.loads(MANIFEST_PATH.read_text())
        cls.upstream = FakeUpstream()
        cls.checks = 0

    @classmethod
    def tearDownClass(cls):
        print(f'Actual resolver assertions: {cls.checks}', flush=True)

    @staticmethod
    def signature(response):
        status = re.search(r'status: (\w+)', response)
        records = [tuple(line.split()[:1] + line.split()[2:]) for line in response.splitlines() if line and not line.startswith(';')]
        return (status[1] if status else 'NO_RESPONSE', records)

    def test_current_generator_company_exception_contract(self):
        for key, meta in sorted(self.manifest.items()):
            allowed = meta.get('allowed', ALLOWED if meta['company'] == 'A' else [])
            probes = list(dict.fromkeys(allowed + meta.get('probes', ALLOWED + BLOCKED)))
            zone = OUTPUT / (key + '.zone')
            for engine in ['unbound', 'bind']:
                baseline = {}
                if meta.get('baseline'):
                    resolver = Resolver(engine, OUTPUT / (key + '-baseline.zone'))
                    try:
                        baseline = {(name, qtype): self.signature(resolver.query(name, qtype)) for name in probes for qtype in ['A', 'AAAA']}
                    finally:
                        resolver.close()
                resolver = Resolver(engine, zone)
                try:
                    for domain in probes:
                        for qtype in ['A', 'AAAA']:
                            with self.subTest(scope=key, source=meta['source'], resolver=engine, domain=domain, qtype=qtype):
                                response = resolver.query(domain, qtype)
                                type(self).checks += 1
                                if domain in allowed:
                                    self.assertIn('192.0.2.42' if qtype == 'A' else '2001:db8::42', response, 'Exception must resolve via upstream')
                                    self.assertNotIn('CNAME', response, 'PASSTHRU must not redirect the allowed domain')
                                elif baseline:
                                    self.assertEqual(baseline[(domain, qtype)], self.signature(response), 'Policy outside exception must equal the original policy')
                                else:
                                    self.assertIn('status: NXDOMAIN', response, 'Existing block must remain effective')
                finally:
                    resolver.close()

    def test_negative_control_detects_naive_passthru_sibling_leak(self):
        for engine in ['unbound', 'bind']:
            resolver = Resolver(engine, OUTPUT / 'company-A-candidate.zone')
            try:
                response = resolver.query('other.deep.example.com')
                type(self).checks += 1
                self.assertIn('192.0.2.42', response, 'The deliberately unsafe control should reproduce the old wildcard leak')
            finally:
                resolver.close()


if __name__ == '__main__':
    unittest.main(verbosity=2)
