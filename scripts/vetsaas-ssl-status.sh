#!/usr/bin/env bash
# Inventario SSL para Plataforma › Operaciones.
# PHP-FPM no puede leer /etc/letsencrypt: este cron (root) escribe un JSON.
#
# crontab -e (root), cada hora:
#   5 * * * * /var/www/vetsaas/scripts/vetsaas-ssl-status.sh >/dev/null 2>&1
#
# El timer de certbot (2× al día) sigue renovando. Esto solo alimenta el panel.

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${APP_DIR}/storage/app/ssl"
OUT="${OUT_DIR}/latest.json"

mkdir -p "${OUT_DIR}"

python3 - <<'PY' > "${OUT}.tmp"
import json, re, subprocess
from datetime import datetime, timezone

raw = subprocess.check_output(
    ["certbot", "certificates"],
    stderr=subprocess.STDOUT,
    text=True,
)

certs = []
cur = None
for line in raw.splitlines():
    name = re.search(r"Certificate Name:\s*(.+)$", line)
    if name:
        if cur:
            certs.append(cur)
        cur = {"name": name.group(1).strip(), "domains": [], "expiry": None, "valid": False}
        continue
    if cur is None:
        continue
    domains = re.search(r"Domains:\s*(.+)$", line)
    if domains:
        cur["domains"] = [d.strip() for d in domains.group(1).split() if d.strip()]
        continue
    exp = re.search(r"Expiry Date:\s*([0-9T:\-\+ ]+?)(?:\s+\((VALID|INVALID)[^)]*\))?$", line)
    if exp:
        stamp = exp.group(1).strip()
        try:
            dt = datetime.strptime(stamp[:19], "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc)
            cur["expiry"] = dt.strftime("%Y-%m-%dT%H:%M:%SZ")
        except ValueError:
            cur["expiry"] = stamp
        cur["valid"] = (exp.group(2) or "") == "VALID"

if cur:
    certs.append(cur)

print(json.dumps({
    "generated_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "certs": certs,
}, ensure_ascii=False))
PY

mv "${OUT}.tmp" "${OUT}"
chmod 644 "${OUT}"
chown www-data:www-data "${OUT}" 2>/dev/null || true
chmod 755 "${OUT_DIR}" 2>/dev/null || true
