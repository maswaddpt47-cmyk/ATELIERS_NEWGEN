
// ── GAS Backend v11.21 ────────────────────────────────────────
// v11.21 : CORRECTIF — TOKEN_TTL_SECONDS valait 8*60*60 (28800s), au-dessus
//          de la limite documentée de CacheService.put() (21600s / 6h max).
//          Hypothèse non confirmée avec certitude (le diff v11.18→v11.20 ne
//          touche ni doGet, ni _verifyToken, ni _generateToken — un "Token
//          invalide ou expiré" est apparu ce soir sur des actions comme
//          getLogs qui fonctionnaient déjà avant le chantier MdP, donc cette
//          régression n'est structurellement pas due aux changements de
//          v11.19/v11.20). Corrigé à 6h par prudence — dépasser la limite
//          documentée d'une API est incorrect indépendamment de la cause
//          réelle du bug signalé. Si le problème persiste après ce correctif,
//          la cause est probablement ponctuelle côté Google (CacheService),
//          pas dans ce script.
// v11.20 : FEAT — politique de mot de passe (12 caractères min., majuscule,
//          minuscule, chiffre, caractère spécial) sur actionSetPassword et
//          actionSelfSetPassword — remplace le simple "8 caractères min.".
//          Ne s'applique pas à resetPassword (mot de passe par défaut
//          cd47+prénom, volontairement faible mais temporaire, remplacé de
//          force au prochain login via selfSetPassword).
// v11.19 : FEAT — nouvelle action selfSetPassword : un conseiller connecté
//          (n'importe quel rôle, pas seulement admin/superviseur) peut changer
//          SON PROPRE mot de passe. Volontairement hors ADMIN_ONLY_ACTIONS —
//          la protection vient de _verifyToken : le conseiller ciblé est
//          toujours celui du token (tokenCheck.conseiller), jamais un
//          p.conseiller envoyé par le client, donc impossible de changer le
//          mot de passe de quelqu'un d'autre par ce chemin. Sert le nouveau
//          flux "mot de passe par défaut détecté à la connexion → changement
//          obligatoire" sur index.html.
// v11.18 : FEAT — actionGetLogs renvoie maintenant le champ action (delete,
//          saveEntry, login, alertesRetard...) au frontend, qui l'affiche dans
//          une colonne dédiée de l'onglet Connexions. Auparavant impossible de
//          distinguer une ligne de suppression d'une ligne de connexion.
// v11.17 : CORRECTIF — actionDelete journalisait toujours 'delete' avec un
//          conseiller vide (_logAction('delete','',id) codé en dur), rendant
//          impossible de savoir qui avait supprimé un atelier en consultant les
//          logs. Le conseiller est maintenant lu sur la ligne juste avant sa
//          suppression et transmis au log.
// v11.16 : CORRECTIF — actionGetLogs classait les lignes 'alertesRetard' (écrites
//          par _logAction, même format que saveEntry/delete) dans la mauvaise
//          branche de parsing car 'alertesRetard' manquait de la liste isB.
//          Conséquence : conseiller affiché comme "alertesRetard" au lieu de
//          "system", le résumé de l'envoi affiché dans la colonne Appareil, et
//          success toujours calculé à false → chaque exécution du trigger
//          quotidien apparaissait comme un "Échec" dans l'onglet Connexions,
//          même quand l'envoi s'était déroulé normalement (0 destinataire actif
//          n'est pas une erreur). isB inclut maintenant 'alertesRetard'.
// v11.9 : suppression keepAlive — retour au cold start natif GAS
// v11.10 : retour keepAlive (touche Sheets — utile pour Index)
//          checkPassword — suppression _logAuth du chemin critique
//          nouvelle action logLogin — appelée en fire-and-forget par le frontend
// v11.11 : keepAlive réchauffe réellement le cache getAll (année en cours
//          uniquement, verrouillé via LockService, no-op si déjà chaud) —
//          au lieu du ping inutile de v11.10 qui ne touchait plus le cache.
// v11.12 : cache getAll segmenté (_cacherGetAll/_lireCacheGetAll/
//          _invalidateGetAllCache) — CacheService.put() refuse toute valeur
//          de plus de 100 Ko, dépassée dès ~200 ateliers ("Argument too
//          large: value", confirmé en prod). doGet et keepAlive partagent
//          désormais les mêmes fonctions de lecture/écriture/invalidation,
//          pour ne plus jamais diverger entre eux.
// v11.13 : SÉCURITÉ — getLogs était listé dans READ_ACTIONS, donc accessible
//          sans aucune authentification (n'importe qui pouvait lire tout
//          l'historique des connexions via /exec?action=getLogs). Déplacé
//          vers ADMIN_ONLY_ACTIONS.
//          SÉCURITÉ — _verifyToken calculait déjà le rôle du token mais
//          doGet/doPost ne le vérifiaient jamais pour les actions admin :
//          un token valide de n'importe quel conseiller (rôle "user")
//          suffisait pour appeler saveConfig, resetPassword, saveCompte,
//          etc. La restriction "admin only" n'existait qu'à l'écran, pas
//          côté serveur. Ajout d'une vérification explicite du rôle
//          (admin ou superviseur, cohérent avec ce que le frontend
//          autorise déjà à l'entrée de admin.html) pour toute action de
//          ADMIN_ONLY_ACTIONS, dans doGet ET doPost.
// v11.14 : CORRECTIF CRITIQUE — saveEntry/saveMany/delete étaient tombés
//          dans la même branche "token obligatoire" que les actions admin
//          (régression déjà présente dans v11.10, jamais encore en
//          production avant qu'une nouvelle version soit déployée cette
//          session). Un conseiller sur Index (jamais de token, pas d'écran
//          de connexion) ne pouvait plus enregistrer un atelier : "Non
//          autorisé : Token manquant". Ces 3 actions sont maintenant
//          routées avant toute vérification de token (OPEN_WRITE_ACTIONS).
// v11.15 : CORRECTIF — _verifyToken comparait le conseiller du token à
//          p.conseiller de la requête. Or plusieurs actions admin ciblent
//          un AUTRE conseiller que l'admin connecté : saveCompte (activer/
//          désactiver un collègue) envoie le nom du collègue ciblé dans
//          p.conseiller, pas celui de l'admin. Confirmé en prod : un admin
//          ne pouvait pas réactiver le compte d'un collègue ("Token ne
//          correspond pas au conseiller"), alors que le rôle du token était
//          bien admin. _verifyToken ne vérifie plus que la validité du
//          token et le rôle qu'il porte — jamais une correspondance de nom.
var APP_URL    = 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/';
var SHEET_NAME = 'Ateliers_next_step';
// Actions qui exigent désormais un token ET un rôle admin/superviseur
// (vérifié dans doGet et doPost — voir v11.13 ci-dessus).
var ADMIN_ONLY_ACTIONS = [
  'saveLists','saveConfig','setConfig',
  'saveVisibility','saveColors','saveEmails',
  'saveCompte','resetPassword','setPassword',
  'getLogs'
];
var TOKEN_TTL_SECONDS = 6 * 60 * 60; // 21600s = maximum documenté de CacheService.put() ; 28800 (8h) le dépassait
var _SS = null;
function _ss() {
  if (!_SS) _SS = SpreadsheetApp.getActiveSpreadsheet();
  return _SS;
}
// ── Cache getAll segmenté ───────────────────────────────────────────────
// CacheService.put() refuse toute valeur de plus de 100 Ko (limite dure,
// non négociable). Le JSON complet d'une année d'ateliers la dépasse dès
// qu'il y a assez d'entrées (confirmé en prod : "Argument too large:
// value" dès ~200 ateliers). On découpe donc la valeur en segments sous
// plusieurs clés (getAll_<annee>_p0, _p1, ... + getAll_<annee>_n pour le
// nombre de segments), écrits en un seul putAll() pour rester atomiques
// (même TTL pour tous les segments d'un même appel).
//
// _CACHE_CHUNK_CHARS est volontairement conservateur : le texte contient
// des accents (communes, remarques), jusqu'à 2 octets/caractère en UTF-8.
// Même dans ce pire cas, 40 000 caractères restent sous 80 000 octets, loin
// des 100 000 autorisés — pas besoin de mesurer précisément chaque segment.
var _CACHE_CHUNK_CHARS = 40000;
var _CACHE_TTL_SECONDS = 600;
function _cacherGetAll(year, result) {
  try {
    var json = JSON.stringify(result);
    var cache = CacheService.getScriptCache();
    var segments = [];
    for (var i = 0; i < json.length; i += _CACHE_CHUNK_CHARS) {
      segments.push(json.substring(i, i + _CACHE_CHUNK_CHARS));
    }
    var payload = {};
    payload['getAll_' + year + '_n'] = String(segments.length);
    segments.forEach(function(seg, idx) { payload['getAll_' + year + '_p' + idx] = seg; });
    cache.putAll(payload, _CACHE_TTL_SECONDS);
    Logger.log('_cacherGetAll ' + year + ' : payload=' + json.length + ' caracteres -> ECRIT OK (' + segments.length + ' segment(s))');
  } catch (err) {
    // Ne doit jamais faire échouer l'appelant : un cache manqué n'est
    // qu'une occasion de recalcul plus tard, jamais une erreur utilisateur.
    Logger.log('_cacherGetAll ' + year + ' error: ' + err);
  }
}
function _lireCacheGetAll(year) {
  try {
    var cache = CacheService.getScriptCache();
    var n = cache.get('getAll_' + year + '_n');
    if (!n) return null;
    var count = parseInt(n, 10);
    if (!count || count < 1) return null;
    var keys = [];
    for (var i = 0; i < count; i++) keys.push('getAll_' + year + '_p' + i);
    var parts = cache.getAll(keys);
    var json = '';
    for (var j = 0; j < count; j++) {
      var part = parts['getAll_' + year + '_p' + j];
      if (part === undefined || part === null) return null; // segment manquant = cache miss propre
      json += part;
    }
    return JSON.parse(json);
  } catch (err) {
    Logger.log('_lireCacheGetAll ' + year + ' error: ' + err);
    return null;
  }
}
function _invalidateGetAllCache(year) {
  try {
    var cache = CacheService.getScriptCache();
    var n = cache.get('getAll_' + year + '_n');
    var count = n ? parseInt(n, 10) : 0;
    var keys = ['getAll_' + year + '_n'];
    for (var i = 0; i < count; i++) keys.push('getAll_' + year + '_p' + i);
    cache.removeAll(keys);
  } catch (_) {}
}
function _invalidateCache() {
  try {
    var year = String(new Date().getFullYear());
    _invalidateGetAllCache(String(parseInt(year) - 1));
    _invalidateGetAllCache(year);
    // CORRECTION C - l'annee suivante manquait : modifier un atelier de 2027
    // laissait le cache 2027 perime jusqu'a expiration du TTL.
    _invalidateGetAllCache(String(parseInt(year) + 1));
    CacheService.getScriptCache().remove('config');
  } catch(_) {}
}
function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
// Rôles autorisés pour ADMIN_ONLY_ACTIONS — admin ET superviseur, cohérent
// avec ce que le frontend autorise déjà à l'entrée de admin.html
// (admin_app.js : "role !== 'admin' && role !== 'superviseur'" → refusé).
var ADMIN_ROLES = ['admin', 'superviseur'];
function _requireAdminRole(tokenCheck) {
  if (!tokenCheck.ok) return {ok:false, error:'Non autorisé : ' + tokenCheck.error};
  if (ADMIN_ROLES.indexOf(tokenCheck.role) === -1) return {ok:false, error:'Non autorisé : réservé aux administrateurs'};
  return {ok:true};
}
function doGet(e) {
  try {
    var p = e.parameter || {};
    var action = p.action || 'getAll';
    var READ_ACTIONS = ['getAll','getConfig','getVisibility','getComptes','checkPassword','logAccesIndex','logLogin'];
    // v11.14 : CORRECTIF CRITIQUE — saveEntry/saveMany/delete doivent rester
    // accessibles aux conseillers sans token (Index n'a pas d'écran de
    // connexion, ils n'en ont jamais). Ce n'était plus le cas : ces 3 actions
    // tombaient dans la branche "token obligatoire" ci-dessous comme
    // n'importe quelle action admin, cassant l'enregistrement d'ateliers
    // dès que ce fichier passait en production ("Non autorisé : Token
    // manquant" au clic sur Enregistrer). Elles sont désormais routées
    // AVANT toute vérification de token, comme des actions de lecture.
    var OPEN_WRITE_ACTIONS = ['saveEntry','saveMany','delete'];
    if (OPEN_WRITE_ACTIONS.indexOf(action) !== -1) return json(handleWriteAction(p));
    if (READ_ACTIONS.indexOf(action) === -1) {
      var tokenCheck = _verifyToken(p.token);
      if (!tokenCheck.ok) return json({ok:false, error:'Non autorisé : ' + tokenCheck.error});
      if (ADMIN_ONLY_ACTIONS.indexOf(action) !== -1) {
        var roleCheck = _requireAdminRole(tokenCheck);
        if (!roleCheck.ok) return json(roleCheck);
      }
      return json(handleWriteAction(p));
    }
    if (action === 'getAll') return json(actionGetAll(p));
    return json(handleReadAction(p));
  } catch(err) {
    return json({ok:false, error:'Erreur GAS: ' + String(err)});
  }
}
function doPost(e) {
  var p = {};
  try { p = JSON.parse(e.postData.contents); } catch(_) { p = e.parameter || {}; }
  var action = p.action || '';
  if (action === 'checkPassword') return json(actionCheckPassword(p));
  if (ADMIN_ONLY_ACTIONS.indexOf(action) !== -1) {
    var tokenCheck = _verifyToken(p.token);
    var roleCheck = _requireAdminRole(tokenCheck);
    if (!roleCheck.ok) return json(roleCheck);
  }
  return json(handleWriteAction(p));
}
function handleReadAction(p) {
  var action = p.action || '';
  if (action === 'checkPassword')  return actionCheckPassword(p);
  if (action === 'logAccesIndex')  return actionLogAccesIndex(p);
  if (action === 'logLogin')       return actionLogLogin(p);
  if (action === 'getConfig')      return actionGetConfig(p);
  if (action === 'getVisibility')  return actionGetVisibility(p);
  if (action === 'getComptes')     return actionGetComptes(p);
  return {ok:false, error:'action inconnue: ' + action};
}
function handleWriteAction(p) {
  var action = p.action || '';
  if (action === 'saveEntry')      return actionSaveEntry(p);
  if (action === 'saveMany')       return actionSaveMany(p);
  if (action === 'delete')         return actionDelete(p);
  if (action === 'saveLists')      return actionSaveLists(p);
  if (action === 'saveConfig')     return actionSaveConfig(p);
  if (action === 'setConfig')      return actionSetConfig(p);
  if (action === 'saveVisibility') return actionSaveVisibility(p);
  if (action === 'saveColors')     return actionSaveColors(p);
  if (action === 'saveEmails')     return actionSaveEmails(p);
  if (action === 'saveCompte')     return actionSaveCompte(p);
  if (action === 'resetPassword')  return actionResetPassword(p);
  if (action === 'setPassword')    return actionSetPassword(p);
  if (action === 'selfSetPassword')return actionSelfSetPassword(p);
  if (action === 'logAccesIndex')  return actionLogAccesIndex(p);
  if (action === 'getLogs')        return actionGetLogs(p);
  return {ok:false, error:'action inconnue: ' + action};
}
function _generateToken(conseiller, role) {
  var token = Utilities.getUuid();
  CacheService.getScriptCache().put('token_' + token, JSON.stringify({conseiller:conseiller, role:role, ts:new Date().getTime()}), TOKEN_TTL_SECONDS);
  return token;
}
// v11.15 : ne vérifie plus que la validité du token et le rôle qu'il porte —
// jamais une correspondance avec p.conseiller (voir note v11.15 en tête de
// fichier : plusieurs actions admin ciblent un AUTRE conseiller que l'admin
// connecté, comparer les deux bloquait ces cas d'usage légitimes).
function _verifyToken(token) {
  if (!token) return {ok:false, error:'Token manquant'};
  var raw = CacheService.getScriptCache().get('token_' + token);
  if (!raw) return {ok:false, error:'Token invalide ou expiré'};
  try {
    var payload = JSON.parse(raw);
    return {ok:true, role:payload.role, conseiller:payload.conseiller};
  } catch(_) { return {ok:false, error:'Token corrompu'}; }
}
// ── v11.10 : checkPassword sans écriture Sheets — chemin critique allégé ──
function actionCheckPassword(p) {
  var nom = String(p.conseiller || '').trim();
  var pwd = String(p.password || '').trim();
  if (!nom || !pwd) return {ok:false, error:'Paramètres manquants'};
  var cache = CacheService.getScriptCache();
  var rlKey = 'rl_' + nom.replace(/\s/g, '_');
  var rlData = cache.get(rlKey) ? JSON.parse(cache.get(rlKey)) : {count:0, lockUntil:0};
  if (rlData.lockUntil && new Date().getTime() < rlData.lockUntil)
    return {ok:false, error:'Trop de tentatives. Réessayez dans ' + Math.ceil((rlData.lockUntil - new Date().getTime()) / 60000) + ' min.'};
  var sh = _ss().getSheetByName('Comptes');
  if (!sh) return {ok:false, error:'Feuille Comptes introuvable'};
  var data = sh.getDataRange().getValues();
  var headers = data[0].map(function(h) { return String(h).trim(); });
  var iC = headers.indexOf('Conseiller'), iHash = headers.indexOf('Hash');
  var iRole = headers.indexOf('Role'), iActif = headers.indexOf('Actif');
  var rowIndex = -1, rowData = null;
  for (var i = 1; i < data.length; i++) {
    if (String(data[i][iC]).trim() === nom) { rowIndex = i + 1; rowData = data[i]; break; }
  }
  if (!rowData) return {ok:false, error:'Conseiller introuvable'};
  if (String(rowData[iActif] || 'OUI').trim() === 'NON') return {ok:false, error:'Compte désactivé'};
  var storedHash = String(rowData[iHash] || '').trim();
  var ok = storedHash.length < 64 ? (pwd === storedHash) : (_sha256(pwd) === storedHash);
  // hash upgrade (plain → SHA256) : écriture unique, une seule fois par compte
  if (storedHash.length < 64 && ok) sh.getRange(rowIndex, iHash + 1).setValue(_sha256(pwd));
  if (!ok) {
    rlData.count = (rlData.count || 0) + 1;
    if (rlData.count >= 5) { rlData.lockUntil = new Date().getTime() + 15*60*1000; rlData.count = 0; }
    cache.put(rlKey, JSON.stringify(rlData), 16*60);
    // log échec uniquement (sécurité brute-force)
    _logAuth(nom, false, rlData.count, '', p.userAgent || '', p.source || '');
    return {ok:false, error:'Mot de passe incorrect'};
  }
  cache.remove(rlKey);
  var role = String(rowData[iRole] || 'user').trim();
  var token = _generateToken(nom, role);
  // pas de _logAuth ici — le frontend appelle logLogin en fire-and-forget
  return {ok:true, role:role, token:token};
}
// ── v11.10 : logLogin — appelé par le frontend après connexion réussie ──
function actionLogLogin(p) {
  try {
    _logAuth(
      String(p.conseiller || ''),
      true,
      0,
      String(p.role || 'user'),
      String(p.userAgent || ''),
      String(p.source || '')
    );
    return {ok:true};
  } catch(e) { return {ok:false, error:e.message}; }
}
function actionGetAll(p) {
  var year = p.year || String(new Date().getFullYear());
  var cached = _lireCacheGetAll(year);
  if (cached) return cached;
  var result = _actionGetAllFresh(p);
  if (result.ok) _cacherGetAll(year, result);
  return result;
}
function _actionGetAllFresh(p) {
  try {
    var year = p.year || String(new Date().getFullYear());
    var cfg = _getFullConfig();
    var isAdmin = (p.source === 'admin');
    if (!isAdmin && (cfg['maintenance'] === 'true' || cfg['maintenance'] === true || cfg['maintenance'] === 'TRUE'))
      return {ok:false, maintenance:true, msg:cfg['maintenance_msg'] || ''};
    var lists = {statuts:[], conseillers:[], publics:[], materiels:[]};
    if (cfg['list_statuts'] || cfg['list_conseillers']) {
      lists.statuts = _parseList(cfg['list_statuts']); lists.conseillers = _parseList(cfg['list_conseillers']);
      lists.publics = _parseList(cfg['list_publics']); lists.materiels = _parseList(cfg['list_materiels']);
    } else if (cfg['lists']) {
      try { var ol = JSON.parse(cfg['lists']); lists.statuts = ol.statuts||[]; lists.conseillers = ol.conseillers||[]; lists.publics = ol.publics||[]; lists.materiels = ol.materiels||[]; } catch(_) {}
    }
    var visibility = {}, conseiller_colors = {}, emails = {};
    try { if (cfg['visibility']) visibility = JSON.parse(cfg['visibility']); } catch(_) {}
    try { if (cfg['conseiller_colors']) conseiller_colors = JSON.parse(cfg['conseiller_colors']); } catch(_) {}
    try { if (cfg['emails']) emails = JSON.parse(cfg['emails']); } catch(_) {}
    var entries = [];
    try {
      var sh = _ss().getSheetByName(SHEET_NAME);
      if (sh) {
        var data = sh.getDataRange().getValues();
        var headers = data[0].map(function(h) { return String(h).trim(); });
        var dateIdx = headers.indexOf('date');
        var FIXED_COLS = ['id','n','statut','date','horaire','ampm','orienteur','commune','lieu','thematique','inscrit','present','public','conseiller','coanimateur','residence','remarque'];
        var MCOLS_DYN = headers.filter(function(h) { return FIXED_COLS.indexOf(_normMat(h)) === -1 && h !== ''; }).map(function(h) { return _normMat(h); });
        // CORRECTION B - _normMat(h) etait recalcule pour chaque cellule de
        // chaque ligne. Les en-tetes ne changent pas : on normalise une fois.
        var EST_MATERIEL = headers.map(function(h) { return MCOLS_DYN.indexOf(_normMat(h)) !== -1; });
        for (var i = 1; i < data.length; i++) {
          var row = data[i]; if (!row[0]) continue;
          if (dateIdx >= 0) {
            var ey = ''; try { var d = row[dateIdx]; ey = d instanceof Date ? String(d.getFullYear()) : String(d).substring(0,4); } catch(_) {}
            if (year && ey !== String(year)) continue;
          }
          var obj = {}, matArr = [];
          headers.forEach(function(h, j) {
            var v = row[j];
            if (v instanceof Date) {
              if (h === 'date') obj[h] = _fmtDate(v);            // etait Utilities.formatDate
              else if (h === 'horaire') obj[h] = _fmtHeure(v);   // etait Utilities.formatDate
              else obj[h] = v.toISOString();
            } else obj[h] = v;
            if (EST_MATERIEL[j] && String(v).trim().toUpperCase() === 'OUI') matArr.push(h);
          });
          obj.materiel = matArr;
          entries.push(obj);
        }
      }
    } catch(err) { Logger.log('entries error: ' + err); }
    return {ok:true, entries:entries, lists:lists, visibility:visibility, conseiller_colors:conseiller_colors, emails:emails};
  } catch(err) { return {ok:false, error:String(err)}; }
}
function actionSaveEntry(p) {
  var d = p;
  if (p.entry) { try { d = typeof p.entry === 'string' ? JSON.parse(p.entry) : p.entry; } catch(_) { d = p; } }
  var sh = _ss().getSheetByName(SHEET_NAME);
  if (!sh) return {ok:false, error:'Feuille introuvable: ' + SHEET_NAME};
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
  var id = d._id || ('entry_' + new Date().getTime() + '_' + Math.floor(Math.random() * 10000));
  var isNew = !d._id, rowIdx = -1;
  var ids = sh.getRange(1,1,sh.getLastRow(),1).getValues();
  for (var i = 1; i < ids.length; i++) { if (ids[i][0] === id) { rowIdx = i + 1; break; } }
  var materielList = [];
  if (Array.isArray(d.materiel)) materielList = d.materiel.map(_normMat);
  else if (typeof d.materiel === 'string' && d.materiel) {
    var sep = d.materiel.indexOf('|') !== -1 ? '|' : ',';
    materielList = d.materiel.split(sep).map(_normMat);
  }
  var FIXED_COLS2 = ['id','n','statut','date','horaire','ampm','orienteur','commune','lieu','thematique','inscrit','present','public','conseiller','coanimateur','residence','remarque'];
  var row = headers.map(function(h) {
    if (h === '_id') return id;
    if (h === '_n') return isNew ? sh.getLastRow() : (d._n || '');
    if (FIXED_COLS2.indexOf(_normMat(h)) === -1 && h !== 'materiel') return materielList.indexOf(_normMat(h)) !== -1 ? 'OUI' : '';
    if (h === 'materiel') return '';
    return d[h] !== undefined ? d[h] : '';
  });
  if (rowIdx > 0) sh.getRange(rowIdx, 1, 1, row.length).setValues([row]); else sh.appendRow(row);
  _logAction('saveEntry', d.conseiller || '', id);
  _invalidateCache();
  return {ok:true, _id:id};
}
function actionSaveMany(p) {
  var entries = p.entries;
  if (typeof entries === 'string') { try { entries = JSON.parse(entries); } catch(_) { return {ok:false, error:'JSON invalide'}; } }
  if (!Array.isArray(entries)) return {ok:false, error:'entries doit être un tableau'};
  var errors = [];
  entries.forEach(function(entry, idx) { try { actionSaveEntry({entry:entry}); } catch(e) { errors.push({idx:idx, error:String(e)}); } });
  _invalidateCache();
  if (errors.length > 0) return {ok:false, error:'Erreurs batch: ' + JSON.stringify(errors)};
  return {ok:true, count:entries.length};
}
function actionDelete(p) {
  var sh = _ss().getSheetByName(SHEET_NAME);
  if (!sh) return {ok:false, error:'Feuille introuvable'};
  var id = p._id || ''; if (!id) return {ok:false, error:'ID manquant'};
  var ids = sh.getRange(1,1,sh.getLastRow(),1).getValues();
  for (var i = 1; i < ids.length; i++) {
    if (ids[i][0] === id) {
      // conseiller lu sur la ligne avant suppression : _logAction('delete','',id)
      // codait ce champ en dur en chaine vide, rendant impossible de savoir qui
      // avait supprime un atelier en consultant les logs.
      var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
      var iCons = headers.indexOf('conseiller');
      var conseiller = iCons >= 0 ? String(sh.getRange(i+1,iCons+1).getValue()||'') : '';
      sh.deleteRow(i + 1);
      _logAction('delete', conseiller, id);
      _invalidateCache();
      return {ok:true};
    }
  }
  return {ok:false, error:'Entrée introuvable'};
}
function actionSaveLists(p) {
  var lists = p.lists ? (typeof p.lists === 'string' ? JSON.parse(p.lists) : p.lists) : {};
  _setConfig('list_statuts', (lists.statuts || []).join('\n'));
  _setConfig('list_conseillers', (lists.conseillers || []).join('\n'));
  _setConfig('list_publics', (lists.publics || []).join('\n'));
  _setConfig('list_materiels', (lists.materiels || []).join('\n'));
  (lists.conseillers || []).forEach(function(nom) { _ensureCompte(nom); });
  _ensureColonnesMateriels(lists.materiels || []);
  _invalidateCache();
  return {ok:true};
}
function _ensureColonnesMateriels(materiels) {
  try {
    var sh = _ss().getSheetByName(SHEET_NAME);
    if (!sh) return;
    var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
    var FIXED = ['_id','_n','statut','date','horaire','ampm','orienteur','commune','lieu','thematique','inscrits','presents','public','conseiller','co_animateur','residence','remarques'];
    materiels.forEach(function(mat) {
      if (!mat) return;
      var existe = headers.some(function(h) { return _normMat(h) === _normMat(mat); });
      if (!existe && FIXED.indexOf(mat) === -1) { sh.getRange(1, sh.getLastColumn() + 1).setValue(mat); headers.push(mat); Logger.log('Colonne créée: ' + mat); }
    });
  } catch(err) { Logger.log('_ensureColonnesMateriels error: ' + err); }
}
function actionSaveConfig(p) {
  var key = p.key || '', val = p.value || '';
  if (!key) return {ok:false, error:'Clé manquante'};
  _setConfig(key, val); _invalidateCache(); return {ok:true};
}
function actionSetConfig(p) {
  var key = p.key || '', val = p.value;
  if (!key) return {ok:false, error:'Clé manquante'};
  _setConfig(key, val !== undefined ? val : ''); _invalidateCache(); return {ok:true};
}
function actionGetConfig(p) {
  var sh = _ss().getSheetByName('Config');
  if (!sh) return {ok:false, error:'Config introuvable'};
  var config = {};
  sh.getDataRange().getValues().forEach(function(r) { if (r[0]) config[String(r[0]).trim()] = r[1]; });
  return {ok:true, config:config};
}
function actionSaveVisibility(p) {
  var vis = p.visibility || '{}';
  _setConfig('visibility', typeof vis === 'string' ? vis : JSON.stringify(vis));
  _invalidateCache(); return {ok:true};
}
function actionGetVisibility(p) {
  var sh = _ss().getSheetByName('Config');
  if (!sh) return {ok:true, visibility:{}};
  var vis = {}, data = sh.getDataRange().getValues();
  for (var i = 0; i < data.length; i++) { if (String(data[i][0]).trim() === 'visibility') { try { vis = JSON.parse(data[i][1] || '{}'); } catch(_) {} break; } }
  return {ok:true, visibility:vis};
}
function actionSaveColors(p) {
  _setConfig('conseiller_colors', typeof p.colors === 'string' ? p.colors : JSON.stringify(p.colors || {}));
  _invalidateCache(); return {ok:true};
}
function actionSaveEmails(p) {
  _setConfig('emails', typeof p.emails === 'string' ? p.emails : JSON.stringify(p.emails || {}));
  _invalidateCache(); return {ok:true};
}
function actionGetComptes(p) {
  var sh = _ss().getSheetByName('Comptes');
  if (!sh) return {ok:true, comptes:[]};
  var data = sh.getDataRange().getValues(); if (data.length < 2) return {ok:true, comptes:[]};
  var headers = data[0].map(function(h) { return String(h).trim(); });
  var iC = headers.indexOf('Conseiller'), iR = headers.indexOf('Role'), iA = headers.indexOf('Actif');
  var comptes = [];
  for (var i = 1; i < data.length; i++) { var r = data[i]; if (!r[iC]) continue; comptes.push({conseiller:r[iC], role:r[iR]||'user', actif:r[iA]||'OUI'}); }
  return {ok:true, comptes:comptes};
}
function actionSaveCompte(p) {
  var nom = String(p.conseiller || '').trim(); if (!nom) return {ok:false, error:'Nom manquant'};
  var sh = _ss().getSheetByName('Comptes');
  if (!sh) return {ok:false, error:'Feuille Comptes introuvable'};
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0];
  var iC = headers.indexOf('Conseiller'), iR = headers.indexOf('Role'), iA = headers.indexOf('Actif');
  var ids = sh.getRange(1, iC + 1, sh.getLastRow(), 1).getValues();
  for (var i = 1; i < ids.length; i++) {
    if (String(ids[i][0]).trim() === nom) {
      if (p.role !== undefined) sh.getRange(i + 1, iR + 1).setValue(p.role);
      if (p.actif !== undefined) sh.getRange(i + 1, iA + 1).setValue(p.actif);
      return {ok:true};
    }
  }
  return {ok:false, error:'Compte introuvable'};
}
function actionResetPassword(p) {
  var nom = String(p.conseiller || '').trim(); if (!nom) return {ok:false, error:'Nom manquant'};
  var row = _findCompte(nom); if (!row) return {ok:false, error:'Conseiller introuvable'};
  var sh = _ss().getSheetByName('Comptes');
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
  var newPwd = defaultPwd(nom);
  sh.getRange(row.rowIndex, headers.indexOf('Hash') + 1).setValue(newPwd);
  return {ok:true, newPassword:newPwd};
}
// v11.20 : politique de mot de passe pour un mot de passe auto-choisi (pas le
// défaut cd47+prénom généré par resetPassword, volontairement faible mais
// temporaire — le changement obligatoire à la connexion force à le remplacer).
var PWD_POLICY_MSG = 'Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
function _pwdPolicyOk(pwd) {
  return pwd.length >= 12 && /[A-Z]/.test(pwd) && /[a-z]/.test(pwd) && /[0-9]/.test(pwd) && /[^A-Za-z0-9]/.test(pwd);
}
function actionSetPassword(p) {
  var nom = String(p.conseiller || '').trim(), pwd = String(p.password || '').trim();
  if (!nom || !pwd) return {ok:false, error:'Paramètres manquants'};
  if (!_pwdPolicyOk(pwd)) return {ok:false, error:PWD_POLICY_MSG};
  var row = _findCompte(nom); if (!row) return {ok:false, error:'Conseiller introuvable'};
  var sh = _ss().getSheetByName('Comptes');
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
  sh.getRange(row.rowIndex, headers.indexOf('Hash') + 1).setValue(_sha256(pwd));
  return {ok:true};
}
// v11.19 : conseiller change SON PROPRE mot de passe — cible toujours
// tokenCheck.conseiller (jamais p.conseiller), donc pas de gate admin requis.
function actionSelfSetPassword(p) {
  var tokenCheck = _verifyToken(p.token);
  if (!tokenCheck.ok) return {ok:false, error:'Non autorisé : ' + tokenCheck.error};
  var nom = tokenCheck.conseiller;
  var pwd = String(p.password || '').trim();
  if (!pwd) return {ok:false, error:'Mot de passe manquant'};
  if (!_pwdPolicyOk(pwd)) return {ok:false, error:PWD_POLICY_MSG};
  var row = _findCompte(nom); if (!row) return {ok:false, error:'Conseiller introuvable'};
  var sh = _ss().getSheetByName('Comptes');
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h) { return String(h).trim(); });
  sh.getRange(row.rowIndex, headers.indexOf('Hash') + 1).setValue(_sha256(pwd));
  return {ok:true};
}
function actionLogAccesIndex(p) {
  try {
    var sh = _ss().getSheetByName('Logs_Connexion');
    if (!sh) return {ok:false, error:'Feuille Logs_Connexion introuvable'};
    sh.appendRow([new Date(), 'accesIndex', p.conseiller||'', '', 'user', p.userAgent||'', true, 0, 'index.html']);
    return {ok:true};
  } catch(e) { return {ok:false, error:e.message}; }
}
function actionGetLogs(p) {
  var n = parseInt(p.n) || 100;
  var sh = _ss().getSheetByName('Logs_Connexion');
  if (!sh) return {ok:false, error:'Feuille Logs_Connexion introuvable'};
  var data = sh.getDataRange().getValues(); if (data.length < 2) return {ok:true, logs:[]};
  var rows = data.slice(1); if (rows.length > n) rows = rows.slice(rows.length - n);
  rows = rows.reverse();
  var logs = rows.map(function(r) {
    var ts = r[0]||''; if (ts instanceof Date) ts = ts.toISOString(); else if (ts && !isNaN(Date.parse(String(ts)))) ts = new Date(String(ts)).toISOString();
    var col1 = String(r[1]||'').trim();
    var isB = (col1==='login'||col1==='loginFail'||col1==='saveEntry'||col1==='delete'||col1==='accesIndex'||col1==='alertesRetard');
    var conseiller, role, success, tentatives, ua, action;
    if (isB) {
      conseiller = String(r[2]||''); role = String(r[4]||'user'); ua = String(r[5]||'');
      var sv = r[6]; success = (sv===true||sv==='TRUE'||sv==='true'||sv===1||sv==='1'); tentatives = parseInt(r[7]||0);
      action = col1;
    } else {
      conseiller = String(r[1]||''); var rawRole = String(r[2]||'').trim(); ua = String(r[4]||r[3]||'');
      tentatives = parseInt(r[5]||0); role = ['admin','user',''].indexOf(rawRole.toLowerCase())>=0?rawRole:'user';
      var sv3 = String(r[3]||'').trim().toUpperCase(); success = (sv3==='OUI'||sv3==='TRUE'||sv3==='1')?true:(sv3==='NON'||sv3==='FALSE'||sv3==='0')?false:(rawRole==='OK'||rawRole==='admin'||rawRole==='user');
      // Format historique (avant l'ajout de la colonne action dédiée) : col1 est
      // ici le nom du conseiller, pas une action — checkPassword est la seule
      // action connue à avoir jamais utilisé ce format.
      action = 'checkPassword';
    }
    return {timestamp:ts, conseiller:conseiller, role:role, success:success, tentatives:tentatives, user_agent:ua, source:String(r[8]||''), action:action};
  });
  return {ok:true, logs:logs};
}
// CORRECTION A - formatage des dates en JavaScript pur.
// Utilities.formatDate est un appel de service facture au coup par coup. Dans
// la boucle de _actionGetAllFresh il partait 2 fois par ligne : sur 204
// ateliers cela fait plus de 400 appels de service dans une seule execution,
// et c'est l'essentiel du temps paye quand le cache est vide. Le fuseau du
// script etant Europe/Paris, les accesseurs locaux de Date donnent exactement
// le meme resultat sans aucun appel de service.
function _fmtDate(d){
  var m = d.getMonth()+1, j = d.getDate();
  return d.getFullYear() + '-' + (m<10?'0':'') + m + '-' + (j<10?'0':'') + j;
}
function _fmtHeure(d){
  var h = d.getHours(), mi = d.getMinutes();
  return (h<10?'0':'') + h + ':' + (mi<10?'0':'') + mi;
}
function _normMat(s) { return String(s).trim().toLowerCase().replace(/[éèêë]/g,'e').replace(/[àâä]/g,'a').replace(/[îï]/g,'i').replace(/[ôö]/g,'o').replace(/[ùûü]/g,'u').replace(/ç/g,'c').replace(/[^a-z0-9]/g,'').replace(/s$/,''); }
function _parseList(val) { if (!val) return []; var s = String(val).trim(); if (s.charAt(0)==='[') { try { return JSON.parse(s); } catch(_) {} } return s.split('\n').map(function(x){return x.trim();}).filter(function(x){return x.length>0;}); }
function _setConfig(key, val) { var ss = _ss(); var sh = ss.getSheetByName('Config'); if (!sh){sh=ss.insertSheet('Config');sh.appendRow(['key','value']);} var data=sh.getDataRange().getValues(); for(var i=0;i<data.length;i++){if(String(data[i][0]).trim()===key){sh.getRange(i+1,2).setValue(val);return;}} sh.appendRow([key,val]); }
function _findCompte(nom) { var sh=_ss().getSheetByName('Comptes'); if(!sh) return null; var data=sh.getDataRange().getValues(); var headers=data[0].map(function(h){return String(h).trim();}); var iC=headers.indexOf('Conseiller'); if(iC<0) return null; for(var i=1;i<data.length;i++){if(String(data[i][iC]).trim()===nom) return {rowIndex:i+1,data:data[i]};} return null; }
function _ensureCompte(nom) { if(_findCompte(nom)) return; var sh=_ss().getSheetByName('Comptes'); if(!sh) return; var headers=sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h){return String(h).trim();}); sh.appendRow(headers.map(function(h){if(h==='Conseiller') return nom; if(h==='Hash') return defaultPwd(nom); if(h==='Role') return 'user'; if(h==='Actif') return 'OUI'; if(h==='FailCount') return 0; if(h==='LockUntil') return ''; return '';})); }
function defaultPwd(nom) { var p=nom.split(' ')[0]||nom; p=p.toLowerCase().replace(/[àâä]/g,'a').replace(/[éèêë]/g,'e').replace(/[îï]/g,'i').replace(/[ôö]/g,'o').replace(/[ùûü]/g,'u').replace(/ç/g,'c'); return 'cd47'+p; }
function _sha256(str) { var bytes=Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,str,Utilities.Charset.UTF_8); return bytes.map(function(b){return('0'+(b<0?b+256:b).toString(16)).slice(-2);}).join(''); }
function _logAction(action,conseiller,ref) { try{ var sh=_ss().getSheetByName('Logs_Connexion'); if(!sh) return; sh.appendRow([new Date(),action,conseiller,ref,'','',true,0,'']); }catch(_){} }
function _logAuth(conseiller,success,tentatives,role,userAgent,source) { try{ var sh=_ss().getSheetByName('Logs_Connexion'); if(!sh) return; sh.appendRow([new Date(),success?'login':'loginFail',conseiller,'',role||'user',userAgent||'',success,tentatives||0,String(source||'')]); }catch(_){} }
function _getFullConfig() {
  var cached = CacheService.getScriptCache().get('config');
  if (cached) { try { return JSON.parse(cached); } catch(_) {} }
  var cfg = {};
  try {
    var sh = _ss().getSheetByName('Config');
    if (sh) sh.getDataRange().getValues().forEach(function(r) { if (r[0]) cfg[String(r[0]).trim()] = r[1]; });
  } catch(_) {}
  try { CacheService.getScriptCache().put('config', JSON.stringify(cfg), 300); } catch(_) {}
  return cfg;
}
function initComptes() {
  var ss = _ss(); var sh = ss.getSheetByName('Comptes');
  if (!sh) { sh = ss.insertSheet('Comptes'); sh.appendRow(['Conseiller','Hash','Role','Actif','FailCount','LockUntil']); }
  ['Michel Aswad','Eva Capelle','Cynthia Pineau','Corentin Tual','Caroline Montoux'].forEach(function(nom) { _ensureCompte(nom); });
  var row = _findCompte('Michel Aswad');
  if (row) { var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h){return String(h).trim();}); sh.getRange(row.rowIndex, headers.indexOf('Role')+1).setValue('admin'); }
  Logger.log('initComptes OK');
}
function debugEntries() {
  var sh = _ss().getSheetByName(SHEET_NAME);
  if (!sh) { Logger.log('FEUILLE INTROUVABLE: ' + SHEET_NAME); return; }
  var data = sh.getDataRange().getValues();
  Logger.log('Nb lignes: ' + data.length);
  Logger.log('Headers: ' + JSON.stringify(data[0]));
}
function envoyerAlertesRetard() {
  var cfg = _getFullConfig();
  var rappelsActifs = {};
  try {
    var parsed = JSON.parse(cfg['rappels_actifs'] || '{}');
    if (typeof parsed === 'object' && parsed !== null) rappelsActifs = parsed;
    else if (parsed === false) { Logger.log('Rappels globalement désactivés.'); return; }
  } catch(_) {}
  var emails = {};
  try { emails = JSON.parse(cfg['emails'] || '{}'); } catch(_) {}
  var destinataires = [], ignores = 0;
  for (var conseiller in emails) {
    if (rappelsActifs[conseiller] === false) { Logger.log('Rappels désactivés pour ' + conseiller + ' — ignoré'); ignores++; continue; }
    var adresse = String(emails[conseiller] || '');
    if (adresse && adresse.indexOf('@') > -1) destinataires.push({conseiller:conseiller, email:adresse});
  }
  if (destinataires.length === 0) { Logger.log('Aucun destinataire actif.'); return; }
  var retards = _getAteliersRetard();
  if (retards.length === 0) { Logger.log('Aucun retard.'); return; }
  var envoyes = 0;
  destinataires.forEach(function(dest) {
    var r = retards.filter(function(x) { return x.conseiller === dest.conseiller; });
    if (r.length > 0) { _envoyerEmail(dest.conseiller, dest.email, r, false); Logger.log('Email envoyé à ' + dest.conseiller + ' (' + dest.email + ') — ' + r.length + ' retard(s)'); envoyes++; }
    else { Logger.log('Pas de retard pour ' + dest.conseiller); }
  });
  var resume = '[Résumé] envoyés:' + envoyes + ' | ignorés:' + ignores + ' | retards total:' + retards.length + ' | ' + Utilities.formatDate(new Date(), 'Europe/Paris', 'dd/MM/yyyy HH:mm');
  Logger.log(resume);
  _logAction('alertesRetard', 'system', resume);
}
function testerAlerteEmail() {
  var EMAIL_TEST = 'm.aswad.dpt47@gmail.com';
  var NOM_TEST = 'Michel Aswad';
  var retards = _getAteliersRetard().filter(function(r) { return r.conseiller === NOM_TEST; });
  if (retards.length === 0) retards = [{thematique:'TEST — Atelier exemple', date:'01/06/2026', horaire:'09:00', conseiller:NOM_TEST, commune:'FUMEL (47500)', lieu:'Convergence', statut:'Planifié'}];
  _envoyerEmail(NOM_TEST, EMAIL_TEST, retards, true);
  Logger.log('TEST envoyé à ' + EMAIL_TEST + ' — ' + retards.length + ' retard(s) pour ' + NOM_TEST);
}
function installerTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(t) { if (t.getHandlerFunction() === 'envoyerAlertesRetard') ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('envoyerAlertesRetard').timeBased().everyDays(1).atHour(8).create();
  Logger.log('Trigger installé.');
}
function verifierTrigger() {
  var t = ScriptApp.getProjectTriggers();
  if (t.length === 0) { Logger.log('Aucun trigger actif.'); return; }
  t.forEach(function(x) { Logger.log('Trigger : ' + x.getHandlerFunction()); });
}
function backupGAS() {
  var cfg = _getFullConfig();
  var date = Utilities.formatDate(new Date(), 'Europe/Paris', 'yyyy-MM-dd_HH-mm');
  var folders = DriveApp.getFoldersByName('GAS_Backups');
  var dossier = folders.hasNext() ? folders.next() : DriveApp.createFolder('GAS_Backups');
  dossier.createFile('GAS_backup_' + date + '.txt', 'BACKUP GAS v11.14 — ' + new Date().toISOString() + '\n\n' + JSON.stringify(cfg, null, 2), MimeType.PLAIN_TEXT);
  Logger.log('Backup créé.');
}
function _getAteliersRetard() {
  var retards = [], today = new Date(); today.setHours(0,0,0,0);
  try {
    var sh = _ss().getSheetByName(SHEET_NAME);
    if (!sh) { Logger.log('Feuille introuvable: ' + SHEET_NAME); return retards; }
    var data = sh.getDataRange().getValues();
    var headers = data[0].map(function(h) { return String(h).trim(); });
    var iDate=headers.indexOf('date'), iStatut=headers.indexOf('statut'), iThema=headers.indexOf('thematique');
    var iCons=headers.indexOf('conseiller'), iCommune=headers.indexOf('commune'), iLieu=headers.indexOf('lieu'), iHoraire=headers.indexOf('horaire');
    for (var i = 1; i < data.length; i++) {
      var row = data[i]; if (!row[0]) continue;
      if (String(row[iStatut]||'').trim() !== 'Planifié') continue;
      var dv = row[iDate]; var da = dv instanceof Date ? new Date(dv) : new Date(String(dv)); da.setHours(0,0,0,0);
      if (da < today) retards.push({thematique:String(row[iThema]||'—'), date:Utilities.formatDate(da,'Europe/Paris','dd/MM/yyyy'), horaire:String(row[iHoraire]||''), conseiller:String(row[iCons]||'—'), commune:String(row[iCommune]||'—'), lieu:String(row[iLieu]||'—'), statut:'Planifié'});
    }
  } catch(err) { Logger.log('_getAteliersRetard error: ' + err); }
  retards.sort(function(a,b) { return a.date > b.date ? 1 : -1; });
  return retards;
}
function _envoyerEmail(destinataireName, email, retards, isTest) {
  var prefix = isTest ? '[TEST] ' : '';
  var sujet = prefix + '⚠️ ' + retards.length + ' atelier(s) en attente de mise à jour';
  var lignes = retards.map(function(r) { return '<tr><td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">'+r.date+(r.horaire?' '+r.horaire:'')+'</td><td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;font-weight:600;">'+r.thematique+'</td><td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">'+r.conseiller+'</td><td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#718096;">'+r.commune+'</td></tr>'; }).join('');
  var html = '<div style="font-family:sans-serif;max-width:640px;margin:0 auto;"><div style="background:#1e3a8a;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0;"><h2 style="margin:0;font-size:18px;">⚠️ Ateliers en attente de mise à jour</h2>'+(isTest?'<p style="margin:6px 0 0;font-size:12px;opacity:.8;">— EMAIL DE TEST —</p>':'')+'</div><div style="background:#fff;padding:20px 24px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;"><p style="color:#4a5568;">Bonjour '+destinataireName+',</p><p style="color:#4a5568;">Les ateliers suivants ont le statut <strong>Planifié</strong> mais leur date est passée :</p><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#f7fafc;"><th style="padding:8px 10px;text-align:left;color:#718096;">Date</th><th style="padding:8px 10px;text-align:left;color:#718096;">Thématique</th><th style="padding:8px 10px;text-align:left;color:#718096;">Conseiller</th><th style="padding:8px 10px;text-align:left;color:#718096;">Commune</th></tr></thead><tbody>'+lignes+'</tbody></table><p style="margin-top:20px;color:#718096;font-size:12px;">Cliquez ci-dessous pour clôturer ces ateliers.</p><div style="text-align:center;margin-top:16px;"><a href="'+APP_URL+'" style="display:inline-block;background:#1e3a8a;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;">📋 Ouvrir l\'application</a></div></div></div>';
  try { MailApp.sendEmail({to:email, subject:sujet, htmlBody:html}); Logger.log('Email envoyé à ' + email); }
  catch(err) { Logger.log('Erreur envoi email à ' + email + ' : ' + err); }
}
function ajouterColonneAutre() {
  var sh = _ss().getSheetByName(SHEET_NAME);
  if (!sh) { Logger.log('Feuille introuvable'); return; }
  var headers = sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0].map(function(h){return String(h).trim();});
  if (headers.indexOf('Autre') !== -1) { Logger.log('Colonne Autre déjà présente'); return; }
  var iOrd = headers.indexOf('Ordinateur');
  if (iOrd !== -1) { sh.insertColumnAfter(iOrd+1); sh.getRange(1,iOrd+2).setValue('Autre'); Logger.log('Colonne Autre ajoutée après Ordinateur'); }
  else { sh.getRange(1,sh.getLastColumn()+1).setValue('Autre'); Logger.log('Colonne Autre ajoutée en fin'); }
}
// ── keepAlive v11.12 : réchauffe le cache getAll segmenté ──────────────────
// v11.10 avait renoncé à tout réchauffement actif (ping léger sur Config
// uniquement) après un incident : l'ancienne version rechargeait 2 années
// complètes (_actionGetAllFresh) à chaque passage, au rythme du trigger réel
// (toutes les 5 min) — une exécution a fini par dépasser les 6 minutes
// (limite dure d'Apps Script) et a été tuée de force par Google, en boucle,
// contendant avec le trafic réel (404 / blocages 30-35 s observés).
//
// v11.11 avait corrigé ça (1 seule année, skip si déjà chaud, LockService),
// mais cache.put() échouait silencieusement dès que le JSON dépassait 100 Ko
// ("Argument too large: value", confirmé en prod dès ~200 ateliers) — le
// cache n'était donc jamais rempli. v11.12 réutilise _cacherGetAll/
// _lireCacheGetAll (cache segmenté, voir plus haut), partagés avec doGet :
//  1. Une seule année (l'année en cours) au lieu de deux → coût divisé par 2.
//  2. Skip immédiat si le cache est déjà chaud → à un trigger de 5 min contre
//     un TTL de 10 min, la moitié des passages ne font qu'un cache.get().
//  3. LockService.tryLock(0) : si une exécution (réelle ou un keepAlive
//     précédent) tourne encore, on ABANDONNE tout de suite plutôt que
//     d'attendre — jamais deux exécutions empilées dans la file GAS.
//  4. Le cache est désormais segmenté (100 Ko/clé n'est plus une limite).
function keepAlive() {
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(0)) { Logger.log('keepAlive : exécution déjà en cours, passage sauté.'); return; }
  try {
    var year = String(new Date().getFullYear());
    if (_lireCacheGetAll(year)) { Logger.log('keepAlive : cache ' + year + ' déjà chaud.'); return; }
    var t0 = new Date().getTime();
    var result = _actionGetAllFresh({year: year});
    if (result.ok) _cacherGetAll(year, result);
    Logger.log('keepAlive : cache ' + year + ' réchauffé en ' + (new Date().getTime() - t0) + ' ms');
  } catch (err) {
    Logger.log('keepAlive error: ' + err);
  } finally {
    lock.releaseLock();
  }
}
function testerSecuriteDoGet() {
  // v11.13 : getLogs ajouté — il manquait ici, c'est pour ça que le trou
  // (accessible sans token) n'avait jamais été détecté par ce test.
  // v11.14 : saveEntry retiré de cette liste — il doit désormais RÉUSSIR
  // sans token (voir OPEN_WRITE_ACTIONS dans doGet), le tester ici comme
  // "doit être bloqué" serait un faux négatif.
  var WRITE_ACTIONS = ['deleteEntry','updateStatut','saveConfig','saveVisibility','getLogs'];
  var READ_ACTIONS  = ['getAll','getConfig','getComptes'];
  Logger.log('=== TEST SÉCURITÉ doGet ===');
  WRITE_ACTIONS.forEach(function(action) {
    var fakeEvent = { parameter: { action: action, _id: 'test' } };
    var result = JSON.parse(doGet(fakeEvent).getContent());
    var bloque = result.ok === false && result.error && result.error.indexOf('Non autorisé') !== -1;
    Logger.log((bloque ? '✅' : '❌') + ' ' + action + ' sans token → ' + JSON.stringify(result).substring(0, 100));
  });
  READ_ACTIONS.forEach(function(action) {
    var fakeEvent = { parameter: { action: action } };
    var result = JSON.parse(doGet(fakeEvent).getContent());
    Logger.log((result.ok !== false ? '✅' : '❌') + ' ' + action + ' sans token → lecture libre');
  });
  // v11.13 : un token valide mais de rôle "user" doit être refusé sur les
  // actions admin — c'était le second trou (rôle jamais vérifié).
  var fakeUserToken = _generateToken('__test_user__', 'user');
  var fakeEvent2 = { parameter: { action: 'saveConfig', token: fakeUserToken, conseiller: '__test_user__', key: 'test', value: 'x' } };
  var result2 = JSON.parse(doGet(fakeEvent2).getContent());
  var bloqueRole = result2.ok === false && result2.error && result2.error.indexOf('administrateurs') !== -1;
  Logger.log((bloqueRole ? '✅' : '❌') + ' saveConfig avec token rôle "user" → ' + JSON.stringify(result2).substring(0, 100));
  Logger.log('=== FIN ===');
}
