# Subir Bruce Fire a AWS

Una sola EC2 con Docker: la web (HTTPS automático), la cola, las tareas programadas y MySQL. Es **otra máquina**, distinta de la de Memora. El costo base estimado es **US$ 18,31 al mes** con 730 horas: t4g.small en Ohio (US$ 0,0168/h), disco gp3 de 30 GB (US$ 2,40/mes) y una IPv4 pública (US$ 0,005/h). Precios consultados el 6 de noviembre de 2026; no incluye impuestos, consumo adicional ni descuentos/créditos de la cuenta. Referencias: [EC2](https://aws.amazon.com/ec2/pricing/on-demand/) · [EBS](https://aws.amazon.com/ebs/pricing/) · [IPv4 pública](https://aws.amazon.com/vpc/pricing/).

SUNAT queda en **modo prueba** (`SUNAT_BETA=true`) hasta tener el certificado digital y el usuario SOL. El instalador genera un certificado autofirmado exclusivo de pruebas dentro de `secretos/certificados/` y configura las credenciales públicas beta (`MODDATOS`). Ese certificado no sirve para facturación de producción.

## 1. Dirección gratis (DuckDNS)

1. Entra a <https://www.duckdns.org> con Google o GitHub.
2. Crea un subdominio, por ejemplo `brucefire`. Queda `brucefire.duckdns.org`.
3. Cuando tengas la IP fija (paso 3), pégala en **current ip** y pulsa **update ip**.

Después se puede cambiar a `sistema.extintoresbrucefire.com` desde Squarespace, sin perder datos.

## 2. La máquina EC2 (región us-east-2, Ohio, la misma de Memora)

En la consola de EC2, pulsa **Launch instance**:

| Campo                           | Valor                                                                                      |
| ------------------------------- | ------------------------------------------------------------------------------------------ |
| Nombre                          | `brucefire`                                                                                |
| Imagen                          | **Ubuntu Server 24.04 LTS**, arquitectura **64-bit (Arm)**                                 |
| Tipo                            | **t4g.small** (2 GB)                                                                       |
| Key pair                        | **Proceed without a key pair** (se entra con Session Manager)                              |
| Red → Security group            | Crear uno nuevo con entradas **HTTP 80** y **HTTPS 443** desde `0.0.0.0/0`. **Sin el 22**  |
| Almacenamiento                  | 30 GB gp3                                                                                  |
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
- **Reintentar una instalación que falló:** vuelve a correr el mismo comando. Conserva las claves y completa la inicialización pendiente. Al terminar una instalación correcta, las actualizaciones ya no cargan usuarios ni catálogo de capacitación.
- **Ver qué pasa**: `cd /opt/brucefire/despliegue && sudo docker compose logs -f web`
- **Respaldo**: se hace solo cada día a las 2:00 dentro del servidor. Pendiente: copiarlo fuera de la máquina.

## 6. Para facturar de verdad

1. Copiar el PEM real (certificado y clave privada) a `/opt/brucefire/despliegue/secretos/certificados/sunat-produccion.pem`, con permisos `600`. En `/opt/brucefire/despliegue/secretos/app.env`, configurar `SUNAT_CERT_PATH=/run/secrets/certificados/sunat-produccion.pem`, `SUNAT_BETA=false`, `SUNAT_RUC`, `SUNAT_USUARIO_SOL` y `SUNAT_CLAVE_SOL` reales. El usuario SOL se escribe sin el RUC, porque el sistema los concatena. No reutilizar el certificado ni las credenciales beta.
2. Base limpia con los datos reales (clientes, productos, stock y usuarios).
3. Series y correlativos según lo que diga la contadora.

Después de cambiar la configuración, ejecutar `sudo bash instalar.sh` para recrear los contenedores y actualizar la caché. El instalador no borra los datos de capacitación: la preparación de una base de producción se realiza aparte, con respaldo y autorización.

Referencias del modo prueba: [pautas beta de SUNAT](https://orientacion.sunat.gob.pe/12-pautas-servicio-beta) y [certificados de prueba en Greenter](https://fe-primer.greenter.dev/docs/requirements/).
