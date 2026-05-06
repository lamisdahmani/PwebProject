<?php
// ============================================================
//  traitementlogin.php — Authentification avec MySQL
// ============================================================
session_start();
require_once 'DB.php';

// ============================================================
//  FONCTIONS DE VALIDATION
// ============================================================
function valider_nin(string $nin): bool
{
    return (bool) preg_match('/^[0-9]{18}$/', $nin);
}

function valider_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ============================================================
//  TRAITEMENT POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Login.php');
    exit;
}

$nin   = trim($_POST['nin']           ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$pwd   = $_POST['mot_de_passe']       ?? '';

// --- Validations côté serveur ---
$erreur = '';

if (!valider_nin($nin)) {
    $erreur = "NIN invalide (18 chiffres requis).";
} elseif (!valider_email($email)) {
    $erreur = "Adresse e-mail invalide.";
} elseif (!$pwd) {
    $erreur = "Mot de passe obligatoire.";
}

// --- Requête BDD si la validation de base est OK ---
if (!$erreur) {
    $db = get_db();

    // Recherche par NIN + email + mot de passe (SHA-256)
    // On utilise SHA2() côté MySQL pour rester cohérent avec l'insertion
    $stmt = $db->prepare(
        'SELECT u.*, w.nom_wilaya
         FROM utilisateurs u
         JOIN wilayas w ON w.id_wilaya = u.id_wilaya
         WHERE u.nin   = :nin
           AND u.email = :email
           AND u.mot_de_passe = SHA2(:pwd, 256)
         LIMIT 1'
    );
    $stmt->execute([
        ':nin'   => $nin,
        ':email' => $email,
        ':pwd'   => $pwd,
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        $erreur = "Identifiants incorrects.";
    } else {
        // Vérifier l'état du compte
        switch ((int)$user['etat_compte']) {
            case 2:
                $erreur = "Votre compte est bloqué. Contactez l'administration.";
                break;
            case 3:
                $erreur = "Votre compte est en attente de validation par un administrateur.";
                break;
            case 4:
                $erreur = "Ce compte a été supprimé.";
                break;
            default:
                // ================================================
                //  Connexion réussie — création de la session
                // ================================================
                session_regenerate_id(true);   // sécurité : anti-fixation

                $_SESSION['user_id']      = (int)$user['id_utilisateur'];
                $_SESSION['user_nin']     = $user['nin'];
                $_SESSION['user_nom']     = $user['nom'] . ' ' . $user['prenom'];
                $_SESSION['user_role']    = (int)$user['role'];
                $_SESSION['user_etat']    = (int)$user['etat_compte'];
                $_SESSION['user_wilaya']  = $user['nom_wilaya'];
                $_SESSION['connecte']     = true;

                // Redirection selon le rôle
                if ((int)$user['role'] === 1) {
                    header('Location: Admindash.html');
                } else {
                    header('Location: Userdash.html');
                }
                exit;
        }
    }
}

// --- Erreur → retour vers Login avec message en session ---
$_SESSION['login_erreur'] = $erreur;
header('Location: Login.php');
exit;