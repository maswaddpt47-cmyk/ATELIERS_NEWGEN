// utils.js et logic.js se chargent sans erreur dans un navigateur, et
// exposent leurs fonctions (porté de sandbox.test.js le 26/09/2026).
const { test, expect } = require('@playwright/test');
const path = require('path');

const GLOBALES = [
  'normCommune', 'normalizeCommune', 'stripAccents', 'htmlEsc',
  'normalizeDate', 'normalizeHoraire', 'fmtDate', 'fmtCardDate', 'buildICS',
  'normalizeMateriel', 'parseMateriel',
  'validateLotRow', 'validateLotForm', 'filterLotRows',
  'computeKpi', 'isEntryRetard', 'isEntryPasse', 'applyFilters',
];

test('utils.js + logic.js chargent proprement, toutes les fonctions présentes', async ({ page }) => {
  const erreurs = [];
  page.on('pageerror', e => erreurs.push(e.message));
  await page.goto('about:blank');
  for (const f of ['utils.js', 'logic.js']) {
    await page.addScriptTag({ path: path.resolve(__dirname, '..', f) });
  }
  const absentes = await page.evaluate(n => n.filter(x => typeof window[x] !== 'function'), GLOBALES);
  expect(erreurs).toEqual([]);
  expect(absentes).toEqual([]);
});
