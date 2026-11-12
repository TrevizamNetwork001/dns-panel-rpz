"""Real first HTTPS transfer and NXDOMAIN, against the isolated installer lab only.
Requires unbound, dig, named-checkzone and /review/root.crt (the lab CA).
"""
import socket
import struct
import subprocess
import tempfile
import threading
import time
from pathlib import Path

stop = threading.Event()
dns = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
dns.bind(('127.0.0.1', 15577))
dns.settimeout(0.2)

def serve_dns():
    while not stop.is_set():
        try:
            packet, address = dns.recvfrom(4096)
        except socket.timeout:
            continue
        end = 12
        while packet[end]:
            end += packet[end] + 1
        end += 5
        qtype = struct.unpack('!H', packet[end-4:end-2])[0]
        answer = (b'\xc0\x0c' + struct.pack('!HHIH', 1, 1, 30, 4) + socket.inet_aton('127.0.0.1')) if qtype == 1 else b''
        dns.sendto(packet[:2] + struct.pack('!HHHHH', 0x8180, 1, bool(answer), 0, 0) + packet[12:end] + answer, address)

thread = threading.Thread(target=serve_dns, daemon=True)
thread.start()
try:
    with tempfile.TemporaryDirectory(prefix='rpz-new-install-') as directory:
        root = Path(directory)
        conf = root/'unbound.conf'
        zone = root/'policy.zone'
        conf.write_text(f'''server:
 interface: 127.0.0.1@15578
 username: ""
 chroot: ""
 directory: "{root}"
 pidfile: "{root}/pid"
 use-syslog: no
 do-not-query-localhost: no
 do-ip6: no
 so-sndbuf: 0
 module-config: "respip iterator"
 tls-cert-bundle: "/review/root.crt"
forward-zone:
 name: "."
 forward-addr: 127.0.0.1@15577
rpz:
 name: "panel.install.test"
 zonefile: "{zone}"
 url: "https://feeds.install.test:18443/rpz/lab-install.zone"
''')
        subprocess.run(['unbound-checkconf', str(conf)], check=True)
        with (root/'unbound.log').open('w') as log:
            process = subprocess.Popen(['unbound', '-d', '-c', str(conf)], stdout=log, stderr=subprocess.STDOUT)
            try:
                for _ in range(100):
                    if process.poll() is not None:
                        raise RuntimeError((root/'unbound.log').read_text())
                    if zone.exists() and 'blocked.install.test' in zone.read_text():
                        break
                    time.sleep(0.2)
                else:
                    raise RuntimeError('Primeiro download não concluiu: '+(root/'unbound.log').read_text())
                subprocess.run(['named-checkzone', 'panel.install.test', str(zone)], check=True)
                result = subprocess.check_output(['dig', '@127.0.0.1', '-p', '15578', 'blocked.install.test', 'A', '+time=2', '+tries=1'], text=True)
                assert 'status: NXDOMAIN' in result, result
                print('Unbound: primeira transferência HTTPS, zonefile válido e resposta NXDOMAIN aprovados.')
            finally:
                process.terminate()
                process.wait(timeout=10)
finally:
    stop.set()
    thread.join(timeout=1)
    dns.close()
