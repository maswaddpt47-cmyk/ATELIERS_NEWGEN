// contract.test.js — vérifie que les objets entry respectent le format attendu par GAS
// Runner : node --test contract.test.js

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
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
    nb_ordinateurs:            '',
    date_prelevement_materiel: '',
    date_retour_materiel:      '',
    ...overrides,
  };
}

// ── Champs obligatoires ───────────────────────────────────────
describe('champs obligatoires présents', () => {
  const CHAMPS = [
    '_id', 'statut', 'date', 'horaire', 'ampm',
    'thematique', 'commune', 'lieu', 'conseiller',
    'orienteur', 'public', 'materiel', 'inscrits', 'presents',
    'nb_ordinateurs', 'date_prelevement_materiel', 'date_retour_materiel',
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

// ── nb_ordinateurs / date_retour_materiel ────────────────────────
describe('nb_ordinateurs / date_retour_materiel', () => {
  it('nb_ordinateurs est un nombre ou \'\' — jamais null/undefined', () => {
    const e = buildEntry({nb_ordinateurs: 4});
    assert.ok(e.nb_ordinateurs !== null && e.nb_ordinateurs !== undefined);
    assert.ok(typeof e.nb_ordinateurs === 'number' || e.nb_ordinateurs === '');
  });
  it('nb_ordinateurs=0 est valide (≠ nb_ordinateurs=\'\')', () => {
    const e = buildEntry({nb_ordinateurs: 0});
    assert.equal(e.nb_ordinateurs, 0);
    assert.notEqual(e.nb_ordinateurs, '');
  });
  it('date_retour_materiel vide est valide (matériel non concerné/pas de prêt)', () => {
    assert.equal(buildEntry().date_retour_materiel, '');
  });
  it('date_retour_materiel renseignée est au format ISO YYYY-MM-DD', () => {
    const e = buildEntry({date_retour_materiel: '2026-06-20'});
    assert.match(e.date_retour_materiel, /^\d{4}-\d{2}-\d{2}$/);
  });
  it('date_prelevement_materiel vide est valide (pas de prêt à l\'avance)', () => {
    assert.equal(buildEntry().date_prelevement_materiel, '');
  });
  it('date_prelevement_materiel renseignée est au format ISO YYYY-MM-DD', () => {
    const e = buildEntry({date_prelevement_materiel: '2026-06-13'});
    assert.match(e.date_prelevement_materiel, /^\d{4}-\d{2}-\d{2}$/);
  });
  it('date_prelevement_materiel peut être antérieure à date (retrait avant l\'atelier)', () => {
    const e = buildEntry({date: '2026-06-20', date_prelevement_materiel: '2026-06-17'});
    assert.ok(e.date_prelevement_materiel < e.date);
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

// ── Format rappels_actifs (config GAS) ───────────────────────
describe('rappels_actifs — format config GAS', () => {
  const exemples = [
    { 'Michel Aswad': true,  'Eva Capelle': false },
    { 'Michel Aswad': false },
    {},
  ];
  exemples.forEach((obj, i) => {
    it(`exemple ${i+1} : est un objet JSON sérialisable`, () => {
      const json = JSON.stringify(obj);
      const parsed = JSON.parse(json);
      assert.strictEqual(typeof parsed, 'object');
      assert.ok(parsed !== null && !Array.isArray(parsed));
    });
    it(`exemple ${i+1} : toutes les valeurs sont des booléens`, () => {
      Object.values(obj).forEach(v => assert.strictEqual(typeof v, 'boolean'));
    });
  });

  it('une valeur false désactive bien le conseiller', () => {
    const cfg = { 'Eva Capelle': false, 'Michel Aswad': true };
    assert.strictEqual(cfg['Eva Capelle'], false);
    assert.notStrictEqual(cfg['Michel Aswad'], false);
  });

  it('une clé absente est traitée comme actif (comportement GAS)', () => {
    const cfg = { 'Michel Aswad': true };
    // conseiller absent → rappelsActifs[conseiller] !== false → inclus
    assert.ok(cfg['Eva Capelle'] !== false);
  });

  it('JSON.parse(false) retourne false (cas global désactivé)', () => {
    assert.strictEqual(JSON.parse('false'), false);
    assert.strictEqual(typeof JSON.parse('false'), 'boolean');
  });
});
