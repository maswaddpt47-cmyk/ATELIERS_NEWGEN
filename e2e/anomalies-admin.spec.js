// Anomalies validées (AG-044, 04/11/2026) : « ✅ Valider » sort une anomalie vérifiée de la liste et des
// compteurs ; plus aucune liste « Validées » depuis le 20/11/2026 (demande de l'utilisateur) ; si la valeur concernée a changé
// depuis la validation (empreinte différente), l'anomalie reste dans la liste. Serveur simulé.
const { test, expect } = require('@playwright/test');
const atelier = (extra = {}) => ({
  _id: 'entry_tard', _n: 1, statut: 'Planifié', date: '2030-01-15', horaire: '17:30', commune: 'Agen', lieu: 'Salle', thematique: 'Mail',
  conseiller: 'Alice Martin', orienteur: 'CCAS', public: 'Séniors', inscrits: 5, presents: '', duree: 90, ...extra,   // duree : sinon « Durée vide » (AG-061) s'ajoute à l'anomalie testée
});
async function ouvrirAnomalies(page, baseURL, { validees = [], appels = [], attendreGet = null, erreurGet = false, repValider = { ok: true }, entries = [atelier()] } = {}) {
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const q = new URLSearchParams(route.request().postData() || ''); const url = route.request().url();
    const action = q.get('action') || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries, years: [], lists: { statuts: ['Planifié', 'Réalisé'], conseillers: ['Alice Martin'], publics: ['Séniors'], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    else if (action === 'getAnomaliesValidees') {
      if (attendreGet) await attendreGet;
      r = erreurGet === true || (erreurGet && erreurGet.encore-- > 0) ? { ok: false, error: 'Panne simulée' } : { ok: true, validees };
    }
    else if (action === 'validerAnomalie') { appels.push({ action, _id: q.get('_id'), categorie: q.get('categorie'), empreinte: q.get('empreinte') }); r = repValider; }
    else if (action === 'annulerAnomalie') appels.push({ action, _id: q.get('_id'), categorie: q.get('categorie'), empreinte: q.get('empreinte') });
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: /Anomalies/ }).first().click();
}

test('Admin — anomalies : valider sort l\'anomalie de la liste et des compteurs, sans liste « Validées »', async ({ page, baseURL }) => {
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
  await expect(page.getByRole('button', { name: /Validées/ })).toHaveCount(0);
  await expect(page.getByText(/— validée/)).toHaveCount(0);   // la ligne de liste ; le message « ✅ Anomalie validée » reste
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
  await expect(page.getByText('Commence à 17:30')).toHaveCount(0);
  await expect(page.getByRole('button', { name: /Validées/ })).toHaveCount(0);
  expect(errs).toEqual([]);
});

// Codex (AG-044) n° 1 : une lecture initiale plus ancienne que la validation réussie ne la défait pas.
test('Admin — anomalies : une lecture initiale tardive ne défait pas une validation réussie', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('dialog', d => d.accept());
  let libere; const attendreGet = new Promise(r => { libere = r; });
  await ouvrirAnomalies(page, baseURL, { attendreGet });
  await page.getByRole('button', { name: '✅ Valider' }).click();
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  libere();                                   // la lecture initiale (vide) arrive APRÈS la validation
  await page.waitForTimeout(400);
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.getByText('Commence à 17:30')).toHaveCount(0);
  expect(errs).toEqual([]);
});

// n° 2 : une erreur de lecture n'est pas une absence de validations ; reprise possible.
test('Admin — anomalies : erreur de lecture dite, comptes non établis, reprise', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  await ouvrirAnomalies(page, baseURL, { erreurGet: { encore: 1 }, validees: [{ atelier_id: 'entry_tard', categorie: 'tard', empreinte: '17:30', valide_le: '2026-11-01 09:00:00', par: 'Alice Martin' }] });
  await expect(page.getByRole('alert')).toContainText('n\'ont pas pu être lues');
  await expect(page.getByText('1 entrée(s) avec anomalie(s)')).toBeVisible();   // état inconnu, signalé
  await page.getByRole('button', { name: /Réessayer/ }).click();
  await expect(page.getByRole('alert')).toHaveCount(0);
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();   // lecture réussie : validation appliquée
  expect(errs).toEqual([]);
});

// n° 3 : la réponse de l'API (date et auteur) est acceptée ; ils ne s'affichent plus depuis le 20/11/2026.
test('Admin — anomalies : validation acceptée avec la date et l\'auteur rendus par l\'API', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('dialog', d => d.accept());
  await ouvrirAnomalies(page, baseURL, { repValider: { ok: true, valide_le: '2026-11-05 00:30:00', par: 'Alice Martin' } });
  await page.getByRole('button', { name: '✅ Valider' }).click();
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.getByText(/validée le/)).toHaveCount(0);
  expect(errs).toEqual([]);
});

// n° 4 : un conseiller dont toutes les anomalies sont validées reste dans les puces.
test('Admin — anomalies : le conseiller dont tout est validé reste choisissable', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('dialog', d => d.accept());
  await ouvrirAnomalies(page, baseURL);
  await expect(page.locator('.chip', { hasText: 'Alice Martin' })).toBeVisible();
  await page.getByRole('button', { name: '✅ Valider' }).click();
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  await expect(page.locator('.chip', { hasText: 'Alice Martin' })).toBeVisible();
  expect(errs).toEqual([]);
});

// AG-061, écarts démontrés par Codex (16/11/2026).
// n° 1 : une durée réellement vide se valide ; l'empreinte envoyée n'est pas vide (l'API refuse '').
test('Admin — anomalies : une durée vide se valide, avec une empreinte non vide', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('dialog', d => d.accept());
  const appels = [];
  await ouvrirAnomalies(page, baseURL, { appels, entries: [atelier({ _id: 'entry_duree', horaire: '10:00', duree: '' })] });
  await expect(page.getByText(/Durée vide : comptée 1 h 30/)).toBeVisible();
  await page.getByRole('button', { name: '✅ Valider' }).click();
  await expect(page.getByText('0 entrée(s) avec anomalie(s)')).toBeVisible();
  expect(appels).toEqual([{ action: 'validerAnomalie', _id: 'entry_duree', categorie: 'duree', empreinte: '(vide)' }]);
  expect(errs).toEqual([]);
});

// n° 2 : « date passée » suit le jour de Paris, pas le fuseau de l'appareil.
test.describe('appareil réglé sur Honolulu', () => {
  test.use({ timezoneId: 'Pacific/Honolulu' });
  test('Admin — anomalies : « Planifié, date passée » suit le jour de Paris', async ({ page, baseURL }) => {
    const errs = []; page.on('pageerror', e => errs.push(e.message));
    await page.clock.setFixedTime(new Date('2026-11-15T23:30:00Z'));   // Paris : 16/11 à 00:30 ; appareil : 15/11 à 13:30
    await ouvrirAnomalies(page, baseURL, { entries: [atelier({ _id: 'entry_retard', horaire: '10:00', date: '2026-11-15' })] });
    await expect(page.getByText(/Encore « Planifié » le .*15\/11\/2026/)).toBeVisible();
    expect(errs).toEqual([]);
  });
});
