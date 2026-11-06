#!/usr/bin/env bash
# Instala o actualiza el sistema Bruce Fire en un Ubuntu 24.04 (AWS EC2).
# Uso: sudo bash instalar.sh
# Las claves se generan o se escriben aquí, en el servidor; nunca en el chat ni en GitHub.
set -euo pipefail
umask 077

REPO="https://github.com/Peugh18/BruceFireSAC---Sistema-de-Gestion-de-ventas.git"
DIR="/opt/brucefire"
SECRETOS="$DIR/despliegue/secretos"

[ "$(id -u)" -eq 0 ] || { echo "Corre con sudo: sudo bash instalar.sh"; exit 1; }

echo "== 1/6 Docker y git"
if ! command -v docker >/dev/null 2>&1; then
    apt-get update -qq && apt-get install -y -qq ca-certificates curl git openssl
    curl -fsSL https://get.docker.com | sh
fi
command -v git >/dev/null 2>&1 || apt-get install -y -qq git
command -v openssl >/dev/null 2>&1 || apt-get install -y -qq openssl

# La compilación de las pantallas necesita memoria: 2 GB de swap si no hay.
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "== 2/6 Código"
if [ -d "$DIR/.git" ]; then
    git -C "$DIR" pull --ff-only
else
    git clone --depth 1 "$REPO" "$DIR"
fi

echo "== 3/6 Claves"
mkdir -p "$SECRETOS" && chmod 700 "$SECRETOS"
if [ ! -f "$SECRETOS/app.env" ]; then
    read -rp "Dominio (ej. brucefire.duckdns.org): " DOMINIO
    if [[ ! "$DOMINIO" =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$ ]] || [[ "$DOMINIO" != *.* ]]; then
        echo 'Escribe solo el dominio, sin https://, espacios ni rutas.' >&2
        exit 1
    fi
    read -rsp "Clave de DeepSeek para Chispa (Enter para dejarla vacía): " DEEPSEEK; echo
    # No confundir claves creadas con una base ya inicializada. Un fallo de
    # build, migración o seed debe poder reintentarse conservando las claves.
    touch "$SECRETOS/.capacitacion-pendiente"
    DB_CLAVE="$(openssl rand -hex 20)"
    cat > "$SECRETOS/db.env" <<EOF
MYSQL_DATABASE=brucefire
MYSQL_USER=brucefire
MYSQL_PASSWORD=$DB_CLAVE
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 20)
EOF
    cat > "$SECRETOS/app.env" <<EOF
APP_NAME="Bruce Fire"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$(openssl rand -base64 32)
APP_URL=https://$DOMINIO
APP_LOCALE=es
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=brucefire
DB_USERNAME=brucefire
DB_PASSWORD=$DB_CLAVE
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
INERTIA_SSR_ENABLED=false
# SUNAT queda en PRUEBA hasta tener certificado y usuario SOL.
SUNAT_BETA=true
SUNAT_RUC=20616376528
SUNAT_USUARIO_SOL=MODDATOS
SUNAT_CLAVE_SOL=MODDATOS
SUNAT_CERT_PATH=/run/secrets/certificados/sunat-beta.pem
BILLING_COMPANY_RUC=20616376528
BILLING_COMPANY_RAZON_SOCIAL="BRUCE FIRE & SAFETY S.A.C."
BILLING_COMPANY_NOMBRE_COMERCIAL="BRUCE FIRE"
BILLING_COMPANY_UBIGEO=130101
BILLING_COMPANY_DIRECCION="CAL. FELIPE PARDO Y ALIAGA NRO. 207 URB. PALERMO, TRUJILLO - TRUJILLO - LA LIBERTAD"
CHISPA_PROVIDER=deepseek
DEEPSEEK_API_KEY=$DEEPSEEK
EOF
    echo "DOMINIO=$DOMINIO" > "$DIR/despliegue/.env"
    chmod 600 "$SECRETOS"/*.env
fi

# Certificado autofirmado exclusivo de las pruebas: no se registra en SUNAT
# ni sustituye el certificado fiscal al pasar a producción.
mkdir -p "$SECRETOS/certificados"
if grep -qx 'SUNAT_BETA=true' "$SECRETOS/app.env" \
    && grep -qx 'SUNAT_CERT_PATH=/run/secrets/certificados/sunat-beta.pem' "$SECRETOS/app.env" \
    && [ ! -s "$SECRETOS/certificados/sunat-beta.pem" ]; then
    openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 365 \
        -subj '/CN=BRUCE FIRE SOLO PRUEBAS/O=BRUCE FIRE' \
        -keyout "$SECRETOS/certificados/sunat-beta.key" \
        -out "$SECRETOS/certificados/sunat-beta.crt" >/dev/null 2>&1
    cat "$SECRETOS/certificados/sunat-beta.crt" "$SECRETOS/certificados/sunat-beta.key" \
        > "$SECRETOS/certificados/sunat-beta.pem.tmp"
    mv "$SECRETOS/certificados/sunat-beta.pem.tmp" "$SECRETOS/certificados/sunat-beta.pem"
    rm -f "$SECRETOS/certificados/sunat-beta.crt" "$SECRETOS/certificados/sunat-beta.key"
fi

cd "$DIR/despliegue"

echo "== 4/6 Construir (la primera vez tarda unos 10 minutos)"
docker compose build
docker compose up -d

echo "== 5/6 Base de datos"
docker compose exec -T web php artisan migrate --force
if [ -f "$SECRETOS/.capacitacion-pendiente" ]; then
    if ! grep -qx 'SUNAT_BETA=true' "$SECRETOS/app.env"; then
        echo 'No se cargan datos de capacitación con SUNAT en producción. Revisa la inicialización pendiente.' >&2
        exit 1
    fi
    docker compose exec -T web php artisan db:seed --force
    docker compose exec -T web php artisan sistema:capacitacion
fi
docker compose exec -T web php artisan storage:link --force >/dev/null 2>&1 || true
docker compose exec -T web php artisan optimize
# Solo tras completar todos los pasos queda finalizada la inicialización.
rm -f "$SECRETOS/.capacitacion-pendiente"

echo "== 6/6 Listo"
docker compose ps
echo "Abre https://$(cut -d= -f2 .env) (el certificado HTTPS puede tardar un minuto la primera vez)."
