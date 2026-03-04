#!/usr/bin/env bash
#
# EPP-CLI Integration Test Script
# ================================
# Runs a full lifecycle test: contacts, domains, updates, transfers, cleanup.
# Requires a working .env with valid EPP credentials.
#
# Usage: ./test-workflow.sh [epp-binary]
#   epp-binary defaults to "php bin/epp"

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
EPP="${1:-php bin/epp}"
TIMESTAMP=$(date +%s)
DOMAIN_PREFIX="epp-test-${TIMESTAMP}"
DOMAIN1="${DOMAIN_PREFIX}-1.at"
DOMAIN2="${DOMAIN_PREFIX}-2.at"
DOMAIN3="${DOMAIN_PREFIX}-3.at"
AUTHINFO="Test!Auth${TIMESTAMP}"

PASS=0
FAIL=0
TESTS=()

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
green()  { printf '\033[0;32m%s\033[0m\n' "$*"; }
red()    { printf '\033[0;31m%s\033[0m\n' "$*"; }
yellow() { printf '\033[0;33m%s\033[0m\n' "$*"; }
bold()   { printf '\033[1m%s\033[0m\n' "$*"; }

step() {
    echo
    bold "── $1 ──"
}

assert_success() {
    local description="$1"
    shift
    local output
    echo "  → $description"
    if output=$($EPP "$@" -n 2>&1); then
        if echo "$output" | grep -q "^SUCCESS:"; then
            green "    ✓ PASS"
            PASS=$((PASS + 1))
            TESTS+=("PASS: $description")
            echo "$output" | sed 's/^/    /'
            LAST_OUTPUT="$output"
            return 0
        else
            red "    ✗ FAIL – command succeeded but no SUCCESS line found"
            echo "$output" | sed 's/^/    /'
            FAIL=$((FAIL + 1))
            TESTS+=("FAIL: $description")
            LAST_OUTPUT="$output"
            return 1
        fi
    else
        red "    ✗ FAIL – command exited with non-zero status"
        echo "$output" | sed 's/^/    /'
        FAIL=$((FAIL + 1))
        TESTS+=("FAIL: $description")
        LAST_OUTPUT="$output"
        return 1
    fi
}

assert_failure() {
    local description="$1"
    shift
    local output
    echo "  → $description"
    if output=$($EPP "$@" -n 2>&1); then
        if echo "$output" | grep -q "^FAILED:"; then
            green "    ✓ PASS (expected failure)"
            PASS=$((PASS + 1))
            TESTS+=("PASS: $description")
            LAST_OUTPUT="$output"
            return 0
        else
            red "    ✗ FAIL – expected failure but got success"
            echo "$output" | sed 's/^/    /'
            FAIL=$((FAIL + 1))
            TESTS+=("FAIL: $description")
            LAST_OUTPUT="$output"
            return 1
        fi
    else
        green "    ✓ PASS (expected failure)"
        PASS=$((PASS + 1))
        TESTS+=("PASS: $description")
        LAST_OUTPUT="${output:-}"
        return 0
    fi
}

extract_handle() {
    echo "$LAST_OUTPUT" | grep -oP '(?<=ATTR: ID: ).*' | head -1 | tr -d '[:space:]'
}

# ---------------------------------------------------------------------------
# Pre-flight
# ---------------------------------------------------------------------------
bold "╔══════════════════════════════════════════════════════════════╗"
bold "║           EPP-CLI Integration Test Suite                    ║"
bold "╠══════════════════════════════════════════════════════════════╣"
bold "║  Domains:  $DOMAIN1"
bold "║           $DOMAIN2"
bold "║           $DOMAIN3"
bold "║  Binary:   $EPP"
bold "╚══════════════════════════════════════════════════════════════╝"

step "1. Server Hello – verify connectivity"
assert_success "Connect to EPP server" server:hello || {
    red "Cannot connect to EPP server. Aborting."
    exit 1
}

# ---------------------------------------------------------------------------
# Phase 1: Check domain availability (should be available)
# ---------------------------------------------------------------------------
step "2. Check domain availability (expect available)"
assert_success "Check ${DOMAIN1} is available" domain:check --domain "$DOMAIN1" || true
assert_success "Check ${DOMAIN2} is available" domain:check --domain "$DOMAIN2" || true
assert_success "Check ${DOMAIN3} is available" domain:check --domain "$DOMAIN3" || true

# ---------------------------------------------------------------------------
# Phase 2: Create first contact (registrant)
# ---------------------------------------------------------------------------
step "3. Create Contact #1 (registrant)"
echo "  → Create registrant contact"
CONTACT1=$($EPP contact:create \
    --name "Test Registrant ${TIMESTAMP}" \
    --street "Teststrasse 1" \
    --city "Wien" \
    --postalcode "1010" \
    --country "AT" \
    --email "test-registrant-${TIMESTAMP}@example.at" \
    --type privateperson \
    --org "" \
    --voice "+43.1234567" \
    --fax "" \
    --disclose-phone 1 \
    --disclose-fax 1 \
    --disclose-email 1 \
    --output-handle-only \
    -n 2>&1 | tr -d '[:space:]')
if [ -n "$CONTACT1" ]; then
    green "    ✓ PASS"
    PASS=$((PASS + 1))
    TESTS+=("PASS: Create registrant contact")
else
    red "    ✗ FAIL – no handle returned"
    FAIL=$((FAIL + 1))
    TESTS+=("FAIL: Create registrant contact")
    red "Cannot create registrant contact. Aborting."
    exit 1
fi
bold "  Contact #1 handle: ${CONTACT1}"

# ---------------------------------------------------------------------------
# Phase 3: Create domains
# ---------------------------------------------------------------------------
step "4. Create domains"
assert_success "Create ${DOMAIN1}" \
    domain:create \
    --domain "$DOMAIN1" \
    --nameserver "ns1.nic.at" --nameserver "ns2.nic.at" \
    --registrant "$CONTACT1" \
    --techc "$CONTACT1" \
    --authinfo "$AUTHINFO" || true

assert_success "Create ${DOMAIN2}" \
    domain:create \
    --domain "$DOMAIN2" \
    --nameserver "ns1.nic.at" --nameserver "ns2.nic.at" \
    --registrant "$CONTACT1" \
    --techc "$CONTACT1" \
    --authinfo "$AUTHINFO" || true

assert_success "Create ${DOMAIN3}" \
    domain:create \
    --domain "$DOMAIN3" \
    --nameserver "ns1.nic.at" --nameserver "ns2.nic.at" \
    --registrant "$CONTACT1" \
    --techc "$CONTACT1" \
    --authinfo "$AUTHINFO" || true

# ---------------------------------------------------------------------------
# Phase 4: Get info on domains
# ---------------------------------------------------------------------------
step "5. Get domain info"
assert_success "Info on ${DOMAIN1}" domain:info --domain "$DOMAIN1" || true
assert_success "Info on ${DOMAIN2}" domain:info --domain "$DOMAIN2" || true
assert_success "Info on ${DOMAIN3}" domain:info --domain "$DOMAIN3" || true

# ---------------------------------------------------------------------------
# Phase 5: Update domains (add/remove nameservers, change auth)
# ---------------------------------------------------------------------------
step "6. Update domains"
assert_success "Add nameserver to ${DOMAIN1}" \
    domain:update --domain "$DOMAIN1" \
    --addns "ns3.nic.at" || true

assert_success "Change authinfo on ${DOMAIN2}" \
    domain:update --domain "$DOMAIN2" \
    --authinfo "NewAuth!${TIMESTAMP}" || true

# ---------------------------------------------------------------------------
# Phase 6: Delete one domain
# ---------------------------------------------------------------------------
step "7. Delete ${DOMAIN3}"
assert_success "Delete ${DOMAIN3}" \
    domain:delete --domain "$DOMAIN3" --scheduledate now || true

# ---------------------------------------------------------------------------
# Phase 7: Update Contact #1
# ---------------------------------------------------------------------------
step "8. Update Contact #1"
assert_success "Update contact ${CONTACT1}" \
    contact:update \
    --id "$CONTACT1" \
    --street "Neue Testgasse 42" \
    --city "Graz" \
    --postalcode "8010" || true

# ---------------------------------------------------------------------------
# Phase 8: Get info on Contact #1
# ---------------------------------------------------------------------------
step "9. Get info on Contact #1"
assert_success "Info on contact ${CONTACT1}" \
    contact:info --id "$CONTACT1" || true

# ---------------------------------------------------------------------------
# Phase 9: Create second contact
# ---------------------------------------------------------------------------
step "10. Create Contact #2 (new tech contact)"
echo "  → Create tech contact"
CONTACT2=$($EPP contact:create \
    --name "Test TechC ${TIMESTAMP}" \
    --street "Techstrasse 99" \
    --city "Salzburg" \
    --postalcode "5020" \
    --country "AT" \
    --email "test-techc-${TIMESTAMP}@example.at" \
    --type organisation \
    --org "Test Org GmbH" \
    --voice "+43.9876543" \
    --fax "" \
    --disclose-phone 1 \
    --disclose-fax 1 \
    --disclose-email 1 \
    --output-handle-only \
    -n 2>&1 | tr -d '[:space:]')
if [ -n "$CONTACT2" ]; then
    green "    ✓ PASS"
    PASS=$((PASS + 1))
    TESTS+=("PASS: Create tech contact")
else
    red "    ✗ FAIL – no handle returned"
    FAIL=$((FAIL + 1))
    TESTS+=("FAIL: Create tech contact")
    red "Cannot create second contact. Continuing anyway."
fi
bold "  Contact #2 handle: ${CONTACT2}"

# ---------------------------------------------------------------------------
# Phase 10: Change registrant on DOMAIN1
# ---------------------------------------------------------------------------
step "11. Change registrant of ${DOMAIN1} to Contact #2"
if [ -n "$CONTACT2" ]; then
    assert_success "Change registrant on ${DOMAIN1}" \
        domain:update --domain "$DOMAIN1" \
        --registrant "$CONTACT2" || true
else
    yellow "  Skipped – Contact #2 handle not available"
fi

# ---------------------------------------------------------------------------
# Phase 11: Change tech-c on DOMAIN2
# ---------------------------------------------------------------------------
step "12. Change tech-c on ${DOMAIN2}"
if [ -n "$CONTACT2" ]; then
    assert_success "Add tech-c ${CONTACT2} to ${DOMAIN2}" \
        domain:update --domain "$DOMAIN2" \
        --addtechc "$CONTACT2" || true

    assert_success "Remove old tech-c ${CONTACT1} from ${DOMAIN2}" \
        domain:update --domain "$DOMAIN2" \
        --deltechc "$CONTACT1" || true
else
    yellow "  Skipped – Contact #2 handle not available"
fi

# ---------------------------------------------------------------------------
# Phase 12: Get info on contacts and remaining domains
# ---------------------------------------------------------------------------
step "13. Final info queries"
assert_success "Info on contact ${CONTACT1}" contact:info --id "$CONTACT1" || true
if [ -n "$CONTACT2" ]; then
    assert_success "Info on contact ${CONTACT2}" contact:info --id "$CONTACT2" || true
fi
assert_success "Info on ${DOMAIN1}" domain:info --domain "$DOMAIN1" || true
assert_success "Info on ${DOMAIN2}" domain:info --domain "$DOMAIN2" || true

# ---------------------------------------------------------------------------
# Phase 13: Cleanup – delete remaining domains
# ---------------------------------------------------------------------------
step "14. Cleanup – delete remaining domains"
assert_success "Delete ${DOMAIN1}" \
    domain:delete --domain "$DOMAIN1" --scheduledate now || true
assert_success "Delete ${DOMAIN2}" \
    domain:delete --domain "$DOMAIN2" --scheduledate now || true

# ---------------------------------------------------------------------------
# Phase 14: Verify domains are available again
# ---------------------------------------------------------------------------
step "15. Verify domains are available again"
assert_success "Check ${DOMAIN1} is available" domain:check --domain "$DOMAIN1" || true
assert_success "Check ${DOMAIN2} is available" domain:check --domain "$DOMAIN2" || true
assert_success "Check ${DOMAIN3} is available" domain:check --domain "$DOMAIN3" || true

# ---------------------------------------------------------------------------
# Phase 15: Cleanup – delete contacts
# ---------------------------------------------------------------------------
step "16. Cleanup – delete contacts"
assert_success "Delete contact ${CONTACT1}" contact:delete --id "$CONTACT1" || true
if [ -n "$CONTACT2" ]; then
    assert_success "Delete contact ${CONTACT2}" contact:delete --id "$CONTACT2" || true
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo
bold "╔══════════════════════════════════════════════════════════════╗"
bold "║                     Test Summary                            ║"
bold "╠══════════════════════════════════════════════════════════════╣"
for t in "${TESTS[@]}"; do
    if [[ "$t" == PASS:* ]]; then
        green "║  ✓ ${t#PASS: }"
    else
        red   "║  ✗ ${t#FAIL: }"
    fi
done
bold "╠══════════════════════════════════════════════════════════════╣"
green "║  Passed: ${PASS}"
if [ "$FAIL" -gt 0 ]; then
    red "║  Failed: ${FAIL}"
else
    green "║  Failed: ${FAIL}"
fi
bold "║  Total:  $((PASS + FAIL))"
bold "╚══════════════════════════════════════════════════════════════╝"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
