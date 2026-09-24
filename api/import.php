<?php
// Page d'import du classeur NextStep dans la base (AG-010).
//
// L'utilisateur y dépose l'export .xlsx de Google Sheets
// (Fichier → Télécharger → Microsoft Excel). Deux boutons :
//   « Analyser » : lit le fichier et affiche le compte rendu, sans rien écrire ;
//   « Importer »  : même analyse, puis remplace TOUT le contenu de la base,
//                   seulement s'il n'y a aucune erreur et si la case de
//                   confirmation est cochée.
// Protégée par la clé d'import (Secret GitHub ALWAYSDATA_CLE_IMPORT, copiée
// dans ~/config-api.php au déploiement). Le fichier reçu n'est jamais
// conservé : PHP efface le fichier temporaire à la fin de la requête.
// Après la bascule, meta.import_verrouille = '1' interdit tout nouvel import.

// Une erreur fatale de PHP (mémoire, extension absente…) donnait une page
// blanche, sans rien à rapporter (constaté le 24/09/2026 sur Alwaysdata) :
// on affiche au moins sa nature. Pas de donnée du fichier dans ce message.
ini_set('display_errors', '0');
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo '<p style="background:#fee2e2;padding:.6rem">Erreur PHP : '
            . htmlspecialchars($e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')', ENT_QUOTES, 'UTF-8')
            . ' — PHP ' . PHP_VERSION . '</p>';
    }
});
@ini_set('memory_limit', '256M');

require_once __DIR__ . '/lib/base.php';
require_once __DIR__ . '/lib/import.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Espaces et retour à la ligne de bord ignorés, des deux côtés : un secret
// collé avec un saut de ligne final ne doit pas rendre la clé inutilisable.
$cle = trim((string) (api_config()['cle_import'] ?? ''));
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

$message = null;   // [classe, texte]
$analyse = null;

if (strlen($cle) < 20) {
    $message = ['erreur', "Import désactivé : aucune clé d'import configurée sur le serveur (Secret ALWAYSDATA_CLE_IMPORT, 20 caractères au moins)."];
} elseif (!$https) {
    $message = ['erreur', 'Import refusé hors HTTPS : ouvrez cette page en https://.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fichier = $_FILES['fichier'] ?? null;
    if (!hash_equals($cle, trim((string) ($_POST['cle'] ?? '')))) {
        sleep(2); // ralentit les essais au hasard
        $message = ['erreur', "Clé d'import incorrecte."];
    } elseif (!$fichier || $fichier['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'])) {
        $message = ['erreur', 'Aucun fichier reçu (ou fichier trop gros pour le serveur).'];
    } else {
        try {
            $analyse = import_analyser(xlsx_lire($fichier['tmp_name']));
            $empreinte = hash_file('sha256', $fichier['tmp_name']);
            if (($_POST['mode'] ?? '') === 'importer') {
                if ($analyse['erreurs']) {
                    $message = ['erreur', "Rien n'a été importé : corrigez d'abord les erreurs ci-dessous dans le classeur, puis refaites l'export."];
                } elseif (($_POST['confirme'] ?? '') !== 'oui') {
                    $message = ['erreur', "Rien n'a été importé : cochez la case de confirmation."];
                } else {
                    import_charger(api_base(), $analyse, $empreinte);
                    $message = ['ok', 'Import terminé : la base contient maintenant les données de ce fichier.'];
                }
            } else {
                $message = $analyse['erreurs']
                    ? ['erreur', 'Analyse terminée : ' . count($analyse['erreurs']) . " erreur(s), l'import serait refusé."]
                    : ['ok', "Analyse terminée : aucune erreur, l'import peut être lancé. Rien n'a été écrit."];
            }
        } catch (Throwable $e) {
            $message = ['erreur', 'Échec : ' . $e->getMessage()];
        } finally {
            @unlink($fichier['tmp_name']);
        }
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Import du classeur</title>
<style>
  body { font: 16px/1.5 system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; color: #1f2937; }
  h1 { font-size: 1.4rem; }
  label { display: block; margin: .8rem 0 .3rem; font-weight: 600; }
  input[type=password], input[type=file] { width: 100%; box-sizing: border-box; padding: .4rem; }
  button { margin: 1rem .5rem 0 0; padding: .5rem 1rem; font-size: 1rem; cursor: pointer; }
  .ok { background: #dcfce7; border-left: 4px solid #16a34a; padding: .6rem .8rem; }
  .erreur { background: #fee2e2; border-left: 4px solid #dc2626; padding: .6rem .8rem; }
  table { border-collapse: collapse; margin: 1rem 0; }
  td, th { border: 1px solid #d1d5db; padding: .3rem .7rem; text-align: left; }
  li { margin: .2rem 0; }
  .confirme { font-weight: normal; }
</style>
</head>
<body>
<h1>Import du classeur NextStep</h1>
<p>Déposez l'export du classeur Google Sheets (<em>Fichier → Télécharger → Microsoft Excel</em>).
Commencez par <strong>Analyser</strong> : rien n'est écrit.
<strong>Importer</strong> remplace tout le contenu de la base.</p>

<?php if ($message): ?>
<p class="<?= h($message[0]) ?>"><?= h($message[1]) ?></p>
<?php endif; ?>

<?php if ($analyse): ?>
<table>
  <tr><th>Table</th><th>Lignes lues</th></tr>
  <?php foreach ($analyse['decomptes'] as $t => $n): ?>
  <tr><td><?= h($t) ?></td><td><?= (int) $n ?></td></tr>
  <?php endforeach; ?>
</table>
<?php if ($analyse['erreurs']): ?>
<h2>Erreurs (<?= count($analyse['erreurs']) ?>) — l'import est refusé</h2>
<ul><?php foreach (array_slice($analyse['erreurs'], 0, 200) as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($analyse['avertissements']): ?>
<h2>Avertissements (<?= count($analyse['avertissements']) ?>)</h2>
<ul><?php foreach ($analyse['avertissements'] as $a): ?><li><?= h($a) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label for="cle">Clé d'import</label>
  <input type="password" id="cle" name="cle" required autocomplete="off">
  <label for="fichier">Fichier .xlsx</label>
  <input type="file" id="fichier" name="fichier" accept=".xlsx" required>
  <label class="confirme"><input type="checkbox" name="confirme" value="oui">
    Je confirme : l'import remplace tous les ateliers, comptes, réglages et le journal de la base.</label>
  <button type="submit" name="mode" value="analyser">Analyser</button>
  <button type="submit" name="mode" value="importer">Importer</button>
</form>
</body>
</html>
