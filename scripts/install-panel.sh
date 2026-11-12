#!/usr/bin/env bash
# Standalone entry point: download this file and run it on a NEW Debian/Ubuntu VPS.
set -Eeuo pipefail
umask 077

rpz_install_dir=${RPZ_INSTALL_DIR:-/opt/rpz-panel}
rpz_repository=${RPZ_REPOSITORY_URL:-https://github.com/TrevizamNetwork001/dns-panel-rpz.git}
rpz_ref=${RPZ_REF:-updates/rpz-2026-10-28}
rpz_source_dir=''
rpz_local_only=0
rpz_check_only=0
rpz_locked=0
RPZ_PANEL_DOMAIN=${RPZ_PANEL_DOMAIN:-}
RPZ_FEED_DOMAIN=${RPZ_FEED_DOMAIN:-}
usage() {
    cat <<'HELP'
Uso: sudo bash install-panel.sh
  --check             Verifica uma instalação existente sem reinstalar.
  --source-dir DIR    Usa um checkout já disponível, sem baixar o repositório.
  --local-only        Laboratório: somente HTTP em loopback; não ocupa 80/443.
  --help              Mostra esta ajuda sem alterar o sistema.

Variáveis opcionais:
  RPZ_INSTALL_DIR, RPZ_REPOSITORY_URL, RPZ_REF
  RPZ_PANEL_DOMAIN, RPZ_FEED_DOMAIN, RPZ_ACME_EMAIL, RPZ_PROJECT_NAME
  RPZ_UPSTREAM_PORT, RPZ_ADMIN_EMAIL, RPZ_ADMIN_NAME
  RPZ_ADMIN_PASSWORD_FILE (senha em arquivo, em vez de digitação oculta)
O instalador preserva .env.install, APP_KEY, volumes e usuários existentes.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }
cleanup() { if (( rpz_locked )); then rmdir "$rpz_install_dir/.install-lock" 2>/dev/null || true; fi; }
trap cleanup EXIT
trap 'printf "Instalação interrompida na linha %s. Dados e volumes foram preservados.\n" "$LINENO" >&2' ERR
while (($#)); do
    case "$1" in
        --help|-h) usage; exit 0 ;;
        --check) rpz_check_only=1 ;;
        --local-only) rpz_local_only=1 ;;
        --source-dir) (($# >= 2)) || fail 'Informe o diretório após --source-dir.'; rpz_source_dir=$2; shift ;;
        *) fail "Opção desconhecida: $1" ;;
    esac
    shift
done
(( EUID == 0 )) || fail 'Execute como root: sudo bash install-panel.sh'

install_requirements() {
    if command -v docker >/dev/null && docker compose version >/dev/null 2>&1 \
        && command -v git >/dev/null && command -v curl >/dev/null && command -v openssl >/dev/null; then
        docker info >/dev/null || fail 'Docker está instalado, mas o daemon não está acessível.'
        return
    fi
    [[ -r /etc/os-release ]] || fail 'Sistema operacional não identificado.'
    # Distribution-owned metadata, never an installer/user configuration file.
    # shellcheck source=/dev/null
    . /etc/os-release
    case "$ID" in debian|ubuntu) ;; *) fail 'Instalação automática de dependências suporta Debian e Ubuntu.' ;; esac
    local rpz_os=$ID rpz_codename=${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}
    [[ -n $rpz_codename ]] || fail 'Codinome da distribuição não identificado.'
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y ca-certificates curl git openssl iproute2
    if ! command -v docker >/dev/null; then
        install -m 0755 -d /etc/apt/keyrings
        curl -fsSL --retry 3 "https://download.docker.com/linux/$rpz_os/gpg" -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
        printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/%s %s stable\n' \
            "$(dpkg --print-architecture)" "$rpz_os" "$rpz_codename" > /etc/apt/sources.list.d/docker-rpz.list
        apt-get update
        apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        systemctl enable --now docker
    fi
    docker compose version >/dev/null 2>&1 || fail 'Docker existente sem Compose v2. Instale o plugin compatível e execute novamente; o instalador não substitui um Docker existente.'
    docker info >/dev/null || fail 'Daemon Docker inacessível.'
}
if (( rpz_check_only )); then
    command -v docker >/dev/null || fail 'Docker não encontrado.'
else
    install_requirements
fi

if [[ -n $rpz_source_dir ]]; then
    [[ -d $rpz_source_dir ]] || fail 'Checkout não encontrado.'
    rpz_install_dir=$(cd "$rpz_source_dir" && pwd -P)
elif [[ ! -d $rpz_install_dir ]]; then
    (( ! rpz_check_only )) || fail 'Instalação não encontrada.'
    mkdir -p "$(dirname "$rpz_install_dir")"
    git clone --depth 1 --branch "$rpz_ref" -- "$rpz_repository" "$rpz_install_dir"
fi
[[ -f $rpz_install_dir/compose.install.yml && -f $rpz_install_dir/.env.install.example ]] || fail 'Diretório não contém a versão do painel com instalador.'
rpz_env_file="$rpz_install_dir/.env.install"
if (( rpz_check_only )) && [[ -d $rpz_install_dir/.install-lock ]]; then fail 'Instalador em andamento; aguarde antes de verificar.'; fi
if (( ! rpz_check_only )); then
    mkdir "$rpz_install_dir/.install-lock" 2>/dev/null || fail 'Outro instalador está ativo (ou existe .install-lock de uma execução interrompida).'
    rpz_locked=1
fi
read_env() { sed -n "s/^$1=//p" "$rpz_env_file" | head -n 1; }
valid_domain() { [[ ${#1} -le 253 && $1 =~ ^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$ && ! $1 =~ ^[0-9.]+$ ]]; }
prompt() {
    local rpz_var=$1 rpz_label=$2
    if [[ -z ${!rpz_var:-} ]]; then
        [[ -t 0 ]] || fail "Informe $rpz_var no ambiente ou execute em um terminal interativo."
        read -r -p "$rpz_label: " "${rpz_var?}"
    fi
}
if [[ ! -f $rpz_env_file ]]; then
    (( ! rpz_check_only )) || fail '.env.install não encontrado.'
    prompt RPZ_PANEL_DOMAIN 'Domínio do painel (ex.: painel.suaempresa.com.br)'
    prompt RPZ_FEED_DOMAIN 'Domínio dos feeds (ex.: feeds.suaempresa.com.br)'
    prompt RPZ_ACME_EMAIL 'E-mail para os certificados HTTPS'
    valid_domain "$RPZ_PANEL_DOMAIN" || fail 'Domínio do painel inválido; informe apenas o hostname, sem https:// ou caminho.'
    valid_domain "$RPZ_FEED_DOMAIN" || fail 'Domínio dos feeds inválido.'
    [[ $RPZ_FEED_DOMAIN != "$RPZ_PANEL_DOMAIN" && $RPZ_FEED_DOMAIN != *."$RPZ_PANEL_DOMAIN" ]] || fail 'Feeds deve ficar FORA da zona do painel. Use painel.exemplo.com e feeds.exemplo.com.'
    [[ $RPZ_ACME_EMAIL =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || fail 'E-mail HTTPS inválido.'
    rpz_project=${RPZ_PROJECT_NAME:-rpz-panel}
    rpz_port=${RPZ_UPSTREAM_PORT:-18082}
    [[ $rpz_project =~ ^[a-z0-9][a-z0-9_-]{0,39}$ ]] || fail 'Nome do projeto inválido.'
    if ! [[ $rpz_port =~ ^[0-9]{1,5}$ ]] || ! (( 10#$rpz_port >= 1024 && 10#$rpz_port <= 65535 )); then fail 'Porta interna deve ficar entre 1024 e 65535.'; fi
    rpz_initial_key=$(openssl rand -base64 32 | tr -d '\n')
    rpz_tmp_env=$(mktemp "$rpz_install_dir/.env.install.XXXXXX")
    while IFS= read -r rpz_line || [[ -n $rpz_line ]]; do
        case "$rpz_line" in
            APP_KEY=*) printf 'APP_KEY=base64:%s\n' "$rpz_initial_key" ;;
            RPZ_PROJECT_NAME=*) printf 'RPZ_PROJECT_NAME=%s\n' "$rpz_project" ;;
            RPZ_UPSTREAM_PORT=*) printf 'RPZ_UPSTREAM_PORT=%s\n' "$rpz_port" ;;
            RPZ_PANEL_DOMAIN=*) printf 'RPZ_PANEL_DOMAIN=%s\n' "$RPZ_PANEL_DOMAIN" ;;
            RPZ_FEED_DOMAIN=*) printf 'RPZ_FEED_DOMAIN=%s\n' "$RPZ_FEED_DOMAIN" ;;
            RPZ_ACME_EMAIL=*) printf 'RPZ_ACME_EMAIL=%s\n' "$RPZ_ACME_EMAIL" ;;
            APP_URL=*) printf 'APP_URL=https://%s\n' "$RPZ_PANEL_DOMAIN" ;;
            RPZ_FEED_BASE_URL=*) printf 'RPZ_FEED_BASE_URL=https://%s\n' "$RPZ_FEED_DOMAIN" ;;
            *) printf '%s\n' "$rpz_line" ;;
        esac
    done < "$rpz_install_dir/.env.install.example" > "$rpz_tmp_env"
    chmod 600 "$rpz_tmp_env"
    mv "$rpz_tmp_env" "$rpz_env_file"
    unset rpz_initial_key
fi
rpz_project=$(read_env RPZ_PROJECT_NAME)
rpz_port=$(read_env RPZ_UPSTREAM_PORT)
rpz_panel_domain=$(read_env RPZ_PANEL_DOMAIN)
rpz_feed_domain=$(read_env RPZ_FEED_DOMAIN)
[[ $rpz_project =~ ^[a-z0-9][a-z0-9_-]{0,39}$ ]] || fail 'Projeto inválido em .env.install.'
if ! [[ $rpz_port =~ ^[0-9]{1,5}$ ]] || ! (( 10#$rpz_port >= 1024 && 10#$rpz_port <= 65535 )); then fail 'Porta inválida em .env.install.'; fi
if ! valid_domain "$rpz_panel_domain" || ! valid_domain "$rpz_feed_domain"; then fail 'Domínios inválidos em .env.install.'; fi
[[ $rpz_feed_domain != "$rpz_panel_domain" && $rpz_feed_domain != *."$rpz_panel_domain" ]] || fail 'Domínio de feeds deve ficar fora da zona do painel.'
# Do not adopt or recreate a legacy/unrelated Compose project with the same name.
rpz_existing_containers=$(docker ps --all --quiet --filter "label=com.docker.compose.project=$rpz_project")
for rpz_existing_id in $rpz_existing_containers; do
    rpz_existing_compose=$(docker inspect --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}' "$rpz_existing_id")
    [[ $rpz_existing_compose == "$rpz_install_dir/compose.install.yml" ]] || fail "O projeto $rpz_project pertence a outra instalação. Escolha outro RPZ_PROJECT_NAME."
done
dc() { env -u RPZ_PROJECT_NAME -u RPZ_RELEASE -u RPZ_UPSTREAM_PORT -u RPZ_PANEL_DOMAIN -u RPZ_FEED_DOMAIN -u RPZ_ACME_EMAIL docker compose --project-name "$rpz_project" --project-directory "$rpz_install_dir" --env-file "$rpz_env_file" -f "$rpz_install_dir/compose.install.yml" "$@"; }
check_services() {
    local rpz_service rpz_container
    for rpz_service in app web queue scheduler external-sync backup; do
        rpz_container=$(dc ps --status running --quiet "$rpz_service")
        [[ -n $rpz_container ]] || fail "Serviço $rpz_service não está em execução."
    done
    for rpz_service in app web; do
        rpz_container=$(dc ps --quiet "$rpz_service")
        [[ $(docker inspect --format '{{.State.Health.Status}}' "$rpz_container") == healthy ]] || fail "Serviço $rpz_service ainda não está saudável."
    done
}
check_http() { curl --silent --show-error --fail --retry 5 --retry-delay 1 --retry-connrefused --max-time 15 "http://127.0.0.1:$rpz_port/login" -o /dev/null; }
if (( rpz_check_only )); then
    dc ps
    check_services
    check_http
    if (( ! rpz_local_only )); then curl --silent --show-error --fail --max-time 20 "https://$rpz_panel_domain/login" -o /dev/null; fi
    printf 'Painel respondeu corretamente.\n'
    exit 0
fi
if [[ -z $(dc ps --quiet app) ]]; then
    if command -v ss >/dev/null; then
        [[ -z $(ss -H -ltn "sport = :$rpz_port") ]] || fail "Porta interna $rpz_port já está ocupada. Escolha RPZ_UPSTREAM_PORT antes da primeira execução."
        if (( ! rpz_local_only )); then
            [[ -z $(ss -H -ltn '( sport = :80 or sport = :443 )') ]] || fail 'Porta 80 ou 443 ocupada. O instalador não desliga serviços existentes.'
        fi
    fi
fi
# Keep the initial key and, on a repeat installation, a pre-migration database backup.
mkdir -p "$rpz_install_dir/.recovery/install-backups"
rpz_stamp=$(date -u +%Y%m%d-%H%M%S)
cp -p "$rpz_env_file" "$rpz_install_dir/.recovery/install-backups/env-$rpz_stamp"
chmod 600 "$rpz_env_file" "$rpz_install_dir/.recovery/install-backups/env-$rpz_stamp"
if [[ -f $rpz_install_dir/.install-state ]]; then
    dc run --rm --no-deps -T --entrypoint php app artisan backup:run
    rpz_old_app=$(dc ps --all --quiet app)
    rpz_old_web=$(dc ps --all --quiet web)
    if [[ -n $rpz_old_app && -n $rpz_old_web ]]; then
        docker image tag "$(docker inspect --format '{{.Image}}' "$rpz_old_app")" "$rpz_project-app:before-$rpz_stamp"
        docker image tag "$(docker inspect --format '{{.Image}}' "$rpz_old_web")" "$rpz_project-web:before-$rpz_stamp"
        printf 'before-%s\n' "$rpz_stamp" > "$rpz_install_dir/.recovery/install-backups/release-$rpz_stamp.txt"
    fi
fi
printf '\nConstruindo imagens a partir do código-fonte…\n'
dc build app web
printf '\nInicializando banco e serviços…\n'
# Wait for PHP before refreshing nginx. Waiting on a stale nginx first can deadlock
# an update: its health check would still target the previous PHP container IP.
dc up -d --wait --wait-timeout 180 app queue scheduler external-sync backup
dc up -d --no-deps web
rpz_nginx_ready=0
for ((rpz_attempt=1; rpz_attempt<=10; rpz_attempt++)); do
    if dc exec -T web nginx -s reload; then rpz_nginx_ready=1; break; fi
    sleep 1
done
(( rpz_nginx_ready )) || fail 'Não foi possível recarregar nginx após inicializar PHP.'
dc up -d --no-deps --wait --wait-timeout 90 web
check_services
check_http
rpz_admin_args=(php artisan panel:admin-create --if-missing "--name=${RPZ_ADMIN_NAME:-Administrador}")
if [[ -n ${RPZ_ADMIN_EMAIL:-} ]]; then rpz_admin_args+=("$RPZ_ADMIN_EMAIL"); fi
if [[ -n ${RPZ_ADMIN_PASSWORD_FILE:-} ]]; then
    [[ -r $RPZ_ADMIN_PASSWORD_FILE ]] || fail 'Arquivo de senha não pode ser lido.'
    [[ -n ${RPZ_ADMIN_EMAIL:-} ]] || fail 'Informe RPZ_ADMIN_EMAIL ao usar um arquivo de senha.'
    dc exec -T app "${rpz_admin_args[@]}" --password-stdin < "$RPZ_ADMIN_PASSWORD_FILE"
else
    dc exec app "${rpz_admin_args[@]}"
fi
dc exec -T app php artisan view:cache
dc exec -T app php artisan backup:run
if (( ! rpz_local_only )); then
    printf '\nAtivando HTTPS. Os dois domínios devem apontar para esta VPS e permitir acesso às portas 80/443.\n'
    dc --profile https up -d edge
    rpz_https_ok=0
    for ((rpz_attempt=1; rpz_attempt<=30; rpz_attempt++)); do
        if curl --silent --fail --max-time 10 "https://$rpz_panel_domain/login" -o /dev/null; then rpz_https_ok=1; break; fi
        sleep 5
    done
    (( rpz_https_ok )) || fail 'Serviços iniciados, mas HTTPS ainda não validou. Confira DNS A/AAAA, firewall e docker compose logs edge; execute novamente após corrigir.'
    rpz_feed_status=$(curl --silent --show-error --max-time 20 --output /dev/null --write-out '%{http_code}' "https://$rpz_feed_domain/login")
    [[ $rpz_feed_status == 404 ]] || fail 'HTTPS de feeds não validou ou expõe uma rota do painel. Confira os logs do serviço edge.'
    printf '\nInstalação concluída: https://%s\nFeeds: https://%s\n' "$rpz_panel_domain" "$rpz_feed_domain"
else
    printf '\nLaboratório pronto em http://127.0.0.1:%s (HTTPS não ativado).\n' "$rpz_port"
fi
printf 'installed\n' > "$rpz_install_dir/.install-state"
printf 'Configuração preservada em %s. Backup local criado; configure R2/Telegram no painel se necessário.\n' "$rpz_env_file"
printf 'Sem empresa/listas fictícias: cadastre sua empresa, licença, fonte e servidor para começar.\n'
