#!/usr/bin/env bash
#
# Build a statically-compiled, self-contained epp-cli binary.
#
# Prerequisites: curl, tar (macOS also needs Xcode CLT / brew).
# The script downloads static-php-cli (spc), compiles a micro PHP runtime
# with the required extensions, builds the PHAR, then combines them into
# a single executable.
#
# Usage:
#   ./build-static.sh              # build for current platform
#   SPC_PHP_VERSION=8.3 ./build-static.sh   # override PHP version
#
set -euo pipefail

PHP_VERSION="${SPC_PHP_VERSION:-8.4}"
EXTENSIONS="openssl,dom,mbstring,libxml,phar,zlib,ctype,filter,tokenizer,iconv,xml,xmlreader,xmlwriter,simplexml,sockets"
BUILD_DIR="$(cd "$(dirname "$0")" && pwd)"
SPC_DIR="${BUILD_DIR}/spc-workspace"
SPC_BIN="${SPC_DIR}/spc"

# Detect platform
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"

case "${OS}" in
    linux)  SPC_OS="linux" ;;
    darwin) SPC_OS="macos" ;;
    *)      echo "Unsupported OS: ${OS}"; exit 1 ;;
esac

case "${ARCH}" in
    x86_64|amd64)  SPC_ARCH="x86_64" ;;
    aarch64|arm64) SPC_ARCH="aarch64" ;;
    *)             echo "Unsupported architecture: ${ARCH}"; exit 1 ;;
esac

echo "==> Platform: ${SPC_OS}-${SPC_ARCH}"
echo "==> PHP version: ${PHP_VERSION}"
echo "==> Extensions: ${EXTENSIONS}"

# --- Step 1: Download spc binary ---
mkdir -p "${SPC_DIR}"
if [ ! -f "${SPC_BIN}" ]; then
    echo "==> Downloading static-php-cli (spc)..."
    curl -fSL "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${SPC_OS}-${SPC_ARCH}" -o "${SPC_BIN}"
    chmod +x "${SPC_BIN}"
fi

# --- Step 2: Check environment ---
echo "==> Running spc doctor..."
cd "${SPC_DIR}"
"${SPC_BIN}" doctor --auto-fix || true

# --- Step 3: Download PHP sources + extension deps ---
echo "==> Downloading sources..."
"${SPC_BIN}" download --with-php="${PHP_VERSION}" --for-extensions="${EXTENSIONS}"

# --- Step 4: Build micro.sfx ---
echo "==> Building micro.sfx with extensions: ${EXTENSIONS}..."
"${SPC_BIN}" build "${EXTENSIONS}" --build-micro

MICRO_SFX="${SPC_DIR}/buildroot/bin/micro.sfx"
if [ ! -f "${MICRO_SFX}" ]; then
    echo "ERROR: micro.sfx not found at ${MICRO_SFX}"
    exit 1
fi
echo "==> micro.sfx built: $(ls -lh "${MICRO_SFX}" | awk '{print $5}')"

# --- Step 5: Build the PHAR ---
echo "==> Building PHAR..."
cd "${BUILD_DIR}"
composer install --no-dev --optimize-autoloader --quiet
vendor/bin/box compile --quiet

PHAR="${BUILD_DIR}/build/epp-cli.phar"
if [ ! -f "${PHAR}" ]; then
    echo "ERROR: PHAR not found at ${PHAR}"
    exit 1
fi
echo "==> PHAR built: $(ls -lh "${PHAR}" | awk '{print $5}')"

# --- Step 6: Combine micro.sfx + PHAR into single binary ---
OUTPUT="${BUILD_DIR}/build/epp-cli-${SPC_OS}-${SPC_ARCH}"
echo "==> Combining micro.sfx + PHAR -> ${OUTPUT}..."
cat "${MICRO_SFX}" "${PHAR}" > "${OUTPUT}"
chmod +x "${OUTPUT}"

echo "==> Done! Static binary: ${OUTPUT} ($(ls -lh "${OUTPUT}" | awk '{print $5}'))"
echo ""
echo "Test with:"
echo "  ${OUTPUT} list"

# Restore dev deps
cd "${BUILD_DIR}"
composer install --quiet 2>/dev/null || true
