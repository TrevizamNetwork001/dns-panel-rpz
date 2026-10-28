"""Generated panel rules, actual Unbound actions, counters and logs; loopback only."""
import os
from pathlib import Path
import re
import subprocess
import unittest
from company_exception_rpz_semantics import FakeUpstream, Resolver


class RpzDiagnosticsSafety(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.upstream = FakeUpstream()

    def test_generated_actions_are_applied_logged_and_counted_without_reset(self):
        for mode in ['nxdomain', 'redirect']:
            with self.subTest(mode=mode):
                resolver = Resolver('unbound', Path(os.environ['RPZ_SAFETY_OUTPUT']) / (mode + '.zone'), diagnostics=True)
                try:
                    self.assertIn('192.0.2.42', resolver.query('unblocked.test'))
                    for qtype, upstream in [('A', '192.0.2.42'), ('AAAA', '2001:db8::42')]:
                        blocked = resolver.query('blocked.example', qtype)
                        wildcard = resolver.query('child.blocked.example', qtype)
                        for response in [blocked, wildcard]:
                            if mode == 'nxdomain':
                                self.assertIn('status: NXDOMAIN', response)
                            else:
                                self.assertIn('CNAME', response)
                                self.assertIn('sink.example.', response)
                        for name in ['allowed.blocked.example', 'child.allowed.blocked.example']:
                            response = resolver.query(name, qtype)
                            self.assertIn(upstream, response)
                            self.assertNotIn('CNAME', response)
                        self.assertIn('status: NXDOMAIN', resolver.query('blocktest.policy.test', qtype))
                    command = ['unbound-control', '-c', str(resolver.root / 'resolver.conf')]
                    zones = subprocess.run(command + ['list_auth_zones'], capture_output=True, text=True, check=True).stdout
                    self.assertIn('policy.test', zones)
                    self.assertRegex(zones, r'\b7\b')
                    snapshots = []
                    for _ in range(2):
                        output = subprocess.run(command + ['stats_noreset'], capture_output=True, text=True, check=True).stdout
                        counters = dict(re.findall(r'^(num\.rpz\.action\.[^=]+)=(\d+)$', output, re.M))
                        self.assertTrue(counters, output)
                        self.assertGreater(int(counters['num.rpz.action.rpz-nxdomain']), 0)
                        self.assertGreater(int(counters['num.rpz.action.rpz-passthru']), 0)
                        if mode == 'redirect':
                            self.assertGreater(int(counters['num.rpz.action.rpz-local-data']), 0)
                        snapshots.append(counters)
                    self.assertEqual(snapshots[0], snapshots[1], 'stats_noreset must preserve RPZ counters')
                    logs = (resolver.root / 'resolver.log').read_text()
                    self.assertIn('rpz: applied [safety-synthetic]', logs)
                    self.assertIn('rpz-passthru', logs)
                    self.assertIn('rpz-nxdomain', logs)
                    if mode == 'redirect':
                        self.assertIn('rpz-local-data', logs)
                finally:
                    resolver.close()


if __name__ == '__main__':
    unittest.main(verbosity=2)
