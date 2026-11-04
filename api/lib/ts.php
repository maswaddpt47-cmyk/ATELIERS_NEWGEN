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
const TS_QUOTA_ENVOIS = 30;      // mails de création et d'oubli en 24 h, tous comptes confondus (AG-043)
const TS_QUOTA_CODES = 20;       // mails de code de connexion en 24 h, par compte (AG-043)
const TS_ENVOIS_JOURS = 7;       // le registre des envois n'a besoin que des dernières 24 h
const TS_JAMAIS_CONNECTE_JOURS = 30;   // provisoire jamais changé : compte supprimé (AG-043)
const TS_OUBLI_HEURES = 24;      // validité du provisoire envoyé par « mot de passe oublié »
const TS_OUBLI_ESPACE_MIN = 15;  // une seule demande d'oubli par compte et par 15 min
const TS_CODE_MINUTES = 10;      // validité du code de connexion envoyé par mail (2FA, AG-043)
const TS_CODE_ESSAIS = 5;        // codes faux avant d'effacer le défi
const TS_DOMAINE = '@lotetgaronne.fr'; // seules adresses acceptées (AG-043, 04/11/2026)
const TS_EMAIL_MAX = 97;         // 'ts:' + e-mail tient dans VARCHAR(100) (sessions, tentatives)
const TS_CHAMPS = ['date', 'horaire', 'statut', 'orienteur', 'commune', 'lieu', 'public', 'thematique'];
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
const TS_ERREUR_CODE = 'Code incorrect ou expiré. Vérifiez le code, ou recommencez la connexion.';

function ts_schema(PDO $db): void
{
    static $ok = false;
    if ($ok) return;
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'EXISTS comptes_ts') || str_contains($sql, 'EXISTS demandes_ts') || str_contains($sql, 'EXISTS envois_ts')) $db->exec($sql);
    }
    // Colonnes ajoutées après la création de la table (AG-043) : bases déjà en service.
    if (!$db->query("SHOW COLUMNS FROM comptes_ts LIKE 'hash_oubli'")->fetch()) {
        $db->exec('ALTER TABLE comptes_ts ADD COLUMN hash_oubli VARCHAR(255) NULL, ADD COLUMN oubli_le DATETIME NULL');
    }
    if (!$db->query("SHOW COLUMNS FROM comptes_ts LIKE 'code_hash'")->fetch()) {
        $db->exec('ALTER TABLE comptes_ts ADD COLUMN code_hash VARCHAR(64) NULL, ADD COLUMN defi_hash VARCHAR(64) NULL, ADD COLUMN code_expire DATETIME NULL, ADD COLUMN code_essais TINYINT NOT NULL DEFAULT 0');
    }
    if (!$db->query("SHOW COLUMNS FROM comptes_ts LIKE 'defi_mdp'")->fetch()) {
        $db->exec('ALTER TABLE comptes_ts ADD COLUMN defi_oubli TINYINT NOT NULL DEFAULT 0, ADD COLUMN defi_mdp VARCHAR(255) NULL, ADD COLUMN mdp_change_le DATETIME NULL');
        $db->exec('UPDATE comptes_ts SET mdp_change_le = NOW() WHERE mdp_change_le IS NULL');   // colonne neuve : tous les comptes sont préexistants
    }
    if (!$db->query("SHOW COLUMNS FROM comptes_ts LIKE 'oubli_promu'")->fetch()) {
        $db->exec('ALTER TABLE comptes_ts ADD COLUMN oubli_promu TINYINT NOT NULL DEFAULT 0');   // AG-046, amendement 1 de Codex
    }
    // Rattrapage exécuté UNE fois, indépendant de l'existence de la colonne (Codex, 3e relecture d'AG-043) :
    // une base déjà migrée par une version précédente garde des comptes à mdp_change_le NULL dont l'historique
    // est inconnu (utilisés puis réinitialisés, ou non). Le drapeau doit_changer ne prouve pas « jamais utilisé » :
    // ils sont préservés de la purge des premiers accès jamais terminés. Le marqueur évite de refaire ce
    // rattrapage ensuite, sinon les comptes créés après lui perdraient leur suivi exact.
    // Exécution UNIQUE même en concurrence (Codex, 4e relecture d'AG-043) : le marqueur est PRIS avant le
    // rattrapage, dans la même transaction. Un second appel simultané attend la fin de la transaction sur la clé
    // du marqueur, puis ne l'obtient pas et ne touche à rien : un compte créé entre-temps garde sa date NULL.
    // Un échec du rattrapage annule aussi la prise du marqueur.
    $deja = $db->prepare("SELECT 1 FROM config WHERE cle = 'ts_rattrapage_mdp_change'");
    $deja->execute();
    if (!$deja->fetchColumn()) {
        $propre = !$db->inTransaction();
        if ($propre) $db->beginTransaction();
        try {
            $prise = $db->prepare("INSERT IGNORE INTO config (cle, valeur) VALUES ('ts_rattrapage_mdp_change', ?)");
            $prise->execute([date('Y-m-d')]);
            if ($prise->rowCount() === 1) $db->exec('UPDATE comptes_ts SET mdp_change_le = NOW() WHERE mdp_change_le IS NULL');
            if ($propre) $db->commit();
        } catch (Throwable $e) {
            if ($propre && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    $ok = true;
}

// Section critique commune aux envois de mails (quota + registre) : acquise, puis relâchée par l'appelant.
function ts_verrou(PDO $db): bool
{
    return (int) $db->query("SELECT GET_LOCK('ts_demande_acces', 5)")->fetchColumn() === 1;
}
function ts_deverrouiller(PDO $db): void
{
    $db->query("SELECT RELEASE_LOCK('ts_demande_acces')");
}
// Registre des envois : une ligne par envoi, identité propre, jamais effacée par une
// opération sur le compte. Nombre d'envois des 24 h pour des types donnés (et une adresse).
function ts_envois_24h(PDO $db, array $types, ?string $email = null): int
{
    $marques = implode(',', array_fill(0, count($types), '?'));
    $s = $db->prepare("SELECT COUNT(*) FROM envois_ts WHERE envoye_le > NOW() - INTERVAL 1 DAY AND type IN ($marques)" . ($email !== null ? ' AND email = ?' : ''));
    $s->execute($email !== null ? array_merge($types, [$email]) : $types);
    return (int) $s->fetchColumn();
}
function ts_envoi_noter(PDO $db, string $type, string $email): int
{
    $db->prepare('INSERT INTO envois_ts (envoye_le, type, email) VALUES (NOW(), ?, ?)')->execute([$type, $email]);
    return (int) $db->lastInsertId();
}
function ts_envoi_annuler(PDO $db, int $id): void
{
    $db->prepare('DELETE FROM envois_ts WHERE id = ?')->execute([$id]);
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
// boîte). Au plus TS_QUOTA_ENVOIS mails de création ou d'oubli par 24 h, comptés
// ENVOI par envoi dans `envois_ts`, contrôle et inscription sous verrou.
function action_ts_demande_acces(PDO $db, array $p): array
{
    ts_schema($db);
    // Champ piège : invisible pour un humain, rempli par les robots.
    if (trim((string) ($p['url'] ?? '')) !== '') return ['ok' => true];
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    if (!ts_email_autorise($email)) return ['ok' => false, 'error' => 'Seules les adresses ' . TS_DOMAINE . ' sont acceptées'];
    $db->exec('DELETE FROM envois_ts WHERE envoye_le < NOW() - INTERVAL ' . TS_ENVOIS_JOURS . ' DAY');
    // Premier accès jamais terminé (mot de passe provisoire jamais changé) depuis 30 jours : compte supprimé.
    // Un compte déjà utilisé puis réinitialisé (mdp_change_le renseigné) n'est jamais concerné.
    $db->exec('DELETE FROM comptes_ts WHERE doit_changer = 1 AND mdp_change_le IS NULL AND cree_le < NOW() - INTERVAL ' . TS_JAMAIS_CONNECTE_JOURS . ' DAY');

    if (!ts_verrou($db)) return ['ok' => false, 'error' => 'Service occupé : réessayez dans un instant.'];
    $mdp = null; $envoi = 0;
    try {
        // Plafond d'abord, pour toute adresse valide connue ou non : une file
        // pleine ne doit pas révéler qu'une adresse a déjà un compte.
        if (ts_envois_24h($db, ['creation', 'oubli']) >= TS_QUOTA_ENVOIS) {
            return ['ok' => false, 'error' => 'Trop de demandes aujourd\'hui : réessayez demain.'];
        }
        $s = $db->prepare('SELECT COUNT(*) FROM comptes_ts WHERE email = ?');
        $s->execute([$email]);
        if ((int) $s->fetchColumn() > 0) return ['ok' => true];
        $mdp = ts_mdp_provisoire();
        $i = $db->prepare('INSERT IGNORE INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES (?, ?, 1, 1, NOW())');
        $i->execute([$email, password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT)]);
        if ($i->rowCount() !== 1) return ['ok' => true];
        $envoi = ts_envoi_noter($db, 'creation', $email);
        $site = api_site_courant() ?: 'nextstep';
    } finally {
        ts_deverrouiller($db);
    }
    $envoye = false;
    try { $envoye = ts_envoyer_provisoire($email, $mdp, $site); } catch (Throwable $e) { /* annulé ci-dessous */ }
    if (!$envoye) {
        // Aucun compte dont personne ne connaît le mot de passe ; l'envoi manqué ne compte pas.
        $db->prepare('DELETE FROM comptes_ts WHERE email = ? AND mdp_change_le IS NULL')->execute([$email]);
        ts_envoi_annuler($db, $envoi);
        return ['ok' => false, 'error' => 'Envoi du mail impossible : réessayez dans un instant.'];
    }
    api_journal($db, 'tsDemande', '', $email, 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

// Mot de passe oublié (AG-043). Même réponse que l'adresse ait un compte ou non.
// Pour un compte actif, un nouveau provisoire part par mail : il est mis de côté
// (hash_oubli, 24 h) et ne remplace le mot de passe actuel que s'il est employé et
// que la connexion est complète (code compris) — un tiers qui connaît l'adresse ne
// peut donc pas bloquer l'agent. Même quota que les créations ; un compte au plus
// toutes les 15 min.
function action_ts_mdp_oublie(PDO $db, array $p): array
{
    ts_schema($db);
    if (trim((string) ($p['url'] ?? '')) !== '') return ['ok' => true];
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    if (!ts_email_autorise($email)) return ['ok' => false, 'error' => 'Seules les adresses ' . TS_DOMAINE . ' sont acceptées'];

    if (!ts_verrou($db)) return ['ok' => false, 'error' => 'Service occupé : réessayez dans un instant.'];
    $mdp = null; $envoi = 0; $empreinte = '';
    try {
        if (ts_envois_24h($db, ['creation', 'oubli']) >= TS_QUOTA_ENVOIS) {
            return ['ok' => false, 'error' => 'Trop de demandes aujourd\'hui : réessayez demain.'];
        }
        $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ? AND actif = 1 AND (oubli_le IS NULL OR oubli_le < NOW() - INTERVAL ' . TS_OUBLI_ESPACE_MIN . ' MINUTE)');
        $s->execute([$email]);
        if (!$s->fetchColumn()) return ['ok' => true];
        $mdp = ts_mdp_provisoire();
        $empreinte = password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT);
        $db->prepare('UPDATE comptes_ts SET hash_oubli = ?, oubli_le = NOW() WHERE email = ?')->execute([$empreinte, $email]);
        $envoi = ts_envoi_noter($db, 'oubli', $email);
        $site = api_site_courant() ?: 'nextstep';
    } finally {
        ts_deverrouiller($db);
    }
    $envoye = false;
    try { $envoye = ts_envoyer_provisoire($email, $mdp, $site, true); } catch (Throwable $e) { /* annulé ci-dessous */ }
    if (!$envoye) {
        // Annule cette tentative seulement (même empreinte), pas un oubli plus récent.
        $db->prepare('UPDATE comptes_ts SET hash_oubli = NULL, oubli_le = NULL WHERE email = ? AND hash_oubli = ?')->execute([$email, $empreinte]);
        ts_envoi_annuler($db, $envoi);
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
        $s = $db->prepare('SELECT hash, actif, doit_changer, (mdp_change_le IS NULL AND oubli_promu = 0 AND cree_le > NOW() - INTERVAL ' . TS_JAMAIS_CONNECTE_JOURS . ' DAY) AS premier_acces, hash_oubli, (oubli_le IS NOT NULL AND oubli_le > NOW() - INTERVAL ' . TS_OUBLI_HEURES . ' HOUR) AS oubli_valide FROM comptes_ts WHERE email = ?');
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
    $verifiee = $ok ? $c['hash'] : null;   // empreinte réellement vérifiée (2FA : revérifiée à la validation du code)
    $parOubli = false;
    // « Mot de passe oublié » : le provisoire reçu par mail est accepté en plus de l'ancien mot de
    // passe, qui reste valable tant que le provisoire n'est pas employé jusqu'au bout. Rien n'est
    // remplacé ici : la promotion n'a lieu qu'à la validation du code (action_ts_code).
    if (!$ok && $role === 'ts' && $valide && !empty($c['hash_oubli']) && (int) $c['oubli_valide'] === 1
        && password_verify(hash('sha256', $mdp), $c['hash_oubli'])) {
        $ok = true;
        $verifiee = $c['hash_oubli'];
        $parOubli = true;
    }
    if (!$ok) {
        // E-mail : compté même inconnu (sinon le blocage dirait qui a un
        // compte). Nom : compté s'il existe, comme checkPassword (les noms
        // sont déjà publics par getComptes).
        if ($role === 'ts' || $c) api_echec_mdp($db, $cleTentatives, (string) ($p['userAgent'] ?? ''), 'ts');
        return ['ok' => false, 'error' => TS_ERREUR_CONNEXION];
    }
    // Inscription allégée (AG-046, demande de l'utilisateur du 04/11/2026) : à la PREMIÈRE connexion
    // (provisoire jamais changé, premier accès jamais terminé), pas de code par mail — le provisoire et le
    // code arriveraient dans la même boîte. La session ouverte ne sert qu'à choisir son mot de passe.
    // « Mot de passe oublié » (même promu, `oubli_promu`), compte déjà utilisé puis réinitialisé, et compte de plus
    // de TS_JAMAIS_CONNECTE_JOURS jours (borne contrôlée ici, pas seulement par la purge) gardent le code (Codex, AG-046).
    if ($role === 'ts' && !$parOubli && $doitChanger && (int) $c['premier_acces'] === 1) {
        return ts_premiere_connexion($db, $id, (string) $verifiee, (string) ($p['userAgent'] ?? ''));
    }
    // Compte TS : le mot de passe ne suffit pas (2FA par code reçu par mail, AG-043).
    // Les échecs ne sont remis à zéro qu'à la fin de la connexion complète.
    if ($role === 'ts') return ts_envoyer_defi($db, $id, (string) ($p['userAgent'] ?? ''), (string) $verifiee, $parOubli);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$cleTentatives]);
    return ts_ouvrir_session($db, $id, $role, false, (string) ($p['userAgent'] ?? ''));
}

// Première connexion sans code (AG-046) : la ligne est verrouillée et revérifiée dans la transaction qui
// ouvre la session (empreinte vérifiée, compte actif, premier accès toujours pas terminé) : un reset, une
// désactivation ou un changement de mot de passe concurrent l'emporte.
function ts_premiere_connexion(PDO $db, string $id, string $verifiee, string $ua): array
{
    $db->beginTransaction();
    try {
        $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ? AND BINARY hash = BINARY ? AND actif = 1 AND doit_changer = 1 AND mdp_change_le IS NULL AND oubli_promu = 0'
            . ' AND cree_le > NOW() - INTERVAL ' . TS_JAMAIS_CONNECTE_JOURS . ' DAY FOR UPDATE');
        $s->execute([$id, $verifiee]);
        if (!$s->fetchColumn()) { $db->rollBack(); return ['ok' => false, 'error' => TS_ERREUR_CONNEXION]; }
        $jeton = ts_creer_session($db, $id);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([TS_PREFIXE . $id]);
    api_journal($db, 'loginTs', $id, '', 'ts', 1, 0, $ua, 'ts');
    return ['ok' => true, 'token' => $jeton, 'doit_changer' => true];
}

// Insère la session et rend son jeton (sans journal). Pour un compte TS, appelée DANS la transaction
// qui consomme le défi : un changement de mot de passe concurrent attend la fin de la transaction, puis
// ferme cette session au lieu de la laisser s'ouvrir après sa révocation.
function ts_creer_session(PDO $db, string $id): string
{
    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM sessions WHERE expire < NOW()');
    $db->prepare('INSERT INTO sessions (jeton_hash, conseiller, role, expire) VALUES (?, ?, ?, ?)')
       ->execute([hash('sha256', $jeton), TS_PREFIXE . $id, 'ts', date('Y-m-d H:i:s', time() + API_JETON_DUREE_S)]);
    return $jeton;
}

function ts_ouvrir_session(PDO $db, string $id, string $role, bool $doitChanger, string $ua): array
{
    $jeton = ts_creer_session($db, $id);
    api_journal($db, 'loginTs', $id, '', $role === 'ts' ? 'ts' : $role, 1, 0, $ua, 'ts');
    $r = ['ok' => true, 'token' => $jeton];
    if ($doitChanger) $r['doit_changer'] = true;
    return $r;
}

// 2FA (AG-043) : mot de passe accepté → code à 6 chiffres envoyé à l'adresse du compte,
// jeton de défi rendu au navigateur (empreintes seules en base). Un seul défi par compte ;
// un nouveau n'est pas accepté pendant la première minute, et au plus TS_QUOTA_CODES mails
// de code par compte et par 24 h (famille d'envois distincte des créations et des oublis).
// Le défi garde l'empreinte de mot de passe vérifiée : la validation du code la revérifie.
function ts_envoyer_defi(PDO $db, string $id, string $ua, string $verifiee, bool $parOubli): array
{
    if (!ts_verrou($db)) return ['ok' => false, 'error' => 'Service occupé : réessayez dans un instant.'];
    $code = sprintf('%06d', random_int(0, 999999));
    $defi = bin2hex(random_bytes(24));
    $envoi = 0;
    try {
        $s = $db->prepare('SELECT (code_expire IS NOT NULL AND code_expire > NOW() + INTERVAL ' . (TS_CODE_MINUTES - 1) . ' MINUTE) FROM comptes_ts WHERE email = ?');
        $s->execute([$id]);
        if ((int) $s->fetchColumn() === 1) return ['ok' => false, 'error' => 'Un code vient d\'être envoyé : attendez une minute avant de recommencer.'];
        if (ts_envois_24h($db, ['code'], $id) >= TS_QUOTA_CODES) return ['ok' => false, 'error' => 'Trop de codes demandés aujourd\'hui : réessayez demain.'];
        $db->prepare('UPDATE comptes_ts SET code_hash = ?, defi_hash = ?, code_expire = NOW() + INTERVAL ' . TS_CODE_MINUTES . ' MINUTE, code_essais = 0, defi_oubli = ?, defi_mdp = ? WHERE email = ?')
           ->execute([hash('sha256', $code . ':' . $id), hash('sha256', $defi), $parOubli ? 1 : 0, $verifiee, $id]);
        $envoi = ts_envoi_noter($db, 'code', $id);
    } finally {
        ts_deverrouiller($db);
    }
    $texte = "Bonjour,\n\nCode de connexion : $code\n\nIl est valable " . TS_CODE_MINUTES . " minutes. Si vous n'êtes pas à l'origine de cette connexion, ignorez ce message et changez votre mot de passe (« Mot de passe oublié » sur la page).\n";
    $html = '<div style="font-family:sans-serif;max-width:640px"><p>Bonjour,</p><p>Code de connexion : <strong style="font-family:Consolas,monospace;font-size:20px;letter-spacing:3px">' . $code . '</strong></p>'
          . '<p style="color:#4a5568;font-size:13px">Il est valable ' . TS_CODE_MINUTES . ' minutes. Si vous n\'êtes pas à l\'origine de cette connexion, ignorez ce message et changez votre mot de passe (« Mot de passe oublié » sur la page).</p></div>';
    $envoye = false;
    try { $envoye = mail_envoyer($id, 'Votre code de connexion — ateliers numériques', $texte, $html); } catch (Throwable $e) { /* annulé ci-dessous */ }
    if (!$envoye) {
        // Efface CE défi seulement, et n'en compte pas l'envoi.
        $db->prepare('UPDATE comptes_ts SET code_hash = NULL, defi_hash = NULL, code_expire = NULL, code_essais = 0, defi_oubli = 0, defi_mdp = NULL WHERE email = ? AND defi_hash = ?')->execute([$id, hash('sha256', $defi)]);
        ts_envoi_annuler($db, $envoi);
        return ['ok' => false, 'error' => 'Envoi du code impossible : réessayez dans un instant.'];
    }
    return ['ok' => true, 'etape2' => true, 'defi' => $defi];
}

// Seconde étape : défi + code → jeton de session. Un code faux compte dans le même
// compteur d'échecs que le mot de passe ; après TS_CODE_ESSAIS codes faux, le défi est effacé.
// Le défi est consommé UNE SEULE fois, de façon atomique : une seconde validation simultanée,
// un défi remplacé, un compte désactivé ou un mot de passe changé entre-temps sont refusés.
// Un provisoire d'oubli n'est promu (et les anciennes sessions fermées) qu'ici, après le code,
// sur l'empreinte effectivement vérifiée.
function action_ts_code(PDO $db, array $p): array
{
    ts_schema($db);
    $defi = trim((string) ($p['defi'] ?? ''));
    $code = preg_replace('/\D/', '', (string) ($p['code'] ?? ''));
    if ($defi === '' || strlen($code) !== 6) return ['ok' => false, 'error' => TS_ERREUR_CODE];
    $s = $db->prepare('SELECT email, actif, doit_changer, code_hash, code_essais, defi_oubli, defi_mdp, (code_expire IS NOT NULL AND code_expire > NOW()) AS valide FROM comptes_ts WHERE defi_hash = ?');
    $s->execute([hash('sha256', $defi)]);
    $c = $s->fetch(PDO::FETCH_ASSOC);
    if (!$c) return ['ok' => false, 'error' => TS_ERREUR_CODE];
    $id = $c['email'];
    $cle = TS_PREFIXE . $id;
    $bloque = api_blocage($db, $cle);
    if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
    $efface = 'UPDATE comptes_ts SET code_hash = NULL, defi_hash = NULL, code_expire = NULL, code_essais = 0, defi_oubli = 0, defi_mdp = NULL WHERE email = ? AND defi_hash = ?';
    $hDefi = hash('sha256', $defi);
    if ((int) $c['actif'] !== 1 || (int) $c['valide'] !== 1 || $c['code_hash'] === null) {
        $db->prepare($efface)->execute([$id, $hDefi]);
        return ['ok' => false, 'error' => TS_ERREUR_CODE];
    }
    if (!hash_equals($c['code_hash'], hash('sha256', $code . ':' . $id))) {
        // Compteur de ce défi incrémenté de façon atomique (cinq codes faux simultanés comptent pour cinq).
        $db->prepare('UPDATE comptes_ts SET code_essais = code_essais + 1 WHERE email = ? AND defi_hash = ?')->execute([$id, $hDefi]);
        $n = $db->prepare('SELECT code_essais FROM comptes_ts WHERE email = ? AND defi_hash = ?');
        $n->execute([$id, $hDefi]);
        $essais = $n->fetchColumn();
        if ($essais === false || (int) $essais >= TS_CODE_ESSAIS) $db->prepare($efface)->execute([$id, $hDefi]);
        api_echec_mdp($db, $cle, (string) ($p['userAgent'] ?? ''), 'ts');
        return ['ok' => false, 'error' => TS_ERREUR_CODE];
    }
    $oubli = (int) $c['defi_oubli'] === 1;
    $db->beginTransaction();
    try {
        // Consommation atomique : la ligne est verrouillée, le défi, le code, l'expiration, l'activité du
        // compte et l'empreinte vérifiée sont contrôlés dans la condition de la mise à jour elle-même.
        $u = $db->prepare('UPDATE comptes_ts SET code_hash = NULL, defi_hash = NULL, code_expire = NULL, code_essais = 0, defi_oubli = 0, defi_mdp = NULL'
            . ' WHERE email = ? AND defi_hash = ? AND code_hash = ? AND code_expire > NOW() AND actif = 1'
            . ' AND ((defi_oubli = 0 AND hash = defi_mdp) OR (defi_oubli = 1 AND hash_oubli = defi_mdp AND oubli_le > NOW() - INTERVAL ' . TS_OUBLI_HEURES . ' HOUR))');
        $u->execute([$id, $hDefi, $c['code_hash']]);
        if ($u->rowCount() !== 1) { $db->rollBack(); return ['ok' => false, 'error' => TS_ERREUR_CODE]; }
        if ($oubli) {
            $pr = $db->prepare('UPDATE comptes_ts SET hash = hash_oubli, doit_changer = 1, oubli_promu = 1, hash_oubli = NULL, oubli_le = NULL WHERE email = ? AND hash_oubli = ?');
            $pr->execute([$id, $c['defi_mdp']]);
            if ($pr->rowCount() !== 1) { $db->rollBack(); return ['ok' => false, 'error' => TS_ERREUR_CODE]; }
            ts_fermer_sessions($db, $id);   // avant d'ouvrir celle qui fera le changement forcé
        }
        $jeton = ts_creer_session($db, $id);   // dans la transaction : un reset concurrent la fermera
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$cle]);
    api_journal($db, 'loginTs', $id, '', 'ts', 1, 0, (string) ($p['userAgent'] ?? ''), 'ts');
    $r = ['ok' => true, 'token' => $jeton];
    if ($oubli || (int) $c['doit_changer'] === 1) $r['doit_changer'] = true;
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
    $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 0, mdp_change_le = NOW(), oubli_promu = 0, hash_oubli = NULL, oubli_le = NULL WHERE email = ?')->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $id]);
    ts_fermer_sessions($db, $id, $session['jeton_hash'] ?? null);
    api_journal($db, 'tsChangerMdp', $id, '', 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

// ── Admin ─────────────────────────────────────────────────────────────────

function action_ts_liste(PDO $db): array
{
    ts_schema($db);
    return [
        'ok' => true,
        'comptes' => array_map(fn($c) => ['email' => $c['email'], 'actif' => (int) $c['actif'] === 1, 'doit_changer' => (int) $c['doit_changer'] === 1, 'cree_le' => $c['cree_le']],
            $db->query('SELECT email, actif, doit_changer, cree_le FROM comptes_ts ORDER BY email')->fetchAll(PDO::FETCH_ASSOC)),
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
    return ['ok' => true, 'email' => $email, 'newPassword' => $mdp];
}

function action_ts_reinit_mdp(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $mdp = ts_mdp_provisoire();
    $u = $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 1, hash_oubli = NULL, oubli_le = NULL, code_hash = NULL, defi_hash = NULL, code_expire = NULL, defi_oubli = 0, defi_mdp = NULL WHERE email = ?');
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

// Supprime un compte (le registre des envois n'est pas touché : il sert au quota).
function action_ts_supprimer(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $a = $db->prepare('DELETE FROM comptes_ts WHERE email = ?');
    $a->execute([$email]);
    if ($a->rowCount() === 0) return ['ok' => false, 'error' => 'Introuvable'];
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
    // Une ligne par identité (même après un changement de rôle d'un compte Admin) ; type d'après
    // l'identité : une adresse est un compte TS, un nom est un compte Admin/superviseur.
    $typeDe = fn(string $id) => str_contains($id, '@') ? 'TS' : 'Admin';
    $comptes = [];
    $s = $db->prepare("SELECT conseiller, COUNT(*) nb, MAX(horodatage) derniere FROM journal WHERE action = 'loginTs' AND source = 'ts' AND succes = 1 AND horodatage >= ? GROUP BY conseiller");
    $s->execute([$depuis]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $comptes[$r['conseiller']] = ['compte' => $r['conseiller'], 'type' => $typeDe($r['conseiller']), 'connexions' => (int) $r['nb'], 'derniere' => $r['derniere'], 'echecs' => 0];
    }
    $s = $db->prepare("SELECT conseiller, COUNT(*) nb FROM journal WHERE action = 'loginFail' AND source = 'ts' AND horodatage >= ? GROUP BY conseiller");
    $s->execute([$depuis]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = str_starts_with($r['conseiller'], TS_PREFIXE) ? substr($r['conseiller'], strlen(TS_PREFIXE)) : $r['conseiller'];
        if (!isset($comptes[$id])) $comptes[$id] = ['compte' => $id, 'type' => $typeDe($id), 'connexions' => 0, 'derniere' => null, 'echecs' => 0];
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
