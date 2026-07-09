// logic.test.js — tests unitaires des fonctions pures métier
// Runner : node --test logic.test.js

const {describe, it} = require('node:test');
const assert = require('node:assert/strict');

const {
  normalizeMateriel, parseMateriel,
  validateLotRow, validateLotForm, filterLotRows,
  computeKpi,
  isEntryRetard, isEntryPasse,
  applyFilters,
} = require('./logic.js');

// ──────────────────────────────────────────────────────────────
describe('normalizeMateriel', () => {
  it('tableau vide → chaîne vide', () => {
    assert.equal(normalizeMateriel([]), '');
  });
  it('Array → pipe-string', () => {
    assert.equal(normalizeMateriel(['Tablette', 'Imprimante']), 'Tablette|Imprimante');
  });
  it('pipe-string passthrough', () => {
    assert.equal(normalizeMateriel('Tablette|Imprimante'), 'Tablette|Imprimante');
  });
  it('comma-string → pipe-string', () => {
    assert.equal(normalizeMateriel('Tablette, Imprimante'), 'Tablette|Imprimante');
  });
  it('null → chaîne vide', () => {
    assert.equal(normalizeMateriel(null), '');
  });
  it('undefined → chaîne vide', () => {
    assert.equal(normalizeMateriel(undefined), '');
  });
  it('Array avec valeurs vides → filtre les vides', () => {
    assert.equal(normalizeMateriel(['Tablette', '', 'Imprimante']), 'Tablette|Imprimante');
  });
  it('Array avec un seul élément → sans pipe', () => {
    assert.equal(normalizeMateriel(['Tablette']), 'Tablette');
  });
});

// ──────────────────────────────────────────────────────────────
describe('parseMateriel', () => {
  it('pipe-string → Array', () => {
    assert.deepEqual(parseMateriel('Tablette|Imprimante'), ['Tablette', 'Imprimante']);
  });
  it('comma-string → Array', () => {
    assert.deepEqual(parseMateriel('Tablette, Imprimante'), ['Tablette', 'Imprimante']);
  });
  it('Array passthrough', () => {
    assert.deepEqual(parseMateriel(['Tablette']), ['Tablette']);
  });
  it('null → []', () => {
    assert.deepEqual(parseMateriel(null), []);
  });
  it('chaîne vide → []', () => {
    assert.deepEqual(parseMateriel(''), []);
  });
  it('valeur unique sans séparateur → Array à un élément', () => {
    assert.deepEqual(parseMateriel('Tablette'), ['Tablette']);
  });
});

// ──────────────────────────────────────────────────────────────
describe('validateLotRow', () => {
  const valid = {date:'2026-06-10', horaire:'9:00', ampm:'AM', thematique:'Numérique', inscrits:4};

  it('ligne valide → pas d\'erreur', () => {
    assert.deepEqual(validateLotRow(valid), {});
  });
  it('date manquante → erreur date', () => {
    assert.ok(validateLotRow({...valid, date:''}).date);
  });
  it('horaire manquant → erreur horaire', () => {
    assert.ok(validateLotRow({...valid, horaire:''}).horaire);
  });
  it('ampm manquant → erreur ampm', () => {
    assert.ok(validateLotRow({...valid, ampm:''}).ampm);
  });
  it('thematique null → erreur thematique', () => {
    assert.ok(validateLotRow({...valid, thematique:null}).thematique);
  });
  it('thematique espaces uniquement → erreur thematique', () => {
    assert.ok(validateLotRow({...valid, thematique:'   '}).thematique);
  });
  it('inscrits chaîne vide → erreur inscrits (champ requis)', () => {
    assert.ok(validateLotRow({...valid, inscrits:''}).inscrits);
  });
  it('inscrits=0 → pas d\'erreur (zéro est valide)', () => {
    assert.equal(validateLotRow({...valid, inscrits:0}).inscrits, undefined);
  });
  it('inscrits=4, presents absent → pas d\'erreur (presents optionnel)', () => {
    const row = {...valid, presents:''};
    assert.deepEqual(validateLotRow(row), {});
  });
  it('tout manquant → toutes les erreurs présentes', () => {
    const e = validateLotRow({date:'', horaire:'', ampm:'', thematique:'', inscrits:''});
    assert.ok(e.date && e.horaire && e.ampm && e.thematique && e.inscrits);
  });
});

// ──────────────────────────────────────────────────────────────
describe('validateLotForm', () => {
  const valid = {commune:'Agen', lieu:'MFR Agen', conseiller:'Alice', orienteur:'CAF', public:'Tous publics'};

  it('formulaire valide → pas d\'erreur', () => {
    assert.deepEqual(validateLotForm(valid), {});
  });
  it('commune vide → erreur commune', () => {
    assert.ok(validateLotForm({...valid, commune:''}).commune);
  });
  it('commune espaces → erreur commune', () => {
    assert.ok(validateLotForm({...valid, commune:'   '}).commune);
  });
  it('conseiller undefined → erreur conseiller', () => {
    assert.ok(validateLotForm({...valid, conseiller:undefined}).conseiller);
  });
  it('public chaîne vide → erreur public', () => {
    assert.ok(validateLotForm({...valid, public:''}).public);
  });
  it('plusieurs champs manquants → plusieurs erreurs', () => {
    const e = validateLotForm({commune:'', lieu:'', conseiller:'', orienteur:'', public:''});
    assert.equal(Object.keys(e).length, 5);
  });
});

// ──────────────────────────────────────────────────────────────
describe('filterLotRows', () => {
  it('toutes vides → tableau vide', () => {
    const rows = [{date:'', horaire:'', thematique:''}, {date:'', horaire:'', thematique:''}];
    assert.deepEqual(filterLotRows(rows), []);
  });
  it('une ligne avec date → conservée', () => {
    const rows = [{date:'2026-06-10', horaire:'', thematique:''}, {date:'', horaire:'', thematique:''}];
    assert.equal(filterLotRows(rows).length, 1);
  });
  it('thematique avec espaces uniquement → ligne considérée vide', () => {
    const rows = [{date:'', horaire:'', thematique:'   '}];
    assert.deepEqual(filterLotRows(rows), []);
  });
  it('thematique non vide → ligne conservée', () => {
    const rows = [{date:'', horaire:'', thematique:'Bureautique'}];
    assert.equal(filterLotRows(rows).length, 1);
  });
  it('toutes remplies → toutes conservées', () => {
    const rows = [
      {date:'2026-06-01', horaire:'9:00', thematique:'A'},
      {date:'2026-06-02', horaire:'10:00', thematique:'B'},
    ];
    assert.equal(filterLotRows(rows).length, 2);
  });
});

// ──────────────────────────────────────────────────────────────
describe('computeKpi', () => {
  const mkEntry = (statut, inscrits, presents) => ({statut, inscrits, presents});

  it('liste vide → tous zéros, tx=0', () => {
    const k = computeKpi([]);
    assert.deepEqual(k, {total:0, realises:0, annules:0, inscrits:0, presents:0, tx:0});
  });
  it('un Réalisé inscrits=4 présents=2 → tx=50', () => {
    const k = computeKpi([mkEntry('Réalisé', 4, 2)]);
    assert.equal(k.tx, 50);
    assert.equal(k.inscrits, 4);
    assert.equal(k.presents, 2);
  });
  it('Planifié n\'entre pas dans inscrits/présents', () => {
    const k = computeKpi([mkEntry('Planifié', 10, 8), mkEntry('Réalisé', 4, 4)]);
    assert.equal(k.inscrits, 4);
    assert.equal(k.presents, 4);
    assert.equal(k.total, 2);
    assert.equal(k.realises, 1);
  });
  it('Annulé n\'entre pas dans inscrits/présents mais compte dans annules', () => {
    const k = computeKpi([mkEntry('Annulé', 8, 0), mkEntry('Réalisé', 6, 3)]);
    assert.equal(k.inscrits, 6);
    assert.equal(k.annules, 1);
  });
  it('inscrits=\'\' traité comme 0', () => {
    const k = computeKpi([mkEntry('Réalisé', '', '')]);
    assert.equal(k.inscrits, 0);
    assert.equal(k.tx, 0);
  });
  it('pas de Réalisé → tx=0 (pas de division par zéro)', () => {
    const k = computeKpi([mkEntry('Planifié', 10, 0)]);
    assert.equal(k.tx, 0);
    assert.equal(k.inscrits, 0);
  });
  it('somme correcte sur plusieurs Réalisés', () => {
    const entries = [
      mkEntry('Réalisé', 4, 4),
      mkEntry('Réalisé', 6, 3),
      mkEntry('Planifié', 10, 0),
    ];
    const k = computeKpi(entries);
    assert.equal(k.inscrits, 10);
    assert.equal(k.presents, 7);
    assert.equal(k.tx, 70);
  });
});

// ──────────────────────────────────────────────────────────────
describe('isEntryRetard', () => {
  const TODAY = '2026-06-15';

  it('Planifié + date passée → true', () => {
    assert.ok(isEntryRetard({statut:'Planifié', date:'2026-06-10'}, TODAY));
  });
  it('Planifié + date future → false', () => {
    assert.ok(!isEntryRetard({statut:'Planifié', date:'2026-06-20'}, TODAY));
  });
  it('Planifié + date aujourd\'hui → false (pas encore en retard)', () => {
    assert.ok(!isEntryRetard({statut:'Planifié', date:TODAY}, TODAY));
  });
  it('Réalisé + date passée → false (pas un retard)', () => {
    assert.ok(!isEntryRetard({statut:'Réalisé', date:'2026-06-10'}, TODAY));
  });
  it('Planifié sans date → false', () => {
    assert.ok(!isEntryRetard({statut:'Planifié', date:''}, TODAY));
  });
});

// ──────────────────────────────────────────────────────────────
describe('isEntryPasse', () => {
  const TODAY = '2026-06-15';

  it('Réalisé + date passée → true', () => {
    assert.ok(isEntryPasse({statut:'Réalisé', date:'2026-06-10'}, TODAY));
  });
  it('Réalisé + date future → false', () => {
    assert.ok(!isEntryPasse({statut:'Réalisé', date:'2026-06-20'}, TODAY));
  });
  it('Planifié + date passée → false', () => {
    assert.ok(!isEntryPasse({statut:'Planifié', date:'2026-06-10'}, TODAY));
  });
  it('Réalisé sans date → false', () => {
    assert.ok(!isEntryPasse({statut:'Réalisé', date:''}, TODAY));
  });
});

// ──────────────────────────────────────────────────────────────
describe('applyFilters', () => {
  const entries = [
    {date:'2026-01-10', statut:'Réalisé',  commune:'AGEN',            conseiller:'Alice', public:'Tous publics', thematique:'Bureautique', lieu:'MFR', orienteur:'CAF', remarques:''},
    {date:'2026-02-15', statut:'Planifié', commune:'VILLENEUVE SUR LOT', conseiller:'Bob', public:'Séniors',      thematique:'Internet',     lieu:'BIJ', orienteur:'CD47', remarques:''},
    {date:'2026-03-20', statut:'Annulé',   commune:'AGEN',            conseiller:'Alice', public:'Tous publics', thematique:'Sécurité',     lieu:'CAF', orienteur:'CAF', remarques:'très utile'},
    {date:'2026-04-05', statut:'Réalisé',  commune:'MARMANDE',        conseiller:'Bob',   public:'Tous publics', thematique:'Bureautique', lieu:'ML',  orienteur:'ML', remarques:''},
  ];

  it('pas de filtre → toutes les entrées triées par date asc', () => {
    const r = applyFilters(entries);
    assert.equal(r.length, 4);
    assert.equal(r[0].date, '2026-01-10');
    assert.equal(r[3].date, '2026-04-05');
  });

  it('filtre statut Réalisé → 2 entrées', () => {
    const r = applyFilters(entries, {statut:'Réalisé'});
    assert.equal(r.length, 2);
    assert.ok(r.every(e => e.statut === 'Réalisé'));
  });

  it('filtre mois 2026-02 → 1 entrée', () => {
    const r = applyFilters(entries, {mois:'2026-02'});
    assert.equal(r.length, 1);
    assert.equal(r[0].date, '2026-02-15');
  });

  it('filtre commune AGEN → 2 entrées', () => {
    const r = applyFilters(entries, {commune:'AGEN'});
    assert.equal(r.length, 2);
  });

  it('filtre dateFrom/dateTo → entrées dans la plage', () => {
    const r = applyFilters(entries, {dateFrom:'2026-02-01', dateTo:'2026-03-31'});
    assert.equal(r.length, 2);
  });

  it('filtre conseiller Alice → 2 entrées', () => {
    const r = applyFilters(entries, {conseiller:'Alice'});
    assert.equal(r.length, 2);
  });

  it('filtre public Séniors → 1 entrée', () => {
    const r = applyFilters(entries, {'public':'Séniors'});
    assert.equal(r.length, 1);
  });

  it('recherche texte "bureau" → 2 entrées (accent insensible)', () => {
    const r = applyFilters(entries, {search:'bureau'});
    assert.equal(r.length, 2);
  });

  it('recherche texte "utile" → trouve dans remarques', () => {
    const r = applyFilters(entries, {search:'utile'});
    assert.equal(r.length, 1);
    assert.equal(r[0].thematique, 'Sécurité');
  });

  it('sortDir=-1 → tri décroissant', () => {
    const r = applyFilters(entries, {sortDir:-1});
    assert.equal(r[0].date, '2026-04-05');
    assert.equal(r[3].date, '2026-01-10');
  });

  it('filtres combinés statut+commune → intersection', () => {
    const r = applyFilters(entries, {statut:'Réalisé', commune:'AGEN'});
    assert.equal(r.length, 1);
    assert.equal(r[0].date, '2026-01-10');
  });

  it('filtre sans résultat → tableau vide', () => {
    const r = applyFilters(entries, {statut:'Reporté'});
    assert.deepEqual(r, []);
  });
});
