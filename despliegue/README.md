# Subir Bruce Fire a AWS

Una sola EC2 con Docker: la web (HTTPS automático), la cola, las tareas programadas y MySQL. Es **otra máquina**, distinta de la de Memora. Cuesta aproximadamente US$ 13 al mes.

SUNAT queda en **modo prueba** (`SUNAT_BETA=true`) hasta tener el certificado digital y el usuario SOL.

## 1. Dirección gratis (DuckDNS)

1. Entra a <https://www.duckdns.org> con Google o GitHub.
2. Crea un subdominio, por ejemplo `brucefire`. Queda `brucefire.duckdns.org`.
3. Cuando tengas la IP fija (paso 3), pégala en **current ip** y pulsa **update ip**.

Después se puede cambiar a `sistema.extintoresbrucefire.com` desde Squarespace, sin perder datos.

## 2. La máquina EC2 (región us-east-2, Ohio, la misma de Memora)

En la consola de EC2, pulsa **Launch instance**:

| Campo | Valor |
|---|---|
| Nombre | `brucefire` |
| Imagen | **Ubuntu Server 24.04 LTS**, arquitectura **64-bit (Arm)** |
| Tipo | **t4g.small** (2 GB) |
| Key pair | **Proceed without a key pair** (se entra con Session Manager) |
| Red → Security group | Crear uno nuevo con entradas **HTTP 80** y **HTTPS 443** desde `0.0.0.0/0`. **Sin el 22** |
| Almacenamiento | 30 GB gp3 |
| Advanced → IAM instance profile | El mismo rol de Memora (`MemoraEc2Profile`), que ya tiene **AmazonSSMManagedInstanceCore** |

## 3. IP fija (para que no cambie al reiniciar)

En EC2 → **Elastic IPs** → **Allocate Elastic IP address** → **Allocate**. Luego **Actions → Associate Elastic IP address** → elige la instancia `brucefire` → **Associate**.

Copia esa IP y pégala en DuckDNS (paso 1).

## 4. Instalar (Session Manager)

EC2 → selecciona `brucefire` → **Connect** → pestaña **Session Manager** → **Connect**. En la terminal que se abre:

```bash
curl -fsSL https://raw.githubusercontent.com/Peugh18/BruceFireSAC---Sistema-de-Gestion-de-ventas/main/despliegue/instalar.sh -o instalar.sh && sudo bash instalar.sh
```

El instalador pregunta dos cosas. Se escriben **en esa terminal**, nunca en el chat:

- el dominio (`brucefire.duckdns.org`);
- la clave de DeepSeek para Chispa (puede quedar vacía).

La primera vez tarda unos 15 minutos. Al final muestra **una tabla con el correo y la contraseña de cada rol**. Cópiala en un lugar seguro: no se vuelve a mostrar.

Abre `https://brucefire.duckdns.org` y entra como gerente.

## 5. Después

- **Actualizar** tras un cambio en GitHub: vuelve a correr `sudo bash instalar.sh`. Conserva los datos y las claves.
- **Ver qué pasa**: `cd /opt/brucefire/despliegue && sudo docker compose logs -f web`
- **Respaldo**: se hace solo cada día a las 2:00 dentro del servidor. Pendiente: copiarlo fuera de la máquina.

## 6. Para facturar de verdad (mañana)

1. Copiar el certificado digital al servidor y cambiar a producción: `SUNAT_BETA=false`, junto con el usuario SOL, en `/opt/brucefire/despliegue/secretos/app.env`.
2. Base limpia con los datos reales (clientes, productos, stock y usuarios).
3. Series y correlativos según lo que diga la contadora.
