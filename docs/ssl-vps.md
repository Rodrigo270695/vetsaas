# SSL en el VPS (Let’s Encrypt / Certbot)

**Recomendación:** no renovar a mano “por las dudas”. `certbot.timer` corre **dos veces al día** y Let’s Encrypt renueva ~30 días antes de vencer. El panel **Plataforma → Operaciones** solo muestra el inventario.

Entrá al VPS **solo si**:

- un cert está **rojo** (≤7 días o vencido), o
- el wildcard de VetSaaS falló (el auto-renew con `authenticator = nginx` **no sirve** para `*.vetsaas.orvae.pe`).

Un cert vencido **no borra datos ni el cron de backup**. Solo corta HTTPS.

Credenciales Cloudflare (no las copies al repo): `/root/.secrets/certbot/cloudflare.ini`

---

## 0. Ver estado (copiar y pegar)

```bash
sudo systemctl list-timers | grep certbot
sudo systemctl status certbot.timer
sudo certbot certificates | grep -E 'Certificate Name:|Expiry Date:|Domains:'
sudo grep authenticator /etc/letsencrypt/renewal/*.conf
```

El timer tiene que estar **active**. VetSaaS wildcard tiene que decir `authenticator = dns-cloudflare`, no `nginx`.

Simular renovación (no toca certs reales):

```bash
sudo certbot renew --dry-run
```

Actualizar el JSON del panel:

```bash
sudo /var/www/vetsaas/scripts/vetsaas-ssl-status.sh
```

---

## 1. Cert normal (un dominio, HTTP-01 / nginx)

Ejemplo: `miboda.orvae.pe`. Cambiá el `--cert-name`.

```bash
sudo certbot renew --cert-name miboda.orvae.pe --force-renewal
sudo nginx -t && sudo systemctl reload nginx
sudo /var/www/vetsaas/scripts/vetsaas-ssl-status.sh
sudo certbot certificates | grep -A8 'Certificate Name: miboda.orvae.pe'
```

---

## 2. Wildcard VetSaaS (el que cubre todas las clínicas)

Este es el bloque que ya funcionó el 2026-09-03. Si `renew` falla, **no insistas con nginx**: usá DNS-01.

```bash
sudo certbot certonly \
  --cert-name vetsaas.orvae.pe \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/certbot/cloudflare.ini \
  --preferred-challenges dns-01 \
  --dns-cloudflare-propagation-seconds 30 \
  -d vetsaas.orvae.pe \
  -d '*.vetsaas.orvae.pe' \
  --force-renewal

sudo nginx -t && sudo systemctl reload nginx
sudo grep authenticator /etc/letsencrypt/renewal/vetsaas.orvae.pe.conf
sudo openssl x509 -in /etc/letsencrypt/live/vetsaas.orvae.pe/fullchain.pem -noout -dates -ext subjectAltName
sudo /var/www/vetsaas/scripts/vetsaas-ssl-status.sh
```

Tiene que decir `authenticator = dns-cloudflare` y dominios `vetsaas.orvae.pe` + `*.vetsaas.orvae.pe`.

Hay un duplicado `vetsaas.orvae.pe-0001` (solo apex). **No lo borres** sin ver qué usa nginx.

---

## 3. Otro wildcard (mismo patrón)

Ejemplos en este VPS: `tallersaas.orvae.pe`, `aulavirtual.orvae.pe`. Cambiá nombre y `-d`.

```bash
sudo cat /etc/letsencrypt/renewal/aulavirtual.orvae.pe.conf

sudo certbot certonly \
  --cert-name aulavirtual.orvae.pe \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/certbot/cloudflare.ini \
  --preferred-challenges dns-01 \
  --dns-cloudflare-propagation-seconds 30 \
  -d aulavirtual.orvae.pe \
  -d '*.aulavirtual.orvae.pe' \
  --force-renewal

sudo nginx -t && sudo systemctl reload nginx
```

---

## 4. Si falla

```bash
sudo tail -n 80 /var/log/letsencrypt/letsencrypt.log
sudo nginx -t
```

Causas típicas:

- Wildcard con `authenticator = nginx` → usar la sección 2.
- Token Cloudflare inválido → el `.ini` de TallerSaaS; no crear uno nuevo si ese archivo existe.
- Plugin ausente:

```bash
sudo apt install -y python3-certbot-dns-cloudflare
```

---

## 5. Inventario para el panel (una sola vez)

Ya debería estar en el crontab de root (`5 * * * *`). Si no:

```bash
sudo chmod +x /var/www/vetsaas/scripts/vetsaas-ssl-status.sh
sudo crontab -e
```

Línea:

```
5 * * * * /var/www/vetsaas/scripts/vetsaas-ssl-status.sh >/dev/null 2>&1
```

Correr ahora:

```bash
sudo /var/www/vetsaas/scripts/vetsaas-ssl-status.sh
```
