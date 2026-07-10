// Sandbox navigateur — vérifie que utils.js et logic.js chargent
// sans erreur dans un contexte browser (Playwright/Chromium).
// Lance avec : node sandbox.test.js

const { chromium } = require('playwright');
const path = require('path');

const UTILS_GLOBALS = [
  'normCommune', 'normalizeCommune', 'stripAccents', 'htmlEsc',
  'normalizeDate', 'normalizeHoraire', 'fmtDate', 'fmtCardDate',
  'buildICS',
];

const LOGIC_GLOBALS = [
  'normalizeMateriel', 'parseMateriel',
  'validateLotRow', 'validateLotForm', 'filterLotRows',
  'computeKpi', 'isEntryRetard', 'isEntryPasse', 'applyFilters',
];

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium',
    args: ['--no-sandbox'],
  });
  const page = await browser.newPage();

  const jsErrors = [];
  page.on('pageerror', err => jsErrors.push(err.message));

  await page.goto('about:blank');

  // Charger dans l'ordre prévu par l'étape 4
  for (const file of ['utils.js', 'logic.js']) {
    try {
      await page.addScriptTag({ path: path.resolve(__dirname, file) });
    } catch (e) {
      jsErrors.push(`${file} — injection échouée : ${e.message}`);
    }
  }

  const globals = await page.evaluate((names) => {
    const out = {};
    for (const n of names) out[n] = typeof window[n] === 'function';
    return out;
  }, [...UTILS_GLOBALS, ...LOGIC_GLOBALS]);

  await browser.close();

  let fail = 0;

  if (jsErrors.length) {
    console.log('\n── Erreurs JS ──');
    for (const e of jsErrors) { console.log(`  ✗ ${e}`); fail++; }
  }

  const missing = Object.entries(globals).filter(([, ok]) => !ok).map(([n]) => n);
  if (missing.length) {
    console.log('\n── Globals manquants ──');
    for (const n of missing) { console.log(`  ✗ ${n}`); fail++; }
  }

  if (fail === 0) {
    console.log('✅ sandbox — utils.js + logic.js chargent proprement, tous les globals présents');
  } else {
    console.log(`\n❌ sandbox — ${fail} problème(s) détecté(s)`);
  }

  process.exit(fail > 0 ? 1 : 0);
})();
