/* ============================================
   admin.js — Shared Admin JavaScript
   Tirage au Sort — Plateforme Hadjj 2026
   ============================================ */

/* ---- User actions ---- */
function validateUser(btn) {
  if (!btn) return;     
  const row = btn.closest('tr');
  const badge = row.querySelector('.badge');
  badge.className = 'badge badge-valid';
  badge.textContent = 'Validé';
  row.dataset.status = 'valide';
  const actions = row.querySelector('.tbl-actions');
  actions.innerHTML = `
    <button class="btn btn-warn btn-sm" onclick="blockUser(this)">⊘ Bloquer</button>
    <button class="btn btn-danger btn-sm" onclick="deleteUser(this)">🗑 Suppr.</button>
    <button class="btn btn-ghost btn-sm" onclick="openUserDetail()">👁 Détail</button>`;
}

function blockUser(btn) {
  if (!btn) return;
  const row = btn.closest('tr');
  const badge = row.querySelector('.badge');
  badge.className = 'badge badge-blocked';
  badge.textContent = 'Bloqué';
  row.dataset.status = 'bloque';
  const actions = row.querySelector('.tbl-actions');
  actions.innerHTML = `
    <button class="btn btn-primary btn-sm" onclick="validateUser(this)">✓ Débloquer</button>
    <button class="btn btn-danger btn-sm" onclick="deleteUser(this)">🗑 Suppr.</button>
    <button class="btn btn-ghost btn-sm" onclick="openUserDetail()">👁 Détail</button>`;
}

function deleteUser(btn) {
  if (confirm('Supprimer cet utilisateur définitivement ?')) {
    btn.closest('tr').remove();
  }
}

function openUserDetail() {
  document.getElementById('userDetailModal').classList.add('open');
}

/* ---- Table filters ---- */
function filterUsers(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('#usersTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterByStatus(status) {
  document.querySelectorAll('#usersTable tbody tr').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}

function filterResults(status) {
  document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.res === status) ? '' : 'none';
  });
}

/* ---- Launch tirage ---- */
function confirmLaunch() {
  document.getElementById('launchModal').classList.add('open');
}

function executeLaunch() {
  document.getElementById('launchModal').classList.remove('open');
  alert('Tirage au sort lancé avec succès ! Les résultats seront disponibles sous peu.');
}

/* ---- Close overlays on backdrop click ---- */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.overlay').forEach(ov => {
    ov.addEventListener('click', function (e) {
      if (e.target === this) this.classList.remove('open');
    });
  });
});