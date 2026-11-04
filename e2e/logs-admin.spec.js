// Journal de connexion (AG-036, Codex n° 3 et 4, 28/10/2026) : une réponse en
// retard d'un autre mode n'écrase pas l'affichage courant ; une erreur d'un
// autre mode ne masque pas le tableau rendu depuis le cache. Serveur simulé,
// réponses getLogs libérées dans l'ordre choisi par le test.
const { test, expect } = require('@playwright/test');
const ligne = (conseiller, role, success = true) => ({ timestamp: new Date().toISOString(), conseiller, role, success, tentatives: 0, user_agent: '', source: 'index.html', site: '', action: 'login', ref: '' });
test('Admin — journal : réponse périmée ignorée, erreur effacée au retour du cache', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const attente = [];
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const body = route.request().postData() || ''; const url = route.request().url();
    const q = new URLSearchParams(body); const action = q.get('action') || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [], years: [], lists: { statuts: ['Réalisé'], conseillers: ['Alice Martin'], publics: [], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    else if (action === 'getUsageOnglets') r = { ok: true, usage: [] };
    else if (action === 'getLogs') { attente.push({ route, sansAdmin: q.get('sansAdmin') === '1' }); return; }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  const repondre = (i, r) => attente[i].route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: /Connexions/ }).first().click();
  await expect.poll(() => attente.length).toBe(1);
  expect(attente[0].sansAdmin).toBe(true);
  await repondre(0, { ok: true, logs: [ligne('Alice Martin', 'conseiller')], nbMasques: 7 });
  const bouton = page.getByRole('button', { name: /Admin masqué|Admin inclus/ });
  await expect(bouton).toContainText('Admin masqué (7');
  await expect(page.getByText('1 entrée', { exact: true })).toBeVisible();
  // Masqué → affiché (réponse retenue) → masqué (cache) ; la réponse « affiché » arrive après.
  await bouton.click();
  await expect.poll(() => attente.length).toBe(2);
  await bouton.click();
  await expect(bouton).toContainText('Admin masqué (7');
  await repondre(1, { ok: true, logs: [ligne('Alice Martin', 'conseiller'), ligne('Bob Durand', 'conseiller'), ligne('Admin', 'admin')] });
  await page.waitForTimeout(300);
  await expect(bouton).toContainText('Admin masqué (7');
  await expect(page.getByText('1 entrée', { exact: true })).toBeVisible();
  // Échec en mode affiché, puis retour au cache masqué : l'erreur disparaît.
  await bouton.click();
  await expect.poll(() => attente.length).toBe(3);
  await repondre(2, { ok: false, error: 'Panne simulée' });
  await expect(page.getByText('Panne simulée')).toBeVisible();
  await bouton.click();
  await expect(page.getByText('Panne simulée')).toHaveCount(0);
  await expect(page.getByText('1 entrée', { exact: true })).toBeVisible();
  expect(errs).toEqual([]);
});

// Onglet « Page travailleurs sociaux » de Connexions (AG-043, 04/11/2026) : le journal reste l'onglet
// d'arrivée, les connexions TS sont dans un onglet à part (lecture `tsStats`).
test('Admin — Connexions : onglet Page travailleurs sociaux', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const actions = [];
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const q = new URLSearchParams(route.request().postData() || ''); const url = route.request().url();
    const action = q.get('action') || new URL(url).searchParams.get('action'); actions.push(action);
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [], years: [], lists: { statuts: ['Réalisé'], conseillers: ['Alice Martin'], publics: [], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    else if (action === 'getUsageOnglets') r = { ok: true, usage: [] };
    else if (action === 'getLogs') r = { ok: true, logs: [] };
    else if (action === 'tsStats') r = { ok: true, depuis: '2026-10-06', comptes: [{ compte: 'agent@lotetgaronne.fr', type: 'TS', connexions: 3, derniere: '2026-11-04 09:12:00', echecs: 1 }], jours: [{ jour: '2026-11-04', connexions: 3, echecs: 1, creations: 1 }], total: { connexions: 3, echecs: 1 } };
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: /Connexions/ }).first().click();
  await expect.poll(() => actions.includes('getLogs')).toBe(true);
  expect(actions).not.toContain('tsStats');                    // pas de lecture TS tant que l'onglet n'est pas ouvert
  await page.getByRole('button', { name: /Page travailleurs sociaux/ }).click();
  await expect(page.getByText('Connexions à la page des travailleurs sociaux')).toBeVisible();
  await expect(page.getByText('agent@lotetgaronne.fr')).toBeVisible();
  await expect(page.getByText('3 connexions · 1 échec')).toBeVisible();
  await page.getByRole('button', { name: /Journal des connexions/ }).click();
  await expect(page.getByText('Connexions à la page des travailleurs sociaux')).toHaveCount(0);
  expect(errs).toEqual([]);
});
