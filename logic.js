// logic.js — fonctions pures métier testables sous Node.js
// Miroir de la logique embarquée dans les composants React de shared.js.
// Chargé uniquement pour les tests (node --test logic.test.js).

if (typeof require !== 'undefined') {
  var {stripAccents, normCommune, addJoursIso} = require('./utils.js');
}
// En contexte navigateur, stripAccents et normCommune sont déjà des globals (utils.js chargé avant)

// ── Matériel ──────────────────────────────────────────────────

// Normalise vers le pipe-string attendu par GAS (pour toute sauvegarde)
function normalizeMateriel(val) {
  if (!val) return '';
  if (Array.isArray(val)) return val.filter(Boolean).join('|');
  const s = String(val).trim();
  if (!s) return '';
  const sep = s.includes('|') ? '|' : ',';
  return s.split(sep).map(v => v.trim()).filter(Boolean).join('|');
}

// Normalise vers Array pour l'affichage et les formulaires
function parseMateriel(val) {
  if (!val) return [];
  if (Array.isArray(val)) return val.filter(Boolean);
  const s = String(val).trim();
  if (!s) return [];
  const sep = s.includes('|') ? '|' : ',';
  return s.split(sep).map(v => v.trim()).filter(Boolean);
}

// Retire de la liste des cases à cocher du formulaire les matériels masqués
// (config 'materiels_caches', Admin → Listes → toggle par matériel) — sans
// les retirer de la liste de référence (`materiels`, celle qui persiste pour
// les ateliers déjà enregistrés). Un matériel masqué mais déjà sélectionné
// (édition d'un atelier existant) reste affiché pour ne pas le désélectionner
// silencieusement.
function filterMaterielsVisibles(materiels, caches, selectionnes) {
  const cachesNorm = (caches || []).map(normalizeMatLabel);
  const selNorm = (selectionnes || []).map(normalizeMatLabel);
  return (materiels || []).filter(m => {
    const n = normalizeMatLabel(m);
    return cachesNorm.indexOf(n) === -1 || selNorm.indexOf(n) !== -1;
  });
}

// ── Validation lot ────────────────────────────────────────────

// Valide une ligne du tableau lot — retourne {} si valide
function validateLotRow(row) {
  const e = {};
  if (!row.date)                     e.date      = 'Requis';
  if (!row.horaire)                  e.horaire   = 'Requis';
  if (!row.ampm)                     e.ampm      = 'Requis';
  if (!(row.thematique || '').trim()) e.thematique = 'Requis';
  if (row.inscrits === '')           e.inscrits  = 'Requis';
  return e;
}

// Valide le formulaire commun du lot — retourne {} si valide
function validateLotForm(form) {
  const e = {};
  if (!(form.commune  || '').trim()) e.commune   = 'Requis';
  if (!(form.lieu     || '').trim()) e.lieu      = 'Requis';
  if (!form.conseiller)              e.conseiller = 'Requis';
  if (!(form.orienteur || '').trim()) e.orienteur = 'Requis';
  if (!form.public)                  e.public    = 'Requis';
  return e;
}

// Filtre les lignes vides du tableau lot
function filterLotRows(rows) {
  return rows.filter(r => r.date || r.horaire || (r.thematique || '').trim());
}

// ── KPI ───────────────────────────────────────────────────────

// Calcule les KPIs sur un tableau d'entrées déjà filtré.
// Inscrits/présents ne portent que sur les Réalisés.
function computeKpi(entries) {
  const realises = entries.filter(e => e.statut === 'Réalisé');
  const annules  = entries.filter(e => e.statut === 'Annulé').length;
  const inscrits = realises.reduce((s, e) => s + (parseInt(e.inscrits) || 0), 0);
  const presents = realises.reduce((s, e) => s + (parseInt(e.presents) || 0), 0);
  const tx       = inscrits > 0 ? Math.round(presents / inscrits * 100) : 0;
  return {total: entries.length, realises: realises.length, annules, inscrits, presents, tx};
}

// ── Statut ────────────────────────────────────────────────────

// today = chaîne ISO YYYY-MM-DD (paramètre explicite pour testabilité)
function isEntryRetard(entry, today) {
  return entry.statut === 'Planifié' && !!entry.date && entry.date < today;
}
function isEntryPasse(entry, today) {
  return entry.statut === 'Réalisé' && !!entry.date && entry.date < today;
}

// ── Filtres ───────────────────────────────────────────────────

// Applique les filtres de l'historique et retourne les entrées triées.
// filtres: {statut, mois, commune, conseiller, public, dateFrom, dateTo, search, sortDir}
function applyFilters(entries, filtres = {}) {
  const {
    statut     = 'Tous',
    mois       = 'Tous',
    commune    = 'Toutes',
    conseiller = 'Tous',
    public: pub = 'Tous',
    dateFrom   = '',
    dateTo     = '',
    search     = '',
    sortDir    = 1,
  } = filtres;

  let r = entries;

  if (statut !== 'Tous')
    r = r.filter(e => e.statut === statut);

  if (mois !== 'Tous')
    r = r.filter(e => e.date && e.date.startsWith(mois));

  if (commune !== 'Toutes') {
    const normFilt = normCommune(String(commune).toUpperCase());
    r = r.filter(e =>
      e.commune === commune ||
      normCommune(String(e.commune || '').toUpperCase()) === normFilt
    );
  }

  if (conseiller !== 'Tous')
    r = r.filter(e => e.conseiller === conseiller);

  if (pub !== 'Tous')
    r = r.filter(e => (e.public || 'Tous publics') === pub);

  if (dateFrom)
    r = r.filter(e => e.date && e.date >= dateFrom);

  if (dateTo)
    r = r.filter(e => e.date && e.date <= dateTo);

  if (search) {
    const q = stripAccents(search);
    r = r.filter(e =>
      [e.lieu, e.thematique, e.orienteur, e.commune, e.public, e.remarques]
        .some(v => stripAccents(String(v || '')).includes(q))
    );
  }

  return [...r].sort((a, b) => {
    const va = a.date || '', vb = b.date || '';
    return va < vb ? -sortDir : va > vb ? sortDir : 0;
  });
}

// ── Conflits matériel ─────────────────────────────────────────

// Miroir de findMobileClassConflicts (shared.js) : Classe mobile est un
// matériel physique unique, ne peut pas être à deux endroits le même jour.
// entries[].materiel est attendu en tableau (forme en mémoire côté navigateur,
// avant la conversion pipe-string faite juste avant l'envoi à GAS).
function findMobileClassConflicts(entries) {
  const parDate = {};
  (entries || []).forEach(e => {
    if (e.statut === 'Annulé') return;
    if (!e.date) return;
    if (!parseMateriel(e.materiel).some(m => normalizeMatLabel(m) === 'classemobile')) return;
    (parDate[e.date] = parDate[e.date] || []).push(e);
  });
  return Object.keys(parDate)
    .map(date => ({ date, entries: parDate[date] }))
    .filter(g => new Set(g.entries.map(e => e.conseiller)).size >= 2)
    .sort((a, b) => a.date < b.date ? -1 : a.date > b.date ? 1 : 0);
}

// Même normalisation que normalizeMat (shared.js) — insensible à la casse,
// aux accents et au pluriel.
function normalizeMatLabel(s) {
  return stripAccents(String(s || '').toLowerCase()).replace(/\s+/g, '').replace(/s$/, '');
}

// Miroir de findOrdinateursConflicts (shared.js). Contrairement à
// findMobileClassConflicts (même jour uniquement, matériel considéré comme
// unique/indivisible), ici la quantité (nb_ordinateurs, saisie manuelle) et
// la date de retour (date_retour_materiel) forment une période de prêt :
// deux ateliers à des dates différentes peuvent quand même se disputer le
// stock si le premier n'a pas rendu le matériel avant que le second en ait
// besoin. On étale chaque atelier sur les jours qu'il occupe (date →
// date_retour_materiel inclus, ou juste date si pas de retour renseigné),
// on cumule les quantités par jour, puis on fusionne les jours consécutifs
// en conflit en un seul bloc (date de début → date de fin) — pour ne pas
// répéter les mêmes conseillers sur chaque jour d'un même chevauchement de
// plusieurs jours. Chaque conseiller d'un bloc est en conflit avec tous les
// autres conseillers du même bloc.
const STOCK_ORDINATEURS = 10;
// Cumul du jour à partir d'une liste d'items {conseiller, qte} — au max par
// conseiller, pas en somme : un même conseiller qui enchaîne deux ateliers
// dos-à-dos (retour du premier = prélèvement du second, sans repasser par
// le local) n'a physiquement qu'un seul jeu d'ordinateurs en main ce
// jour-là, jamais deux fois sa quantité. La contention réelle du stock ne
// vient que de conseillers DIFFÉRENTS qui en ont besoin en même temps.
function totalJourParConseiller(items) {
  const parConseiller = {};
  (items || []).forEach(x => { parConseiller[x.conseiller] = Math.max(parConseiller[x.conseiller] || 0, x.qte); });
  return Object.values(parConseiller).reduce((s, q) => s + q, 0);
}
// Jour de semaine ISO (0=dimanche...6=samedi), indépendant du fuseau (parse
// manuel plutôt que new Date(dateIso) qui interprète 'YYYY-MM-DD' en UTC).
function estWeekend(dateIso) {
  const [y, m, j] = dateIso.split('-').map(Number);
  const jourSemaine = new Date(y, m - 1, j).getDay();
  return jourSemaine === 0 || jourSemaine === 6;
}
// Jour ouvré précédent/suivant le plus proche (saute samedi/dimanche) — sert
// de valeur par défaut au prélèvement/retour matériel quand le champ n'est
// pas renseigné : le retrait/dépôt du matériel a lieu un jour ouvré, jamais
// le week-end. Ex. atelier un lundi → prélèvement par défaut le vendredi.
function veilleOuvree(dateIso) {
  let d = addJoursIso(dateIso, -1);
  while (estWeekend(d)) d = addJoursIso(d, -1);
  return d;
}
function lendemainOuvre(dateIso) {
  let d = addJoursIso(dateIso, 1);
  while (estWeekend(d)) d = addJoursIso(d, 1);
  return d;
}
// Période réelle d'indisponibilité du matériel pour un atelier : du
// prélèvement (peut précéder la date de l'atelier — ex. retrait le mardi
// pour un atelier le vendredi) au retour. Repli indépendant sur chaque champ
// quand il n'est pas renseigné : veille/lendemain ouvrés de la date de
// l'atelier (jamais un jour de week-end), plutôt que la date de l'atelier
// elle-même — le matériel est concrètement retiré/rendu un jour ouvré,
// généralement la veille/le lendemain de la séance. Aligné sur
// ateliers-cd47_NextStep le 19/09/2026 (auparavant : repli simple sur la
// date de l'atelier des deux côtés).
function periodePretMateriel(e) {
  const debut = e.date_prelevement_materiel
    ? ((e.date_prelevement_materiel < e.date) ? e.date_prelevement_materiel : e.date)
    : veilleOuvree(e.date);
  const fin = e.date_retour_materiel
    ? ((e.date_retour_materiel > e.date) ? e.date_retour_materiel : e.date)
    : lendemainOuvre(e.date);
  return { debut, fin };
}
function findOrdinateursConflicts(entries, stock = STOCK_ORDINATEURS) {
  const parJour = {};
  (entries || []).forEach(e => {
    if (e.statut === 'Annulé') return;
    if (!e.date) return;
    if (!parseMateriel(e.materiel).some(m => normalizeMatLabel(m) === 'classemobile')) return;
    // Classe mobile cochée sans nombre saisi ⇒ on suppose au moins 1
    // ordinateur (jamais 0) : la barre/le conflit apparaît dès la coche,
    // s'ajuste automatiquement dès qu'une saisie ultérieure précise le
    // nombre réel — demande explicite utilisateur suite à des ateliers déjà
    // enregistrés avec Classe mobile cochée mais sans quantité (invisibles
    // dans la Frise malgré un vrai conflit Classe mobile détecté).
    const qte = parseInt(e.nb_ordinateurs) || 1;
    const { debut, fin } = periodePretMateriel(e);
    // Garde-fou : une date de prélèvement/retour saisie à la main peut être
    // erronée (année oubliée, inversion jour/mois...) — on plafonne à 90
    // jours pour ne jamais boucler indéfiniment sur une période aberrante.
    let d = debut, garde = 0;
    while (d <= fin && garde < 90) {
      (parJour[d] = parJour[d] || []).push({
        _id: e._id, conseiller: e.conseiller, qte,
        commune: e.commune || '', lieu: e.lieu || '',
        dateDebut: debut, dateFin: fin,
      });
      d = addJoursIso(d, 1);
      garde++;
    }
  });
  const joursConflit = Object.keys(parJour)
    .map(date => ({ date, entries: parJour[date], total: totalJourParConseiller(parJour[date]) }))
    .filter(g => g.total > stock)
    .sort((a, b) => a.date < b.date ? -1 : a.date > b.date ? 1 : 0);

  const blocs = [];
  joursConflit.forEach(g => {
    const dernier = blocs[blocs.length - 1];
    if (dernier && addJoursIso(dernier.dateFin, 1) === g.date) {
      dernier.dateFin = g.date;
      dernier.total = Math.max(dernier.total, g.total);
      g.entries.forEach(e => { if (!dernier._vus.has(e._id)) { dernier._vus.add(e._id); dernier.entries.push(e); } });
    } else {
      blocs.push({ date: g.date, dateFin: g.date, total: g.total, entries: [...g.entries], _vus: new Set(g.entries.map(e => e._id)) });
    }
  });
  return blocs.map(({ _vus, ...b }) => b);
}

// Liste tous les prêts Classe mobile (période prélèvement → retour), pas
// seulement les jours en conflit (findOrdinateursConflicts ne renvoie que
// ça) — sert à la frise/Gantt où on veut voir tous les prêts pour repérer
// les chevauchements visuellement, pas uniquement ceux déjà détectés en
// dépassement de stock.
function getPretsMateriel(entries) {
  return (entries || [])
    .filter(e => e.statut !== 'Annulé' && e.date
      && parseMateriel(e.materiel).some(m => normalizeMatLabel(m) === 'classemobile'))
    .map(e => {
      const { debut, fin } = periodePretMateriel(e);
      // Même hypothèse par défaut que findOrdinateursConflicts : au moins 1
      // ordinateur supposé si le nombre n'est pas encore renseigné.
      return {
        _id: e._id, conseiller: e.conseiller, qte: parseInt(e.nb_ordinateurs) || 1,
        commune: e.commune || '', lieu: e.lieu || '', thematique: e.thematique || '',
        dateAtelier: e.date, debut, fin,
      };
    })
    .sort((a, b) => a.debut < b.debut ? -1 : a.debut > b.debut ? 1 : 0);
}

// Cumul des ordinateurs réservés pour chaque jour de `jours` (tableau de
// dates ISO) — sert à teinter la frise là où le cumul dépasse le stock.
function totauxParJourMateriel(prets, jours) {
  const totaux = {};
  (jours || []).forEach(j => {
    totaux[j] = totalJourParConseiller((prets || []).filter(p => j >= p.debut && j <= p.fin));
  });
  return totaux;
}

// Un conflit (issu de findMobileClassConflicts ou findOrdinateursConflicts)
// devient "historique" une fois sa période entièrement passée — plus rien à
// arbitrer une fois que l'atelier a eu lieu. dateFin est absent sur un
// conflit Classe mobile (toujours un seul jour) : on retombe sur date.
function estConflitPasse(conflit, today) {
  return (conflit.dateFin || conflit.date) < today;
}

if (typeof module !== 'undefined') {
  module.exports = {
    normalizeMateriel, parseMateriel, filterMaterielsVisibles,
    validateLotRow, validateLotForm, filterLotRows,
    computeKpi,
    isEntryRetard, isEntryPasse,
    applyFilters,
    findMobileClassConflicts,
    STOCK_ORDINATEURS, totalJourParConseiller, findOrdinateursConflicts, periodePretMateriel,
    getPretsMateriel, totauxParJourMateriel,
    estConflitPasse,
    estWeekend, veilleOuvree, lendemainOuvre,
  };
}
