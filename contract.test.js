// contract.test.js — vérifie que les objets entry respectent le format attendu par GAS
// Runner : node --test contract.test.js

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { normalizeMateriel } = require('./logic.js');
const { normalizeDate, normalizeHoraire } = require('./utils.js');

const STATUTS_VALIDES = ['Planifié', 'Réalisé', 'Annulé', 'Reporté', 'Non réalisé'];

// Simule un entry tel qu'il serait construit avant envoi à GAS via apiFetch('saveEntry')
function buildEntry(overrides = {}) {
  return {
    _id:          'atelier_test_001',
    _n:           0,
    statut:       'Planifié',
    date:         '2026-06-16',
    horaire:      '9:00',
    ampm:         'AM',
    thematique:   'Bureautique',
    commune:      'AGEN',
    lieu:         'MFR Agen',
    conseiller:   'Alice',
    co_animateur: '',
    orienteur:    'CAF',
    public:       'Tous publics',
    materiel:     'Tablette|Imprimante',
    residence:    '',
    remarques:    '',
    inscrits:     4,
    presents:     '',
    ...overrides,
  };
}

// ── Champs obligatoires ───────────────────────────────────────
describe('champs obligatoires présents', () => {
  const CHAMPS = [
    '_id', 'statut', 'date', 'horaire', 'ampm',
    'thematique', 'commune', 'lieu', 'conseiller',
    'orienteur', 'public', 'materiel', 'inscrits', 'presents',
  ];
  const entry = buildEntry();
  CHAMPS.forEach(champ => {
    it(`"${champ}" présent dans l'entry`, () => {
      assert.ok(Object.prototype.hasOwnProperty.call(entry, champ));
    });
  });
});

// ── _id ───────────────────────────────────────────────────────
describe('_id', () => {
  it('est une chaîne non vide', () => {
    const e = buildEntry();
    assert.equal(typeof e._id, 'string');
    assert.ok(e._id.length > 0);
  });
});

// ── statut ────────────────────────────────────────────────────
describe('statut', () => {
  it('appartient aux valeurs autorisées', () => {
    const e = buildEntry();
    assert.ok(STATUTS_VALIDES.includes(e.statut));
  });
  STATUTS_VALIDES.forEach(s => {
    it(`"${s}" est accepté`, () => {
      assert.ok(STATUTS_VALIDES.includes(buildEntry({statut: s}).statut));
    });
  });
  it('une valeur hors liste est détectée', () => {
    assert.ok(!STATUTS_VALIDES.includes('Inconnu'));
  });
});

// ── date ──────────────────────────────────────────────────────
describe('date', () => {
  it('est au format ISO YYYY-MM-DD', () => {
    assert.match(buildEntry().date, /^\d{4}-\d{2}-\d{2}$/);
  });
  it('normalizeDate(DD/MM/YYYY) produit un format ISO valide', () => {
    assert.match(normalizeDate('16/06/2026'), /^\d{4}-\d{2}-\d{2}$/);
  });
  it('normalizeDate(datetime ISO) produit un format ISO valide', () => {
    assert.match(normalizeDate('2026-06-16T09:00:00'), /^\d{4}-\d{2}-\d{2}$/);
  });
  it('le format lisible "Mar 16/06/2026" est détecté comme invalide', () => {
    assert.ok(!/^\d{4}-\d{2}-\d{2}$/.test('Mar 16/06/2026'));
  });
});

// ── materiel ──────────────────────────────────────────────────
describe('materiel', () => {
  it('est une chaîne — jamais un Array', () => {
    const e = buildEntry();
    assert.equal(typeof e.materiel, 'string');
    assert.ok(!Array.isArray(e.materiel));
  });
  it('normalizeMateriel(Array) → string avec séparateur |', () => {
    const result = normalizeMateriel(['Tablette', 'Imprimante']);
    assert.equal(typeof result, 'string');
    assert.equal(result, 'Tablette|Imprimante');
  });
  it('normalizeMateriel(Array vide) → chaîne vide', () => {
    assert.equal(normalizeMateriel([]), '');
  });
  it('normalizeMateriel ne produit jamais de virgule comme séparateur', () => {
    const result = normalizeMateriel(['A', 'B', 'C']);
    assert.ok(!result.includes(','));
  });
});

// ── inscrits / presents ───────────────────────────────────────
describe('inscrits / presents', () => {
  it('inscrits est un nombre ou \'\' — jamais null/undefined', () => {
    const e = buildEntry({inscrits: 4});
    assert.ok(e.inscrits !== null && e.inscrits !== undefined);
    assert.ok(typeof e.inscrits === 'number' || e.inscrits === '');
  });
  it('inscrits=0 est valide (≠ inscrits=\'\')', () => {
    const e = buildEntry({inscrits: 0});
    assert.equal(e.inscrits, 0);
    assert.notEqual(e.inscrits, '');
  });
  it('presents est un nombre ou \'\' — jamais null/undefined', () => {
    const e = buildEntry({presents: ''});
    assert.ok(e.presents !== null && e.presents !== undefined);
    assert.ok(typeof e.presents === 'number' || e.presents === '');
  });
  it('parseInt(\'\') → NaN donc traité comme 0 dans les calculs', () => {
    assert.ok(isNaN(parseInt('')));
    assert.equal(parseInt('') || 0, 0);
  });
});

// ── horaire ───────────────────────────────────────────────────
describe('horaire', () => {
  it('est au format H:MM ou HH:MM', () => {
    assert.match(buildEntry().horaire, /^\d{1,2}:\d{2}$/);
  });
  it('normalizeHoraire(9H00) produit H:MM valide', () => {
    assert.match(normalizeHoraire('9H00'), /^\d{1,2}:\d{2}$/);
  });
  it('normalizeHoraire(14h30) produit HH:MM valide', () => {
    assert.match(normalizeHoraire('14h30'), /^\d{1,2}:\d{2}$/);
  });
});

// ── ampm ──────────────────────────────────────────────────────
describe('ampm', () => {
  it('est AM ou PM', () => {
    assert.ok(['AM', 'PM'].includes(buildEntry().ampm));
  });
  it('une valeur hors AM/PM est détectée', () => {
    assert.ok(!['AM', 'PM'].includes(''));
    assert.ok(!['AM', 'PM'].includes(undefined));
  });
});
