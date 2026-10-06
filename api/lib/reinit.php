<?php
// « Mot de passe oublié » en libre-service (AG-013, feu vert de
// l'utilisateur le 24/09/2026).
//
//   demanderReinit (public)   : envoie un lien par mail à l'adresse du
//                               conseiller (config « emails ») ;
//   reinitMotDePasse (public) : consomme le lien et pose le nouveau mot
//                               de passe.
//
// Précautions :
//   - réponse identique que le compte ait une adresse ou non ;
//   - jeton aléatoire de 32 octets, seule son empreinte est stockée,
//     valable 30 min, usage unique ;
//   - 3 demandes par compte et par heure au plus ;
//   - le lien ne peut viser que les pages publiées sur GitHub Pages
//     (sinon un tiers ferait envoyer par nous un lien vers son propre site,
//     qui recevrait le jeton) ;
//   - réinitialiser coupe toutes les connexions en cours du compte.

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/import.php';   // import_requetes_schema
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/ecriture.php'; // api_changer_mdp

const REINIT_DUREE_MIN = 30;
const REINIT_MAX_PAR_HEURE = 3;
const REINIT_ORIGINE = 'https://maswaddpt47-cmyk.github.io/';
const REINIT_REPONSE = "Si une adresse mail est enregistrée pour ce compte, un lien vient d'y être envoyé. Il est valable 30 minutes.";

// La table est apparue après l'import d'essai du 24/09/2026 : on s'assure
// qu'elle existe (CREATE TABLE IF NOT EXISTS, sans effet si elle est là).
function reinit_schema(PDO $db): void
{
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'reinitialisations')) $db->exec($sql);
    }
}

function action_demander_reinit(PDO $db, array $p): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    $retour = trim((string) ($p['retour'] ?? ''));
    if ($nom === '') return ['ok' => false, 'error' => 'Choisissez votre nom.'];
    if (!reinit_retour_valide($retour)) return ['ok' => false, 'error' => 'Adresse de retour refusée.'];
    // Réponse identique quoi qu'il arrive : rien ne se devine (RGPD-10).
    reinit_envoyer($db, $nom, $retour, (string) ($p['userAgent'] ?? ''), false);
    return ['ok' => true, 'message' => REINIT_REPONSE];
}

// Admin (06/10/2026) : envoie le lien à un collègue, à la place d'un mot de
// passe provisoire que l'administrateur devait lui transmettre — le collègue
// choisit le sien, personne d'autre ne le connaît. Réponse explicite : c'est
// l'administrateur qui demande, il doit savoir si le mail est parti.
function action_envoyer_lien_reinit(PDO $db, array $p): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    $retour = trim((string) ($p['retour'] ?? ''));
    if ($nom === '') return ['ok' => false, 'error' => 'Choisissez un conseiller.'];
    if (!reinit_retour_valide($retour)) return ['ok' => false, 'error' => 'Adresse de retour refusée.'];
    return match (reinit_envoyer($db, $nom, $retour, '', true)) {
        'envoye' => ['ok' => true, 'message' => "Lien envoyé par mail à $nom, valable 30 minutes."],
        'sans_adresse' => ['ok' => false, 'error' => "Aucune adresse mail pour $nom (Listes → Conseillers) : donnez-lui un mot de passe provisoire."],
        'quota' => ['ok' => false, 'error' => 'Déjà ' . REINIT_MAX_PAR_HEURE . " liens envoyés à $nom dans l'heure : réessayez plus tard."],
        default => ['ok' => false, 'error' => 'Compte introuvable.'],
    };
}

// Le lien ne peut viser que les pages publiées sur GitHub Pages (sinon un
// tiers ferait envoyer par nous un lien vers son propre site).
function reinit_retour_valide(string $retour): bool
{
    return str_starts_with($retour, REINIT_ORIGINE) && !str_contains($retour, '#');
}

// Envoie le lien si possible : 'envoye', 'inconnu', 'quota' ou 'sans_adresse'.
function reinit_envoyer(PDO $db, string $nom, string $retour, string $ua, bool $parAdmin): string
{
    reinit_schema($db);
    $s = $db->prepare('SELECT 1 FROM comptes WHERE conseiller = ?');
    $s->execute([$nom]);
    if (!$s->fetchColumn()) return 'inconnu';

    $n = $db->prepare('SELECT COUNT(*) FROM reinitialisations WHERE conseiller = ? AND cree > NOW() - INTERVAL 1 HOUR');
    $n->execute([$nom]);
    if ((int) $n->fetchColumn() >= REINIT_MAX_PAR_HEURE) return 'quota';

    $emails = api_json(api_config_base($db)['emails'] ?? '', []);
    $adresse = is_array($emails) ? trim((string) ($emails[$nom] ?? '')) : '';
    if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) return 'sans_adresse';

    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM reinitialisations WHERE expire < NOW() - INTERVAL 1 DAY');
    $db->prepare('INSERT INTO reinitialisations (jeton_hash, conseiller, cree, expire) VALUES (?, ?, NOW(), NOW() + INTERVAL ' . REINIT_DUREE_MIN . ' MINUTE)')
       ->execute([hash('sha256', $jeton), $nom]);

    // Après le # : le jeton n'est jamais envoyé à GitHub Pages, donc absent
    // de ses journaux (audit Codex du 06/10/2026). $retour n'a pas de #.
    $lien = $retour . '#reinit=' . $jeton;
    $h = fn($x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    $intro = $parAdmin
        ? "L'administrateur des Ateliers numériques vous envoie un lien pour choisir votre mot de passe."
        : 'Une réinitialisation de votre mot de passe a été demandée.';
    $fin = $parAdmin
        ? "Votre mot de passe actuel reste valable tant que vous n'avez pas utilisé ce lien."
        : "Si vous n'êtes pas à l'origine de cette demande, ignorez ce mail : votre mot de passe actuel reste valable.";
    mail_envoyer($adresse, 'Réinitialisation de votre mot de passe — Ateliers numériques',
        "Bonjour $nom,\n\n$intro\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 30 minutes, une seule fois) :\n$lien\n\n$fin",
        '<p>Bonjour ' . $h($nom) . ',</p><p>' . $h($intro) . '</p>'
        . '<p><a href="' . $h($lien) . '" style="display:inline-block;background:#1e3a8a;color:#ffffff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700"><span style="color:#ffffff">Choisir un nouveau mot de passe</span></a></p>'
        . '<p style="color:#718096;font-size:13px">Lien valable 30 minutes, utilisable une seule fois. ' . $h($fin) . '</p>');
    if (!$parAdmin) api_journal($db, 'reinitDemande', $nom, '', '', 1, 0, $ua, '');
    return 'envoye';
}

function action_reinit_mot_de_passe(PDO $db, array $p): array
{
    $jeton = trim((string) ($p['jeton'] ?? ''));
    $invalide = ['ok' => false, 'error' => 'Lien invalide ou expiré : refaites une demande de mot de passe oublié.'];
    if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return $invalide;
    reinit_schema($db);
    $s = $db->prepare('SELECT conseiller FROM reinitialisations WHERE jeton_hash = ? AND utilise = 0 AND expire > NOW()');
    $s->execute([hash('sha256', $jeton)]);
    $nom = $s->fetchColumn();
    if ($nom === false) return $invalide;

    // Politique de mot de passe vérifiée AVANT de consommer le lien : un mot
    // de passe refusé ne doit pas obliger à refaire la demande.
    $r = api_changer_mdp($db, (string) $nom, (string) ($p['password'] ?? ''));
    if (!$r['ok']) return $r;

    // Liens et connexions du compte : tombés dans api_changer_mdp.
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$nom]);
    api_journal($db, 'reinitMotDePasse', (string) $nom, '', '', 1, 0, (string) ($p['userAgent'] ?? ''), '');
    return ['ok' => true, 'conseiller' => $nom];
}
