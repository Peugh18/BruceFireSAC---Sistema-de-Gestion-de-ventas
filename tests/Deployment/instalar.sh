#!/usr/bin/env bash
# Ejecuta el instalador real en un directorio temporal, sin Docker ni AWS.
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
BASE_TEMPORAL="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
TEMPORAL="$(mktemp -d "$BASE_TEMPORAL/brucefire-instalador.XXXXXX")"
limpiar() {
    resultado=$?
    if [ "$resultado" -ne 0 ] && [ -f "${CASO:-}/salida.log" ]; then
        cat "$CASO/salida.log" >&2
    fi
    [[ "$TEMPORAL" == "$BASE_TEMPORAL"/brucefire-instalador.* ]] && rm -rf "$TEMPORAL"
    return "$resultado"
}
trap limpiar EXIT
mkdir -p "$TEMPORAL/bin"

cat > "$TEMPORAL/bin/id" <<'EOF'
#!/usr/bin/env bash
echo 0
EOF
cat > "$TEMPORAL/bin/swapon" <<'EOF'
#!/usr/bin/env bash
echo '/swapfile file 2G'
EOF
cat > "$TEMPORAL/bin/git" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
cat > "$TEMPORAL/bin/docker" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$REGISTRO_DOCKER"
if [ -n "${FALLAR_EN:-}" ] && [[ "$*" == *"$FALLAR_EN"* ]]; then
    exit 23
fi
EOF
chmod +x "$TEMPORAL/bin/"*
export PATH="$TEMPORAL/bin:$PATH"
# Git Bash no debe convertir el subject de OpenSSL en una ruta de Windows.
export MSYS2_ARG_CONV_EXCL='/CN='

preparar() {
    CASO="$TEMPORAL/$1"
    mkdir -p "$CASO/repo/.git" "$CASO/repo/despliegue"
    # Solo sustituye la ruta fija de instalación para no tocar /opt del equipo.
    sed "s|^DIR=\"/opt/brucefire\"$|DIR=\"$CASO/repo\"|" \
        "$RAIZ/despliegue/instalar.sh" > "$CASO/instalar.sh"
    grep -qxF "DIR=\"$CASO/repo\"" "$CASO/instalar.sh" \
        || { echo 'No se pudo aislar la ruta del instalador; no se ejecuta.' >&2; exit 1; }
    export REGISTRO_DOCKER="$CASO/docker.log"
    : > "$REGISTRO_DOCKER"
}

ejecutar() {
    printf 'brucefire-prueba.duckdns.org\n\n' | bash "$CASO/instalar.sh" > "$CASO/salida.log" 2>&1
}

fallar() { echo "FALLO: $*" >&2; exit 1; }
contiene() { grep -qF -- "$2" "$1" || fallar "$3"; }
no_contiene() { if grep -qF -- "$2" "$1"; then fallar "$3"; fi; }

preparar fallo-build
export FALLAR_EN='compose build'
if ejecutar; then fallar 'el fallo del build debe detener la instalación'; fi
[ -f "$CASO/repo/despliegue/secretos/.capacitacion-pendiente" ] || fallar 'debe conservar la inicialización pendiente tras fallar el build'
cp "$CASO/repo/despliegue/secretos/app.env" "$CASO/app-original.env"
unset FALLAR_EN
ejecutar
cmp "$CASO/app-original.env" "$CASO/repo/despliegue/secretos/app.env" || fallar 'el reintento debe conservar las claves'
contiene "$REGISTRO_DOCKER" 'php artisan db:seed --force' 'el reintento debe sembrar la base'
contiene "$REGISTRO_DOCKER" 'php artisan sistema:capacitacion' 'el reintento debe crear las cuentas'
[ ! -f "$CASO/repo/despliegue/secretos/.capacitacion-pendiente" ] || fallar 'la inicialización completada debe quitar el pendiente'

CERTIFICADO="$CASO/repo/despliegue/secretos/certificados/sunat-beta.pem"
[ -f "$CERTIFICADO" ] || fallar 'debe generar el certificado beta'
openssl x509 -in "$CERTIFICADO" -noout >/dev/null 2>&1 || fallar 'el PEM debe contener un certificado válido'
openssl pkey -in "$CERTIFICADO" -noout >/dev/null 2>&1 || fallar 'el PEM debe contener su clave privada'
contiene "$CASO/app-original.env" 'SUNAT_CERT_PATH=/run/secrets/certificados/sunat-beta.pem' 'debe configurar la ruta del certificado montado'
contiene "$CASO/app-original.env" 'SUNAT_USUARIO_SOL=MODDATOS' 'debe configurar el usuario beta'
contiene "$CASO/app-original.env" 'SUNAT_CLAVE_SOL=MODDATOS' 'debe configurar la clave pública beta'
cp "$CERTIFICADO" "$CASO/certificado-original.pem"
: > "$REGISTRO_DOCKER"
ejecutar
cmp "$CERTIFICADO" "$CASO/certificado-original.pem" || fallar 'una actualización no debe rotar el certificado'
no_contiene "$REGISTRO_DOCKER" 'db:seed' 'una actualización no debe sembrar el catálogo'
no_contiene "$REGISTRO_DOCKER" 'sistema:capacitacion' 'una actualización no debe recrear las cuentas'
echo 'OK: reintento de build, certificado beta y actualización sin recrear claves ni datos'

preparar fallo-capacitacion
export FALLAR_EN='sistema:capacitacion'
if ejecutar; then fallar 'el fallo de capacitación debe detener la instalación'; fi
[ -f "$CASO/repo/despliegue/secretos/.capacitacion-pendiente" ] || fallar 'capacitación fallida debe seguir pendiente'
unset FALLAR_EN
: > "$REGISTRO_DOCKER"
ejecutar
contiene "$REGISTRO_DOCKER" 'sistema:capacitacion' 'el reintento debe completar la capacitación'
[ ! -f "$CASO/repo/despliegue/secretos/.capacitacion-pendiente" ] || fallar 'debe completar la inicialización reintentada'
echo 'OK: recuperación de capacitación fallida'

preparar produccion-existente
mkdir -p "$CASO/repo/despliegue/secretos"
printf 'SUNAT_BETA=false\nSUNAT_CERT_PATH=/certificado-real.pem\n' > "$CASO/repo/despliegue/secretos/app.env"
printf 'DOMINIO=brucefire-prueba.duckdns.org\n' > "$CASO/repo/despliegue/.env"
cp "$CASO/repo/despliegue/secretos/app.env" "$CASO/app-original.env"
ejecutar
cmp "$CASO/app-original.env" "$CASO/repo/despliegue/secretos/app.env" || fallar 'no debe cambiar configuración de producción existente'
no_contiene "$REGISTRO_DOCKER" 'db:seed' 'no debe sembrar datos de capacitación en producción existente'
no_contiene "$REGISTRO_DOCKER" 'sistema:capacitacion' 'no debe crear usuarios de capacitación en producción existente'
[ ! -f "$CASO/repo/despliegue/secretos/certificados/sunat-beta.pem" ] || fallar 'no debe generar certificado beta en producción existente'
echo 'OK: producción existente conserva configuración y datos'

touch "$CASO/repo/despliegue/secretos/.capacitacion-pendiente"
: > "$REGISTRO_DOCKER"
if ejecutar; then fallar 'debe rechazar capacitación pendiente si SUNAT ya está en producción'; fi
no_contiene "$REGISTRO_DOCKER" 'db:seed' 'el pendiente no debe permitir sembrar en producción'
no_contiene "$REGISTRO_DOCKER" 'sistema:capacitacion' 'el pendiente no debe permitir cuentas de capacitación en producción'
[ -f "$CASO/repo/despliegue/secretos/.capacitacion-pendiente" ] || fallar 'no debe dar por completada la inicialización rechazada'
echo 'OK: se bloquea capacitación pendiente con SUNAT en producción'
