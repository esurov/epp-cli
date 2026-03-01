# EPP CLI

A lightweight command-line interface for managing .at domains via the Extensible Provisioning Protocol (EPP). Built on Symfony Console and the [metaregistrar/php-epp-client](https://github.com/metaregistrar/php-epp-client) library, it provides 16 commands for domain and contact operations against the nic.at registry.

Distributable as a single PHAR file.

## Requirements

- PHP 8.2+
- Extensions: `openssl`, `dom`, `mbstring`

## Installation

### From source

```bash
git clone <repository-url>
cd epp-cli
composer install
cp .env.example .env
```

### As PHAR

Download `epp-cli.phar` from the releases page and place it anywhere on your system:

```bash
chmod +x epp-cli.phar
./epp-cli.phar epp:hello
```

The PHAR loads `.env` from the current working directory.

## Building the PHAR

```bash
composer install --no-dev
vendor/bin/box compile
# produces build/epp-cli.phar
```

## Configuration

Add your EPP credentials to `.env` in the working directory:

```env
EPP_HOST=epp.nic.at
EPP_PORT=700
EPP_USERNAME=your-username
EPP_PASSWORD=your-password
EPP_SSL=true
EPP_VERIFY_PEER=true
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
| `EPP_TIMEOUT` | `10` | Connection timeout in seconds |
| `EPP_LOG_DIR` | | Directory for EPP XML logs (disabled when empty) |

## Usage

Run without arguments for an interactive command picker:

```bash
php bin/epp
```

All commands support `--cltrid` (client transaction ID, 4-64 chars) and `--logdir` (per-call log directory override). When required options are omitted, commands prompt interactively.

### Server

```bash
# Test connection and server capabilities
php bin/epp epp:hello
php bin/epp epp:hello --lang=en --ver=1.0

# Change EPP password
php bin/epp epp:change-password --newpassword=mynewpass123

# Poll server messages
php bin/epp epp:poll-message
php bin/epp epp:poll-message --delete-after-poll
```

### Domains

```bash
# Check availability
php bin/epp epp:check-domain --domain=example.at
php bin/epp epp:check-domain --domain=one.at --domain=two.at

# Get domain info
php bin/epp epp:info-domain --domain=example.at

# Create domain
php bin/epp epp:create-domain \
  --domain=example.at \
  --nameserver=ns1.example.com \
  --nameserver=ns2.example.com/1.2.3.4 \
  --registrant=REG001 \
  --techc=TECH001 \
  --authinfo='s3cretAuth!'

# Update domain (add/remove nameservers, contacts, statuses, DNSSEC)
php bin/epp epp:update-domain --domain=example.at --addns=ns3.example.com
php bin/epp epp:update-domain --domain=example.at --restore
php bin/epp epp:update-domain --domain=example.at --delsecdns-all

# Delete domain
php bin/epp epp:delete-domain --domain=example.at --scheduledate=now

# Withdraw domain
php bin/epp epp:withdraw-domain --domain=example.at --deletezone
```

### Contacts

```bash
# Get contact info
php bin/epp epp:info-contact --id=CONTACT001

# Create contact
php bin/epp epp:create-contact \
  --name="Jane Doe" \
  --street="Main Street 1" \
  --city=Vienna \
  --postalcode=1010 \
  --country=AT \
  --email=jane@example.at \
  --type=privateperson

# Update contact
php bin/epp epp:update-contact --id=CONTACT001 --email=new@example.at

# Delete contact
php bin/epp epp:delete-contact --id=CONTACT001
```

### Transfers

```bash
# Query transfer status
php bin/epp epp:transfer-query-domain --domain=example.at

# Request transfer
php bin/epp epp:transfer-request-domain --domain=example.at --authinfo=secret

# Cancel transfer
php bin/epp epp:transfer-cancel-domain --domain=example.at
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
