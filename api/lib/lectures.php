<?php
// Mémoire « déjà lu » côté serveur (AG-066, 21/11/2026).
//
// Avant : le dernier numéro de Nouveautés lu et la dernière réponse de ticket lue
// vivaient dans le localStorage de l'appareil ; vider les données du site les
// effaçait et toutes les annonces redevenaient « Nouveau ». Une ligne par
// (personne connectée, rubrique), écrite de façon atomique.
//   nouveautes_index, nouveautes_admin : plus grand numéro d'annonce lu (entier) ;
//   ateliers_index_AAAA, ateliers_admin_AAAA : plus grand numéro d'atelier (_n) signalé pour l'année AAAA (entier, ramené
//   au plus grand n des ateliers de cette année) ; livrés dans getAll en objet { "AAAA": n } par page (Codex, AG-066).
//   tickets : date « AAAA-MM-JJ HH:MM:SS » de la dernière réponse lue (vraie date, jamais au-delà
//   de la dernière réponse reçue par la personne).
// La valeur ne diminue JAMAIS : un appareil en retard ou un onglet ancien ne
// rallume pas des pastilles déjà éteintes. L'identité vient de la session, pas
// des paramètres. Ne change AUCUN calcul : nouveautesNonVues et le nombre de
// tickets restent ceux d'avant, seule la source de la valeur « lue » change.

require_once __DIR__ . '/base.php';

const LECTURES_CLES = ['nouveautes_index', 'nouveautes_admin', 'tickets'];
// Pastille « nouveaux ateliers » (extension du 21/11/2026) : une mémoire par page et par année. Une seule année
// chargée n'acquitte jamais les ateliers d'une autre (Codex, AG-066).
const LECTURES_CLE_ATELIERS = '/^ateliers_(index|admin)_(20\d\d)$/';

function lectures_schema(PDO $db): void
{
    static $ok = false;
    if ($ok) return;
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'EXISTS lectures')) $db->exec($sql);
    }
    $ok = true;
}

// Valeurs « lues » de la personne, pour getAll (aucun appel de plus au démarrage).
// Null si la lecture échoue : getAll ne doit jamais être bloqué par cette mémoire.
function lectures_lire(PDO $db, string $conseiller): ?array
{
    $lire = function () use ($db, $conseiller): array {
        $s = $db->prepare('SELECT cle, valeur FROM lectures WHERE conseiller = ?');
        $s->execute([$conseiller]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    };
    try {
        try {
            $l = $lire();
        } catch (Throwable) {
            lectures_schema($db);   // table absente : créée une fois, sur le chemin d'erreur seulement
            $l = $lire();
        }
        $ateliers = ['index' => [], 'admin' => []];
        foreach ($l as $cle => $v) {
            if (preg_match(LECTURES_CLE_ATELIERS, (string) $cle, $m)) $ateliers[$m[1]][$m[2]] = (int) $v;
        }
        return [
            'nouveautes_index' => (int) ($l['nouveautes_index'] ?? 0),
            'nouveautes_admin' => (int) ($l['nouveautes_admin'] ?? 0),
            'tickets' => (string) ($l['tickets'] ?? ''),
            // Objets, même vides : leur présence dit au client que l'API connaît la mémoire par année.
            'ateliers_index' => (object) $ateliers['index'],
            'ateliers_admin' => (object) $ateliers['admin'],
        ];
    } catch (Throwable) {
        return null;
    }
}

function action_marquer_lu(PDO $db, array $p, string $conseiller): array
{
    $cle = (string) ($p['cle'] ?? '');
    $val = (string) ($p['valeur'] ?? '');
    $annee = preg_match(LECTURES_CLE_ATELIERS, $cle, $m) ? (int) $m[2] : 0;
    if (!$annee && !in_array($cle, LECTURES_CLES, true)) return ['ok' => false, 'error' => 'Rubrique inconnue'];
    if ($cle === 'tickets') {
        // Une vraie date (calendrier et heure), pas seulement la forme : « 9999-99-99 99:99:99 » dépasserait
        // toute date valide et empêcherait ensuite un acquittement ordinaire (Codex, AG-066).
        $d = DateTime::createFromFormat('!Y-m-d H:i:s', $val);
        if (!$d || $d->format('Y-m-d H:i:s') !== $val) return ['ok' => false, 'error' => 'Date invalide'];
        // Un curseur ne dépasse pas la dernière réponse reçue sur les tickets de la personne.
        try {
            $m = $db->prepare('SELECT MAX(repondu_le) FROM tickets WHERE auteur = ?');
            $m->execute([$conseiller]);
            $max = (string) ($m->fetchColumn() ?: '');
        } catch (Throwable) { $max = ''; }
        if ($max === '' || $val > $max) return ['ok' => false, 'error' => 'Date postérieure à vos réponses'];
        $plus = 'VALUES(valeur) > valeur';   // même gabarit : l'ordre des textes est l'ordre des dates
    } else {
        if (!preg_match('/^\d{1,6}$/', $val)) return ['ok' => false, 'error' => 'Numéro invalide'];
        // Numéro d'atelier : jamais au-delà du plus grand de l'année, sinon il masquerait les ateliers à venir.
        // Ramené à cette borne plutôt que refusé : un atelier passé en corbeille entre le chargement et le clic
        // ne doit pas laisser l'acquittement en attente pour toujours (Codex, AG-066).
        if ($annee) {
            $s = $db->prepare('SELECT COALESCE(MAX(n), 0) FROM ateliers WHERE date >= ? AND date < ?');
            $s->execute([$annee . '-01-01', ($annee + 1) . '-01-01']);
            $max = (int) $s->fetchColumn();
            if ($max === 0) return ['ok' => false, 'error' => 'Aucun atelier cette année'];
            $val = (string) min((int) $val, $max);
        }
        $plus = 'CAST(VALUES(valeur) AS UNSIGNED) > CAST(valeur AS UNSIGNED)';
    }
    lectures_schema($db);
    // `maj` avant `valeur` : MySQL évalue les affectations dans l'ordre, la comparaison
    // doit voir l'ancienne valeur. Une valeur plus basse ou égale ne change rien.
    $db->prepare(
        'INSERT INTO lectures (conseiller, cle, valeur, maj) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE '
        . "maj = IF($plus, NOW(), maj), valeur = IF($plus, VALUES(valeur), valeur)"
    )->execute([$conseiller, $cle, $val]);
    return ['ok' => true, 'lu' => lectures_lire($db, $conseiller)];
}
