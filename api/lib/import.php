<?php
// Import du classeur NextStep (export .xlsx de Google Sheets) dans MySQL.
//
// Deux temps, séparés exprès :
//   1. import_analyser() lit et convertit tout, sans toucher à la base, et
//      renvoie le compte rendu (erreurs, avertissements, décomptes).
//   2. import_charger() écrit dans la base, en une seule transaction, et
//      seulement si l'analyse n'a trouvé aucune erreur.
//
// Règle décidée le 23/09/2026 (AG-010) : l'import REFUSE plutôt que de
// deviner. Une colonne inconnue, une date illisible, un nombre qui n'en est
// pas un : erreur avec le numéro de ligne, à corriger dans le classeur.
// Une donnée perdue en silence ne se verrait qu'après la bascule.

require_once __DIR__ . '/xlsx.php';

const IMPORT_FEUILLE_ATELIERS = 'Ateliers_next_step';

// Les 20 colonnes « métier » d'un atelier et leur type. Longueur maximale
// pour les colonnes VARCHAR (voir schema.sql), 0 pour les colonnes TEXT.
const IMPORT_COLONNES_ATELIER = [
    '_id'                       => ['id', 64],
    '_n'                        => ['entier', 0],
    'statut'                    => ['texte', 50],
    'date'                      => ['date', 0],
    'horaire'                   => ['heure', 0],
    'ampm'                      => ['texte', 10],
    'orienteur'                 => ['texte', 0],
    'commune'                   => ['texte', 255],
    'lieu'                      => ['texte', 0],
    'thematique'                => ['texte', 0],
    'inscrits'                  => ['entier', 0],
    'presents'                  => ['entier', 0],
    'public'                    => ['texte', 255],
    'conseiller'                => ['texte', 100],
    'co_animateur'              => ['texte', 0],
    'residence'                 => ['texte', 0],
    'remarques'                 => ['texte', 0],
    'nb_ordinateurs'            => ['entier', 0],
    'date_prelevement_materiel' => ['date_ou_vide', 0],
    'date_retour_materiel'      => ['date_ou_vide', 0],
];

// Colonnes de matériel de la feuille NextStep (GAS_NEXTSTEP.js:482) :
// « OUI » = emprunté, vide = non.
const IMPORT_COLONNES_MATERIEL = [
    'Videoprojecteur', 'Ecran', 'Classe mobile', 'Boitier 4G', 'Tablette',
    'Scanner', 'Multiprise', 'Ordinateur', 'Autre',
];

const IMPORT_COLONNES_COMPTES = ['Conseiller', 'Hash', 'Role', 'Actif', 'FailCount', 'LockUntil'];

// Clés de Config lues par le GAS NEWGEN (celui dont l'API reprend le contrat).
const IMPORT_CONFIG_UTILES = [
    'conseiller_colors', 'emails', 'list_conseillers', 'list_materiels',
    'list_publics', 'list_statuts', 'lists', 'maintenance', 'maintenance_msg',
    'materiels_caches', 'rappels_actifs', 'stock_ordinateurs', 'visibility',
];
// Même réglage, nom différent dans NextStep (INVENTAIRE.md §1).
const IMPORT_CONFIG_RENOMMEES = ['materiels_masques' => 'materiels_caches'];
// Clés présentes dans le classeur réel du 23/09/2026 mais lues par aucun des
// deux codes (vérifié par recherche dans les deux dépôts) : non importées.
const IMPORT_CONFIG_MORTES = [
    'app_version', 'frontend_visibility', 'conseiller_emails',
    'rappels_notified', 'admin_password',
];
// Les clés « lock_<prénom>_<nom> » sont elles aussi mortes.
const IMPORT_CONFIG_MORTES_PREFIXE = 'lock_';

// Actions du format récent du journal (GAS_NEXTSTEP.js:958).
const IMPORT_ACTIONS_JOURNAL = ['login', 'loginFail', 'saveEntry', 'delete', 'accesIndex', 'alertesRetard'];

// Analyse le fichier. Renvoie :
//   erreurs, avertissements : listes de phrases à afficher ;
//   donnees : lignes prêtes pour la base (ateliers, materiel, config,
//             comptes, journal) ;
//   decomptes : nombre de lignes par table.
function import_analyser(array $feuilles): array
{
    $r = [
        'erreurs' => [], 'avertissements' => [],
        'donnees' => ['ateliers' => [], 'materiel' => [], 'config' => [], 'comptes' => [], 'journal' => []],
    ];

    foreach (['Ateliers_next_step', 'Comptes', 'Config', 'Logs_Connexion'] as $nom) {
        if (!array_key_exists($nom, $feuilles)) $r['erreurs'][] = "Feuille « $nom » absente du fichier.";
    }
    // Le GAS ne lit que ces quatre feuilles : une autre feuille (ex. « Copie
    // de Config ») ne contient rien que l'appli ait jamais affiché.
    foreach (array_keys($feuilles) as $nom) {
        if (!in_array($nom, ['Ateliers_next_step', 'Comptes', 'Config', 'Logs_Connexion'], true)) {
            $r['avertissements'][] = "Feuille « $nom » ignorée (jamais lue par l'appli).";
        }
    }
    if ($r['erreurs']) return import_decompter($r);

    import_ateliers($feuilles['Ateliers_next_step'], $r);
    import_comptes($feuilles['Comptes'], $r);
    import_config($feuilles['Config'], $r);
    import_journal($feuilles['Logs_Connexion'], $r);
    return import_decompter($r);
}

function import_decompter(array $r): array
{
    $r['decomptes'] = array_map('count', $r['donnees']);
    return $r;
}

// Lit la ligne d'en-tête : renvoie [nom => index] et signale les colonnes
// inconnues, manquantes ou en double.
function import_entetes(array $ligne, array $attendues, string $feuille, array &$r): ?array
{
    $index = [];
    foreach ($ligne as $i => $v) {
        $nom = trim((string) $v);
        if ($nom === '') continue;
        if (!in_array($nom, $attendues, true)) {
            $r['erreurs'][] = "$feuille : colonne « $nom » inconnue (colonne " . import_lettre($i) . ').';
        } elseif (isset($index[$nom])) {
            $r['erreurs'][] = "$feuille : colonne « $nom » en double.";
        } else {
            $index[$nom] = $i;
        }
    }
    foreach ($attendues as $nom) {
        if (!isset($index[$nom])) $r['erreurs'][] = "$feuille : colonne « $nom » absente.";
    }
    return count($index) === count($attendues) ? $index : null;
}

function import_ateliers(array $lignes, array &$r): void
{
    $f = IMPORT_FEUILLE_ATELIERS;
    $attendues = array_merge(array_keys(IMPORT_COLONNES_ATELIER), IMPORT_COLONNES_MATERIEL);
    $index = import_entetes($lignes[0] ?? [], $attendues, $f, $r);
    if ($index === null) return;
    import_colonnes_sans_entete($lignes, $index, $f, $r);

    $vus = [];
    foreach (array_slice($lignes, 1, null, true) as $i => $ligne) {
        $num = $i + 1;
        $id = trim((string) ($ligne[$index['_id']] ?? ''));
        if ($id === '') {
            // Le GAS saute les lignes sans _id : elles n'ont jamais été visibles.
            if (import_ligne_non_vide($ligne)) {
                $r['avertissements'][] = "$f ligne $num : pas d'_id, ignorée (jamais affichée par l'appli).";
            }
            continue;
        }
        if (isset($vus[$id])) {
            $r['erreurs'][] = "$f ligne $num : _id « $id » déjà vu ligne {$vus[$id]}.";
            continue;
        }
        $vus[$id] = $num;

        $atelier = [];
        foreach (IMPORT_COLONNES_ATELIER as $col => [$type, $max]) {
            $brut = $ligne[$index[$col]] ?? null;
            $err = null;
            $atelier[$col] = import_convertir($brut, $type, $max, $err);
            if ($err !== null) $r['erreurs'][] = "$f ligne $num, colonne $col : $err";
        }
        $r['donnees']['ateliers'][] = $atelier;

        foreach (IMPORT_COLONNES_MATERIEL as $mat) {
            $v = strtoupper(trim(import_texte($ligne[$index[$mat]] ?? null)));
            if ($v === 'OUI') {
                $r['donnees']['materiel'][] = ['atelier_id' => $id, 'materiel' => $mat];
            } elseif ($v !== '' && $v !== 'NON') {
                $r['erreurs'][] = "$f ligne $num, colonne $mat : « $v » au lieu de OUI ou vide.";
            }
        }
    }
}

// Une donnée sous une colonne sans titre serait perdue : on la signale.
function import_colonnes_sans_entete(array $lignes, array $index, string $feuille, array &$r): void
{
    $connues = array_flip($index);
    $signalees = [];
    foreach (array_slice($lignes, 1, null, true) as $i => $ligne) {
        foreach ($ligne as $c => $v) {
            if (isset($connues[$c]) || isset($signalees[$c])) continue;
            if ($v === null || (is_string($v) && trim($v) === '')) continue;
            $signalees[$c] = true;
            $r['erreurs'][] = "$feuille : données sans titre de colonne en colonne " . import_lettre($c) . ' (ligne ' . ($i + 1) . ').';
        }
    }
}

function import_ligne_non_vide(array $ligne): bool
{
    foreach ($ligne as $v) {
        if ($v !== null && !(is_string($v) && trim($v) === '')) return true;
    }
    return false;
}

// Convertit une cellule vers le type de la colonne. $err reçoit la raison
// du refus, ou reste null.
function import_convertir(mixed $v, string $type, int $max, ?string &$err): mixed
{
    $vide = $v === null || (is_string($v) && trim($v) === '');
    switch ($type) {
        case 'id':
        case 'texte':
            if ($v instanceof XlsxDate || is_bool($v)) {
                $err = 'valeur de type date ou vrai/faux dans une colonne texte.';
                return '';
            }
            $s = $vide ? '' : trim(import_texte($v));
            if ($max > 0 && mb_strlen($s) > $max) $err = "texte trop long (" . mb_strlen($s) . " caractères, maximum $max).";
            elseif ($max === 0 && strlen($s) > 65535) $err = 'texte trop long (plus de 64 Ko).';
            return $s;

        case 'entier':
            if ($vide) return null;
            if (is_int($v)) return $v;
            if (is_string($v) && preg_match('/^\s*\d+\s*$/', $v)) return (int) $v;
            $err = '« ' . import_texte($v) . ' » n\'est pas un nombre entier.';
            return null;

        case 'date':
        case 'date_ou_vide':
            if ($vide) {
                if ($type === 'date') $err = 'date vide (un atelier sans date n\'apparaît jamais dans l\'appli).';
                return null;
            }
            if ($v instanceof XlsxDate) return $v->jour();
            if (is_string($v) && preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})\s*$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return "$m[1]-$m[2]-$m[3]";
            }
            $err = '« ' . import_texte($v) . ' » n\'est pas une date (attendu : cellule date ou aaaa-mm-jj).';
            return null;

        case 'heure':
            if ($vide) return null;
            if ($v instanceof XlsxDate) return $v->heure();
            if (is_string($v) && preg_match('/^\s*(\d{1,2})[:hH](\d{2})\s*$/', $v, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
                return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }
            $err = '« ' . import_texte($v) . ' » n\'est pas une heure (attendu : hh:mm).';
            return null;
    }
    throw new LogicException("Type de colonne inconnu : $type");
}

// Texte d'une cellule quelle que soit sa nature (pour les messages et les
// colonnes texte). Un entier reste sans « .0 ».
function import_texte(mixed $v): string
{
    if ($v === null) return '';
    if ($v instanceof XlsxDate) return $v->dateHeure();
    if (is_bool($v)) return $v ? 'true' : 'false';
    return (string) $v;
}

function import_comptes(array $lignes, array &$r): void
{
    $f = 'Comptes';
    $index = import_entetes($lignes[0] ?? [], IMPORT_COLONNES_COMPTES, $f, $r);
    if ($index === null) return;
    import_colonnes_sans_entete($lignes, $index, $f, $r);
    $vus = [];
    foreach (array_slice($lignes, 1, null, true) as $i => $ligne) {
        $num = $i + 1;
        $nom = trim(import_texte($ligne[$index['Conseiller']] ?? null));
        if ($nom === '') continue;
        if (mb_strlen($nom) > 100) { $r['erreurs'][] = "$f ligne $num : nom trop long."; continue; }
        if (isset($vus[$nom])) { $r['erreurs'][] = "$f ligne $num : « $nom » déjà vu ligne {$vus[$nom]}."; continue; }
        $vus[$nom] = $num;

        $role = trim(import_texte($ligne[$index['Role']] ?? null));
        if ($role === '') $role = 'user';
        if (!in_array($role, ['admin', 'superviseur', 'user'], true)) {
            $r['erreurs'][] = "$f ligne $num : rôle « $role » inconnu (admin, superviseur ou user).";
        }
        $actif = strtoupper(trim(import_texte($ligne[$index['Actif']] ?? null)));
        if (!in_array($actif, ['', 'OUI', 'NON'], true)) {
            $r['erreurs'][] = "$f ligne $num : Actif « $actif » au lieu de OUI, NON ou vide.";
        }

        // Jamais de mot de passe en clair dans la base (AG-009). On stocke
        // password_hash(sha256_hex) : les empreintes SHA-256 de la feuille
        // se reprennent telles quelles, sans connaître les mots de passe.
        $brut = trim(import_texte($ligne[$index['Hash']] ?? null));
        $doitChanger = 0;
        if ($brut === '') {
            $hash = null;
            $r['avertissements'][] = "$f : « $nom » n'a pas de mot de passe — à réinitialiser par un admin après l'import.";
        } elseif (preg_match('/^[0-9a-fA-F]{64}$/', $brut)) {
            $hash = password_hash(strtolower($brut), PASSWORD_DEFAULT);
        } else {
            // Mot de passe encore en clair (compte jamais connecté) : même
            // traitement que le GAS au premier succès, puis changement forcé.
            $hash = password_hash(hash('sha256', $brut), PASSWORD_DEFAULT);
            $doitChanger = 1;
            $r['avertissements'][] = "$f : le mot de passe de « $nom » était en clair dans la feuille — haché, à changer à la prochaine connexion.";
        }
        $r['donnees']['comptes'][] = [
            'conseiller' => $nom, 'hash' => $hash, 'role' => $role,
            'actif' => $actif === 'NON' ? 0 : 1, 'doit_changer' => $doitChanger,
        ];
    }
}

// La feuille Config n'a pas de ligne d'en-tête : la ligne 1 est déjà une
// paire clé/valeur (constaté sur l'export du 23/09/2026 ; le GAS lit toutes
// les lignes, GAS_NEWGEN.js:1013).
function import_config(array $lignes, array &$r): void
{
    $f = 'Config';
    $vus = [];
    foreach ($lignes as $i => $ligne) {
        $num = $i + 1;
        $cle = trim(import_texte($ligne[0] ?? null));
        foreach (array_slice($ligne, 2, null, true) as $c => $v) {
            if ($v !== null && trim(import_texte($v)) !== '') {
                $r['erreurs'][] = "$f ligne $num : donnée en colonne " . import_lettre($c) . ' (seules A et B sont lues).';
            }
        }
        if ($cle === '') continue;
        if (in_array($cle, IMPORT_CONFIG_MORTES, true) || str_starts_with($cle, IMPORT_CONFIG_MORTES_PREFIXE)) {
            $msg = "$f : clé « $cle » ignorée (lue par aucun des deux codes).";
            if ($cle === 'admin_password') {
                $msg .= ' ⚠️ Elle contient un mot de passe en clair : à supprimer du classeur.';
            }
            $r['avertissements'][] = $msg;
            continue;
        }
        $cible = IMPORT_CONFIG_RENOMMEES[$cle] ?? $cle;
        if (!in_array($cible, IMPORT_CONFIG_UTILES, true)) {
            $r['erreurs'][] = "$f ligne $num : clé « $cle » inconnue.";
            continue;
        }
        if (isset($vus[$cible])) {
            $r['erreurs'][] = "$f ligne $num : clé « $cible » déjà vue ligne {$vus[$cible]}.";
            continue;
        }
        $vus[$cible] = $num;
        $v = $ligne[1] ?? null;
        if ($v instanceof XlsxDate) {
            $r['erreurs'][] = "$f ligne $num : valeur de type date pour « $cle ».";
            continue;
        }
        $r['donnees']['config'][] = ['cle' => $cible, 'valeur' => import_texte($v)];
    }
}

// Deux formats mêlés dans la feuille (GAS_NEXTSTEP.js:944-990) : on
// applique la même lecture que actionGetLogs pour les unifier.
function import_journal(array $lignes, array &$r): void
{
    $f = 'Logs_Connexion';
    foreach (array_slice($lignes, 1, null, true) as $i => $l) {
        $num = $i + 1;
        if (!import_ligne_non_vide($l)) continue;
        foreach (array_slice($l, 9, null, true) as $c => $v) {
            if ($v !== null && trim(import_texte($v)) !== '') {
                $r['erreurs'][] = "$f ligne $num : donnée en colonne " . import_lettre($c) . ' (9 colonnes lues au plus).';
            }
        }
        $ts = $l[0] ?? null;
        if ($ts instanceof XlsxDate) {
            $horodatage = $ts->dateHeure();
        } elseif (is_string($ts) && ($t = strtotime($ts)) !== false) {
            $horodatage = date('Y-m-d H:i:s', $t);
        } else {
            $r['erreurs'][] = "$f ligne $num : horodatage « " . import_texte($ts) . ' » illisible.';
            continue;
        }
        $col1 = trim(import_texte($l[1] ?? null));
        if (in_array($col1, IMPORT_ACTIONS_JOURNAL, true)) {
            $e = [
                'action' => $col1,
                'conseiller' => import_texte($l[2] ?? null),
                'ref' => import_texte($l[3] ?? null),
                'role' => import_texte($l[4] ?? null) ?: 'user',
                'user_agent' => import_texte($l[5] ?? null),
                'succes' => in_array($l[6] ?? null, [true, 1, '1', 'TRUE', 'true'], true) ? 1 : 0,
                'tentatives' => (int) import_texte($l[7] ?? null),
                'source' => import_texte($l[8] ?? null),
            ];
        } else {
            // Ancien format : col1 = conseiller, action implicite checkPassword.
            $role = trim(import_texte($l[2] ?? null));
            $s3 = strtoupper(trim(import_texte($l[3] ?? null)));
            $succes = in_array($s3, ['OUI', 'TRUE', '1'], true) ? 1
                : (in_array($s3, ['NON', 'FALSE', '0'], true) ? 0
                : (in_array($role, ['OK', 'admin', 'user'], true) ? 1 : 0));
            $e = [
                'action' => 'checkPassword',
                'conseiller' => $col1,
                'ref' => '',
                'role' => in_array(strtolower($role), ['admin', 'user', ''], true) ? $role : 'user',
                'user_agent' => import_texte($l[4] ?? null) ?: import_texte($l[3] ?? null),
                'succes' => $succes,
                'tentatives' => (int) import_texte($l[5] ?? null),
                'source' => '',
            ];
        }
        $e['horodatage'] = $horodatage;
        // Bornes des colonnes (schema.sql) : un navigateur au nom très long
        // est tronqué, ce n'est pas une donnée métier.
        $e['user_agent'] = mb_substr($e['user_agent'], 0, 500);
        foreach (['conseiller' => 100, 'ref' => 100, 'role' => 20, 'source' => 50, 'action' => 30] as $c => $max) {
            if (mb_strlen($e[$c]) > $max) $r['erreurs'][] = "$f ligne $num : $c trop long (maximum $max).";
        }
        $r['donnees']['journal'][] = $e;
    }
}

function import_lettre(int $i): string
{
    $s = '';
    for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) $s = chr(65 + ($n - 1) % 26) . $s;
    return $s;
}

// Crée les tables si besoin, puis remplace tout leur contenu par celui de
// l'analyse, en une transaction : soit tout passe, soit rien ne change.
// Les jetons de connexion et les compteurs d'échecs sont vidés aussi : toute
// l'équipe se reconnecte (AG-009).
function import_charger(PDO $db, array $analyse, string $empreinteFichier): void
{
    if ($analyse['erreurs']) throw new RuntimeException("Import refusé : l'analyse contient des erreurs.");

    // CREATE TABLE valide implicitement toute transaction : on le fait avant.
    foreach (import_requetes_schema() as $sql) $db->exec($sql);

    $verrou = $db->query("SELECT valeur FROM meta WHERE cle = 'import_verrouille'")->fetchColumn();
    if ($verrou === '1') {
        throw new RuntimeException("Import verrouillé : la base est en production depuis la bascule. Un import écraserait les saisies de l'équipe.");
    }

    $db->beginTransaction();
    try {
        foreach (['ateliers_materiel', 'ateliers', 'config', 'comptes', 'journal', 'sessions', 'tentatives', 'reinitialisations'] as $t) {
            $db->exec("DELETE FROM $t");
        }
        $d = $analyse['donnees'];
        import_inserer($db, 'ateliers', [
            'id', 'n', 'statut', 'date', 'horaire', 'ampm', 'orienteur', 'commune', 'lieu',
            'thematique', 'inscrits', 'presents', 'public', 'conseiller', 'co_animateur',
            'residence', 'remarques', 'nb_ordinateurs', 'date_prelevement_materiel', 'date_retour_materiel',
        ], array_map(fn($a) => array_values(['id' => $a['_id'], 'n' => $a['_n']] + array_diff_key($a, ['_id' => 0, '_n' => 0])), $d['ateliers']));
        import_inserer($db, 'ateliers_materiel', ['atelier_id', 'materiel'], array_map('array_values', $d['materiel']));
        import_inserer($db, 'config', ['cle', 'valeur'], array_map('array_values', $d['config']));
        import_inserer($db, 'comptes', ['conseiller', 'hash', 'role', 'actif', 'doit_changer'], array_map('array_values', $d['comptes']));
        import_inserer($db, 'journal',
            ['horodatage', 'action', 'conseiller', 'ref', 'role', 'succes', 'tentatives', 'user_agent', 'source'],
            array_map(fn($e) => [$e['horodatage'], $e['action'], $e['conseiller'], $e['ref'], $e['role'], $e['succes'], $e['tentatives'], $e['user_agent'], $e['source']], $d['journal']));

        $meta = $db->prepare('REPLACE INTO meta (cle, valeur) VALUES (?, ?)');
        $meta->execute(['dernier_import', date('Y-m-d H:i:s')]);
        $meta->execute(['dernier_import_empreinte', $empreinteFichier]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function import_inserer(PDO $db, string $table, array $colonnes, array $lignes): void
{
    if (!$lignes) return;
    $sql = "INSERT INTO $table (" . implode(', ', array_map(fn($c) => "`$c`", $colonnes)) . ') VALUES ('
         . implode(', ', array_fill(0, count($colonnes), '?')) . ')';
    $st = $db->prepare($sql);
    foreach ($lignes as $l) $st->execute($l);
}

// Découpe schema.sql en requêtes (commentaires « -- » retirés).
function import_requetes_schema(): array
{
    $sql = preg_replace('/--[^\n]*/', '', file_get_contents(__DIR__ . '/schema.sql'));
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}
