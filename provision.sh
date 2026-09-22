#!/usr/bin/env bash
# One-time provisioning for a fresh Ubuntu 24.04 box to serve truehold.
# Run as root on the new server:  bash provision.sh <site-domain>
set -euo pipefail

DOMAIN="${1:?usage: provision.sh <site-domain>   e.g. truehold.yaenlinea.co}"
APP_DIR=/var/www/truehold
REPO=https://github.com/rivendesu12/Truehold.git
BRANCH="${BRANCH:-main}"
PHP=8.4

echo "==> [1/9] Base packages + security"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq software-properties-common curl git unzip ufw fail2ban \
  unattended-upgrades nginx

echo "==> [2/9] Firewall (SSH + HTTP + HTTPS only)"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null
systemctl enable --now fail2ban

echo "==> [3/9] Swap (2G) — headroom for composer on a 4G box"
if ! swapon --show | grep -q /swapfile; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap -q /swapfile && swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> [4/9] PHP $PHP (matches local dev exactly)"
# Use packages.sury.org rather than ppa:ondrej/php. Same maintainer, but the PPA
# route depends on Launchpad's API and PPA hosting, which returned 500/503 during
# the first provision of this box. sury.org is self-hosted and has a noble build.
# Drop any stale Launchpad PPA source; an unsigned repo makes apt-get update
# hard-fail, which aborts this script under `set -e`.
{ grep -rl "launchpadcontent" /etc/apt/sources.list.d/ 2>/dev/null || true; } | xargs -r rm -f
mkdir -p /etc/apt/keyrings
if [ ! -s /etc/apt/keyrings/sury-php.gpg ]; then
  curl -fsSL --max-time 30 https://packages.sury.org/php/apt.gpg -o /etc/apt/keyrings/sury-php.gpg
fi
. /etc/os-release
echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" \
  > /etc/apt/sources.list.d/sury-php.list
apt-get update -qq
apt-get install -y -qq \
  php$PHP-fpm php$PHP-cli php$PHP-mbstring php$PHP-xml php$PHP-curl \
  php$PHP-zip php$PHP-intl php$PHP-bcmath php$PHP-gd \
  php$PHP-mysql php$PHP-sqlite3 php$PHP-opcache

echo "==> [5/9] MySQL"
apt-get install -y -qq mysql-server
systemctl enable --now mysql

echo "==> [6/9] Composer"
if ! command -v composer >/dev/null; then
  curl -fsS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f /tmp/composer-setup.php
fi

echo "==> [7/9] Clone application"
mkdir -p "$(dirname "$APP_DIR")"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> [8/9] nginx site for $DOMAIN"
cat > /etc/nginx/sites-available/truehold <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    # Real visitor IP when behind Cloudflare
    set_real_ip_from 0.0.0.0/0;
    real_ip_header CF-Connecting-IP;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
    gzip_min_length 1024;
}
NGINX
ln -sf /etc/nginx/sites-available/truehold /etc/nginx/sites-enabled/truehold
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "==> [9/9] Nightly off-box-ready backup"
cat > /usr/local/bin/truehold-backup <<'BACKUP'
#!/usr/bin/env bash
# Dumps DB + storage to /var/backups/truehold, keeps 14 days.
set -euo pipefail
DEST=/var/backups/truehold
mkdir -p "$DEST"
STAMP=$(date +%Y%m%d-%H%M)
cd /var/www/truehold
if grep -q '^DB_CONNECTION=mysql' .env 2>/dev/null; then
  DB=$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
  mysqldump --single-transaction --quick "$DB" | gzip > "$DEST/db-$STAMP.sql.gz"
else
  SQLITE=$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
  [ -f "$SQLITE" ] && gzip -c "$SQLITE" > "$DEST/db-$STAMP.sqlite.gz"
fi
tar czf "$DEST/storage-$STAMP.tgz" storage
find "$DEST" -type f -mtime +14 -delete
BACKUP
chmod +x /usr/local/bin/truehold-backup
echo "15 3 * * * root /usr/local/bin/truehold-backup" > /etc/cron.d/truehold-backup

echo
echo "==> Provisioned. Remaining manual steps:"
echo "   1. Create the MySQL database + user, then write $APP_DIR/.env"
echo "      (template: $APP_DIR/.env.production.example)"
echo "   2. php artisan key:generate && php artisan migrate --force && php artisan optimize"
echo "   3. Point $DOMAIN at this server, then: certbot --nginx -d $DOMAIN"
echo "   4. Backups are LOCAL only so far — add off-box copies before real CRM data lands."
