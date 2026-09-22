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
  const parCreneau = {};
  entries.forEach(e => {
    if (e.statut === 'Annulé') return;
    if (!e.date) return;
    if (!parseMateriel(e.materiel).some(m => normalizeMatLabel(m) === 'classemobile')) return;
    const demi = demiJourneeAtelier(e);
    (demi ? [demi] : ['AM', 'PM']).forEach(d => {
      const k = e.date + '|' + d;
      (parCreneau[k] = parCreneau[k] || []).push(e);
    });
  });
  // Un seul groupe par DATE, portant la ou les demi-journées en conflit.
  // Sans ce regroupement, un atelier sans ampm — qui compte dans les deux
  // demi-journées — produirait deux fois le même conflit à l'écran.
  const parDate = {};
  Object.keys(parCreneau).sort().forEach(k => {
    const items = parCreneau[k];
    if (new Set(items.map(e => e.conseiller)).size < 2) return;
    const date = k.split('|')[0];
    (parDate[date] = parDate[date] || []).push({ demi: k.split('|')[1], items });
  });
  return Object.keys(parDate).sort().map(date => {
    const parts = parDate[date];
    const vus = new Set(), entriesBloc = [];
    parts.forEach(p => p.items.forEach(e => { if (!vus.has(e)) { vus.add(e); entriesBloc.push(e); } }));
    return { date, demi: parts.map(p => p.demi).join('+'), entries: entriesBloc };
  });
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
// Demi-journée d'un atelier : 'AM', 'PM', ou null quand on ne peut pas
// trancher. Le champ ampm est obligatoire à la saisie depuis longtemps, mais
// les entrées importées ou antérieures peuvent ne pas l'avoir : on retombe
// alors sur l'horaire (même repli que le dashboard, shared.js), puis sur null
// — et null réserve la journée entière, jamais l'inverse : mieux vaut une
// alerte de trop qu'un conflit matériel non signalé.
function demiJourneeAtelier(e) {
  const v = String((e && e.ampm) || '').trim().toUpperCase();
  if (v === 'AM' || v === 'PM') return v;
  const h = parseInt((e && e.horaire) || '', 10);
  if (!isNaN(h) && h >= 0 && h <= 23) return h < 12 ? 'AM' : 'PM';
  return null;
}

// Période réelle d'indisponibilité du matériel pour un atelier : du
// prélèvement (peut précéder la date de l'atelier — ex. retrait le mardi
// pour un atelier le vendredi) au retour.
// Repli quand un champ n'est pas renseigné : **la date de l'atelier**, des
// deux côtés. Le matériel est alors pris et rendu le jour même — c'est le
// fonctionnement réel confirmé par l'utilisateur le 22/09/2026. Ce repli
// remplace celui de la veille/lendemain ouvrés (19/09/2026), qui était une
// hypothèse : il étendait chaque atelier sans dates saisies à trois jours et
// fabriquait des chevauchements qui n'existent pas sur le terrain.
function periodePretMateriel(e) {
  const debut = e.date_prelevement_materiel
    ? ((e.date_prelevement_materiel < e.date) ? e.date_prelevement_materiel : e.date)
    : e.date;
  const fin = e.date_retour_materiel
    ? ((e.date_retour_materiel > e.date) ? e.date_retour_materiel : e.date)
    : e.date;
  return { debut, fin };
}

// Le retour du matériel a lieu le matin (confirmé le 21/09/2026) : le jour du
// retour, les machines sont de nouveau disponibles pour un autre conseiller
// qui les prélève le même jour. L'occupation va donc de `debut` INCLUS à
// `fin` EXCLU — sauf pour un prêt d'une seule journée, qui occupe bien ce
// jour-là, sinon il disparaîtrait du cumul.
function finOccupationMateriel(debut, fin) {
  return fin > debut ? addJoursIso(fin, -1) : fin;
}

// Occupation à la DEMI-JOURNÉE (confirmé le 22/09/2026) : deux ateliers le
// même jour, l'un le matin l'autre l'après-midi, ne se disputent pas le
// matériel — le premier rend à midi, le second prend l'après-midi.
// Cette finesse ne vaut que pour un prêt d'une seule journée : dès que le
// matériel dort ailleurs une nuit, il est immobilisé en continu, y compris
// les demi-journées intermédiaires.
function occupeCreneauMateriel(p, jour, demi) {
  if (jour < p.debut || jour > finOccupationMateriel(p.debut, p.fin)) return false;
  if (p.debut === p.fin && p.demi) return demi === p.demi;
  return true;
}

function findOrdinateursConflicts(entries, stock = STOCK_ORDINATEURS) {
  const parCreneau = {};
  (entries || []).forEach(e => {
    if (e.statut === 'Annulé') return;
    if (!e.date) return;
    if (!parseMateriel(e.materiel).some(m => normalizeMatLabel(m) === 'classemobile')) return;
    // Classe mobile cochée sans quantité renseignée (entrées historiques
    // antérieures au champ obligatoire, ou import) → on suppose 1 ordinateur
    // plutôt que d'exclure l'entrée : sinon elle disparaît silencieusement
    // de la frise/Gantt alors qu'elle réserve bien la Classe mobile ce
    // jour-là (même principe que ATELIERS_NEWGEN, confirmé en prod le
    // 18/09/2026 — conseillers manquants dans le Gantt malgré des conflits).
    const qte = parseInt(e.nb_ordinateurs) || 1;
    const { debut, fin } = periodePretMateriel(e);
    const pret = { _id: e._id, conseiller: e.conseiller, qte,
      commune: e.commune || '', lieu: e.lieu || '',
      dateDebut: debut, dateFin: fin, demi: demiJourneeAtelier(e), debut, fin };
    // Garde-fou : une date de prélèvement/retour saisie à la main peut être
    // erronée (année oubliée, inversion jour/mois...) — on plafonne à 90
    // jours pour ne jamais boucler indéfiniment sur une période aberrante.
    const finOcc = finOccupationMateriel(debut, fin);
    let d = debut, garde = 0;
    while (d <= finOcc && garde < 90) {
      ['AM', 'PM'].forEach(demi => {
        if (occupeCreneauMateriel(pret, d, demi)) {
          (parCreneau[d + '|' + demi] = parCreneau[d + '|' + demi] || []).push(pret);
        }
      });
      d = addJoursIso(d, 1);
      garde++;
    }
  });

  // Un JOUR est en conflit dès qu'une de ses deux demi-journées dépasse le
  // stock. Le bloc porte le total le plus élevé et nomme la ou les
  // demi-journées concernées : « 11 demandés (après-midi) » se corrige
  // autrement que « 11 demandés toute la journée ».
  const joursConflit = [];
  const datesVues = {};
  Object.keys(parCreneau).forEach(k => { datesVues[k.split('|')[0]] = true; });
  Object.keys(datesVues).sort().forEach(date => {
    const parts = ['AM', 'PM'].map(demi => {
      const items = parCreneau[date + '|' + demi] || [];
      return { demi, items, total: totalJourParConseiller(items) };
    }).filter(p => p.total > stock);
    if (!parts.length) return;
    // Déduplication par RÉFÉRENCE et non par _id : le même prêt est poussé
    // dans les deux demi-journées, et toutes les entrées n'ont pas d'_id
    // (import, saisie en lot avant attribution).
    const vus = new Set();
    const entriesBloc = [];
    parts.forEach(p => p.items.forEach(it => {
      if (!vus.has(it)) { vus.add(it); entriesBloc.push(it); }
    }));
    joursConflit.push({
      date,
      entries: entriesBloc,
      total: Math.max.apply(null, parts.map(p => p.total)),
      demi: parts.map(p => p.demi).join('+'),
    });
  });

  const blocs = [];
  joursConflit.forEach(g => {
    const dernier = blocs[blocs.length - 1];
    if (dernier && addJoursIso(dernier.dateFin, 1) === g.date && dernier.demi === g.demi) {
      dernier.dateFin = g.date;
      dernier.total = Math.max(dernier.total, g.total);
      g.entries.forEach(e => { if (!dernier._vus.has(e)) { dernier._vus.add(e); dernier.entries.push(e); } });
    } else {
      blocs.push({ date: g.date, dateFin: g.date, total: g.total, demi: g.demi, entries: [...g.entries], _vus: new Set(g.entries) });
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
      return {
        _id: e._id, conseiller: e.conseiller, qte: parseInt(e.nb_ordinateurs) || 1,
        commune: e.commune || '', lieu: e.lieu || '', thematique: e.thematique || '',
        dateAtelier: e.date, debut, fin, demi: demiJourneeAtelier(e),
      };
    })
    .sort((a, b) => a.debut < b.debut ? -1 : a.debut > b.debut ? 1 : 0);
}

// Cumul des ordinateurs réservés pour chaque DEMI-JOURNÉE de `jours` :
// { '2026-09-30': { AM: 10, PM: 11 } }. C'est le détail que la frise montre
// en infobulle et sur lequel se décide la couleur de la case.
function totauxParDemiJourneeMateriel(prets, jours) {
  const totaux = {};
  (jours || []).forEach(j => {
    totaux[j] = {
      AM: totalJourParConseiller((prets || []).filter(p => occupeCreneauMateriel(p, j, 'AM'))),
      PM: totalJourParConseiller((prets || []).filter(p => occupeCreneauMateriel(p, j, 'PM'))),
    };
  });
  return totaux;
}

// Cumul par jour = le maximum des deux demi-journées. C'est ce que la case de
// la frise affiche : la pointe de la journée, celle qui décide du dépassement.
// Sommer les deux compterait deux fois un prêt qui court toute la journée.
function totauxParJourMateriel(prets, jours) {
  const detail = totauxParDemiJourneeMateriel(prets, jours);
  const totaux = {};
  Object.keys(detail).forEach(j => { totaux[j] = Math.max(detail[j].AM, detail[j].PM); });
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
    demiJourneeAtelier, finOccupationMateriel, occupeCreneauMateriel,
    totauxParDemiJourneeMateriel,
    STOCK_ORDINATEURS, totalJourParConseiller, findOrdinateursConflicts, periodePretMateriel,
    getPretsMateriel, totauxParJourMateriel,
    estConflitPasse,
    estWeekend, veilleOuvree, lendemainOuvre,
  };
}
