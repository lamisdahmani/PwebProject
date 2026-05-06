<?php
// ============================================================
//  traitementinscription.php — Traitement du formulaire d'inscription
//  Intégration MySQL via PDO
// ============================================================
session_start();
require_once 'DB.php';

// ============================================================
//  FONCTIONS DE VALIDATION CÔTÉ SERVEUR
// ============================================================
function valider_nin(string $nin): bool
{
    return (bool) preg_match('/^[0-9]{18}$/', $nin);
}

function valider_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valider_telephone(string $tel): bool
{
    $tel = preg_replace('/\s/', '', $tel);
    return (bool) preg_match('/^0[567][0-9]{8}$/', $tel);
}

function valider_date_naissance(string $ddn): bool
{
    if (empty($ddn)) return false;
    $naissance   = new DateTime($ddn);
    $aujourd_hui = new DateTime();
    return $aujourd_hui->diff($naissance)->y >= 18;
}

function valider_mot_de_passe(string $pwd): bool
{
    return strlen($pwd) >= 8;
}

// ============================================================
//  TRAITEMENT POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Register.html');
    exit;
}

$erreurs = [];

// --- Récupération et nettoyage des entrées ---
$nin        = trim($_POST['nin']                  ?? '');
$nom        = trim($_POST['nom']                  ?? '');
$prenom     = trim($_POST['prenom']               ?? '');
$ddn        = trim($_POST['date_naissance']       ?? '');
$pere       = trim($_POST['prenom_pere']          ?? '');
$grandpere  = trim($_POST['prenom_grandpere']     ?? '');
$mere       = trim($_POST['nom_mere']             ?? '');
$email      = strtolower(trim($_POST['email']     ?? ''));
$tel        = trim($_POST['telephone']            ?? '');
$wilaya     = trim($_POST['wilaya']               ?? '');
$pwd        = $_POST['mot_de_passe']              ?? '';
$confirm    = $_POST['confirmer_mot_de_passe']    ?? '';

// --- Validations ---
if (!$nom)                          $erreurs['nom']       = "Le nom est obligatoire.";
if (!$prenom)                       $erreurs['prenom']    = "Le prénom est obligatoire.";
if (!valider_nin($nin))             $erreurs['nin']       = "Le NIN doit comporter exactement 18 chiffres.";
if (!valider_date_naissance($ddn))  $erreurs['ddn']       = "Vous devez avoir au moins 18 ans.";
if (!$pere)                         $erreurs['pere']      = "Le prénom du père est obligatoire.";
if (!$grandpere)                    $erreurs['grandpere'] = "Le prénom du grand-père est obligatoire.";
if (!$mere)                         $erreurs['mere']      = "Le nom et prénom de la mère sont obligatoires.";
if (!valider_email($email))         $erreurs['email']     = "Adresse e-mail invalide.";
if (!valider_telephone($tel))       $erreurs['tel']       = "Numéro invalide (05/06/07 + 8 chiffres).";
if (!$wilaya || !ctype_digit($wilaya)) $erreurs['wilaya'] = "Veuillez sélectionner une wilaya.";
if (!valider_mot_de_passe($pwd))    $erreurs['pwd']       = "Le mot de passe doit contenir au moins 8 caractères.";
if ($pwd !== $confirm)              $erreurs['confirm']   = "Les mots de passe ne correspondent pas.";

// --- Vérification doublon NIN et email en BDD ---
if (empty($erreurs)) {
    $db = get_db();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM utilisateurs WHERE nin = ? OR email = ?'
    );
    $stmt->execute([$nin, $email]);
    $nb = (int) $stmt->fetchColumn();

    if ($nb > 0) {
        // Distinguer NIN vs email pour un message précis
        $stmt2 = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE nin = ?');
        $stmt2->execute([$nin]);
        if ((int)$stmt2->fetchColumn() > 0) {
            $erreurs['nin'] = "Ce NIN est déjà enregistré.";
        } else {
            $erreurs['email'] = "Cette adresse e-mail est déjà utilisée.";
        }
    }
}

// --- Si des erreurs → renvoyer vers le formulaire ---
if (!empty($erreurs)) {
    $_SESSION['erreurs_inscription'] = $erreurs;
    $_SESSION['old_input']           = array_diff_key($_POST, ['mot_de_passe'=>1,'confirmer_mot_de_passe'=>1]);
    header('Location: Register.html');
    exit;
}

// ============================================================
//  INSERTION EN BASE DE DONNÉES
// ============================================================
$db = get_db();

$stmt = $db->prepare(
    'INSERT INTO utilisateurs
       (nin, nom, prenom, prenom_pere, prenom_grandpere, nom_mere,
        date_naissance, email, telephone, id_wilaya, mot_de_passe, role, etat_compte)
     VALUES
       (:nin, :nom, :prenom, :pere, :grandpere, :mere,
        :ddn, :email, :tel, :wilaya, SHA2(:pwd, 256), 2, 3)'
    //                                                  ↑ rôle=utilisateur, etat=en_attente
);

$stmt->execute([
    ':nin'       => $nin,
    ':nom'       => $nom,
    ':prenom'    => $prenom,
    ':pere'      => $pere,
    ':grandpere' => $grandpere,
    ':mere'      => $mere,
    ':ddn'       => $ddn,
    ':email'     => $email,
    ':tel'       => preg_replace('/\s/', '', $tel),
    ':wilaya'    => (int)$wilaya,
    ':pwd'       => $pwd,
]);

// ============================================================
//  NOTIFICATIONS — Envoi aux administrateurs (sur plateforme)
// ============================================================
$id_nouvel_user = (int) $db->lastInsertId();

// Récupérer tous les admins
$admins = $db->query('SELECT id_utilisateur FROM utilisateurs WHERE role = 1')->fetchAll();

$stmt_notif = $db->prepare(
    'INSERT INTO notifications (id_utilisateur, type_notif, message)
     VALUES (:id, :type, :msg)'
);

foreach ($admins as $admin) {
    $stmt_notif->execute([
        ':id'   => $admin['id_utilisateur'],
        ':type' => 'nouveau_compte',
        ':msg'  => "Nouveau compte en attente de validation : $nom $prenom (NIN : $nin).",
    ]);
}

// ============================================================
//  REDIRECTION VERS PAGE DE SUCCÈS
// ============================================================
$_SESSION['inscription_ok']  = true;
$_SESSION['new_user_nom']    = $nom . ' ' . $prenom;
header('Location: inscriptionsuccess.php');
exit;