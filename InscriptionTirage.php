<?php
// ============================================================
//  inscription_tirage.php — Inscription d'un utilisateur
//  au tirage en cours
// ============================================================
session_start();
require_once 'db.php';
require_once 'authguard.php';   // vérifie session connecte=true

header('Content-Type: application/json');

$id_user = (int)$_SESSION['user_id'];
$db      = get_db();

// ============================================================
//  1. Vérifier que le compte est actif
// ============================================================
$stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$id_user]);
$user = $stmt->fetch();

if (!$user || (int)$user['etat_compte'] !== 1) {
    echo json_encode(['succes' => false, 'message' => 'Votre compte doit être validé pour vous inscrire.']);
    exit;
}

// ============================================================
//  2. Récupérer le tirage ouvert (etat=2 ET dates valides)
// ============================================================
$aujourd_hui = date('Y-m-d');
$stmt = $db->prepare(
    "SELECT * FROM tirages
     WHERE etat = 2
       AND date_ouverture_inscr <= :auj
       AND date_cloture_inscr   >= :auj
     LIMIT 1"
);
$stmt->execute([':auj' => $aujourd_hui]);
$tirage = $stmt->fetch();

if (!$tirage) {
    echo json_encode(['succes' => false, 'message' => 'Les inscriptions ne sont pas ouvertes actuellement.']);
    exit;
}

$id_tirage = (int)$tirage['id_tirage'];

// ============================================================
//  3. Vérifier l'âge minimum (18 ans le jour du tirage)
// ============================================================
$date_tirage  = new DateTime($tirage['date_tirage']);
$date_naiss   = new DateTime($user['date_naissance']);
$age_au_tirage = $date_tirage->diff($date_naiss)->y;

if ($age_au_tirage < 18) {
    echo json_encode(['succes' => false, 'message' => "Vous devez avoir au moins 18 ans le jour du tirage."]);
    exit;
}

// ============================================================
//  4. Vérifier que l'utilisateur n'a pas gagné dans les 5
//     derniers tirages
// ============================================================
$annee_limite = (int)$tirage['annee'] - 5;
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM resultats r
     JOIN inscriptions i ON i.id_inscription = r.id_inscription
     JOIN tirages t      ON t.id_tirage      = i.id_tirage
     WHERE i.id_utilisateur = ?
       AND r.gagnant = 1
       AND t.annee  >= ?"
);
$stmt->execute([$id_user, $annee_limite]);
if ((int)$stmt->fetchColumn() > 0) {
    echo json_encode(['succes' => false, 'message' => "Vous avez déjà gagné récemment. Vous pourrez participer à nouveau dans quelques années."]);
    exit;
}

// ============================================================
//  5. Vérifier que l'utilisateur n'est pas déjà inscrit
// ============================================================
$stmt = $db->prepare(
    'SELECT id_inscription FROM inscriptions
     WHERE id_utilisateur = ? AND id_tirage = ?'
);
$stmt->execute([$id_user, $id_tirage]);
if ($stmt->fetch()) {
    echo json_encode(['succes' => false, 'message' => 'Vous êtes déjà inscrit(e) à ce tirage.']);
    exit;
}

// ============================================================
//  6. Calculer le poids (pondération) :
//     nb de tirages perdus consécutifs + 1
// ============================================================
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM resultats r
     JOIN inscriptions i ON i.id_inscription = r.id_inscription
     WHERE i.id_utilisateur = ? AND r.gagnant = 0"
);
$stmt->execute([$id_user]);
$nb_pertes = (int)$stmt->fetchColumn();
$poids     = $nb_pertes + 1;   // apparaît $poids fois dans l'urne

// ============================================================
//  7. Insérer l'inscription
// ============================================================
$stmt = $db->prepare(
    'INSERT INTO inscriptions (id_utilisateur, id_tirage, nb_participations)
     VALUES (?, ?, ?)'
);
$stmt->execute([$id_user, $id_tirage, $poids]);

echo json_encode([
    'succes'          => true,
    'message'         => "Inscription au tirage " . $tirage['annee'] . " enregistrée avec succès.",
    'poids'           => $poids,
    'date_cloture'    => date('d/m/Y', strtotime($tirage['date_cloture_inscr'])),
    'date_tirage'     => date('d/m/Y', strtotime($tirage['date_tirage'])),
]);