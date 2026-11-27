#!/bin/sh
set -eu

fail() {
    printf 'unbound-install:%s\n' "$1"
    exit 1
}

[ "$(id -u)" -eq 0 ] || fail permission
exec 9>/run/lock/dns-recursivo-unbound.lock
flock -n 9 || fail busy

os_id=$(sed -n 's/^ID=//p' /etc/os-release | tr -d '"')
os_version=$(sed -n 's/^VERSION_ID=//p' /etc/os-release | tr -d '"')
[ "$os_id:$os_version" = debian:13 ] || fail platform

if command -v unbound >/dev/null 2>&1 || [ -x /usr/sbin/unbound ] ||
    [ -e /etc/unbound ] || [ "$(dpkg-query -W -f='${db:Status-Status}' unbound 2>/dev/null || true)" = installed ]; then
    fail existing
fi

systemctl show-environment >/dev/null 2>&1 || fail systemd
command -v ss >/dev/null 2>&1 || fail sockets
sockets=$(ss -H -lntu 'sport = :53') || fail sockets
[ -z "$sockets" ] || fail port
if [ -x /sbin/resolvconf ]; then
    case "$(readlink /sbin/resolvconf || true)" in
        *resolvectl) ;;
        *) fail resolvconf ;;
    esac
fi

cpu=$(getconf _NPROCESSORS_ONLN)
memory=$(awk '/^MemTotal:/ {print int($2/1024)}' /proc/meminfo)
[ "$1" -le "$cpu" ] && [ "$(( $2 + $3 ))" -le "$(( memory / 2 ))" ] || fail resources

export DEBIAN_FRONTEND=noninteractive
apt-get -q -o DPkg::Lock::Timeout=0 -o Acquire::Retries=0 -o Acquire::http::Timeout=8 -o Acquire::https::Timeout=8 update || fail packages
apt-get -qy -o DPkg::Lock::Timeout=0 -o Acquire::Retries=0 -o Acquire::http::Timeout=8 -o Acquire::https::Timeout=8 install --no-install-recommends unbound dnsutils dns-root-data || fail packages
/usr/libexec/unbound-helper root_trust_anchor_update || fail trust

target=/etc/unbound/unbound.conf.d/55-dns-recursivo.conf
[ ! -e "$target" ] || fail existing
candidate=$(mktemp "$target.XXXXXX")
applied=0
complete=0
cleanup() {
    rm -f "$candidate"
    if [ "$applied" -eq 1 ] && [ "$complete" -eq 0 ]; then
        rm -f "$target"
        systemctl restart unbound || printf 'unbound-install:restore\n'
    fi
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
cat > "$candidate" <<'UNBOUND_CONFIGURATION'
__UNBOUND_CONFIGURATION__
UNBOUND_CONFIGURATION
chmod 0644 "$candidate"
mv "$candidate" "$target"
applied=1

unbound-checkconf /etc/unbound/unbound.conf || fail config
[ "$(unbound-checkconf -o num-threads /etc/unbound/unbound.conf)" = "$1" ] || fail config
systemctl restart unbound || fail service
systemctl is-active --quiet unbound || fail service
answer=$(dig @127.0.0.1 debian.org A +short +time=4 +tries=2) || fail query
[ -n "$answer" ] || fail query
systemctl enable unbound || fail service

complete=1
printf 'unbound-install:ok\n'
