<?php
session_start();

// ===== DONNÉES SIMULÉES (tableau = remplace MySQL) =====
$users_simulés = [
    ['nin' => '123456789123456789', 'email' => 'admin@hadj.dz', 'psswd' => md5('Admin123!'), 'role' => 1, 'etat_compte' => 1],
    ['nin' => '987654321987654321', 'email' => 'user@hadj.dz',  'psswd' => md5('User1234!'),  'role' => 2, 'etat_compte' => 3],
];

// ===== FONCTIONS DE VALIDATION CÔTÉ SERVEUR =====
function valider_nin($nin) {
    return preg_match('/^[0-9]{18}$/', $nin);
}

function valider_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valider_telephone($tel) {
    return preg_match('/^0[567][0-9]{8}$/', preg_replace('/\s/', '', $tel));
}

function valider_date_naissance($ddn) {
    if (empty($ddn)) return false;
    $naissance   = new DateTime($ddn);
    $aujourd_hui = new DateTime();
    $age = $aujourd_hui->diff($naissance)->y;
    return $age >= 18;
}

function valider_mot_de_passe($pwd) {
    return strlen($pwd) >= 8;
}

// ===== TRAITEMENT POST =====
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nin        = trim($_POST['nin']        ?? '');
    $nom        = trim($_POST['nom']        ?? '');
    $prenom     = trim($_POST['prenom']     ?? '');
    $ddn        = trim($_POST['date_naissance'] ?? '');
    $pere       = trim($_POST['prenom_pere']    ?? '');
    $grandpere  = trim($_POST['prenom_grandpere'] ?? '');
    $mere       = trim($_POST['nom_mere']    ?? '');
    $email      = trim($_POST['email']      ?? '');
    $tel        = trim($_POST['telephone']  ?? '');
    $wilaya     = trim($_POST['wilaya']     ?? '');
    $pwd        = $_POST['mot_de_passe']    ?? '';
    $confirm    = $_POST['confirmer_mot_de_passe'] ?? '';

    // Validations avec regex côté serveur
    if (!$nom)                          $erreurs['nom']      = "Le nom est obligatoire.";
    if (!$prenom)                       $erreurs['prenom']   = "Le prénom est obligatoire.";
    if (!valider_nin($nin))             $erreurs['nin']      = "Le NIN doit comporter exactement 18 chiffres.";
    if (!valider_date_naissance($ddn))  $erreurs['ddn']      = "Vous devez avoir au moins 18 ans.";
    if (!$pere)                         $erreurs['pere']     = "Le prénom du père est obligatoire.";
    if (!$grandpere)                    $erreurs['grandpere']= "Le prénom du grand-père est obligatoire.";
    if (!$mere)                         $erreurs['mere']     = "Le nom et prénom de la mère sont obligatoires.";
    if (!valider_email($email))         $erreurs['email']    = "Adresse e-mail invalide.";
    if (!valider_telephone($tel))       $erreurs['tel']      = "Numéro invalide (05/06/07 + 8 chiffres).";
    if (!$wilaya)                       $erreurs['wilaya']   = "Veuillez sélectionner une wilaya.";
    if (!valider_mot_de_passe($pwd))    $erreurs['pwd']      = "Le mot de passe doit contenir au moins 8 caractères.";
    if ($pwd !== $confirm)              $erreurs['confirm']  = "Les mots de passe ne correspondent pas.";

    // Vérifier doublon NIN (simulation)
    foreach ($users_simulés as $u) {
        if ($u['nin'] === $nin) {
            $erreurs['nin'] = "Ce NIN est déjà enregistré.";
            break;
        }
    }

    if (empty($erreurs)) {
        // Simulation : compte créé avec etat=3 (en attente)
        $_SESSION['inscription_ok'] = true;
        $_SESSION['new_user_nom']   = $nom . ' ' . $prenom;
        header('Location: inscriptionsuccess.php');
        exit;
    }

    // Erreurs → repasser en session pour les réafficher
    $_SESSION['erreurs_inscription'] = $erreurs;
    $_SESSION['old_input'] = $_POST;
    header('Location: Register.html');
    exit;
}
?>