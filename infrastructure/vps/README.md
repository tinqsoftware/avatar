# Despliegue del VPS

Este repositorio publica releases inmutables en `/var/www/avatar/releases` y
activa `/var/www/avatar/current`. El contenido persistente no forma parte de
Git: se mantiene en `/var/www/avatar/shared/storage`.

## Preparación única en el VPS

1. Crear `/var/www/avatar/{releases,shared/storage/app/public/avatars}` y
   asignarlo a `deploy:www-data` con permisos de escritura de grupo.
2. Copiar `.env.production.example` a `/var/www/avatar/shared/.env`, completar
   `APP_KEY`, MySQL, Redis y los tokens de Salad directamente en el VPS.
3. Instalar Redis, Supervisor y la extensión PHP `redis` si no existen. Usar
   `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` y `SESSION_DRIVER=redis`.
4. Instalar el archivo `nginx/juanito.conf` como
   `/etc/nginx/sites-available/avatar`, enlazarlo en `sites-enabled` y ajustar
   el socket PHP-FPM si el servidor no usa PHP 8.5.
5. Instalar `supervisor/juanito-queue.conf` como
   `/etc/supervisor/conf.d/avatar-queue.conf`; ejecutar `supervisorctl reread`
   y `supervisorctl update`.
6. El usuario `deploy` necesita acceso SSH por clave y sudo únicamente para
   `supervisorctl restart avatar-queue:*`. No usar contraseñas compartidas.

## Medios estáticos

Antes del primer despliegue, transferir desde la máquina de desarrollo los MP3
y el `.riv` al almacenamiento compartido. Ejemplo, sustituyendo IP o host del
VPS y ejecutándolo localmente:

```sh
rsync -av --progress storage/app/public/avatars/ deploy@VPS:/var/www/avatar/shared/storage/app/public/avatars/
```

Al terminar el primer release, ejecutar una vez en el VPS:

```sh
cd /var/www/avatar/current
php artisan storage:link
```

## GitHub Actions

Crear el entorno protegido `production` y estos secretos del repositorio:

- `VPS_HOST`: host o IP del VPS.
- `VPS_PORT`: puerto SSH, normalmente `22`.
- `VPS_SSH_KEY`: clave privada del usuario `deploy`.

El workflow de despliegue no copia `.env`, almacenamiento persistente ni
tokens. Un push a `main` crea y activa un release, ejecuta migraciones y
reinicia los dos workers.

## DNS y certificado

En Punto.pe crear `A ia.tinq.pe` y `A *.ia.tinq.pe` hacia la IP del VPS. Luego
emitir el certificado con DNS-01 para `ia.tinq.pe` y `*.ia.tinq.pe`; Punto.pe
pedirá crear el TXT temporal `_acme-challenge.ia.tinq.pe` en cada emisión o
renovación manual. No apuntar los subdominios a Salad.

## Router Salad

Publicar la imagen de `.github/workflows/router-image.yml` en GHCR, crear un
Container Group de Salad con esa imagen privada en GPU RTX 3090, puerto 8790 y
estas variables (los valores reales se configuran solo allí):

```dotenv
ROUTER_TOKEN=
ROUTER_MAX_ACTIVE=20
ROUTER_MAX_QUEUE=80
ROUTER_MODEL=Qwen/Qwen3-4B
```

En el `.env` del VPS usar el mismo `ROUTER_TOKEN` en
`AVATAR_ROUTER_TOKEN` y la URL pública del grupo en `AVATAR_ROUTER_URL`. El
endpoint `/health` solo marca el contenedor listo cuando vLLM ya cargó Qwen.
