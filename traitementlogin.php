<?php
session_start();

// ===== DONNÉES SIMULÉES =====
$users = [
    [
        'nin'         => '111111111111111111',
        'email'       => 'admin@hadj.dz',
        'psswd'       => md5('Admin123!'),
        'role'        => 1,   // admin
        'etat_compte' => 1,   // actif
        'nom'         => 'Admin',
        'prenom'      => 'Principal',
    ],
    [
        'nin'         => '222222222222222222',
        'email'       => 'lamis@gmail.com',
        'psswd'       => md5('User1234!'),
        'role'        => 2,   // simple user
        'etat_compte' => 1,   // actif
        'nom'         => 'Dahmani',
        'prenom'      => 'Lamis',
    ],
    [
        'nin'         => '333333333333333333',
        'email'       => 'bloque@gmail.com',
        'psswd'       => md5('User1234!'),
        'role'        => 2,
        'etat_compte' => 2,   // bloqué
        'nom'         => 'Rezki',
        'prenom'      => 'Maria',
    ],
    [
        'nin'         => '444444444444444444',
        'email'       => 'attente@gmail.com',
        'psswd'       => md5('User1234!'),
        'role'        => 2,
        'etat_compte' => 3,   // en attente
        'nom'         => 'Torkman',
        'prenom'      => 'Reda',
    ],
];

// ===== VALIDATION SERVEUR =====
function valider_nin($nin)   { return preg_match('/^[0-9]{18}$/', $nin); }
function valider_email($em)  { return filter_var($em, FILTER_VALIDATE_EMAIL); }

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nin   = trim($_POST['nin']          ?? '');
    $email = trim($_POST['email']        ?? '');
    $pwd   = $_POST['mot_de_passe']      ?? '';

    // Contrôles côté serveur
    if (!valider_nin($nin))   { $erreur = "NIN invalide (18 chiffres requis)."; }
    elseif (!valider_email($email)) { $erreur = "Adresse e-mail invalide."; }
    elseif (!$pwd)            { $erreur = "Mot de passe obligatoire."; }
    else {
        $trouve = null;
        foreach ($users as $u) {
            if ($u['nin'] === $nin && $u['email'] === $email && $u['psswd'] === md5($pwd)) {
                $trouve = $u;
                break;
            }
        }

        if (!$trouve) {
            $erreur = "Identifiants incorrects.";
        } else {
            // Vérifier etat_compte
            switch ($trouve['etat_compte']) {
                case 2: $erreur = "Votre compte est bloqué. Contactez l'administration."; break;
                case 3: $erreur = "Votre compte est en attente de validation par un administrateur."; break;
                case 4: $erreur = "Ce compte a été supprimé."; break;
                default:
                    // Connexion OK → créer la session
                    $_SESSION['user_nin']    = $trouve['nin'];
                    $_SESSION['user_nom']    = $trouve['nom'] . ' ' . $trouve['prenom'];
                    $_SESSION['user_role']   = $trouve['role'];
                    $_SESSION['user_etat']   = $trouve['etat_compte'];
                    $_SESSION['connecte']    = true;

                    // Redirection selon rôle
                    if ($trouve['role'] === 1) {
                        header('Location: Admindash.php');
                    } else {
                        header('Location: Userdash.php');
                    }
                    exit;
            }
        }
    }

    $_SESSION['login_erreur'] = $erreur;
    header('Location: Login.html');
    exit;
}
?>