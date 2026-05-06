<?php
// traitementinscription.php — Inscription MySQL
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: Register.html'); exit; }

// Fonctions validation
function val_nin($v)   { return preg_match('/^[0-9]{18}$/', $v); }
function val_email($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }
function val_tel($v)   { return preg_match('/^0[567][0-9]{8}$/', preg_replace('/\s/','',$v)); }
function val_ddn($v)   {
    if (!$v) return false;
    $age = (new DateTime())->diff(new DateTime($v))->y;
    return $age >= 18;
}

$erreurs = [];
$nin       = trim($_POST['nin']                 ?? '');
$nom       = trim($_POST['nom']                 ?? '');
$prenom    = trim($_POST['prenom']              ?? '');
$ddn       = trim($_POST['date_naissance']      ?? '');
$pere      = trim($_POST['prenom_pere']         ?? '');
$grandpere = trim($_POST['prenom_grandpere']    ?? '');
$mere      = trim($_POST['nom_mere']            ?? '');
$email     = strtolower(trim($_POST['email']    ?? ''));
$tel       = trim($_POST['telephone']           ?? '');
$wilaya    = trim($_POST['wilaya']              ?? '');
$pwd       = $_POST['mot_de_passe']             ?? '';
$confirm   = $_POST['confirmer_mot_de_passe']   ?? '';

if (!$nom)             $erreurs['nom']       = "Le nom est obligatoire.";
if (!$prenom)          $erreurs['prenom']    = "Le prénom est obligatoire.";
if (!val_nin($nin))    $erreurs['nin']       = "Le NIN doit comporter exactement 18 chiffres.";
if (!val_ddn($ddn))    $erreurs['ddn']       = "Vous devez avoir au moins 18 ans.";
if (!$pere)            $erreurs['pere']      = "Le prénom du père est obligatoire.";
if (!$grandpere)       $erreurs['grandpere'] = "Le prénom du grand-père est obligatoire.";
if (!$mere)            $erreurs['mere']      = "Le nom et prénom de la mère sont obligatoires.";
if (!val_email($email))$erreurs['email']     = "Adresse e-mail invalide.";
if (!val_tel($tel))    $erreurs['tel']       = "Numéro invalide (05/06/07 + 8 chiffres).";
if (!$wilaya || !ctype_digit($wilaya)) $erreurs['wilaya'] = "Veuillez sélectionner une wilaya.";
if (strlen($pwd)<8)    $erreurs['pwd']       = "Le mot de passe doit contenir au moins 8 caractères.";
if ($pwd !== $confirm) $erreurs['confirm']   = "Les mots de passe ne correspondent pas.";

if (empty($erreurs)) {
    $db   = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE nin=? OR email=?');
    $stmt->execute([$nin, $email]);
    if ((int)$stmt->fetchColumn() > 0) {
        $s2 = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE nin=?');
        $s2->execute([$nin]);
        if ((int)$s2->fetchColumn() > 0) $erreurs['nin']   = "Ce NIN est déjà enregistré.";
        else                             $erreurs['email'] = "Cette adresse e-mail est déjà utilisée.";
    }
}

if (!empty($erreurs)) {
    $_SESSION['erreurs_inscription'] = $erreurs;
    $_SESSION['old_input'] = array_diff_key($_POST, ['mot_de_passe'=>1,'confirmer_mot_de_passe'=>1]);
    header('Location: Register.html'); exit;
}

$db = get_db();
$db->prepare(
    'INSERT INTO utilisateurs
     (nin,nom,prenom,prenom_pere,prenom_grandpere,nom_mere,
      date_naissance,email,telephone,id_wilaya,mot_de_passe,role,etat_compte)
     VALUES(:nin,:nom,:prenom,:pere,:gp,:mere,:ddn,:email,:tel,:wil,SHA2(:pwd,256),2,3)'
)->execute([
    ':nin'=>$nin,':nom'=>$nom,':prenom'=>$prenom,':pere'=>$pere,
    ':gp'=>$grandpere,':mere'=>$mere,':ddn'=>$ddn,':email'=>$email,
    ':tel'=>preg_replace('/\s/','',$tel),':wil'=>(int)$wilaya,':pwd'=>$pwd
]);

// Notifier les admins
$id_new = (int)$db->lastInsertId();
$admins = $db->query('SELECT id_utilisateur FROM utilisateurs WHERE role=1')->fetchAll(PDO::FETCH_COLUMN);
$sn = $db->prepare('INSERT INTO notifications (id_utilisateur,type_notif,message) VALUES(?,?,?)');
foreach ($admins as $aid) {
    $sn->execute([$aid,'nouveau_compte',"Nouveau compte en attente : $nom $prenom (NIN: $nin)."]);
}

$_SESSION['inscription_ok']  = true;
$_SESSION['new_user_nom']    = $nom.' '.$prenom;
header('Location: inscriptionsuccess.php');
exit;