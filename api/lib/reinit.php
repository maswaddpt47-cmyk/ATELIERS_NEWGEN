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
    if (!str_starts_with($retour, REINIT_ORIGINE) || str_contains($retour, '#')) {
        return ['ok' => false, 'error' => 'Adresse de retour refusée.'];
    }
    reinit_schema($db);
    $reponse = ['ok' => true, 'message' => REINIT_REPONSE];

    $s = $db->prepare('SELECT 1 FROM comptes WHERE conseiller = ?');
    $s->execute([$nom]);
    if (!$s->fetchColumn()) return $reponse;

    $n = $db->prepare('SELECT COUNT(*) FROM reinitialisations WHERE conseiller = ? AND cree > NOW() - INTERVAL 1 HOUR');
    $n->execute([$nom]);
    if ((int) $n->fetchColumn() >= REINIT_MAX_PAR_HEURE) return $reponse;

    $emails = api_json(api_config_base($db)['emails'] ?? '', []);
    $adresse = is_array($emails) ? trim((string) ($emails[$nom] ?? '')) : '';
    if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) return $reponse;

    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM reinitialisations WHERE expire < NOW() - INTERVAL 1 DAY');
    $db->prepare('INSERT INTO reinitialisations (jeton_hash, conseiller, cree, expire) VALUES (?, ?, NOW(), NOW() + INTERVAL ' . REINIT_DUREE_MIN . ' MINUTE)')
       ->execute([hash('sha256', $jeton), $nom]);

    $lien = $retour . (str_contains($retour, '?') ? '&' : '?') . 'reinit=' . $jeton;
    $h = fn($x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    mail_envoyer($adresse, 'Réinitialisation de votre mot de passe — Ateliers numériques',
        "Bonjour $nom,\n\nUne réinitialisation de votre mot de passe a été demandée.\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 30 minutes, une seule fois) :\n$lien\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce mail : votre mot de passe actuel reste valable.",
        '<p>Bonjour ' . $h($nom) . ',</p><p>Une réinitialisation de votre mot de passe a été demandée.</p>'
        . '<p><a href="' . $h($lien) . '" style="display:inline-block;background:#1e3a8a;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700">Choisir un nouveau mot de passe</a></p>'
        . '<p style="color:#718096;font-size:13px">Lien valable 30 minutes, utilisable une seule fois. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce mail : votre mot de passe actuel reste valable.</p>');
    api_journal($db, 'reinitDemande', $nom, '', '', 1, 0, (string) ($p['userAgent'] ?? ''), '');
    return $reponse;
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

    // Tous les liens du compte tombent, et toutes ses connexions en cours.
    $db->prepare('UPDATE reinitialisations SET utilise = 1 WHERE conseiller = ?')->execute([$nom]);
    $db->prepare('DELETE FROM sessions WHERE conseiller = ?')->execute([$nom]);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$nom]);
    api_journal($db, 'reinitMotDePasse', (string) $nom, '', '', 1, 0, (string) ($p['userAgent'] ?? ''), '');
    return ['ok' => true, 'conseiller' => $nom];
}
