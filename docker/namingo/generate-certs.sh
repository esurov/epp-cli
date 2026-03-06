#!/bin/bash
set -e

CERT_DIR="/opt/registry/epp"
mkdir -p "$CERT_DIR"

openssl req -x509 -newkey rsa:2048 \
    -keyout "$CERT_DIR/epp.key" \
    -out "$CERT_DIR/epp.crt" \
    -days 3650 \
    -nodes \
    -subj "/C=AT/ST=Vienna/L=Vienna/O=Namingo Test/CN=epp.test.example"

chmod 644 "$CERT_DIR/epp.crt"
chmod 600 "$CERT_DIR/epp.key"
