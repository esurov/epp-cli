# EPP CLI

A lightweight command-line interface for managing .at domains via the Extensible Provisioning Protocol (EPP). Built on Symfony Console and the [metaregistrar/php-epp-client](https://github.com/metaregistrar/php-epp-client) library, it provides 16 commands for domain and contact operations against the nic.at registry.

Distributable as a single PHAR file or a **static self-contained binary** (no PHP installation required) for macOS and Linux.

## Requirements

For running from source: PHP 8.2+ with extensions `openssl`, `dom`, `mbstring`.

The static binary has **zero dependencies** — it embeds PHP and all extensions.

## Installation

### Static binary (recommended)

Download the binary for your platform from the [releases page](../../releases):

| Platform | File |
|---|---|
| Linux x86_64 | `epp-cli-linux-x86_64` |
| Linux ARM64 | `epp-cli-linux-aarch64` |
| macOS Apple Silicon | `epp-cli-macos-aarch64` |

```bash
chmod +x epp-cli-linux-x86_64
./epp-cli-linux-x86_64 server:hello
```

### As PHAR

Download `epp-cli.phar` from the releases page (requires PHP 8.2+ on the system):

```bash
php epp-cli.phar server:hello
```

### From source

```bash
git clone <repository-url>
cd epp-cli
composer install
cp .env.example .env
```

All variants load `.env` from the current working directory.

## Building

### PHAR only

```bash
composer install --no-dev
vendor/bin/box compile
# produces build/epp-cli.phar
```

### Static binary (current platform)

Uses [static-php-cli](https://github.com/crazywhalecc/static-php-cli) to compile a PHP micro runtime, then combines it with the PHAR into a single executable.

```bash
./build-static.sh
# produces build/epp-cli-{os}-{arch}
```

Override PHP version: `SPC_PHP_VERSION=8.3 ./build-static.sh`

The first build downloads and compiles PHP from source — expect 10-30 minutes depending on your machine. Subsequent builds reuse cached sources.

### CI/CD

Push a `v*` tag to trigger the GitHub Actions workflow, which builds static binaries for all platforms (linux-x86_64, linux-aarch64, macos-aarch64) and attaches them to a GitHub release. You can also trigger the workflow manually from the Actions tab.

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
php bin/epp server:hello
php bin/epp server:hello --lang=en --ver=1.0

# Change EPP password
php bin/epp password:change --newpassword=mynewpass123

# Poll server messages
php bin/epp message:poll
php bin/epp message:poll --delete-after-poll
```

### Domains

```bash
# Check availability
php bin/epp domain:check --domain=example.at
php bin/epp domain:check --domain=one.at --domain=two.at

# Get domain info
php bin/epp domain:info --domain=example.at

# Create domain
php bin/epp domain:create \
  --domain=example.at \
  --nameserver=ns1.example.com \
  --nameserver=ns2.example.com/1.2.3.4 \
  --registrant=REG001 \
  --techc=TECH001 \
  --authinfo='s3cretAuth!'

# Update domain (add/remove nameservers, contacts, statuses, DNSSEC)
php bin/epp domain:update --domain=example.at --addns=ns3.example.com
php bin/epp domain:update --domain=example.at --restore
php bin/epp domain:update --domain=example.at --delsecdns-all

# Delete domain
php bin/epp domain:delete --domain=example.at --scheduledate=now

# Withdraw domain
php bin/epp domain:withdraw --domain=example.at --deletezone
```

### Contacts

```bash
# Get contact info
php bin/epp contact:info --id=CONTACT001

# Create contact
php bin/epp contact:create \
  --name="Jane Doe" \
  --street="Main Street 1" \
  --city=Vienna \
  --postalcode=1010 \
  --country=AT \
  --email=jane@example.at \
  --type=privateperson

# Create contact and capture the handle for scripting
HANDLE=$(php bin/epp contact:create \
  --name="Jane Doe" \
  --street="Main Street 1" \
  --city=Vienna \
  --postalcode=1010 \
  --country=AT \
  --email=jane@example.at \
  --type=privateperson \
  --output-handle-only)
php bin/epp domain:create --domain=example.at --registrant=$HANDLE

# Update contact
php bin/epp contact:update --id=CONTACT001 --email=new@example.at

# Delete contact
php bin/epp contact:delete --id=CONTACT001
```

### Transfers

```bash
# Query transfer status
php bin/epp domain:transfer-query --domain=example.at

# Request transfer
php bin/epp domain:transfer-request --domain=example.at --authinfo=secret

# Cancel transfer
php bin/epp domain:transfer-cancel --domain=example.at
```

## Local Development with Namingo (Docker)

A Docker Compose setup is included to run a local [Namingo](https://github.com/getnamingo/registry) EPP registry for testing.

### Build

```bash
docker compose build
```

### Start

```bash
docker compose up -d
```

### Start clean (wipe database)

```bash
docker compose down -v
docker compose up -d
```

The `-v` flag removes the MariaDB data volume, so the database is re-created and re-seeded from scratch on the next start.

### Services

| Service | Port | Description |
|---|---|---|
| EPP (TLS) | 700 | EPP server |
| WHOIS | 43 | WHOIS server |
| RDAP | 7500 | RDAP server |
| DAS | 1043 | Domain Availability Service |
| Control Panel | 8080 | Web UI |
| MariaDB | 3306 | Database |
| Redis | 6379 | Cache |

### Seeded data

The development environment is pre-seeded with:

- **TLD:** `.test` with pricing for create, renew, and transfer operations
- **Registrar:** `testregistrar` with $10,000 account balance
- **IP whitelist:** `0.0.0.0/0` (all IPs allowed)
- **Control panel admin:** `admin@test.example`

### Credentials

| Service | Username | Password |
|---|---|---|
| EPP | `testregistrar` | `testpassword123` |
| Control Panel | `admin@test.example` | `admin123` |
| MariaDB | `namingo` | `namingo_password` |
| MariaDB (root) | `root` | `namingo_root` |

### .env for local testing

```env
EPP_HOST=localhost
EPP_PORT=700
EPP_USERNAME=testregistrar
EPP_PASSWORD=testpassword123
EPP_SSL=true
EPP_VERIFY_PEER=false
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
