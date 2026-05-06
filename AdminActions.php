<?php
// ============================================================
//  admin_actions.php — Actions AJAX de gestion des utilisateurs
//  Appelé par Admin.js via fetch()
//  Actions : valider | bloquer | supprimer | detail
// ============================================================
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

header('Content-Type: application/json');

// ============================================================
//  Lecture de l'action et de l'identifiant utilisateur
// ============================================================
$action     = trim($_POST['action']         ?? '');
$id_user    = (int)($_POST['id_utilisateur'] ?? 0);

if (!$action || $id_user <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Paramètres manquants.']);
    exit;
}

$db = get_db();

// ============================================================
//  Vérifier que l'utilisateur cible existe
// ============================================================
$stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$id_user]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['succes' => false, 'message' => 'Utilisateur introuvable.']);
    exit;
}

// ============================================================
//  SWITCH sur l'action demandée
// ============================================================
switch ($action) {

    // ----------------------------------------------------------
    //  VALIDER — passe etat_compte de 3 (attente) à 1 (actif)
    // ----------------------------------------------------------
    case 'valider':
        if ((int)$user['etat_compte'] === 1) {
            echo json_encode(['succes' => false, 'message' => 'Ce compte est déjà actif.']);
            exit;
        }
        $db->prepare('UPDATE utilisateurs SET etat_compte = 1 WHERE id_utilisateur = ?')
           ->execute([$id_user]);

        // Notification à l'utilisateur
        $db->prepare(
            'INSERT INTO notifications (id_utilisateur, type_notif, message)
             VALUES (?, ?, ?)'
        )->execute([
            $id_user,
            'compte_valide',
            'Votre compte a été validé. Vous pouvez maintenant vous connecter et participer aux tirages.',
        ]);

        echo json_encode(['succes' => true, 'message' => 'Compte validé avec succès.', 'nouvel_etat' => 1]);
        break;

    // ----------------------------------------------------------
    //  BLOQUER — passe etat_compte à 2
    // ----------------------------------------------------------
    case 'bloquer':
        if ((int)$user['etat_compte'] === 2) {
            echo json_encode(['succes' => false, 'message' => 'Ce compte est déjà bloqué.']);
            exit;
        }
        $db->prepare('UPDATE utilisateurs SET etat_compte = 2 WHERE id_utilisateur = ?')
           ->execute([$id_user]);

        echo json_encode(['succes' => true, 'message' => 'Compte bloqué.', 'nouvel_etat' => 2]);
        break;

    // ----------------------------------------------------------
    //  DÉBLOQUER — ramène etat_compte à 1
    // ----------------------------------------------------------
    case 'debloquer':
        $db->prepare('UPDATE utilisateurs SET etat_compte = 1 WHERE id_utilisateur = ?')
           ->execute([$id_user]);

        $db->prepare(
            'INSERT INTO notifications (id_utilisateur, type_notif, message)
             VALUES (?, ?, ?)'
        )->execute([
            $id_user,
            'compte_debloque',
            'Votre compte a été débloqué. Vous pouvez à nouveau vous connecter.',
        ]);

        echo json_encode(['succes' => true, 'message' => 'Compte débloqué.', 'nouvel_etat' => 1]);
        break;

    // ----------------------------------------------------------
    //  SUPPRIMER — suppression logique (etat=4) ou physique
    //  Ici suppression physique ; les FK CASCADE s'occupent
    //  des inscriptions / résultats liés.
    // ----------------------------------------------------------
    case 'supprimer':
        // Empêcher la suppression d'un admin
        if ((int)$user['role'] === 1) {
            echo json_encode(['succes' => false, 'message' => 'Impossible de supprimer un administrateur.']);
            exit;
        }
        $db->prepare('DELETE FROM utilisateurs WHERE id_utilisateur = ?')
           ->execute([$id_user]);

        echo json_encode(['succes' => true, 'message' => 'Compte supprimé définitivement.']);
        break;

    // ----------------------------------------------------------
    //  DETAIL — renvoie les informations complètes de l'utilisateur
    // ----------------------------------------------------------
    case 'detail':
        $stmt = $db->prepare(
            'SELECT u.*, w.nom_wilaya,
                    (SELECT COUNT(*) FROM inscriptions i WHERE i.id_utilisateur = u.id_utilisateur) AS nb_participations,
                    (SELECT COUNT(*) FROM inscriptions i
                       JOIN resultats r ON r.id_inscription = i.id_inscription
                     WHERE i.id_utilisateur = u.id_utilisateur AND r.gagnant = 1)                  AS nb_victoires
             FROM utilisateurs u
             JOIN wilayas w ON w.id_wilaya = u.id_wilaya
             WHERE u.id_utilisateur = ?'
        );
        $stmt->execute([$id_user]);
        $detail = $stmt->fetch();

        // Ne pas renvoyer le hash du mot de passe
        unset($detail['mot_de_passe']);

        echo json_encode(['succes' => true, 'data' => $detail]);
        break;

    default:
        echo json_encode(['succes' => false, 'message' => 'Action inconnue.']);
}