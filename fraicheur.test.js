// Essais de scripts/fraicheur-copies.sh (24/11/2026) : alerte « copie trop
// vieille ». Dates fictives, horloge figée par MAINTENANT (secondes UTC), aucun
// accès réseau. Cible alternative pour une mutation : FRAICHEUR_SH=<fichier>.
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');
const { spawnSync } = require('child_process');

const SH = process.env.FRAICHEUR_SH || path.join(__dirname, 'scripts', 'fraicheur-copies.sh');
const utc = (iso) => String(Date.parse(iso) / 1000);
function lancer(entree, maintenant) {
  const r = spawnSync('bash', [SH], { input: entree, env: { ...process.env, MAINTENANT: utc(maintenant) }, encoding: 'utf8' });
  return { code: r.status, sortie: r.stdout };
}

// Hiver : Paris = UTC+1. Copie de nuit 03:00 Paris le 23/11 = 02:00Z.
const HIVER_OK = 'ateliers-2026-11-23_030001.sql.gz\nCHIFFREE=2026-11-23 04:20\n';

test('les deux copies récentes : OK', () => {
  const r = lancer(HIVER_OK, '2026-11-24T04:00:00Z'); // 05:00 Paris = 26 h pile : pas encore d’alerte
  assert.equal(r.code, 0, r.sortie);
  assert.match(r.sortie, /^OK$/m);
});

test('copie de nuit de plus de 26 h : ALERTE', () => {
  const r = lancer(HIVER_OK, '2026-11-24T04:30:00Z'); // 05:30 Paris = 26,5 h
  assert.equal(r.code, 1);
  assert.match(r.sortie, /ALERTE : copie de nuit/);
  assert.doesNotMatch(r.sortie, /ALERTE : copie chiffrée/);
});

test('une copie de déploiement récente ne masque pas une nuit en panne (AG-052)', () => {
  const entree = 'ateliers-2026-11-22_030001.sql.gz\nateliers-2026-11-24_153000.sql.gz\nCHIFFREE=2026-11-24 04:20\n';
  const r = lancer(entree, '2026-11-24T15:40:00Z');
  assert.equal(r.code, 1);
  assert.match(r.sortie, /ALERTE : copie de nuit \(Alwaysdata\) — dernière copie du 2026-11-22 03:00/);
});

test('copie chiffrée trop vieille ou marqueur absent : ALERTE', () => {
  let r = lancer('ateliers-2026-11-24_030001.sql.gz\nCHIFFREE=2026-11-22 04:20\n', '2026-11-24T08:00:00Z');
  assert.equal(r.code, 1);
  assert.match(r.sortie, /ALERTE : copie chiffrée/);
  r = lancer('ateliers-2026-11-24_030001.sql.gz\nCHIFFREE=\n', '2026-11-24T08:00:00Z');
  assert.equal(r.code, 1);
  assert.match(r.sortie, /ALERTE : copie chiffrée hors site — aucune date lisible/);
});

test('dossier vide : ALERTE sur les deux copies', () => {
  const r = lancer('CHIFFREE=\n', '2026-11-24T08:00:00Z');
  assert.equal(r.code, 1);
  assert.match(r.sortie, /copie de nuit.*aucune date lisible/);
});

test('été (Paris = UTC+2) : le seuil suit l\'heure de Paris', () => {
  const entree = 'ateliers-2026-07-10_030001.sql.gz\nCHIFFREE=2026-07-10 04:20\n';
  // 03:00 Paris = 01:00Z. À 02:00Z le 11/07 (04:00 Paris) : 25 h → OK ; à 03:30Z : 26,5 h → ALERTE.
  assert.equal(lancer(entree, '2026-07-11T02:00:00Z').code, 0);
  assert.equal(lancer(entree, '2026-07-11T03:30:00Z').code, 1);
});

test('la plus récente est retenue même si la liste arrive dans le désordre', () => {
  const entree = 'ateliers-2026-11-23_030001.sql.gz\nateliers-2026-11-20_030001.sql.gz\nCHIFFREE=2026-11-23 04:20\n';
  assert.equal(lancer(entree, '2026-11-24T04:00:00Z').code, 0);
});
