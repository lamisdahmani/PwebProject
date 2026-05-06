<?php
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

$db = get_db();
$stmt = $db->query(
    "SELECT u.id_utilisateur,u.nin,u.nom,u.prenom,u.date_naissance,u.email,u.etat_compte,
            w.nom_wilaya
     FROM utilisateurs u
     JOIN wilayas w ON w.id_wilaya=u.id_wilaya
     WHERE u.role=2
     ORDER BY u.date_creation DESC"
);
$users = $stmt->fetchAll();
$total = count($users);
$en_attente = count(array_filter($users, fn($u)=>(int)$u['etat_compte']===3));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Utilisateurs – Admin</title>
  <link rel="stylesheet" href="AdminView.css"/>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand"><h2>Tirage au sort</h2><span>Espace Admin</span></div>
    <div class="sidebar-divider"></div>
    <p class="sidebar-nav-label">Navigation</p>
    <nav class="sidebar-nav">
      <a href="Admindash.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="Adminusers.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Utilisateurs <?php if($en_attente>0): ?><span class="notif-count"><?=$en_attente?></span><?php endif; ?></a>
      <a href="Adminparametres.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Paramétrage</a>
      <a href="Adminreslts.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Résultats</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
        <div><div class="sidebar-username"><?=htmlspecialchars($_SESSION['user_nom'])?></div><div class="sidebar-role">Administrateur</div></div>
      </div>
      <button class="sidebar-logout-btn" onclick="document.getElementById('logoutModal').classList.add('open')">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </button>
    </div>
  </aside>

  <main class="main">
    <div class="page-header"><h1>Gestion des utilisateurs</h1><p>Validation, blocage et suppression des comptes</p></div>

    <div class="card">
      <div class="card-header">
        <div><h2>Liste des comptes</h2><p><?=$total?> comptes enregistrés</p></div>
      </div>
      <div style="padding:1rem 1.5rem 0;">
        <div class="toolbar">
          <div class="search-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a0b8a0" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Rechercher…" oninput="filtrer(this.value)">
          </div>
          <select class="filter-select" onchange="filtrerStatut(this.value)">
            <option value="all">Tous les statuts</option>
            <option value="3">En attente</option>
            <option value="1">Validé</option>
            <option value="2">Bloqué</option>
          </select>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="usersTable">
          <thead>
            <tr><th>NIN</th><th>Nom et prénom</th><th>Naissance</th><th>Wilaya</th><th>Email</th><th>Statut</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach($users as $u):
              $etat = (int)$u['etat_compte'];
              $badge_class = ['1'=>'badge-valid','2'=>'badge-blocked','3'=>'badge-waiting'][$etat] ?? 'badge-waiting';
              $badge_text  = ['1'=>'Validé','2'=>'Bloqué','3'=>'En attente'][$etat] ?? '?';
            ?>
            <tr data-id="<?=(int)$u['id_utilisateur']?>" data-statut="<?=$etat?>">
              <td><?=htmlspecialchars($u['nin'])?></td>
              <td><strong><?=htmlspecialchars($u['nom'].' '.$u['prenom'])?></strong></td>
              <td><?=$u['date_naissance']?date('d/m/Y',strtotime($u['date_naissance'])):'—'?></td>
              <td><?=htmlspecialchars($u['nom_wilaya'])?></td>
              <td><?=htmlspecialchars($u['email'])?></td>
              <td><span class="badge <?=$badge_class?>"><?=$badge_text?></span></td>
              <td>
                <div class="tbl-actions">
                  <?php if($etat===3): ?>
                    <button class="btn btn-primary btn-sm" onclick="action(this,'valider')">✓ Valider</button>
                    <button class="btn btn-warn btn-sm"    onclick="action(this,'bloquer')">⊘ Bloquer</button>
                  <?php elseif($etat===1): ?>
                    <button class="btn btn-warn btn-sm"    onclick="action(this,'bloquer')">⊘ Bloquer</button>
                    <button class="btn btn-danger btn-sm"  onclick="action(this,'supprimer')">🗑 Suppr.</button>
                  <?php elseif($etat===2): ?>
                    <button class="btn btn-primary btn-sm" onclick="action(this,'debloquer')">✓ Débloquer</button>
                    <button class="btn btn-danger btn-sm"  onclick="action(this,'supprimer')">🗑 Suppr.</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div id="logoutModal" class="overlay">
  <div class="modal">
    <div class="modal-icon" style="background:#fce4e4;color:#b71c1c;"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
    <h2>Se déconnecter ?</h2>
    <p>Êtes-vous sûr(e) de vouloir quitter votre session ?</p>
    <div class="modal-actions">
      <button class="btn-cancel-modal" onclick="document.getElementById('logoutModal').classList.remove('open')">Annuler</button>
      <a href="logout.php" class="btn-confirm-modal">Se déconnecter</a>
    </div>
  </div>
</div>

<script>
function action(btn, act) {
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (act==='supprimer' && !confirm('Supprimer définitivement ce compte ?')) return;
  fetch('AdminActions.php', {
    method:'POST',
    body: new URLSearchParams({action:act, id_utilisateur:id})
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.succes) location.reload();
    else alert('❌ '+data.message);
  });
}

function filtrer(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#usersTable tbody tr').forEach(r=>{
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filtrerStatut(statut) {
  document.querySelectorAll('#usersTable tbody tr').forEach(r=>{
    r.style.display = (statut==='all' || r.dataset.statut===statut) ? '' : 'none';
  });
}

document.querySelectorAll('.overlay').forEach(ov=>{
  ov.addEventListener('click',e=>{ if(e.target===ov) ov.classList.remove('open'); });
});
</script>
</body>
</html>