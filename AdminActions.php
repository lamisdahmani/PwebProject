<?php
// admin_actions.php — Actions AJAX admin
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

header('Content-Type: application/json');

$action  = trim($_POST['action']          ?? '');
$id_user = (int)($_POST['id_utilisateur'] ?? 0);

if (!$action || $id_user <= 0) {
    echo json_encode(['succes'=>false,'message'=>'Paramètres manquants.']); exit;
}

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur=?');
$stmt->execute([$id_user]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['succes'=>false,'message'=>'Utilisateur introuvable.']); exit;
}

$sn = $db->prepare('INSERT INTO notifications (id_utilisateur,type_notif,message) VALUES(?,?,?)');

switch ($action) {
    case 'valider':
        $db->prepare('UPDATE utilisateurs SET etat_compte=1 WHERE id_utilisateur=?')->execute([$id_user]);
        $sn->execute([$id_user,'compte_valide','Votre compte a été validé. Vous pouvez maintenant vous connecter.']);
        echo json_encode(['succes'=>true,'message'=>'Compte validé.','nouvel_etat'=>1]);
        break;

    case 'bloquer':
        $db->prepare('UPDATE utilisateurs SET etat_compte=2 WHERE id_utilisateur=?')->execute([$id_user]);
        echo json_encode(['succes'=>true,'message'=>'Compte bloqué.','nouvel_etat'=>2]);
        break;

    case 'debloquer':
        $db->prepare('UPDATE utilisateurs SET etat_compte=1 WHERE id_utilisateur=?')->execute([$id_user]);
        $sn->execute([$id_user,'compte_debloque','Votre compte a été débloqué.']);
        echo json_encode(['succes'=>true,'message'=>'Compte débloqué.','nouvel_etat'=>1]);
        break;

    case 'supprimer':
        if ((int)$user['role']===1) {
            echo json_encode(['succes'=>false,'message'=>'Impossible de supprimer un admin.']); exit;
        }
        $db->prepare('DELETE FROM utilisateurs WHERE id_utilisateur=?')->execute([$id_user]);
        echo json_encode(['succes'=>true,'message'=>'Compte supprimé.']);
        break;

    case 'detail':
        $stmt = $db->prepare(
            'SELECT u.*,w.nom_wilaya,
             (SELECT COUNT(*) FROM inscriptions i WHERE i.id_utilisateur=u.id_utilisateur) AS nb_participations,
             (SELECT COUNT(*) FROM inscriptions i JOIN resultats r ON r.id_inscription=i.id_inscription
              WHERE i.id_utilisateur=u.id_utilisateur AND r.gagnant=1) AS nb_victoires
             FROM utilisateurs u JOIN wilayas w ON w.id_wilaya=u.id_wilaya
             WHERE u.id_utilisateur=?'
        );
        $stmt->execute([$id_user]);
        $d = $stmt->fetch();
        unset($d['mot_de_passe']);
        echo json_encode(['succes'=>true,'data'=>$d]);
        break;

    default:
        echo json_encode(['succes'=>false,'message'=>'Action inconnue.']);
}