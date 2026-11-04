<?php
// Page des travailleurs sociaux (AG-042, 04/11/2026) : consultation seule
// des ateliers « Planifié » à venir, sur ts.html (NEWGEN et NextStep).
//
// Comptes à part (table comptes_ts, identifiant = e-mail @lotetgaronne.fr)
// créés à la demande sur la page : le mot de passe provisoire part par mail
// à l'adresse saisie (AG-043, 04/11/2026) ; l'Admin peut aussi en créer un
// à la main, réinitialiser, désactiver, supprimer. Les comptes
// admin/superviseur à accès Admin actif peuvent aussi s'y connecter
// (demande de l'utilisateur, 04/11/2026), toujours en lecture seule.
//
// Session : table `sessions`, role 'ts', conseiller = 'ts:' + identifiant.
// Un jeton 'ts' n'ouvre que API_ACTIONS_TS (garde dans api_traiter) : sans
// elle, il ouvrirait getAll (remarques, inscrits, conseillers…).

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/mail.php';

const TS_PREFIXE = 'ts:';
const TS_DEMANDES_MAX = 30;      // comptes créés par demande en 24 h, au-delà on refuse (AG-043)
const TS_DEMANDES_JOURS = 90;    // purge du registre des demandes
const TS_JAMAIS_CONNECTE_JOURS = 30;   // provisoire jamais changé : compte supprimé (AG-043)
const TS_OUBLI_HEURES = 24;      // validité du provisoire envoyé par « mot de passe oublié »
const TS_OUBLI_ESPACE_MIN = 15;  // une seule demande d'oubli par compte et par 15 min
const TS_DOMAINE = '@lotetgaronne.fr'; // seules adresses acceptées (AG-043, 04/11/2026)
const TS_EMAIL_MAX = 97;         // 'ts:' + e-mail tient dans VARCHAR(100) (sessions, tentatives)
const TS_CHAMPS = ['date', 'statut', 'orienteur', 'commune', 'lieu', 'public', 'thematique'];
// Adresse de la page, dans le mail du mot de passe provisoire : jamais prise
// de la requête (un mail du Département avec un lien choisi par l'appelant
// serait un hameçonnage). Bac à sable : ses propres pages.
const TS_URL_PAGE = [
    'newgen'   => 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/ts.html',
    'nextstep' => 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/ts.html',
];
const TS_URL_PAGE_BAC = [
    'newgen'   => 'https://ateliers-numeriques.alwaysdata.net/sandbox/ts.html',
    'nextstep' => 'https://ateliers-numeriques.alwaysdata.net/sandbox/nextstep/ts.html',
];
const TS_ERREUR_CONNEXION = 'Identifiant ou mot de passe incorrect';

function ts_schema(PDO $db): void
{
    static $ok = false;
    if ($ok) return;
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'EXISTS comptes_ts') || str_contains($sql, 'EXISTS demandes_ts')) $db->exec($sql);
    }
    // Colonnes ajoutées après la création de la table (AG-043) : bases déjà en service.
    if (!$db->query("SHOW COLUMNS FROM comptes_ts LIKE 'hash_oubli'")->fetch()) {
        $db->exec('ALTER TABLE comptes_ts ADD COLUMN hash_oubli VARCHAR(255) NULL, ADD COLUMN oubli_le DATETIME NULL');
    }
    $ok = true;
}

// E-mail normalisé (minuscules), ou null s'il est invalide ou trop long.
function ts_email(mixed $v): ?string
{
    // Espaces retirés : le clavier d'un téléphone en ajoute après un point.
    $e = mb_strtolower(preg_replace('/\s+/u', '', (string) $v));
    if ($e === '' || strlen($e) > TS_EMAIL_MAX || !filter_var($e, FILTER_VALIDATE_EMAIL)) return null;
    return $e;
}

// Adresse acceptée pour une demande ou une création : domaine du Département
// exact (ni sous-domaine, ni suffixe : « x@lotetgaronne.fr.autre.com » refusé).
function ts_email_autorise(?string $e): bool
{
    return $e !== null && str_ends_with($e, TS_DOMAINE);
}

function ts_mdp_provisoire(): string
{
    // Sans caractères ambigus (0/O, 1/l/I) : il sera recopié à la main.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $mdp = '';
    for ($i = 0; $i < 12; $i++) $mdp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $mdp;
}

function ts_fermer_sessions(PDO $db, string $identifiant, ?string $garder = null): void
{
    $db->prepare('DELETE FROM sessions WHERE conseiller = ? AND jeton_hash <> ?')->execute([TS_PREFIXE . $identifiant, (string) $garder]);
}

// ── Public ────────────────────────────────────────────────────────────────

// Demande d'accès (AG-043, 04/11/2026) : le compte est créé tout de suite et
// le mot de passe provisoire part PAR MAIL à l'adresse saisie, jamais dans la
// réponse : la boîte de l'agent est la preuve qu'il en est le titulaire.
// Réponse identique que l'adresse soit connue ou non (aucun mail dans ce cas :
// sinon un tiers ferait changer le mot de passe d'un autre ou inonderait sa
// boîte). Au plus TS_DEMANDES_MAX créations par 24 h, contrôle et insertion
// sous verrou (deux demandes simultanées ne dépassent pas le plafond).
function action_ts_demande_acces(PDO $db, array $p): array
{
    ts_schema($db);
    // Champ piège : invisible pour un humain, rempli par les robots.
    if (trim((string) ($p['url'] ?? '')) !== '') return ['ok' => true];
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    if (!ts_email_autorise($email)) return ['ok' => false, 'error' => 'Seules les adresses ' . TS_DOMAINE . ' sont acceptées'];
    $db->exec('DELETE FROM demandes_ts WHERE demande_le < NOW() - INTERVAL ' . TS_DEMANDES_JOURS . ' DAY');
    $db->exec('DELETE FROM comptes_ts WHERE doit_changer = 1 AND cree_le < NOW() - INTERVAL ' . TS_JAMAIS_CONNECTE_JOURS . ' DAY');

    $verrou = (int) $db->query("SELECT GET_LOCK('ts_demande_acces', 5)")->fetchColumn() === 1;
    if (!$verrou) return ['ok' => false, 'error' => 'Service occupé : réessayez dans un instant.'];
    $mdp = null;
    try {
        // Plafond d'abord, pour toute adresse valide connue ou non : une file
        // pleine ne doit pas révéler qu'une adresse a déjà un compte.
        if ((int) $db->query('SELECT COUNT(*) FROM demandes_ts WHERE demande_le > NOW() - INTERVAL 1 DAY')->fetchColumn() >= TS_DEMANDES_MAX) {
            return ['ok' => false, 'error' => 'Trop de demandes aujourd\'hui : réessayez demain.'];
        }
        $s = $db->prepare('SELECT COUNT(*) FROM comptes_ts WHERE email = ?');
        $s->execute([$email]);
        if ((int) $s->fetchColumn() > 0) return ['ok' => true];
        $mdp = ts_mdp_provisoire();
        $i = $db->prepare('INSERT IGNORE INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES (?, ?, 1, 1, NOW())');
        $i->execute([$email, password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT)]);
        if ($i->rowCount() !== 1) return ['ok' => true];
        $site = api_site_courant() ?: 'nextstep';
        $db->prepare('REPLACE INTO demandes_ts (email, demande_le, site) VALUES (?, NOW(), ?)')->execute([$email, $site]);
    } finally {
        $db->query("SELECT RELEASE_LOCK('ts_demande_acces')");
    }
    $envoye = false;
    try { $envoye = ts_envoyer_provisoire($email, $mdp, $site); } catch (Throwable $e) { /* annulé ci-dessous */ }
    if (!$envoye) {
        // Aucun compte dont personne ne connaît le mot de passe.
        $db->prepare('DELETE FROM comptes_ts WHERE email = ?')->execute([$email]);
        $db->prepare('DELETE FROM demandes_ts WHERE email = ?')->execute([$email]);
        return ['ok' => false, 'error' => 'Envoi du mail impossible : réessayez dans un instant.'];
    }
    api_journal($db, 'tsDemande', '', $email, 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

// Mot de passe oublié (AG-043). Même réponse que l'adresse ait un compte ou non.
// Pour un compte actif, un nouveau provisoire part par mail : il est mis de côté
// (hash_oubli, 24 h) et ne remplace le mot de passe actuel que s'il est employé à
// la connexion — un tiers qui connaît l'adresse ne peut donc pas bloquer l'agent.
// Même plafond que les créations (30 mails en 24 h) ; un compte au plus toutes les 15 min.
function action_ts_mdp_oublie(PDO $db, array $p): array
{
    ts_schema($db);
    if (trim((string) ($p['url'] ?? '')) !== '') return ['ok' => true];
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    if (!ts_email_autorise($email)) return ['ok' => false, 'error' => 'Seules les adresses ' . TS_DOMAINE . ' sont acceptées'];

    $verrou = (int) $db->query("SELECT GET_LOCK('ts_demande_acces', 5)")->fetchColumn() === 1;
    if (!$verrou) return ['ok' => false, 'error' => 'Service occupé : réessayez dans un instant.'];
    $mdp = null;
    try {
        if ((int) $db->query('SELECT COUNT(*) FROM demandes_ts WHERE demande_le > NOW() - INTERVAL 1 DAY')->fetchColumn() >= TS_DEMANDES_MAX) {
            return ['ok' => false, 'error' => 'Trop de demandes aujourd\'hui : réessayez demain.'];
        }
        $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ? AND actif = 1 AND (oubli_le IS NULL OR oubli_le < NOW() - INTERVAL ' . TS_OUBLI_ESPACE_MIN . ' MINUTE)');
        $s->execute([$email]);
        if (!$s->fetchColumn()) return ['ok' => true];
        $mdp = ts_mdp_provisoire();
        $db->prepare('UPDATE comptes_ts SET hash_oubli = ?, oubli_le = NOW() WHERE email = ?')
           ->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $email]);
        $site = api_site_courant() ?: 'nextstep';
        $db->prepare('REPLACE INTO demandes_ts (email, demande_le, site) VALUES (?, NOW(), ?)')->execute([$email, $site]);
    } finally {
        $db->query("SELECT RELEASE_LOCK('ts_demande_acces')");
    }
    $envoye = false;
    try { $envoye = ts_envoyer_provisoire($email, $mdp, $site, true); } catch (Throwable $e) { /* annulé ci-dessous */ }
    if (!$envoye) {
        $db->prepare('UPDATE comptes_ts SET hash_oubli = NULL, oubli_le = NULL WHERE email = ?')->execute([$email]);
        $db->prepare('DELETE FROM demandes_ts WHERE email = ?')->execute([$email]);
        return ['ok' => false, 'error' => 'Envoi du mail impossible : réessayez dans un instant.'];
    }
    api_journal($db, 'tsOubli', '', $email, 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

function ts_envoyer_provisoire(string $email, string $mdp, string $site, bool $oubli = false): bool
{
    $bac = (api_config()['bac_a_sable'] ?? false) === true;
    $url = ($bac ? TS_URL_PAGE_BAC : TS_URL_PAGE)[$site] ?? ($bac ? TS_URL_PAGE_BAC : TS_URL_PAGE)['nextstep'];
    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $sujet = $oubli ? 'Nouveau mot de passe provisoire — ateliers numériques' : 'Votre accès à la page des ateliers numériques';
    $fin = $oubli
        ? "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable. Ce mot de passe provisoire ne vaut que " . TS_OUBLI_HEURES . ' heures.'
        : "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : sans changement de mot de passe, ce compte sera supprimé au bout de " . TS_JAMAIS_CONNECTE_JOURS . ' jours.';
    $objet = $oubli ? 'un nouveau mot de passe pour la page' : 'un accès à la page';
    $texte = "Bonjour,\n\nVous avez demandé $objet de consultation des ateliers numériques.\n\n"
           . "Adresse : $url\nIdentifiant : $email\nMot de passe provisoire : $mdp\n\n"
           . "À la connexion, la page vous demandera de choisir votre propre mot de passe.\n\n"
           . $fin . "\n";
    $html = '<div style="font-family:sans-serif;max-width:640px"><p>Bonjour,</p>'
          . '<p>Vous avez demandé ' . $h($objet) . ' de consultation des ateliers numériques.</p>'
          . '<p>Adresse : <a href="' . $h($url) . '">' . $h($url) . '</a><br>Identifiant : <strong>' . $h($email) . '</strong><br>'
          . 'Mot de passe provisoire : <strong style="font-family:Consolas,monospace;font-size:16px">' . $h($mdp) . '</strong></p>'
          . '<p>À la connexion, la page vous demandera de choisir votre propre mot de passe.</p>'
          . '<p style="color:#4a5568;font-size:13px">' . $h($fin) . '</p></div>';
    return mail_envoyer($email, $sujet, $texte, $html);
}

// Connexion. Identifiant avec « @ » : compte TS ; sans : compte
// admin/superviseur à accès Admin actif (même compteur d'échecs que sa
// connexion habituelle). Un seul message d'échec pour tous les cas.
function action_ts_connexion(PDO $db, array $p): array
{
    ts_schema($db);
    $saisi = trim((string) ($p['identifiant'] ?? ''));
    $mdp = trim((string) ($p['password'] ?? ''));
    if ($saisi === '' || $mdp === '') return ['ok' => false, 'error' => 'Identifiant et mot de passe requis'];

    if (str_contains($saisi, '@')) {
        $id = ts_email($saisi);
        if ($id === null) return ['ok' => false, 'error' => TS_ERREUR_CONNEXION];
        $cleTentatives = TS_PREFIXE . $id;
        $s = $db->prepare('SELECT hash, actif, doit_changer, hash_oubli, (oubli_le IS NOT NULL AND oubli_le > NOW() - INTERVAL ' . TS_OUBLI_HEURES . ' HOUR) AS oubli_valide FROM comptes_ts WHERE email = ?');
        $s->execute([$id]);
        $c = $s->fetch(PDO::FETCH_ASSOC);
        $valide = $c && (int) $c['actif'] === 1;
        $doitChanger = $c && (int) $c['doit_changer'] === 1;
        $role = 'ts';
    } else {
        $id = $saisi;
        $cleTentatives = $id;
        $s = $db->prepare('SELECT hash, role, actif FROM comptes WHERE conseiller = ?');
        $s->execute([$id]);
        $c = $s->fetch(PDO::FETCH_ASSOC);
        $valide = $c && (int) $c['actif'] === 1 && in_array($c['role'], API_ROLES_ADMIN, true);
        $doitChanger = false;
        $role = $c ? (string) $c['role'] : '';
    }

    $bloque = api_blocage($db, $cleTentatives);
    if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
    $ok = $valide && $c['hash'] !== null && password_verify(hash('sha256', $mdp), $c['hash']);
    // « Mot de passe oublié » : le provisoire reçu par mail ouvre le compte (il remplace alors
    // l'ancien mot de passe, à changer aussitôt). L'ancien reste valable tant qu'il n'est pas employé.
    if (!$ok && $role === 'ts' && $valide && !empty($c['hash_oubli']) && (int) $c['oubli_valide'] === 1
        && password_verify(hash('sha256', $mdp), $c['hash_oubli'])) {
        $db->prepare('UPDATE comptes_ts SET hash = hash_oubli, doit_changer = 1, hash_oubli = NULL, oubli_le = NULL WHERE email = ?')->execute([$id]);
        $ok = true;
        $doitChanger = true;
    }
    if (!$ok) {
        // E-mail : compté même inconnu (sinon le blocage dirait qui a un
        // compte). Nom : compté s'il existe, comme checkPassword (les noms
        // sont déjà publics par getComptes).
        if ($role === 'ts' || $c) api_echec_mdp($db, $cleTentatives, (string) ($p['userAgent'] ?? ''), 'ts');
        return ['ok' => false, 'error' => TS_ERREUR_CONNEXION];
    }
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$cleTentatives]);
    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM sessions WHERE expire < NOW()');
    $db->prepare('INSERT INTO sessions (jeton_hash, conseiller, role, expire) VALUES (?, ?, ?, ?)')
       ->execute([hash('sha256', $jeton), TS_PREFIXE . $id, 'ts', date('Y-m-d H:i:s', time() + API_JETON_DUREE_S)]);
    api_journal($db, 'loginTs', $id, '', $role === 'ts' ? 'ts' : $role, 1, 0, (string) ($p['userAgent'] ?? ''), 'ts');
    $r = ['ok' => true, 'token' => $jeton];
    if ($doitChanger) $r['doit_changer'] = true;
    return $r;
}

// ── Session TS ────────────────────────────────────────────────────────────

// Identifiant derrière une session 'ts' (e-mail ou nom d'admin), si le
// compte est toujours ouvert : relu à chaque appel, une désactivation
// prend effet aussitôt. null sinon.
function ts_identite(PDO $db, array $session): ?array
{
    if (($session['role'] ?? '') !== 'ts' || !str_starts_with((string) $session['conseiller'], TS_PREFIXE)) return null;
    $id = substr((string) $session['conseiller'], strlen(TS_PREFIXE));
    if (str_contains($id, '@')) {
        $s = $db->prepare('SELECT doit_changer FROM comptes_ts WHERE email = ? AND actif = 1');
        $s->execute([$id]);
        $d = $s->fetchColumn();
        return $d === false ? null : ['id' => $id, 'ts' => true, 'doit_changer' => (int) $d === 1];
    }
    $s = $db->prepare('SELECT role FROM comptes WHERE conseiller = ? AND actif = 1');
    $s->execute([$id]);
    $role = $s->fetchColumn();
    return in_array($role, API_ROLES_ADMIN, true) ? ['id' => $id, 'ts' => false, 'doit_changer' => false] : null;
}

function action_ts_ateliers(PDO $db, array $session): array
{
    ts_schema($db);
    $qui = ts_identite($db, $session);
    if ($qui === null) return ['ok' => false, 'error' => 'Non autorisé', 'auth' => true];
    if ($qui['doit_changer']) return ['ok' => false, 'error' => 'Mot de passe provisoire à changer', 'doit_changer' => true];
    $cols = implode(', ', array_map(fn($c) => "`$c`", TS_CHAMPS));
    // Jour de Paris calculé par PHP, jamais CURDATE() : le fuseau de MySQL peut être
    // UTC (amendement 3 de Codex, règle « dates du jour » du CLAUDE.md).
    $q = $db->prepare("SELECT $cols FROM ateliers WHERE statut = 'Planifié' AND date >= ? ORDER BY date, horaire");
    $q->execute([date('Y-m-d')]);
    $l = $q->fetchAll(PDO::FETCH_ASSOC);
    // Un interrupteur par type de public (Admin → Visibilité — Page TS,
    // décision de l'utilisateur du 04/11/2026) : clé « ts_public:<type> » de la
    // config « visibility ». Éteint = les ateliers de ce public ne partent pas
    // vers la page. Absent = visible (un type ajouté plus tard n'est pas caché
    // par surprise). Clés à plat : visibiliteEffective (utils.js) réduit
    // chaque valeur à un booléen, un objet imbriqué serait écrasé.
    $vis = api_json(api_config_base($db)['visibility'] ?? '', []);
    $l = array_values(array_filter($l, fn($a) => !ts_public_cache($vis, (string) $a['public'])));
    return ['ok' => true, 'ateliers' => $l];
}

// Type de public éteint pour la page TS ? Même lecture que visibiliteEffective
// (utils.js) : false, 0, '' ou 'false' = éteint ; absent = allumé.
function ts_public_cache(mixed $vis, string $public): bool
{
    $cle = 'ts_public:' . trim($public);
    if (!is_array($vis) || trim($public) === '' || !array_key_exists($cle, $vis)) return false;
    $v = $vis[$cle];
    return $v === false || $v === 0 || $v === '' || $v === null || strtolower((string) $v) === 'false';
}

// Change le mot de passe du compte TS de la session. Le mot de passe actuel
// est exigé, sauf pour quitter un provisoire (comme selfSetPassword).
function action_ts_changer_mdp(PDO $db, array $p, array $session): array
{
    ts_schema($db);
    $qui = ts_identite($db, $session);
    if ($qui === null) return ['ok' => false, 'error' => 'Non autorisé', 'auth' => true];
    if (!$qui['ts']) return ['ok' => false, 'error' => 'Changez votre mot de passe depuis l\'Admin'];
    $id = $qui['id'];
    if (!$qui['doit_changer']) {
        $bloque = api_blocage($db, TS_PREFIXE . $id);
        if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
        $s = $db->prepare('SELECT hash FROM comptes_ts WHERE email = ?');
        $s->execute([$id]);
        $hash = $s->fetchColumn();
        if (!$hash || !password_verify(hash('sha256', trim((string) ($p['currentPwd'] ?? ''))), $hash)) {
            api_echec_mdp($db, TS_PREFIXE . $id, '', 'ts');
            return ['ok' => false, 'error' => 'Mot de passe actuel incorrect'];
        }
    }
    $mdp = trim((string) ($p['password'] ?? ''));
    if (!(strlen($mdp) >= 12 && preg_match('/[A-Z]/', $mdp) && preg_match('/[a-z]/', $mdp) && preg_match('/[0-9]/', $mdp) && preg_match('/[^A-Za-z0-9]/', $mdp))) {
        return ['ok' => false, 'error' => API_MDP_POLITIQUE];
    }
    $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 0, hash_oubli = NULL, oubli_le = NULL WHERE email = ?')->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $id]);
    ts_fermer_sessions($db, $id, $session['jeton_hash'] ?? null);
    api_journal($db, 'tsChangerMdp', $id, '', 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

// ── Admin ─────────────────────────────────────────────────────────────────

function action_ts_liste(PDO $db): array
{
    ts_schema($db);
    $db->exec('DELETE FROM demandes_ts WHERE demande_le < NOW() - INTERVAL ' . TS_DEMANDES_JOURS . ' DAY');
    return [
        'ok' => true,
        'demandes' => $db->query('SELECT email, demande_le, site FROM demandes_ts ORDER BY demande_le')->fetchAll(PDO::FETCH_ASSOC),
        'comptes' => array_map(fn($c) => ['email' => $c['email'], 'actif' => (int) $c['actif'] === 1, 'doit_changer' => (int) $c['doit_changer'] === 1, 'cree_le' => $c['cree_le']],
            $db->query('SELECT email, actif, doit_changer, cree_le FROM comptes_ts ORDER BY email')->fetchAll(PDO::FETCH_ASSOC)),
        'demandesMax' => TS_DEMANDES_MAX,
    ];
}

// Crée le compte (depuis une demande ou une adresse saisie) avec un mot de
// passe provisoire, rendu une seule fois à l'Admin qui le transmet.
function action_ts_creer_compte(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    if (!ts_email_autorise($email)) return ['ok' => false, 'error' => 'Seules les adresses ' . TS_DOMAINE . ' sont acceptées'];
    $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ?');
    $s->execute([$email]);
    if ($s->fetchColumn()) return ['ok' => false, 'error' => 'Ce compte existe déjà'];
    $mdp = ts_mdp_provisoire();
    $db->prepare('INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES (?, ?, 1, 1, NOW())')
       ->execute([$email, password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT)]);
    $db->prepare('DELETE FROM demandes_ts WHERE email = ?')->execute([$email]);
    return ['ok' => true, 'email' => $email, 'newPassword' => $mdp];
}

function action_ts_reinit_mdp(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $mdp = ts_mdp_provisoire();
    $u = $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 1, hash_oubli = NULL, oubli_le = NULL WHERE email = ?');
    $u->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $email]);
    if ($u->rowCount() === 0) return ['ok' => false, 'error' => 'Compte introuvable'];
    ts_fermer_sessions($db, $email);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([TS_PREFIXE . $email]);
    return ['ok' => true, 'newPassword' => $mdp];
}

function action_ts_activer(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $actif = in_array((string) ($p['actif'] ?? ''), ['1', 'true'], true) ? 1 : 0;
    $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ?');
    $s->execute([$email]);
    if (!$s->fetchColumn()) return ['ok' => false, 'error' => 'Compte introuvable'];
    $db->prepare('UPDATE comptes_ts SET actif = ? WHERE email = ?')->execute([$actif, $email]);
    if (!$actif) ts_fermer_sessions($db, $email);
    return ['ok' => true];
}

// Supprime un compte, ou refuse une demande (même action : l'adresse
// disparaît des deux tables).
function action_ts_supprimer(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $a = $db->prepare('DELETE FROM comptes_ts WHERE email = ?');
    $a->execute([$email]);
    $b = $db->prepare('DELETE FROM demandes_ts WHERE email = ?');
    $b->execute([$email]);
    if ($a->rowCount() + $b->rowCount() === 0) return ['ok' => false, 'error' => 'Introuvable'];
    ts_fermer_sessions($db, $email);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([TS_PREFIXE . $email]);
    return ['ok' => true];
}

// Connexions à la page (AG-043) : lecture seule du journal existant, sur 30
// jours. Succès = `loginTs`, échecs = `loginFail` de source `ts`, créations par
// demande = `tsDemande`. Les adresses y restent 12 mois, comme aujourd'hui.
function action_ts_stats(PDO $db): array
{
    ts_schema($db);
    $depuis = date('Y-m-d', strtotime('-29 days')) . ' 00:00:00';
    $comptes = [];
    $s = $db->prepare("SELECT conseiller, role, COUNT(*) nb, MAX(horodatage) derniere FROM journal WHERE action = 'loginTs' AND source = 'ts' AND succes = 1 AND horodatage >= ? GROUP BY conseiller, role");
    $s->execute([$depuis]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $comptes[$r['conseiller']] = ['compte' => $r['conseiller'], 'type' => $r['role'] === 'ts' ? 'TS' : 'Admin', 'connexions' => (int) $r['nb'], 'derniere' => $r['derniere'], 'echecs' => 0];
    }
    $s = $db->prepare("SELECT conseiller, COUNT(*) nb FROM journal WHERE action = 'loginFail' AND source = 'ts' AND horodatage >= ? GROUP BY conseiller");
    $s->execute([$depuis]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = str_starts_with($r['conseiller'], TS_PREFIXE) ? substr($r['conseiller'], strlen(TS_PREFIXE)) : $r['conseiller'];
        if (!isset($comptes[$id])) $comptes[$id] = ['compte' => $id, 'type' => str_contains($id, '@') ? 'TS' : 'Admin', 'connexions' => 0, 'derniere' => null, 'echecs' => 0];
        $comptes[$id]['echecs'] += (int) $r['nb'];
    }
    $jours = [];
    $s = $db->prepare("SELECT DATE(horodatage) jour, action, COUNT(*) nb FROM journal WHERE source = 'ts' AND horodatage >= ? AND ((action = 'loginTs' AND succes = 1) OR action IN ('loginFail', 'tsDemande')) GROUP BY DATE(horodatage), action");
    $s->execute([$depuis]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $j = $r['jour'];
        $jours[$j] ??= ['jour' => $j, 'connexions' => 0, 'echecs' => 0, 'creations' => 0];
        $cle = ['loginTs' => 'connexions', 'loginFail' => 'echecs', 'tsDemande' => 'creations'][$r['action']];
        $jours[$j][$cle] = (int) $r['nb'];
    }
    krsort($jours);
    $liste = array_values($comptes);
    usort($liste, fn($a, $b) => $b['connexions'] <=> $a['connexions'] ?: strcmp($a['compte'], $b['compte']));
    return ['ok' => true, 'depuis' => substr($depuis, 0, 10), 'comptes' => $liste, 'jours' => array_values($jours),
            'total' => ['connexions' => array_sum(array_column($liste, 'connexions')), 'echecs' => array_sum(array_column($liste, 'echecs'))]];
}
