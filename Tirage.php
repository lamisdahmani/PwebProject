<?php
// tirage.php — Algorithme du tirage au sort
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

header('Content-Type: application/json');

$db = get_db();

// 1. Trouver le tirage à lancer aujourd'hui
$stmt = $db->prepare("SELECT * FROM tirages WHERE etat IN(2,3) AND date_tirage=CURDATE() LIMIT 1");
$stmt->execute();
$tirage = $stmt->fetch();
if (!$tirage) {
    echo json_encode(['succes'=>false,'message'=>"Aucun tirage prévu aujourd'hui, ou déjà effectué."]);
    exit;
}
$id_tirage   = (int)$tirage['id_tirage'];
$nb_gagnants = (int)$tirage['nb_gagnants'];

// 2. Déjà effectué ?
$stmt = $db->prepare('SELECT COUNT(*) FROM resultats r JOIN inscriptions i ON i.id_inscription=r.id_inscription WHERE i.id_tirage=?');
$stmt->execute([$id_tirage]);
if ((int)$stmt->fetchColumn()>0) {
    echo json_encode(['succes'=>false,'message'=>"Ce tirage a déjà été effectué."]);
    exit;
}

// 3. Participants éligibles
$an_lim = (int)$tirage['annee'] - 5;
$stmt = $db->prepare(
    "SELECT i.id_inscription, i.id_utilisateur, i.nb_participations
     FROM inscriptions i
     JOIN utilisateurs u ON u.id_utilisateur=i.id_utilisateur
     WHERE i.id_tirage=:idt AND u.etat_compte=1
       AND u.id_utilisateur NOT IN (
           SELECT DISTINCT i2.id_utilisateur
           FROM resultats r2
           JOIN inscriptions i2 ON i2.id_inscription=r2.id_inscription
           JOIN tirages t2      ON t2.id_tirage=i2.id_tirage
           WHERE r2.gagnant=1 AND t2.annee>=:anlim
       )"
);
$stmt->execute([':idt'=>$id_tirage, ':anlim'=>$an_lim]);
$participants = $stmt->fetchAll();

if (empty($participants)) {
    echo json_encode(['succes'=>false,'message'=>"Aucun participant éligible."]);
    exit;
}

// 4. Construire l'urne pondérée
$urne = [];
foreach ($participants as $p) {
    $poids = max(1,(int)$p['nb_participations']);
    for ($i=0;$i<$poids;$i++) $urne[] = ['id_insc'=>(int)$p['id_inscription'],'id_user'=>(int)$p['id_utilisateur']];
}
shuffle($urne);

// 5. Tirer les gagnants (unique par utilisateur)
$gagnants_insc = [];
$users_gagnes  = [];
foreach ($urne as $billet) {
    if (count($gagnants_insc) >= $nb_gagnants) break;
    if (isset($users_gagnes[$billet['id_user']])) continue;
    $gagnants_insc[]                      = $billet['id_insc'];
    $users_gagnes[$billet['id_user']]     = true;
}

$tous_insc        = array_column($participants,'id_inscription');
$non_gagnants_insc = array_values(array_diff($tous_insc,$gagnants_insc));

// 6. Enregistrer (transaction)
$db->beginTransaction();
try {
    $sr = $db->prepare('INSERT INTO resultats (id_inscription,gagnant) VALUES(?,?)');
    foreach ($gagnants_insc    as $id) $sr->execute([$id,1]);
    foreach ($non_gagnants_insc as $id) $sr->execute([$id,0]);
    $db->prepare('UPDATE tirages SET etat=4, date_lancement=NOW() WHERE id_tirage=?')
       ->execute([$id_tirage]);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('Tirage: '.$e->getMessage());
    echo json_encode(['succes'=>false,'message'=>"Erreur lors de l'enregistrement."]);
    exit;
}

// 7. Notifications
$sn = $db->prepare('INSERT INTO notifications (id_utilisateur,type_notif,message) VALUES(?,?,?)');
// gagnants
if ($gagnants_insc) {
    $ph = implode(',',array_fill(0,count($gagnants_insc),'?'));
    $ug = $db->prepare("SELECT id_utilisateur FROM inscriptions WHERE id_inscription IN($ph)");
    $ug->execute($gagnants_insc);
    foreach ($ug->fetchAll(PDO::FETCH_COLUMN) as $uid)
        $sn->execute([$uid,'resultat_gagnant',"Félicitations ! Vous êtes sélectionné(e) pour le Hadj ".$tirage['annee']."."]);
}
// non-gagnants
if ($non_gagnants_insc) {
    $ph = implode(',',array_fill(0,count($non_gagnants_insc),'?'));
    $ug = $db->prepare("SELECT id_utilisateur FROM inscriptions WHERE id_inscription IN($ph)");
    $ug->execute($non_gagnants_insc);
    foreach ($ug->fetchAll(PDO::FETCH_COLUMN) as $uid)
        $sn->execute([$uid,'resultat_non_gagnant',"Tirage ".$tirage['annee']." : vous n'avez pas été sélectionné(e). Vos chances augmentent!"]);
}
// admins
foreach ($db->query('SELECT id_utilisateur FROM utilisateurs WHERE role=1')->fetchAll(PDO::FETCH_COLUMN) as $aid)
    $sn->execute([$aid,'tirage_effectue',"Tirage ".$tirage['annee']." effectué. ".count($gagnants_insc)." gagnants."]);

echo json_encode([
    'succes'      => true,
    'message'     => "Tirage effectué avec succès !",
    'annee'       => $tirage['annee'],
    'nb_gagnants' => count($gagnants_insc),
    'nb_perdants' => count($non_gagnants_insc),
    'total'       => count($participants),
]);