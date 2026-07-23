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

# Sandbox navigateur (Playwright — utils.js + logic.js isolés)
if node sandbox.test.js 2>/dev/null; then
  echo "✅ sandbox.test.js — chargement navigateur OK"
else
  echo "❌ sandbox.test.js — erreur de chargement navigateur"
  FAIL=$((FAIL + 1))
fi

# E2E navigateur — uniquement si index.html, admin.html ou shared.js ont changé récemment
UI_CHANGED=$(git log --name-only -5 --format="" 2>/dev/null | grep -E '^(index|admin)\.html$|^shared\.js$' | head -1)
if [ -n "$UI_CHANGED" ]; then
  if node e2e.test.js 2>&1 | tail -1 | grep -q "✅"; then
    echo "✅ e2e.test.js — tous les onglets OK"
  else
    echo "❌ e2e.test.js — erreurs JS détectées"
    node e2e.test.js 2>&1 | grep "❌"
    FAIL=$((FAIL + 1))
  fi
else
  echo "⏭  e2e.test.js — ignoré (pas de changement UI dans les 5 derniers commits)"
fi

if [ "$FAIL" -eq 0 ]; then
  echo "=== ✅ Tous les tests passent — code sain ==="
else
  echo "=== ⚠️  $FAIL test(s) en échec — vérifier avant de commiter ==="
fi

exit 0
