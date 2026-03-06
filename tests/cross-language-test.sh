#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=== Cross-Language OTel Output Test ==="
echo ""

# Prerequisite: firstance-ts-test and firstance-php-test images must be built first
# docker build -t firstance-ts-test -f packages/typescript/tests/Dockerfile packages/typescript
# docker build -t firstance-php-test -f packages/php/tests/Dockerfile packages/php

echo "[1/4] Running TypeScript OTel log emission..."
TS_OUTPUT=$(docker run --rm \
  -v "$ROOT_DIR/tests/emit-log-ts.ts:/app/emit-log.ts:ro" \
  -e AWS_REGION="" \
  -e _X_AMZN_TRACE_ID="" \
  firstance-ts-test npx tsx emit-log.ts 2>/dev/null | grep -v "^$" | tail -1)

echo "TS output: $TS_OUTPUT"
echo ""

echo "[2/4] Running PHP OTel log emission..."
PHP_OUTPUT=$(docker run --rm \
  -v "$ROOT_DIR/tests/emit-log-php.php:/app/emit-log.php:ro" \
  -e AWS_REGION="" \
  -e _X_AMZN_TRACE_ID="" \
  firstance-php-test php emit-log.php 2>/dev/null | grep -v "^$" | tail -1)

echo "PHP output: $PHP_OUTPUT"
echo ""

echo "[3/4] Comparing JSON structure..."

# Extract and sort keys from both outputs
TS_KEYS=$(echo "$TS_OUTPUT" | python3 -c "
import json, sys
d = json.load(sys.stdin)
def get_keys(obj, prefix=''):
    keys = []
    for k, v in sorted(obj.items()):
        full = f'{prefix}.{k}' if prefix else k
        keys.append(full)
        if isinstance(v, dict):
            keys.extend(get_keys(v, full))
    return keys
print('\n'.join(get_keys(d)))
")

PHP_KEYS=$(echo "$PHP_OUTPUT" | python3 -c "
import json, sys
d = json.load(sys.stdin)
def get_keys(obj, prefix=''):
    keys = []
    for k, v in sorted(obj.items()):
        full = f'{prefix}.{k}' if prefix else k
        keys.append(full)
        if isinstance(v, dict):
            keys.extend(get_keys(v, full))
    return keys
print('\n'.join(get_keys(d)))
")

echo "TS keys:"
echo "$TS_KEYS"
echo ""
echo "PHP keys:"
echo "$PHP_KEYS"
echo ""

# Compare (ignoring service.language which differs by design)
TS_FILTERED=$(echo "$TS_KEYS" | grep -v "service.language" | sort)
PHP_FILTERED=$(echo "$PHP_KEYS" | grep -v "service.language" | sort)

if [ "$TS_FILTERED" = "$PHP_FILTERED" ]; then
    echo "[4/4] ✅ PASS — JSON structure is identical (excluding service.language)"
else
    echo "[4/4] ❌ FAIL — JSON structure differs!"
    echo ""
    echo "Diff:"
    diff <(echo "$TS_FILTERED") <(echo "$PHP_FILTERED") || true
    exit 1
fi

echo ""
echo "=== Verifying field values ==="

# Check common values match
TS_BODY=$(echo "$TS_OUTPUT" | python3 -c "import json,sys; print(json.load(sys.stdin)['Body'])")
PHP_BODY=$(echo "$PHP_OUTPUT" | python3 -c "import json,sys; print(json.load(sys.stdin)['Body'])")

TS_SEV=$(echo "$TS_OUTPUT" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['SeverityText'], d['SeverityNumber'])")
PHP_SEV=$(echo "$PHP_OUTPUT" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['SeverityText'], d['SeverityNumber'])")

TS_SVC=$(echo "$TS_OUTPUT" | python3 -c "import json,sys; print(json.load(sys.stdin)['Resource']['service.name'])")
PHP_SVC=$(echo "$PHP_OUTPUT" | python3 -c "import json,sys; print(json.load(sys.stdin)['Resource']['service.name'])")

PASS=true

[ "$TS_BODY" = "$PHP_BODY" ] && echo "  ✅ Body matches: $TS_BODY" || { echo "  ❌ Body mismatch: TS=$TS_BODY PHP=$PHP_BODY"; PASS=false; }
[ "$TS_SEV" = "$PHP_SEV" ] && echo "  ✅ Severity matches: $TS_SEV" || { echo "  ❌ Severity mismatch: TS=$TS_SEV PHP=$PHP_SEV"; PASS=false; }
[ "$TS_SVC" = "$PHP_SVC" ] && echo "  ✅ Service name matches: $TS_SVC" || { echo "  ❌ Service name mismatch: TS=$TS_SVC PHP=$PHP_SVC"; PASS=false; }

echo ""
if $PASS; then
    echo "=== ALL CROSS-LANGUAGE CHECKS PASSED ==="
else
    echo "=== SOME CHECKS FAILED ==="
    exit 1
fi
