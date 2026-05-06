<?php
// ============================================================
//  tirage.php — Algorithme du tirage au sort
//
//  Règles implémentées :
//  1. Ne peut être lancé qu'à la date fixée (vérifiée en BDD)
//  2. Seuls les utilisateurs inscrits + validés participent
//  3. Un gagnant des 5 derniers tirages est exclu
//  4. Les non-gagnants apparaissent autant de fois qu'ils se sont
//     inscrits sans gagner (système de pondération)
//  5. Un même utilisateur ne peut gagner qu'une seule fois
//  6. Les résultats sont stockés et les notifications envoyées
// ============================================================
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);   // réservé aux admins

$db = get_db();

// ============================================================
//  1. VÉRIFIER QUE LE TIRAGE EST AUTORISÉ
// ============================================================

// Récupérer le tirage actif (etat = 3 = inscriptions fermées, ou 2 si clôture dépassée)
$stmt = $db->prepare(
    "SELECT * FROM tirages
     WHERE etat IN (2, 3)
       AND date_tirage = CURDATE()
     ORDER BY annee DESC
     LIMIT 1"
);
$stmt->execute();
$tirage = $stmt->fetch();

if (!$tirage) {
    die(json_encode([
        'succes'  => false,
        'message' => "Le tirage ne peut être lancé qu'à la date fixée, ou aucun tirage n'est configuré.",
    ]));
}

$id_tirage   = (int) $tirage['id_tirage'];
$nb_gagnants = (int) $tirage['nb_gagnants'];

// ============================================================
//  2. VÉRIFIER QUE LE TIRAGE N'A PAS DÉJÀ ÉTÉ EFFECTUÉ
// ============================================================
$stmt = $db->prepare('SELECT COUNT(*) FROM resultats r
    JOIN inscriptions i ON i.id_inscription = r.id_inscription
    WHERE i.id_tirage = ?');
$stmt->execute([$id_tirage]);
if ((int)$stmt->fetchColumn() > 0) {
    die(json_encode([
        'succes'  => false,
        'message' => "Ce tirage a déjà été effectué.",
    ]));
}

// ============================================================
//  3. CONSTRUIRE L'URNE (liste pondérée des participants)
// ============================================================

/*
 * Récupérer tous les inscrits au tirage courant :
 *   - compte actif (etat=1)
 *   - n'ayant PAS gagné dans les 5 derniers tirages
 *   - avec leur poids (nb_participations = 1 + nb pertes consécutives)
 *
 * La colonne nb_participations dans la table inscriptions
 * est calculée et mise à jour à chaque nouvelle inscription.
 */
$stmt = $db->prepare(
    "SELECT i.id_inscription, i.id_utilisateur, i.nb_participations
     FROM inscriptions i
     JOIN utilisateurs u ON u.id_utilisateur = i.id_utilisateur
     WHERE i.id_tirage = :id_tirage
       AND u.etat_compte = 1
       AND u.id_utilisateur NOT IN (
           -- Exclure les gagnants des 5 derniers tirages
           SELECT DISTINCT i2.id_utilisateur
           FROM resultats r2
           JOIN inscriptions i2 ON i2.id_inscription = r2.id_inscription
           JOIN tirages t2      ON t2.id_tirage      = i2.id_tirage
           WHERE r2.gagnant = 1
             AND t2.annee   >= :annee_limite
       )"
);
$annee_limite = (int)$tirage['annee'] - 5;
$stmt->execute([':id_tirage' => $id_tirage, ':annee_limite' => $annee_limite]);
$participants = $stmt->fetchAll();

if (empty($participants)) {
    die(json_encode([
        'succes'  => false,
        'message' => "Aucun participant éligible pour ce tirage.",
    ]));
}

// Construire l'urne : chaque participant apparaît nb_participations fois
$urne = [];
foreach ($participants as $p) {
    $poids = max(1, (int)$p['nb_participations']);
    for ($i = 0; $i < $poids; $i++) {
        $urne[] = $p['id_inscription'];
    }
}

// Mélanger l'urne (Fisher-Yates via shuffle — PHP utilise mt_rand)
shuffle($urne);

// ============================================================
//  4. TIRAGE : sélectionner nb_gagnants uniques
// ============================================================
$gagnants_ids    = [];   // id_inscription des gagnants
$users_gagnes    = [];   // id_utilisateur déjà gagnants (anti-doublon)

foreach ($urne as $id_inscription) {
    if (count($gagnants_ids) >= $nb_gagnants) break;

    // Retrouver l'utilisateur associé à cette inscription
    foreach ($participants as $p) {
        if ((int)$p['id_inscription'] === (int)$id_inscription) {
            $id_user = (int)$p['id_utilisateur'];
            break;
        }
    }

    // Un utilisateur ne peut gagner qu'une seule fois
    if (isset($users_gagnes[$id_user])) continue;

    $gagnants_ids[]          = (int)$id_inscription;
    $users_gagnes[$id_user]  = true;
}

// Ensemble des id_inscription NON gagnants
$tous_ids    = array_column($participants, 'id_inscription');
$non_gagnants_ids = array_diff($tous_ids, $gagnants_ids);

// ============================================================
//  5. ENREGISTRER LES RÉSULTATS (transaction)
// ============================================================
$db->beginTransaction();

try {
    $stmt_res = $db->prepare(
        'INSERT INTO resultats (id_inscription, gagnant) VALUES (:id, :gagnant)'
    );

    // Gagnants
    foreach ($gagnants_ids as $id_insc) {
        $stmt_res->execute([':id' => $id_insc, ':gagnant' => 1]);
    }

    // Non gagnants
    foreach ($non_gagnants_ids as $id_insc) {
        $stmt_res->execute([':id' => $id_insc, ':gagnant' => 0]);
    }

    // Passer le tirage à l'état "effectué"
    $db->prepare('UPDATE tirages SET etat = 4, date_lancement = NOW() WHERE id_tirage = ?')
       ->execute([$id_tirage]);

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    error_log('Erreur tirage : ' . $e->getMessage());
    die(json_encode([
        'succes'  => false,
        'message' => "Erreur lors de l'enregistrement des résultats.",
    ]));
}

// ============================================================
//  6. ENVOYER LES NOTIFICATIONS (sur plateforme)
// ============================================================

$stmt_notif = $db->prepare(
    'INSERT INTO notifications (id_utilisateur, type_notif, message)
     VALUES (:id_user, :type, :msg)'
);

// Récupérer les id_utilisateur des gagnants pour les notifier
$in_placeholders = implode(',', array_fill(0, count($gagnants_ids), '?'));
if ($gagnants_ids) {
    $stmt_users = $db->prepare(
        "SELECT i.id_utilisateur FROM inscriptions i
         WHERE i.id_inscription IN ($in_placeholders)"
    );
    $stmt_users->execute($gagnants_ids);
    $users_gagnants = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

    foreach ($users_gagnants as $id_user) {
        $stmt_notif->execute([
            ':id_user' => $id_user,
            ':type'    => 'resultat_gagnant',
            ':msg'     => "Félicitations ! Vous avez été sélectionné(e) pour le Hadj " . $tirage['annee'] . ".",
        ]);
    }
}

// Non gagnants
if ($non_gagnants_ids) {
    $in_ng = implode(',', array_fill(0, count($non_gagnants_ids), '?'));
    $stmt_users_ng = $db->prepare(
        "SELECT i.id_utilisateur FROM inscriptions i
         WHERE i.id_inscription IN ($in_ng)"
    );
    $stmt_users_ng->execute(array_values($non_gagnants_ids));
    $users_ng = $stmt_users_ng->fetchAll(PDO::FETCH_COLUMN);

    foreach ($users_ng as $id_user) {
        $stmt_notif->execute([
            ':id_user' => $id_user,
            ':type'    => 'resultat_non_gagnant',
            ':msg'     => "Résultat tirage " . $tirage['annee'] . " : vous n'avez pas été sélectionné(e). Vos chances augmentent pour le prochain tirage.",
        ]);
    }
}

// Notifier les admins
$admins = $db->query('SELECT id_utilisateur FROM utilisateurs WHERE role = 1')->fetchAll(PDO::FETCH_COLUMN);
foreach ($admins as $id_admin) {
    $stmt_notif->execute([
        ':id_user' => $id_admin,
        ':type'    => 'tirage_effectue',
        ':msg'     => "Le tirage " . $tirage['annee'] . " a été effectué. " . count($gagnants_ids) . " gagnants désignés.",
    ]);
}

// ============================================================
//  7. RÉPONSE JSON (utilisée par Admin.js via fetch)
// ============================================================
header('Content-Type: application/json');
echo json_encode([
    'succes'       => true,
    'message'      => "Tirage effectué avec succès.",
    'annee'        => $tirage['annee'],
    'nb_gagnants'  => count($gagnants_ids),
    'nb_perdants'  => count($non_gagnants_ids),
    'total'        => count($participants),
]);