"""Real UDP listener checks; run at project root with PHP dependencies installed."""
import socket
import subprocess
import time
import unittest


HARNESS = r'''
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['rpz.primary_enabled' => true, 'rpz.primary_bind' => $argv[1], 'rpz.primary_port' => (int) $argv[2]]);
$app->instance(App\Services\RpzPrimaryResponder::class, new class extends App\Services\RpzPrimaryResponder {
    public function __construct() {}
    public function respond(string $packet, string $sourceIp): ?string {
        return $packet === 'listener-probe' ? $sourceIp : null;
    }
});
exit(Illuminate\Support\Facades\Artisan::call('rpz:primary-serve'));
'''


class DualStackListenerTest(unittest.TestCase):
    def check_listener(self, family, address):
        with socket.socket(family, socket.SOCK_DGRAM) as reservation:
            reservation.bind((address, 0))
            port = reservation.getsockname()[1]
        process = subprocess.Popen(
            ['php', '-r', HARNESS, address, str(port)],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        )
        try:
            with socket.socket(family, socket.SOCK_DGRAM) as client:
                client.bind((address, 0))
                client.settimeout(0.2)
                deadline = time.monotonic() + 8
                while time.monotonic() < deadline:
                    if process.poll() is not None:
                        self.fail('Listener exited: ' + repr(process.communicate()))
                    client.sendto(b'listener-probe', (address, port))
                    try:
                        reply, _ = client.recvfrom(512)
                        break
                    except socket.timeout:
                        continue
                else:
                    self.fail('Listener did not respond')
                self.assertEqual(socket.inet_pton(family, address),
                                 socket.inet_pton(family, reply.decode()))
                client.sendto(b'ignored', (address, port))
                with self.assertRaises(socket.timeout):
                    client.recvfrom(512)
        finally:
            process.terminate()
            try:
                output = process.communicate(timeout=3)
            except subprocess.TimeoutExpired:
                process.kill()
                process.communicate()
                self.fail('Listener did not stop on SIGTERM')
        self.assertEqual(0, process.returncode, repr(output))

    def test_ipv4_listener(self):
        self.check_listener(socket.AF_INET, '127.0.0.1')

    def test_ipv6_listener(self):
        self.check_listener(socket.AF_INET6, '::1')


if __name__ == '__main__':
    unittest.main()
