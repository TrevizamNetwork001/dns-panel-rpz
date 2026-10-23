"""Validate rendered Compose JSON, never run Compose up or access Docker volumes."""
import json
import os
import re
from pathlib import Path
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
MODEL_PATH = sys.argv.pop(1) if __name__ == '__main__' and len(sys.argv) > 1 else os.environ.get('CONTABO_COMPOSE_JSON')
MODEL = json.loads(Path(MODEL_PATH).read_text()) if MODEL_PATH else None


@unittest.skipIf(MODEL is None, 'Supply a non-secret compose config --no-env-resolution JSON file')
class ContaboProposalSafety(unittest.TestCase):
    def test_no_public_ports_or_host_network(self):
        self.assertTrue(all(network.get('internal') for network in MODEL['networks'].values()))
        for service in MODEL['services'].values():
            self.assertFalse(service.get('ports'))
            self.assertNotEqual(service.get('network_mode'), 'host')
            self.assertFalse(service.get('privileged', False))
            self.assertNotIn('memswap_limit', service)

    def test_external_volumes_never_reuse_recovered_or_staging_names(self):
        for volume in MODEL['volumes'].values():
            self.assertTrue(volume['external'])
            self.assertTrue(volume['name'].startswith('dns-panel-rpz-contabo-'))
            self.assertNotIn('staging', volume['name'])

    def test_delivery_publication_and_integrations_are_disabled(self):
        for name, service in MODEL['services'].items():
            if name == 'nginx':
                continue
            env = service['environment']
            for flag in ['RPZ_ENDPOINTS_ENABLED', 'RPZ_PUBLICATION_ENABLED', 'RPZ_STATIC_ARTIFACTS_ENABLED',
                         'RPZ_PRIMARY_ENABLED', 'RPZ_NOTIFY_ENABLED', 'EXTERNAL_SYNC_ENABLED']:
                self.assertEqual(env[flag], 'false')
            self.assertEqual(env['RPZ_NOTIFY_SERVER_IDS'], '')
            self.assertEqual(env['CONTABO_AUTOMATION_APPROVED'], '0')

    def test_only_explicit_profiles_and_no_external_sync_or_primary_service(self):
        self.assertNotIn('external-sync', MODEL['services'])
        self.assertNotIn('rpz-primary', MODEL['services'])
        for service in MODEL['services'].values():
            self.assertTrue(service['profiles'])
            self.assertEqual(service['restart'], 'no')

    def test_app_and_worker_use_the_same_release_and_compatible_timeouts(self):
        app = MODEL['services']['app']
        for name in ['preflight', 'worker', 'scheduler', 'backup']:
            self.assertEqual(MODEL['services'][name]['image'], app['image'])
            self.assertEqual(MODEL['services'][name]['build']['dockerfile'], 'docker/Dockerfile.contabo')
        self.assertGreater(int(app['environment']['DB_QUEUE_RETRY_AFTER']), 360)
        self.assertGreaterEqual(sum(int(n) * {'h': 3600, 'm': 60, 's': 1}[unit] for n, unit in re.findall(r'(\d+)([hms])', MODEL['services']['worker']['stop_grace_period'])), 360)
        self.assertGreaterEqual(int(app['environment']['ANATEL_EXTRACT_TIMEOUT']) + 30, 300)

    def test_preflight_mounts_are_readonly(self):
        for mount in MODEL['services']['preflight']['volumes']:
            self.assertTrue(mount['read_only'])

    def test_automations_refuse_to_execute_even_when_called_directly(self):
        for role in ['worker', 'scheduler', 'backup']:
            result = subprocess.run(['/bin/sh', str(ROOT / 'docker/contabo-automation.sh'), role],
                                    env={'PATH': '/usr/bin:/bin'}, capture_output=True, text=True, timeout=3)
            self.assertEqual(result.returncode, 64)
            self.assertIn('bloqueadas', result.stderr)

    def test_gateway_prepares_native_dual_stack_without_absam_address(self):
        gateway = (ROOT / 'deploy/nginx/rpz-contabo.conf.example').read_text()
        self.assertIn('listen 213.199.47.105:443 ssl', gateway)
        self.assertIn('listen [2a02:c207:2368:3678::1]:443 ssl', gateway)
        self.assertIn('return 503', gateway)
        self.assertNotIn('108.181.224.210', json.dumps(MODEL) + gateway)


if __name__ == '__main__':
    unittest.main(verbosity=2)
