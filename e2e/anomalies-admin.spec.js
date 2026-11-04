// Anomalies validées (AG-044, 04/11/2026) : « ✅ Valider » sort une anomalie vérifiée de la liste et des
// compteurs ; « Validées » la montre avec « Annuler la validation » ; si la valeur concernée a changé
// depuis la validation (empreinte différente), l'anomalie reste dans la liste. Serveur simulé.
const { test, expect } = require('@playwright/test');
const atelier = (extra = {}) => ({
  _id: 'entry_tard', _n: 1, statut: 'Planifié', date: '2030-01-15', horaire: '17:30', commune: 'Agen', lieu: 'Salle', thematique: 'Mail',
  conseiller: 'Alice Martin', orienteur: 'CCAS', public: 'Séniors', inscrits: 5, presents: '', ...extra,
});
async function ouvrirAnomalies(page, baseURL, { validees = [], appels = [] } = {}) {
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const q = new URLSearchParams(route.request().postData() || ''); const url = route.request().url();
    const action = q.get('action') || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [atelier()], years: [], lists: { statuts: ['Planifié', 'Réalisé'], conseillers: ['Alice Martin'], publics: ['Séniors'], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    else if (action === 'getAnomaliesValidees') r = { ok: true, validees };
    else if (action === 'validerAnomalie' || action === 'annulerAnomalie') appels.push({ action, _id: q.get('_id'), categorie: q.get('categorie'), empreinte: q.get('empreinte') });
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: /Anomalies/ }).first().click();
}

test('Admin — anomalies : valider sort l\'anomalie de la liste et des compteurs, annuler la remet', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  page.on('dialog', d => d.accept());
  const appels = [];
  await ouvrirAnomalies(page, baseURL, { appels });
  await expect(page.getByText('1 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.getByText('Commence à 17:30')).toBeVisible();
  await page.getByRole('button', { name: '✅ Valider' }).click();
  await expect(page.getByText('Aucune anomalie dans cette catégorie')).toBeVisible();
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  expect(appels).toEqual([{ action: 'validerAnomalie', _id: 'entry_tard', categorie: 'tard', empreinte: '17:30' }]);
  const validees = page.getByRole('button', { name: /Validées \(1\)/ });
  await validees.click();
  await expect(page.getByText(/Début après 15h30 — validée/)).toBeVisible();
  await page.getByRole('button', { name: '↩ Annuler la validation' }).click();
  await expect(page.getByText('1 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.getByText('Commence à 17:30')).toBeVisible();
  expect(appels[1]).toEqual({ action: 'annulerAnomalie', _id: 'entry_tard', categorie: 'tard', empreinte: null });
  expect(errs).toEqual([]);
});

test('Admin — anomalies : une validation dont la valeur a changé n\'est plus valable', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  await ouvrirAnomalies(page, baseURL, { validees: [{ atelier_id: 'entry_tard', categorie: 'tard', empreinte: '17:00', valide_le: '2026-11-01 09:00:00', par: 'Alice Martin' }] });
  await expect(page.getByText('1 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.getByText('Commence à 17:30')).toBeVisible();
  await expect(page.getByRole('button', { name: /Validées/ })).toHaveCount(0);
  expect(errs).toEqual([]);
});

test('Admin — anomalies : une validation dont la valeur est inchangée masque l\'anomalie dès l\'ouverture', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  await ouvrirAnomalies(page, baseURL, { validees: [{ atelier_id: 'entry_tard', categorie: 'tard', empreinte: '17:30', valide_le: '2026-11-01 09:00:00', par: 'Alice Martin' }] });
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  await page.getByRole('button', { name: /Validées \(1\)/ }).click();
  await expect(page.getByText('validée le 01/11/2026 par Alice Martin')).toBeVisible();
  expect(errs).toEqual([]);
});
