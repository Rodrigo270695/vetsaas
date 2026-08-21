# Reverb en producción (VPS)

## 1. Dependencias PHP (obligatorio tras `git pull`)

```bash
cd /var/www/vetsaas
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan vetsaas:tenant-migrate-all
pnpm install
pnpm run build
```

Si ves `Class "Pusher\Pusher" not found`, faltó el `composer install` (Reverb usa `pusher/pusher-php-server`).

Si `pnpm run build` falla en Wayfinder:

```bash
php artisan wayfinder:generate --with-form
# si eso falla, revisa .env / APP_KEY / permisos storage
pnpm run build
```

## 2. `.env` (producción)

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...

REVERB_HOST=vetsaas.orvae.pe
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Generar secrets:

```bash
php -r "echo 'REVERB_APP_ID='.bin2hex(random_bytes(8)).PHP_EOL; echo 'REVERB_APP_KEY='.bin2hex(random_bytes(16)).PHP_EOL; echo 'REVERB_APP_SECRET='.bin2hex(random_bytes(20)).PHP_EOL;"
```

## 3. Nginx (WebSocket TLS)

Dentro del `server` de `vetsaas.orvae.pe` (y opcionalmente el mismo bloque en `*.vetsaas.orvae.pe` si el WS va por el central):

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

Luego: `nginx -t && systemctl reload nginx`.

> Echo/Reverb por defecto usa el path `/app` del protocolo Pusher. El host público es `REVERB_HOST` con puerto `443` y `https`.

## 4. Supervisor

`/etc/supervisor/conf.d/vetsaas-reverb.conf`:

```ini
[program:vetsaas-reverb]
process_name=%(program_name)s
command=php /var/www/vetsaas/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/vetsaas/storage/logs/reverb.log
stopwaitsecs=10
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vetsaas-reverb
sudo supervisorctl status vetsaas-reverb
```

## 5. Verificación

1. `ss -lntp | grep 8080` → Reverb escuchando.
2. Chat en dos usuarios del mismo tenant → mensaje casi instantáneo.
3. Si Reverb cae, el chat sigue con polling (fallback).
