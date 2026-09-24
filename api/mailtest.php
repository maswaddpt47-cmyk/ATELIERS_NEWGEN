<?php
// Page de test d'envoi de mail (AG-013, 24/09/2026).
//
// Vérifie que les mails partis d'Alwaysdata arrivent (adresse pro
// @lotetgaronne.fr, Gmail…) avant d'en faire dépendre le « mot de passe
// oublié ». Protégée par la clé d'import. L'adresse testée n'est ni
// enregistrée ni journalisée.

require_once __DIR__ . '/lib/mail.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$cle = trim((string) (api_config()['cle_import'] ?? ''));
$message = null;
if (strlen($cle) < 20) {
    $message = ['erreur', "Page désactivée : aucune clé configurée sur le serveur."];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = trim((string) ($_POST['adresse'] ?? ''));
    if (!hash_equals($cle, trim((string) ($_POST['cle'] ?? '')))) {
        sleep(2);
        $message = ['erreur', 'Clé incorrecte.'];
    } elseif (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
        $message = ['erreur', 'Adresse invalide.'];
    } else {
        $heure = date('d/m/Y H:i:s');
        $ok = mail_envoyer($a, "Test d'envoi — Ateliers numériques ($heure)",
            "Ceci est un mail de test envoyé depuis Alwaysdata le $heure.\nS'il est arrivé dans la boîte de réception (et pas dans les indésirables), l'envoi fonctionne.",
            '<p>Ceci est un mail de test envoyé depuis Alwaysdata le <strong>' . h($heure) . '</strong>.</p><p>S\'il est arrivé dans la boîte de réception (et pas dans les indésirables), l\'envoi fonctionne.</p>');
        $message = $ok
            ? ['ok', "Le serveur a accepté le mail ($heure, expéditeur " . mail_expediteur() . "). Vérifie la boîte de réception ET les indésirables dans les 5 minutes."]
            : ['erreur', "Le serveur a refusé l'envoi (mail() a échoué)."];
    }
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Test d'envoi de mail</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;color:#1f2937}
label{display:block;margin:.8rem 0 .3rem;font-weight:600}
input{width:100%;box-sizing:border-box;padding:.4rem}
button{margin-top:1rem;padding:.5rem 1rem;font-size:1rem;cursor:pointer}
.ok{background:#dcfce7;border-left:4px solid #16a34a;padding:.6rem .8rem}
.erreur{background:#fee2e2;border-left:4px solid #dc2626;padding:.6rem .8rem}
</style></head><body>
<h1>Test d'envoi de mail</h1>
<p>Envoie un mail de test depuis le serveur, pour vérifier qu'il arrive (adresse pro, Gmail…).</p>
<?php if ($message): ?><p class="<?= h($message[0]) ?>"><?= h($message[1]) ?></p><?php endif; ?>
<form method="post">
  <label for="cle">Clé d'import</label><input type="password" id="cle" name="cle" required autocomplete="off">
  <label for="adresse">Adresse de destination</label><input type="email" id="adresse" name="adresse" required>
  <button type="submit">Envoyer le mail de test</button>
</form>
</body></html>
