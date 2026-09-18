// logic.test.js — tests unitaires des fonctions pures métier
// Runner : node --test logic.test.js

const {describe, it} = require('node:test');
const assert = require('node:assert/strict');

const {
  normalizeMateriel, parseMateriel, filterMaterielsVisibles,
  validateLotRow, validateLotForm, filterLotRows,
  computeKpi,
  isEntryRetard, isEntryPasse,
  applyFilters,
  findMobileClassConflicts,
  findOrdinateursConflicts, periodePretMateriel, totalJourParConseiller,
  getPretsMateriel, totauxParJourMateriel,
  estConflitPasse,
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
describe('filterMaterielsVisibles', () => {
  const materiels = ['Videoprojecteur', 'Ecran', 'Classe mobile', 'Scanner'];
  it('sans caches, renvoie la liste complète', () => {
    assert.deepEqual(filterMaterielsVisibles(materiels, [], []), materiels);
  });
  it('retire les matériels masqués', () => {
    assert.deepEqual(filterMaterielsVisibles(materiels, ['Scanner'], []), ['Videoprojecteur', 'Ecran', 'Classe mobile']);
  });
  it('garde un matériel masqué s\'il est déjà sélectionné (édition)', () => {
    assert.deepEqual(filterMaterielsVisibles(materiels, ['Scanner'], ['Scanner']), materiels);
  });
  it('insensible à la casse/pluriel', () => {
    assert.deepEqual(filterMaterielsVisibles(materiels, ['scanners'], []), ['Videoprojecteur', 'Ecran', 'Classe mobile']);
  });
  it('caches vide/absent → aucun filtrage', () => {
    assert.deepEqual(filterMaterielsVisibles(materiels, undefined, undefined), materiels);
  });
  it('liste de matériels vide → []', () => {
    assert.deepEqual(filterMaterielsVisibles([], ['Scanner'], []), []);
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

// ── findMobileClassConflicts ──────────────────────────────────────────────────
describe('findMobileClassConflicts', () => {
  it('détecte 2 conseillers distincts avec Classe mobile le même jour', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'] },
    ];
    const conflits = findMobileClassConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].date, '2026-10-01');
    assert.equal(conflits[0].entries.length, 2);
  });

  it('pas de conflit si un seul conseiller ce jour-là (même avec 2 ateliers)', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 0);
  });

  it('pas de conflit si dates différentes', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-02', conseiller: 'Bob',   materiel: ['Classe mobile'] },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 0);
  });

  it('ignore les ateliers Annulés', () => {
    const entries = [
      { statut: 'Annulé',   date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'] },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 0);
  });

  it('ignore les ateliers sans Classe mobile', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Tablette'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Videoprojecteur'] },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 0);
  });

  it('insensible à la casse/pluriel', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe Mobiles'] },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 1);
  });

  it('accepte aussi le format pipe-string envoyé par GAS', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: 'Classe mobile|Tablette' },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: 'Classe mobile' },
    ];
    assert.equal(findMobileClassConflicts(entries).length, 1);
  });

  it('3 conseillers distincts le même jour → un seul groupe de conflit avec les 3 entrées', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Cynthia', materiel: ['Classe mobile'] },
    ];
    const conflits = findMobileClassConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].entries.length, 3);
  });

  it('tableau vide → aucun conflit', () => {
    assert.deepEqual(findMobileClassConflicts([]), []);
  });
});

// ── totalJourParConseiller ───────────────────────────────────────────────────
describe('totalJourParConseiller', () => {
  it('additionne des conseillers différents', () => {
    assert.equal(totalJourParConseiller([{ conseiller: 'Alice', qte: 6 }, { conseiller: 'Bob', qte: 3 }]), 9);
  });
  it('prend le max, pas la somme, pour un même conseiller', () => {
    assert.equal(totalJourParConseiller([{ conseiller: 'Alice', qte: 6 }, { conseiller: 'Alice', qte: 6 }]), 6);
  });
  it('mélange conseillers identiques et différents', () => {
    assert.equal(totalJourParConseiller([{ conseiller: 'Alice', qte: 6 }, { conseiller: 'Alice', qte: 4 }, { conseiller: 'Bob', qte: 3 }]), 9);
  });
  it('liste vide → 0', () => {
    assert.equal(totalJourParConseiller([]), 0);
  });
});

// ── findOrdinateursConflicts ────────────────────────────────────────────────
describe('findOrdinateursConflicts', () => {
  it('pas de conflit si le cumul ne dépasse pas le stock (10)', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 5 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 5 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('détecte un conflit si le cumul du même jour dépasse le stock', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].date, '2026-10-01');
    assert.equal(conflits[0].total, 12);
  });

  it('détecte un conflit sur une période de prêt qui chevauche (dates différentes)', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-10-05', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-03', conseiller: 'Bob', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].date, '2026-10-03');
  });

  it('la période part du prélèvement, pas de la date de l\'atelier (ex. retrait avant l\'atelier)', () => {
    // Atelier vendredi 20/11 à Fumel, retrait mardi 17 à Agen, retour le
    // mardi suivant 24/11 — l'indisponibilité réelle démarre le 17.
    const entries = [
      { statut: 'Planifié', date: '2026-11-20', date_prelevement_materiel: '2026-11-17', date_retour_materiel: '2026-11-24', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-11-18', conseiller: 'Bob', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].date, '2026-11-18');
  });

  it('pas de conflit si le même conseiller enchaîne deux ateliers dos-à-dos (pas de double comptage)', () => {
    // Retour du premier atelier = prélèvement du second, même conseiller :
    // c'est le même jeu de 6 ordinateurs, jamais 6+6=12 sur le stock (10).
    const entries = [
      { statut: 'Planifié', date: '2026-09-25', date_prelevement_materiel: '2026-09-22', date_retour_materiel: '2026-09-29', conseiller: 'Michel Aswad', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-02', date_prelevement_materiel: '2026-09-29', conseiller: 'Michel Aswad', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    assert.deepEqual(findOrdinateursConflicts(entries), []);
  });

  it('pas de conflit si les périodes de prêt ne se chevauchent pas', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-10-02', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-03', conseiller: 'Bob', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('ignore les ateliers Annulés', () => {
    const entries = [
      { statut: 'Annulé',   date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 8 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 8 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('ignore les ateliers sans Classe mobile même avec nb_ordinateurs renseigné', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Tablette'], nb_ordinateurs: 20 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('nb_ordinateurs manquant ou à 0 → 1 supposé (pas assez pour dépasser le stock à lui seul)', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'] },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 0 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('plusieurs Classe mobile sans quantité peuvent quand même dépasser le stock (1 chacun)', () => {
    // Demande explicite : Classe mobile cochée sans nombre saisi suppose au
    // moins 1 ordinateur — sur 11 conseillers un même jour, ça dépasse déjà
    // le stock par défaut (10), sans qu'aucun n'ait renseigné de quantité.
    const entries = Array.from({ length: 11 }, (_, i) => ({
      statut: 'Planifié', date: '2026-10-01', conseiller: 'Conseiller' + i, materiel: ['Classe mobile'],
    }));
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].total, 11);
  });

  it('accepte un stock personnalisé en 2e argument', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 3 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 3 },
    ];
    assert.equal(findOrdinateursConflicts(entries, 5).length, 1);
    assert.equal(findOrdinateursConflicts(entries, 10).length, 0);
  });

  it('date_retour_materiel antérieure ou égale à date n\'étend pas la période', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-09-28', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-02', conseiller: 'Bob', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    assert.equal(findOrdinateursConflicts(entries).length, 0);
  });

  it('tableau vide → aucun conflit', () => {
    assert.deepEqual(findOrdinateursConflicts([]), []);
  });

  it('fusionne les jours consécutifs en conflit en un seul bloc', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-10-04', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-10-02', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 1);
    assert.equal(conflits[0].date, '2026-10-01');
    assert.equal(conflits[0].dateFin, '2026-10-02');
    assert.equal(conflits[0].entries.length, 2);
  });

  it('ne fusionne pas deux blocs séparés par un jour sans conflit', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob',   materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-05', conseiller: 'Cynthia', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-05', conseiller: 'David',   materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    assert.equal(conflits.length, 2);
    assert.equal(conflits[0].date, '2026-10-01');
    assert.equal(conflits[1].date, '2026-10-05');
  });

  it('chaque entrée porte commune/lieu/dateDebut/dateFin pour l\'affichage', () => {
    const entries = [
      { statut: 'Planifié', date: '2026-10-01', date_retour_materiel: '2026-10-03', conseiller: 'Alice', commune: 'AGEN', lieu: 'MFR Agen', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
      { statut: 'Planifié', date: '2026-10-01', conseiller: 'Bob', commune: 'NERAC', lieu: 'CMS Nérac', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const conflits = findOrdinateursConflicts(entries);
    const alice = conflits[0].entries.find(e => e.conseiller === 'Alice');
    assert.equal(alice.commune, 'AGEN');
    assert.equal(alice.lieu, 'MFR Agen');
    assert.equal(alice.dateDebut, '2026-10-01');
    assert.equal(alice.dateFin, '2026-10-03');
    const bob = conflits[0].entries.find(e => e.conseiller === 'Bob');
    assert.equal(bob.dateDebut, '2026-10-01');
    assert.equal(bob.dateFin, '2026-10-01');
  });
});

// ──────────────────────────────────────────────────────────────
describe('periodePretMateriel', () => {
  it('sans prélèvement ni retour, la période est réduite à la date de l\'atelier', () => {
    const p = periodePretMateriel({ date: '2026-10-01' });
    assert.deepEqual(p, { debut: '2026-10-01', fin: '2026-10-01' });
  });
  it('prélèvement avant la date de l\'atelier étend le début', () => {
    const p = periodePretMateriel({ date: '2026-11-20', date_prelevement_materiel: '2026-11-17' });
    assert.equal(p.debut, '2026-11-17');
    assert.equal(p.fin, '2026-11-20');
  });
  it('un prélèvement après la date de l\'atelier est ignoré (repli sur la date)', () => {
    const p = periodePretMateriel({ date: '2026-11-20', date_prelevement_materiel: '2026-11-25' });
    assert.equal(p.debut, '2026-11-20');
  });
  it('retour après la date de l\'atelier étend la fin', () => {
    const p = periodePretMateriel({ date: '2026-11-20', date_retour_materiel: '2026-11-24' });
    assert.equal(p.fin, '2026-11-24');
  });
  it('prélèvement et retour combinés', () => {
    const p = periodePretMateriel({ date: '2026-11-20', date_prelevement_materiel: '2026-11-17', date_retour_materiel: '2026-11-24' });
    assert.deepEqual(p, { debut: '2026-11-17', fin: '2026-11-24' });
  });
});

// ──────────────────────────────────────────────────────────────
describe('getPretsMateriel', () => {
  it('liste un prêt avec sa période complète', () => {
    const entries = [
      { _id: 'a1', statut: 'Planifié', date: '2026-11-20', date_prelevement_materiel: '2026-11-17', date_retour_materiel: '2026-11-24', conseiller: 'Alice', commune: 'FUMEL', lieu: 'MFR', thematique: 'Bureautique', materiel: ['Classe mobile'], nb_ordinateurs: 6 },
    ];
    const prets = getPretsMateriel(entries);
    assert.equal(prets.length, 1);
    assert.deepEqual(prets[0], { _id: 'a1', conseiller: 'Alice', qte: 6, commune: 'FUMEL', lieu: 'MFR', thematique: 'Bureautique', dateAtelier: '2026-11-20', debut: '2026-11-17', fin: '2026-11-24' });
  });
  it('ignore les ateliers Annulés ou sans Classe mobile', () => {
    const entries = [
      { statut: 'Annulé', date: '2026-11-01', conseiller: 'A', materiel: ['Classe mobile'], nb_ordinateurs: 4 },
      { statut: 'Planifié', date: '2026-11-01', conseiller: 'B', materiel: ['Tablette'], nb_ordinateurs: 4 },
    ];
    assert.deepEqual(getPretsMateriel(entries), []);
  });
  it('Classe mobile cochée sans quantité renseignée → qte par défaut à 1 (pas exclu)', () => {
    // Demande explicite : un atelier avec Classe mobile déjà cochée mais
    // sans nombre d'ordinateurs saisi doit quand même apparaître dans la
    // Frise (qte=1 supposé), quitte à s'ajuster dès qu'une saisie
    // ultérieure précise le nombre réel — plutôt que de rester invisible.
    const entries = [
      { _id: 'c1', statut: 'Planifié', date: '2026-11-01', conseiller: 'C', materiel: ['Classe mobile'] },
      { _id: 'c2', statut: 'Planifié', date: '2026-11-02', conseiller: 'D', materiel: ['Classe mobile'], nb_ordinateurs: 0 },
    ];
    const prets = getPretsMateriel(entries);
    assert.equal(prets.length, 2);
    assert.equal(prets.find(p => p._id === 'c1').qte, 1);
    assert.equal(prets.find(p => p._id === 'c2').qte, 1);
  });
  it('trie par date de début (prélèvement inclus)', () => {
    const entries = [
      { _id: 'x', statut: 'Planifié', date: '2026-11-20', conseiller: 'Alice', materiel: ['Classe mobile'], nb_ordinateurs: 2 },
      { _id: 'y', statut: 'Planifié', date: '2026-11-15', date_prelevement_materiel: '2026-11-10', conseiller: 'Bob', materiel: ['Classe mobile'], nb_ordinateurs: 2 },
    ];
    const prets = getPretsMateriel(entries);
    assert.deepEqual(prets.map(p => p._id), ['y', 'x']);
  });
  it('tableau vide → []', () => {
    assert.deepEqual(getPretsMateriel([]), []);
  });
});

// ──────────────────────────────────────────────────────────────
describe('totauxParJourMateriel', () => {
  it('cumule les prêts de conseillers différents qui couvrent chaque jour', () => {
    const prets = [
      { conseiller: 'Alice', qte: 6, debut: '2026-11-17', fin: '2026-11-20' },
      { conseiller: 'Bob',   qte: 3, debut: '2026-11-19', fin: '2026-11-22' },
    ];
    const totaux = totauxParJourMateriel(prets, ['2026-11-17', '2026-11-19', '2026-11-21']);
    assert.deepEqual(totaux, { '2026-11-17': 6, '2026-11-19': 9, '2026-11-21': 3 });
  });
  it('ne double-compte pas le même conseiller sur deux prêts qui se chevauchent (ateliers dos-à-dos)', () => {
    // Le retour du premier tombe le même jour que le prélèvement du second :
    // c'est le même jeu de 6 ordinateurs, jamais 12.
    const prets = [
      { conseiller: 'Michel Aswad', qte: 6, debut: '2026-09-22', fin: '2026-09-29' },
      { conseiller: 'Michel Aswad', qte: 6, debut: '2026-09-29', fin: '2026-10-06' },
    ];
    assert.deepEqual(totauxParJourMateriel(prets, ['2026-09-29']), { '2026-09-29': 6 });
  });
  it('jour hors de toute période → 0', () => {
    const prets = [{ qte: 5, debut: '2026-11-01', fin: '2026-11-02' }];
    assert.deepEqual(totauxParJourMateriel(prets, ['2026-11-10']), { '2026-11-10': 0 });
  });
  it('aucun prêt → tous les jours à 0', () => {
    assert.deepEqual(totauxParJourMateriel([], ['2026-11-01', '2026-11-02']), { '2026-11-01': 0, '2026-11-02': 0 });
  });
});

// ──────────────────────────────────────────────────────────────
describe('estConflitPasse', () => {
  it('un conflit Classe mobile (sans dateFin) avant aujourd\'hui est passé', () => {
    assert.equal(estConflitPasse({ date: '2026-09-01' }, '2026-09-17'), true);
  });
  it('un conflit dont la date est aujourd\'hui n\'est pas passé', () => {
    assert.equal(estConflitPasse({ date: '2026-09-17' }, '2026-09-17'), false);
  });
  it('un conflit dont la date est dans le futur n\'est pas passé', () => {
    assert.equal(estConflitPasse({ date: '2026-10-01' }, '2026-09-17'), false);
  });
  it('un bloc stock ordinateurs (dateFin) : utilise dateFin, pas date', () => {
    assert.equal(estConflitPasse({ date: '2026-09-01', dateFin: '2026-09-20' }, '2026-09-17'), false);
  });
  it('un bloc stock ordinateurs entièrement passé (dateFin < today)', () => {
    assert.equal(estConflitPasse({ date: '2026-08-01', dateFin: '2026-08-05' }, '2026-09-17'), true);
  });
});
