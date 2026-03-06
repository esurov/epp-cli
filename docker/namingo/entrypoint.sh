#!/bin/bash
set -e

echo "Waiting for MariaDB to be ready..."
until mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" &>/dev/null; do
    echo "MariaDB not ready yet, waiting..."
    sleep 2
done
echo "MariaDB is ready."

# Import schema if not already done
if ! mariadb -h"$DB_HOST" -uroot -p"$DB_ROOT_PASSWORD" -e "USE registry; SELECT COUNT(*) FROM domain_tld;" &>/dev/null; then
    echo "Importing database schema..."
    mariadb -h"$DB_HOST" -uroot -p"$DB_ROOT_PASSWORD" < /opt/registry/database/registry.mariadb.sql
    echo "Schema imported."

    echo "Seeding test data..."
    mariadb -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" registry < /opt/init-data.sql
    echo "Test data seeded."
else
    echo "Database already initialized, skipping schema import."
fi

# Generate TLS certificates for EPP if not present
if [ ! -f /opt/registry/epp/epp.crt ]; then
    echo "Generating self-signed TLS certificates for EPP..."
    /generate-certs.sh
    echo "Certificates generated."
fi

# Ensure log directory exists
mkdir -p /var/log/namingo
touch /var/log/namingo/epp.log
touch /var/log/namingo/epp_application.log
touch /var/log/namingo/whois.log
touch /var/log/namingo/rdap.log
touch /var/log/namingo/das.log
touch /var/log/namingo/automation.log
touch /var/log/namingo/cron.log

# Ensure CP cache directories exist and are writable
mkdir -p /opt/registry/cp/cache /opt/registry/cp/logs
chown -R www-data:www-data /opt/registry/cp/cache /opt/registry/cp/logs

echo "Starting supervisord..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/namingo.conf
