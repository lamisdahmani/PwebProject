<?php
// traitementlogin.php — Authentification MySQL
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Login.php'); exit;
}

$nin   = trim($_POST['nin']           ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$pwd   = $_POST['mot_de_passe']       ?? '';
$erreur = '';

// Validation basique
if (!preg_match('/^[0-9]{18}$/', $nin))       { $erreur = "NIN invalide (18 chiffres requis)."; }
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erreur = "Adresse e-mail invalide."; }
elseif (!$pwd)                                 { $erreur = "Mot de passe obligatoire."; }

if (!$erreur) {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT u.*, w.nom_wilaya
         FROM utilisateurs u
         JOIN wilayas w ON w.id_wilaya = u.id_wilaya
         WHERE u.nin = :nin AND u.email = :email AND u.mot_de_passe = SHA2(:pwd,256)
         LIMIT 1'
    );
    $stmt->execute([':nin'=>$nin, ':email'=>$email, ':pwd'=>$pwd]);
    $user = $stmt->fetch();

    if (!$user) {
        $erreur = "Identifiants incorrects.";
    } else {
        switch ((int)$user['etat_compte']) {
            case 2: $erreur = "Votre compte est bloqué. Contactez l'administration."; break;
            case 3: $erreur = "Votre compte est en attente de validation par un administrateur."; break;
            case 4: $erreur = "Ce compte a été supprimé."; break;
            default:
                session_regenerate_id(true);
                $_SESSION['user_id']    = (int)$user['id_utilisateur'];
                $_SESSION['user_nin']   = $user['nin'];
                $_SESSION['user_nom']   = $user['nom'].' '.$user['prenom'];
                $_SESSION['user_role']  = (int)$user['role'];
                $_SESSION['user_etat']  = (int)$user['etat_compte'];
                $_SESSION['connecte']   = true;
                header('Location: '.((int)$user['role']===1 ? 'Admindash.php' : 'Userdash.php'));
                exit;
        }
    }
}

$_SESSION['login_erreur'] = $erreur;
header('Location: Login.php');
exit;