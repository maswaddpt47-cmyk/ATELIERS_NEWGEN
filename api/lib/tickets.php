<?php
// Tickets de l'équipe — rubrique « Signaler » (02/10/2026, AG-016 amendé).
//
//   creerTicket    tout conseiller connecté ; id fourni par le client, INSERT
//                  IGNORE : rejouer l'envoi (réponse perdue) ne crée ni second
//                  ticket ni second mail. Mail aux admin/superviseur après
//                  l'insertion ; son échec ne fait pas échouer le ticket.
//   getTickets     tout conseiller connecté, TOUS les tickets (choix de
//                  l'utilisateur : voir ceux des collègues évite les doublons).
//   repondreTicket admin/superviseur (API_ACTIONS_ADMIN, journalisé avec _id
//                  pour cible, jamais le texte) : statut, réponse, doublon.
//                  Mail à l'AUTEUR du ticket quand le statut ou la réponse a
//                  changé (AG-045, 04/11/2026) : sauf s'il répond lui-même, si
//                  le ticket est anonymisé, sans adresse ou interrupteur
//                  `tickets_mail_demandeur` éteint ; l'API dit ce qui s'est passé.
//   modifierTicket tout conseiller connecté (décision de l'utilisateur,
//                  03/11/2026, AG-041) : type, gêne, onglet, titre,
//                  description, mêmes validations qu'à la création, tant que
//                  le statut est « Nouveau » (refus dès que l'Admin l'a
//                  changé). Ni mention « modifié », ni mail, ni journal.
//
// Dates en heure de Paris (date() PHP), jamais NOW() : le serveur MySQL peut
// être en UTC (CHANTIERS, « Dates du jour »).

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/mail.php';

const TICKET_TYPES = ['Bug', 'Amélioration', 'Question', 'Autre'];
const TICKET_GENES = ['bloquant', 'gênant', 'mineur'];
const TICKET_STATUTS = ['Nouveau', 'Vu', 'En cours', 'Résolu', 'Non retenu'];
const TICKET_STATUTS_CLOS = ['Résolu', 'Non retenu'];
// Conservation (décision de l'utilisateur, 02/10/2026 : garder des archives
// au-delà de 12 mois si le RGPD le permet) : 12 mois après la clôture, le
// ticket est ANONYMISÉ (auteur et répondant effacés) et reste consultable en
// archive ; supprimé 36 mois après la clôture. Jamais clos : supprimé à 24 mois.
const TICKET_ANONYME_MOIS = 12;
const TICKET_CLOS_MOIS = 36;
const TICKET_OUVERT_MOIS = 24;
const TICKET_URL_ADMIN = [
    'newgen'   => 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/admin.html',
    'nextstep' => 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/admin.html',
];

// Page de l'application où l'auteur retrouve sa contribution (onglet Contribuer).
const TICKET_URL_APP = [
    'newgen'   => 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/index.html',
    'nextstep' => 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/index.html',
];
const TICKET_URL_APP_BAC = [
    'newgen'   => 'https://ateliers-numeriques.alwaysdata.net/sandbox/index.html',
    'nextstep' => 'https://ateliers-numeriques.alwaysdata.net/sandbox/nextstep/index.html',
];

function tickets_schema(PDO $db): void
{
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'CREATE TABLE IF NOT EXISTS tickets')) $db->exec($sql);
    }
}

// Anonymisation et purge RGPD, appelées à chaque connexion (comme le journal
// et la corbeille).
function tickets_purger(PDO $db): void
{
    tickets_schema($db);
    $anonyme = date('Y-m-d H:i:s', strtotime('-' . TICKET_ANONYME_MOIS . ' months'));
    $db->prepare("UPDATE tickets SET auteur = '—', repondu_par = '' WHERE clos_le IS NOT NULL AND clos_le < ? AND auteur <> '—'")
       ->execute([$anonyme]);
    $clos = date('Y-m-d H:i:s', strtotime('-' . TICKET_CLOS_MOIS . ' months'));
    $ouvert = date('Y-m-d H:i:s', strtotime('-' . TICKET_OUVERT_MOIS . ' months'));
    $db->prepare('DELETE FROM tickets WHERE (clos_le IS NOT NULL AND clos_le < ?) OR (clos_le IS NULL AND cree_le < ?)')
       ->execute([$clos, $ouvert]);
}

function ticket_texte(array $p, string $cle, int $max): string
{
    return mb_substr(trim((string) ($p[$cle] ?? '')), 0, $max);
}

function action_creer_ticket(PDO $db, array $p, array $session): array
{
    $id = (string) ($p['_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{6,64}$/', $id)) return ['ok' => false, 'error' => 'Identifiant de ticket invalide'];
    $type = (string) ($p['type'] ?? '');
    if (!in_array($type, TICKET_TYPES, true)) return ['ok' => false, 'error' => 'Type invalide'];
    $titre = ticket_texte($p, 'titre', 120);
    $description = ticket_texte($p, 'description', 2000);
    if ($titre === '' || $description === '') return ['ok' => false, 'error' => 'Titre et description requis'];
    $gene = $type === 'Bug' && in_array($p['gene'] ?? '', TICKET_GENES, true) ? (string) $p['gene'] : '';
    $appareil = in_array($p['appareil'] ?? '', ['PC', 'Téléphone'], true) ? (string) $p['appareil'] : '';
    $t = [
        'id' => $id, 'cree_le' => date('Y-m-d H:i:s'), 'auteur' => $session['conseiller'],
        'site' => api_site_courant(), 'version' => ticket_texte($p, 'version', 30), 'appareil' => $appareil,
        'type' => $type, 'onglet' => ticket_texte($p, 'onglet', 40), 'gene' => $gene,
        'titre' => $titre, 'description' => $description,
    ];
    tickets_schema($db);
    $s = $db->prepare('INSERT IGNORE INTO tickets (id, cree_le, auteur, site, version, appareil, type, onglet, gene, titre, description, reponse)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'\')');
    $s->execute(array_values($t));
    $nouveau = $s->rowCount() === 1;
    $mails = $nouveau ? tickets_prevenir($db, $t) : 0;
    return ['ok' => true, 'ticket' => ticket_lire($db, $id), 'nouveau' => $nouveau, 'mails' => $mails];
}

// Modification d'un ticket encore « Nouveau » (AG-041). L'écriture est
// conditionnelle (WHERE statut = 'Nouveau') : si l'Admin change le statut
// entre la lecture et l'écriture, rien n'est modifié et le refus est rendu.
// Site, version, appareil, auteur et date restent ceux de la création.
function action_modifier_ticket(PDO $db, array $p): array
{
    tickets_schema($db);
    $id = (string) ($p['_id'] ?? '');
    $t = ticket_lire($db, $id);
    if ($t === null) return ['ok' => false, 'error' => 'Ticket introuvable'];
    $refus = ['ok' => false, 'error' => 'Ce ticket a déjà été pris en charge par l\'administrateur : il ne peut plus être modifié.'];
    if ($t['statut'] !== 'Nouveau') return $refus;
    $type = (string) ($p['type'] ?? '');
    if (!in_array($type, TICKET_TYPES, true)) return ['ok' => false, 'error' => 'Type invalide'];
    $titre = ticket_texte($p, 'titre', 120);
    $description = ticket_texte($p, 'description', 2000);
    if ($titre === '' || $description === '') return ['ok' => false, 'error' => 'Titre et description requis'];
    $gene = $type === 'Bug' && in_array($p['gene'] ?? '', TICKET_GENES, true) ? (string) $p['gene'] : '';
    $onglet = ticket_texte($p, 'onglet', 40);
    $db->prepare("UPDATE tickets SET type = ?, gene = ?, onglet = ?, titre = ?, description = ? WHERE id = ? AND statut = 'Nouveau'")
       ->execute([$type, $gene, $onglet, $titre, $description, $id]);
    // rowCount ne suffit pas (0 aussi quand rien ne change) : on relit.
    $apres = ticket_lire($db, $id);
    if ($apres === null) return ['ok' => false, 'error' => 'Ticket introuvable'];
    $voulu = ['type' => $type, 'gene' => $gene, 'onglet' => $onglet, 'titre' => $titre, 'description' => $description];
    foreach ($voulu as $k => $v) if ((string) $apres[$k] !== $v) return $refus;
    return ['ok' => true, 'ticket' => $apres];
}

// Destinataires : comptes admin/superviseur à accès Admin actif, croisés avec
// la config « emails » (chaîne seule ou objet {email}, comme rappels.php).
function tickets_destinataires(PDO $db): array
{
    $emails = api_json(api_config_base($db)['emails'] ?? '', []);
    if (!is_array($emails)) return [];
    $noms = $db->query("SELECT conseiller FROM comptes WHERE role IN ('admin', 'superviseur') AND actif = 1")->fetchAll(PDO::FETCH_COLUMN);
    $a = [];
    foreach ($noms as $nom) {
        $e = $emails[$nom] ?? '';
        $adresse = trim((string) (is_array($e) ? ($e['email'] ?? '') : $e));
        if (filter_var($adresse, FILTER_VALIDATE_EMAIL)) $a[] = $adresse;
    }
    return array_values(array_unique($a));
}

function tickets_prevenir(PDO $db, array $t): int
{
    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $url = TICKET_URL_ADMIN[$t['site']] ?? TICKET_URL_ADMIN['nextstep'];
    $sujet = 'Ticket ' . $t['type'] . ($t['gene'] ? ' (' . $t['gene'] . ')' : '') . ' — ' . $t['titre'];
    $infos = [['De', $t['auteur']], ['Onglet', $t['onglet'] ?: '—'], ['Site', $t['site'] ?: '—'],
              ['Appareil', $t['appareil'] ?: '—'], ['Version', $t['version'] ?: '—']];
    $texte = "Nouveau ticket « {$t['titre']} »\n\n" . implode("\n", array_map(fn($l) => $l[0] . ' : ' . $l[1], $infos))
           . "\n\n{$t['description']}\n\nRépondre dans l'Admin, onglet Tickets : $url\n";
    $lignes = implode('', array_map(fn($l) => '<tr><td style="padding:3px 10px 3px 0;color:#718096">' . $h($l[0])
            . '</td><td style="padding:3px 0">' . $h($l[1]) . '</td></tr>', $infos));
    $html = '<div style="font-family:sans-serif;max-width:640px">'
          . '<h2 style="color:#1e3a8a;margin:0 0 4px">' . $h($t['type']) . ($t['gene'] ? ' — ' . $h($t['gene']) : '') . '</h2>'
          . '<p style="font-size:16px;font-weight:700;margin:0 0 10px">' . $h($t['titre']) . '</p>'
          . '<table style="font-size:13px;border-collapse:collapse">' . $lignes . '</table>'
          . '<p style="white-space:pre-wrap;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">' . $h($t['description']) . '</p>'
          . '<p><a href="' . $h($url) . '" style="color:#1e3a8a;font-weight:700">Répondre dans l\'Admin, onglet Tickets</a></p></div>';
    $n = 0;
    foreach (tickets_destinataires($db) as $a) {
        try { if (mail_envoyer($a, $sujet, $texte, $html)) $n++; } catch (Throwable $e) { /* le ticket reste enregistré */ }
    }
    return $n;
}

// Interrupteur global (AG-045) : absent = allumé. Sans lien avec `rappels_actifs` (individuel).
function tickets_mail_actif(PDO $db): bool
{
    $v = strtolower(trim((string) (api_config_base($db)['tickets_mail_demandeur'] ?? '')));
    return !in_array($v, ['false', '0'], true);
}

// Prévient l'auteur d'un ticket que son statut ou la réponse a changé. Rend l'état exact de l'envoi :
// 'envoye', 'echec', 'sans_adresse', 'desactive' ou 'inutile' (rien n'a changé, l'auteur répond lui-même,
// ticket anonymisé). Un échec d'envoi n'annule jamais l'enregistrement.
function tickets_prevenir_auteur(PDO $db, array $avant, array $apres, string $parQui): string
{
    $statutChange = $apres['statut'] !== $avant['statut'];
    $reponseChangee = (string) $apres['reponse'] !== (string) $avant['reponse'] && (string) $apres['reponse'] !== '';
    if (!$statutChange && !$reponseChangee) return 'inutile';
    $auteur = (string) $avant['auteur'];
    if ($auteur === '' || $auteur === '—' || $auteur === $parQui) return 'inutile';
    if (!tickets_mail_actif($db)) return 'desactive';
    $emails = api_json(api_config_base($db)['emails'] ?? '', []);
    $e = is_array($emails) ? ($emails[$auteur] ?? '') : '';
    $adresse = trim((string) (is_array($e) ? ($e['email'] ?? '') : $e));
    if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) return 'sans_adresse';

    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $bac = (api_config()['bac_a_sable'] ?? false) === true;
    $url = ($bac ? TICKET_URL_APP_BAC : TICKET_URL_APP)[$apres['site']] ?? ($bac ? TICKET_URL_APP_BAC : TICKET_URL_APP)['nextstep'];
    $sujet = 'Votre contribution « ' . $apres['titre'] . ' » : ' . $apres['statut'];
    $doublon = (string) $apres['doublon_de'] !== '' ? "Elle a été rattachée à une contribution déjà existante.
" : '';
    $texte = "Bonjour,\n\nVotre contribution « {$apres['titre']} » est maintenant : {$apres['statut']}.\n" . $doublon;
    if ((string) $apres['reponse'] !== '') $texte .= "\nRéponse de $parQui :\n{$apres['reponse']}\n";
    $texte .= "\nLa retrouver dans l'application, onglet Contribuer : $url\n";
    $html = '<div style="font-family:sans-serif;max-width:640px"><p>Bonjour,</p>'
          . '<p>Votre contribution <strong>« ' . $h($apres['titre']) . ' »</strong> est maintenant : <strong>' . $h($apres['statut']) . '</strong>.</p>'
          . ($doublon !== '' ? '<p>' . $h(trim($doublon)) . '</p>' : '')
          . ((string) $apres['reponse'] !== '' ? '<p style="margin:0 0 4px;color:#718096;font-size:13px">Réponse de ' . $h($parQui) . ' :</p>'
              . '<p style="white-space:pre-wrap;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">' . $h($apres['reponse']) . '</p>' : '')
          . '<p><a href="' . $h($url) . '" style="color:#1e3a8a;font-weight:700">La retrouver dans l\'application, onglet Contribuer</a></p></div>';
    try { return mail_envoyer($adresse, $sujet, $texte, $html) ? 'envoye' : 'echec'; } catch (Throwable $e) { return 'echec'; }
}

function ticket_lire(PDO $db, string $id): ?array
{
    $s = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $s->execute([$id]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function action_get_tickets(PDO $db, array $session): array
{
    tickets_schema($db);
    $l = $db->query('SELECT * FROM tickets ORDER BY cree_le DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    $r = ['ok' => true, 'tickets' => $l, 'moi' => $session['conseiller']];
    if (api_acces_admin($db, $session)) $r['mail_demandeur'] = tickets_mail_actif($db);   // interrupteur affiché à l'Admin seulement
    return $r;
}

function action_repondre_ticket(PDO $db, array $p, array $session): array
{
    tickets_schema($db);
    $id = (string) ($p['_id'] ?? '');
    $t = ticket_lire($db, $id);
    if ($t === null) return ['ok' => false, 'error' => 'Ticket introuvable'];
    $statut = (string) ($p['statut'] ?? $t['statut']);
    if (!in_array($statut, TICKET_STATUTS, true)) return ['ok' => false, 'error' => 'Statut invalide'];
    $doublon = trim((string) ($p['doublon_de'] ?? ''));
    if ($doublon !== '') {
        if ($doublon === $id || ticket_lire($db, $doublon) === null) return ['ok' => false, 'error' => 'Ticket d\'origine introuvable'];
        $statut = 'Non retenu';   // un doublon est clos ; son auteur suit l'original
    }
    $reponse = array_key_exists('reponse', $p) ? ticket_texte($p, 'reponse', 2000) : (string) $t['reponse'];
    $maintenant = date('Y-m-d H:i:s');
    $reponduLe = $t['repondu_le']; $reponduPar = $t['repondu_par'];
    if ($reponse !== (string) $t['reponse'] && $reponse !== '') { $reponduLe = $maintenant; $reponduPar = $session['conseiller']; }
    $closLe = in_array($statut, TICKET_STATUTS_CLOS, true) ? ($t['clos_le'] ?: $maintenant) : null;
    $db->prepare('UPDATE tickets SET statut = ?, reponse = ?, repondu_le = ?, repondu_par = ?, doublon_de = ?, clos_le = ? WHERE id = ?')
       ->execute([$statut, $reponse, $reponduLe, $reponduPar, $doublon, $closLe, $id]);
    $ticket = ticket_lire($db, $id);
    $mail = tickets_prevenir_auteur($db, $t, $ticket, (string) $session['conseiller']);
    return ['ok' => true, 'ticket' => $ticket, 'mail' => $mail, 'mail_a' => $mail === 'inutile' ? '' : (string) $t['auteur']];
}

// Suppression définitive (test, envoi par erreur), réservée à l'admin et à la
// superviseure (02/10/2026). Journalisée avec le numéro pour cible, jamais le
// texte. Les tickets marqués « doublon » de celui-ci perdent ce renvoi.
// Rejouée sur un ticket déjà supprimé : ok, rien à faire.
function action_supprimer_ticket(PDO $db, array $p): array
{
    tickets_schema($db);
    $id = (string) ($p['_id'] ?? '');
    if ($id === '') return ['ok' => false, 'error' => 'Ticket introuvable'];
    $db->prepare("UPDATE tickets SET doublon_de = '' WHERE doublon_de = ?")->execute([$id]);
    $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$id]);
    return ['ok' => true];
}

// Pour les pastilles, livré avec getAll (aucun appel de plus au démarrage) :
// tickets « Nouveau » (Admin) et dernière réponse sur les tickets de la
// personne connectée (Index). Table absente : rien, sans erreur.
function tickets_resume(PDO $db, string $conseiller): ?array
{
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE statut = 'Nouveau'")->fetchColumn();
        $s = $db->prepare('SELECT MAX(repondu_le) FROM tickets WHERE auteur = ?');
        $s->execute([$conseiller]);
        return ['nouveaux' => $n, 'derniere_reponse' => (string) ($s->fetchColumn() ?: '')];
    } catch (Throwable $e) {
        return null;
    }
}
