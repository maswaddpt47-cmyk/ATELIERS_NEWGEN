<?php
// Envoi de mails depuis Alwaysdata (fonction mail() de PHP).
//
// Sert au « mot de passe oublié » (AG-013) et, plus tard, aux rappels
// d'ateliers en retard (remplaçant de MailApp côté GAS).
//
// ⚠️ Hypothèse non vérifiée (24/09/2026) : mail() fonctionne chez Alwaysdata
// sans réglage, et un expéditeur en @<compte>.alwaysdata.net passe les filtres
// anti-spam (Gmail, passerelle du Département). La page api/mailtest.php sert
// à le vérifier avant toute fonction qui en dépend.

require_once __DIR__ . '/base.php';

// Expéditeur : réglable dans ~/config-api.php (clé mail_expediteur), sinon
// une adresse du domaine du compte Alwaysdata.
function mail_expediteur(): string
{
    $c = api_config();
    if (!empty($c['mail_expediteur'])) return (string) $c['mail_expediteur'];
    $hote = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return 'noreply@' . preg_replace('/[^a-z0-9.-]/i', '', $hote);
}

// Envoie un mail HTML (avec version texte). Renvoie true si le serveur l'a
// accepté — ce qui ne garantit PAS qu'il arrive (filtres anti-spam).
function mail_envoyer(string $a, string $sujet, string $texte, string $html): bool
{
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) return false;
    // Pas de saut de ligne dans un en-tête : empêche l'injection d'en-têtes.
    $sujet = str_replace(["\r", "\n"], ' ', $sujet);
    $de = mail_expediteur();
    $frontiere = 'b' . bin2hex(random_bytes(12));
    $entetes = implode("\r\n", [
        'From: Ateliers numériques CD47 <' . $de . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
    ]);
    $corps = "--$frontiere\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$texte\r\n"
           . "--$frontiere\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n"
           . "--$frontiere--\r\n";
    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    // -f : adresse de retour (bounces) alignée sur l'expéditeur.
    return mail($a, $sujetEncode, $corps, $entetes, '-f' . $de);
}
