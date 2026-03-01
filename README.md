# EPP CLI

A command-line interface for managing .at domains via the Extensible Provisioning Protocol (EPP). Built on Laravel and the [metaregistrar/php-epp-client](https://github.com/metaregistrar/php-epp-client) library, it provides 16 Artisan commands for domain and contact operations against the nic.at registry.

## Requirements

- PHP 8.2+
- Composer
- An EPP account with nic.at (or compatible registry)

### PHP Extensions

| Extension | Required by | Purpose |
|---|---|---|
| `openssl` | Laravel, EPP client | SSL/TLS connections to the EPP server |
| `dom` | EPP client | Parsing and building EPP XML messages |
| `libxml` | EPP client | XML processing (underlying `dom`) |
| `mbstring` | Laravel, EPP client | Multibyte string handling |
| `ctype` | Laravel | Character type checking |
| `filter` | Laravel | Input validation and sanitization |
| `hash` | Laravel | Hashing functions |
| `session` | Laravel | Session support |
| `tokenizer` | Laravel | PHP code tokenization |

Most of these are enabled by default in standard PHP installations. Verify with `php -m`.

## Installation

```bash
git clone <repository-url>
cd epp-cli
composer install
cp .env.example .env
php artisan key:generate
```

## Configuration

Add your EPP credentials and connection settings to `.env`:

```env
EPP_HOST=epp.nic.at
EPP_PORT=700
EPP_USERNAME=your-username
EPP_PASSWORD=your-password
EPP_SSL=true
EPP_VERIFY_PEER=true
EPP_ALLOW_SELF_SIGNED=false
EPP_TIMEOUT=10
EPP_LOG_DIR=
```

| Variable | Default | Description |
|---|---|---|
| `EPP_HOST` | `epp.nic.at` | EPP server hostname |
| `EPP_PORT` | `700` | EPP server port |
| `EPP_USERNAME` | | Registry username |
| `EPP_PASSWORD` | | Registry password |
| `EPP_SSL` | `true` | Use SSL/TLS connection |
| `EPP_VERIFY_PEER` | `true` | Verify SSL certificate |
| `EPP_ALLOW_SELF_SIGNED` | `false` | Accept self-signed certificates |
| `EPP_TIMEOUT` | `10` | Connection timeout in seconds |
| `EPP_LOG_DIR` | | Directory for EPP XML logs (disabled when empty) |

## Commands

All commands support `--cltrid` (client transaction ID, 4-64 chars) and `--logdir` (per-call log directory override). When required options are omitted, commands prompt interactively.

### Server

```bash
# Test connection and server capabilities
php artisan epp:hello
php artisan epp:hello --lang=en --ver=1.0

# Change EPP password
php artisan epp:change-password --newpassword=mynewpass123

# Poll server messages
php artisan epp:poll-message
php artisan epp:poll-message --delete-after-poll
```

### Domains

```bash
# Check availability
php artisan epp:check-domain --domain=example.at
php artisan epp:check-domain --domain=one.at --domain=two.at

# Get domain info
php artisan epp:info-domain --domain=example.at

# Create domain
php artisan epp:create-domain \
  --domain=example.at \
  --nameserver=ns1.example.com \
  --nameserver=ns2.example.com/1.2.3.4 \
  --registrant=REG001 \
  --techc=TECH001 \
  --authinfo='s3cretAuth!'

# Update domain (add/remove nameservers, contacts, statuses, DNSSEC)
php artisan epp:update-domain --domain=example.at --addns=ns3.example.com
php artisan epp:update-domain --domain=example.at --restore
php artisan epp:update-domain --domain=example.at --delsecdns-all

# Delete domain
php artisan epp:delete-domain --domain=example.at --scheduledate=now

# Withdraw domain
php artisan epp:withdraw-domain --domain=example.at --deletezone
```

### Contacts

```bash
# Get contact info
php artisan epp:info-contact --id=CONTACT001

# Create contact
php artisan epp:create-contact \
  --name="Jane Doe" \
  --street="Main Street 1" \
  --city=Vienna \
  --postalcode=1010 \
  --country=AT \
  --email=jane@example.at \
  --type=privateperson

# Update contact
php artisan epp:update-contact --id=CONTACT001 --email=new@example.at

# Delete contact
php artisan epp:delete-contact --id=CONTACT001
```

### Transfers

```bash
# Query transfer status
php artisan epp:transfer-query-domain --domain=example.at

# Request transfer
php artisan epp:transfer-request-domain --domain=example.at --authinfo=secret

# Cancel transfer
php artisan epp:transfer-cancel-domain --domain=example.at
```

## Output Format

Commands output structured lines for machine parsing:

```
SUCCESS: 1000
ATTR: name: example.at
ATTR: clTRID: abc-12345
ATTR: svTRID: srv-54321
```

Failed operations return a non-zero exit code:

```
FAILED: 2303
Domain info failed: Object does not exist
```

## Logging

Set `EPP_LOG_DIR` in `.env` or pass `--logdir=/path/to/logs` to any command. Log files are named by date (`2026-03-01.log`) and contain the raw EPP XML request/response exchange.
