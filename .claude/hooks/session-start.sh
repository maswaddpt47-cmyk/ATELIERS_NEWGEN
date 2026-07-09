#!/bin/bash
# Session start hook — ATELIERS_NEWGEN
# Exécuté au démarrage de chaque session Claude Code (remote uniquement)

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"

echo "=== Tests ATELIERS_NEWGEN ==="

FAIL=0

run_suite() {
  local file="$1"
  local result
  result=$(node --test "$file" 2>&1)
  local pass=$(echo "$result" | grep "^# pass" | awk '{print $3}')
  local fail=$(echo "$result" | grep "^# fail" | awk '{print $3}')
  if [ "${fail:-0}" = "0" ]; then
    echo "✅ $file — $pass tests OK"
  else
    echo "❌ $file — $fail ÉCHEC(S)"
    echo "$result" | grep "^not ok" | head -5
    FAIL=$((FAIL + fail))
  fi
}

run_suite utils.test.js
run_suite logic.test.js
run_suite contract.test.js

if [ "$FAIL" -eq 0 ]; then
  echo "=== ✅ Tous les tests passent — code sain ==="
else
  echo "=== ⚠️  $FAIL test(s) en échec — vérifier avant de commiter ==="
fi

exit 0
