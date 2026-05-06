<?php
// inscription_tirage.php — Inscription au tirage (appelé via fetch depuis Userdash)
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Vérifier la session
if (!isset($_SESSION['connecte']) || !$_SESSION['connecte']) {
    echo json_encode(['succes'=>false,'message'=>'Non connecté.']); exit;
}
if ((int)$_SESSION['user_role'] !== 2) {
    echo json_encode(['succes'=>false,'message'=>'Réservé aux utilisateurs.']); exit;
}

$id_user     = (int)$_SESSION['user_id'];
$aujourd_hui = date('Y-m-d');
$db          = get_db();

// 1. Compte actif ?
$stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur=?');
$stmt->execute([$id_user]);
$user = $stmt->fetch();
if (!$user || (int)$user['etat_compte'] !== 1) {
    echo json_encode(['succes'=>false,'message'=>'Votre compte doit être validé pour vous inscrire.']); exit;
}

// 2. Tirage ouvert ?
$stmt = $db->prepare(
    "SELECT * FROM tirages
     WHERE etat=2
       AND date_ouverture_inscr <= :auj
       AND date_cloture_inscr   >= :auj
     LIMIT 1"
);
$stmt->execute([':auj'=>$aujourd_hui]);
$tirage = $stmt->fetch();
if (!$tirage) {
    echo json_encode(['succes'=>false,'message'=>'Les inscriptions ne sont pas ouvertes actuellement.']); exit;
}
$id_tirage = (int)$tirage['id_tirage'];

// 3. Âge >= 18 au jour du tirage
$dt  = new DateTime($tirage['date_tirage']);
$dn  = new DateTime($user['date_naissance']);
if ($dt->diff($dn)->y < 18) {
    echo json_encode(['succes'=>false,'message'=>'Vous devez avoir au moins 18 ans le jour du tirage.']); exit;
}

// 4. Pas gagnant dans les 5 derniers tirages
$an_lim = (int)$tirage['annee'] - 5;
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM resultats r
     JOIN inscriptions i ON i.id_inscription=r.id_inscription
     JOIN tirages t      ON t.id_tirage=i.id_tirage
     WHERE i.id_utilisateur=? AND r.gagnant=1 AND t.annee>=?"
);
$stmt->execute([$id_user,$an_lim]);
if ((int)$stmt->fetchColumn()>0) {
    echo json_encode(['succes'=>false,'message'=>'Vous avez gagné récemment. Vous pourrez participer à nouveau dans quelques années.']); exit;
}

// 5. Déjà inscrit ?
$stmt = $db->prepare('SELECT id_inscription FROM inscriptions WHERE id_utilisateur=? AND id_tirage=?');
$stmt->execute([$id_user,$id_tirage]);
if ($stmt->fetch()) {
    echo json_encode(['succes'=>false,'message'=>'Vous êtes déjà inscrit(e) à ce tirage.']); exit;
}

// 6. Calculer le poids (nb pertes + 1)
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM resultats r
     JOIN inscriptions i ON i.id_inscription=r.id_inscription
     WHERE i.id_utilisateur=? AND r.gagnant=0"
);
$stmt->execute([$id_user]);
$poids = (int)$stmt->fetchColumn() + 1;

// 7. Insérer l'inscription
$db->prepare('INSERT INTO inscriptions (id_utilisateur,id_tirage,nb_participations) VALUES(?,?,?)')
   ->execute([$id_user,$id_tirage,$poids]);

echo json_encode([
    'succes'       => true,
    'message'      => "Inscription au tirage ".$tirage['annee']." enregistrée ! Vous apparaissez $poids fois dans l'urne.",
    'date_tirage'  => date('d/m/Y', strtotime($tirage['date_tirage'])),
    'date_cloture' => date('d/m/Y', strtotime($tirage['date_cloture_inscr'])),
]);