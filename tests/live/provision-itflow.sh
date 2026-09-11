#!/usr/bin/env bash
# Provision a live ITFlow 26.09 for testing Nexus Theme Manager 4.1.0.
#
# Runs inside WSL2 Ubuntu. Everything is scripted so the environment can be torn
# down and rebuilt without ceremony.
#
# The install MUST be a real git checkout with an origin remote: the whole point
# of this environment is to run ITFlow's own scripts/update_cli.php, which does
# `git fetch --all` then `git reset --hard origin/<branch>`. A zip deployment has
# no .git and ITFlow skips the application update entirely, which would test
# nothing.

set -euo pipefail

DB_NAME=itflow
DB_USER=itflow
DB_PASS='NexusTest!2609'
WEB_ROOT=/var/www/itflow
ADMIN_EMAIL='tgifol@itdonerightnc.com'
ADMIN_PASS='NexusAdmin!2609'

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

say "Installing packages"
export DEBIAN_FRONTEND=noninteractive
# php-imap left out on purpose: imap left PHP core in 8.4 and Ubuntu 26.04 has no
# package for it. ITFlow uses it only for the mail parser, which this test does not
# exercise.
sudo apt-get update -qq
sudo apt-get install -y -qq \
    apache2 mariadb-server git cron unzip curl \
    php php-cli php-mysql php-mbstring php-curl php-gd php-zip php-intl php-xml \
    libapache2-mod-php >/dev/null

say "Starting services"
sudo service mariadb start >/dev/null 2>&1 || true
sudo service apache2 start >/dev/null 2>&1 || true
sudo service cron start   >/dev/null 2>&1 || true
sleep 3

say "Creating database and user"
sudo mariadb <<SQL
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

say "Cloning ITFlow (real git checkout, tracking master)"
sudo rm -rf "${WEB_ROOT}"
sudo git clone -q https://github.com/itflow-org/itflow.git "${WEB_ROOT}"
sudo chown -R www-data:www-data "${WEB_ROOT}"
# git refuses to operate on a tree owned by another user without this. Set at the
# system level: www-data has no writable HOME, so --global cannot be written.
sudo git config --system --add safe.directory "${WEB_ROOT}"
echo "ITFlow at: $(sudo -u www-data git -C "${WEB_ROOT}" rev-parse HEAD)"
echo "Version:   $(grep -oP 'APP_VERSION",\s*"\K[^"]+' "${WEB_ROOT}/includes/app_version.php")"

say "Configuring Apache"
sudo tee /etc/apache2/sites-available/itflow.conf >/dev/null <<CONF
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot ${WEB_ROOT}
    <Directory ${WEB_ROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/itflow-error.log
    CustomLog \${APACHE_LOG_DIR}/itflow-access.log combined
</VirtualHost>
CONF
sudo a2dissite 000-default >/dev/null 2>&1 || true
sudo a2ensite itflow >/dev/null
sudo a2enmod rewrite >/dev/null
sudo service apache2 reload >/dev/null

say "Running ITFlow headless setup"
sudo -u www-data php "${WEB_ROOT}/scripts/setup_cli.php" \
    --host=localhost \
    --username="${DB_USER}" \
    --password="${DB_PASS}" \
    --database="${DB_NAME}" \
    --base-url=localhost \
    --locale=en_US \
    --timezone=America/New_York \
    --currency=USD \
    --company-name="IT Done Right" \
    --country="United States" \
    --user-name="Tyler Gifol" \
    --user-email="${ADMIN_EMAIL}" \
    --user-password="${ADMIN_PASS}" \
    --non-interactive

say "Applying any outstanding database updates"
sudo -u www-data php "${WEB_ROOT}/scripts/update_cli.php" --update_db || true

say "Done"
cat <<SUMMARY
ITFlow:    http://localhost/
Login:     ${ADMIN_EMAIL}
Password:  ${ADMIN_PASS}
Web root:  ${WEB_ROOT}
Database:  ${DB_NAME} / ${DB_USER}
SUMMARY
