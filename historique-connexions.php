<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

if (isTeamMember() && !isPrivilegedMember()) {
    header('Location: ' . APP_URL . '/dashboard.php?access_denied=settings');
    exit;
}

$user      = getCurrentUser();
$pageTitle = 'Historique des connexions';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:#111827;margin:0;">🔐 Historique des connexions</h1>
        <p style="font-size:12px;color:#6B7280;margin:4px 0 0;">Toutes les sessions de votre espace — admin et membres d'équipe</p>
    </div>
    <button onclick="loadSessions()" class="btn-secondary" style="gap:6px;">🔄 Actualiser</button>
</div>

<!-- Filters -->
<div class="card" style="padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:8px;">
        <label style="font-size:12.5px;font-weight:600;color:#374151;">Filtrer :</label>
        <select id="filterType" onchange="applyFilter()" style="padding:6px 10px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:12.5px;color:#374151;">
            <option value="all">Tous les comptes</option>
            <option value="admin">Admin seulement</option>
            <option value="member">Membres seulement</option>
        </select>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        <select id="filterStatus" onchange="applyFilter()" style="padding:6px 10px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:12.5px;color:#374151;">
            <option value="all">Tous les statuts</option>
            <option value="active">En ligne</option>
            <option value="inactive">Déconnecté</option>
        </select>
    </div>
    <div id="sessionCount" style="margin-left:auto;font-size:12px;color:#9CA3AF;"></div>
</div>

<!-- Table -->
<div class="card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;" id="sessionsTable">
            <thead>
                <tr style="border-bottom:1.5px solid #F3F4F6;background:#FAFAFA;">
                    <th style="padding:12px 18px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Utilisateur</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Type</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Navigateur</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">IP</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Connexion</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Déconnexion</th>
                    <th style="padding:12px 14px;text-align:left;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Statut</th>
                    <th style="padding:12px 14px;text-align:right;font-size:11.5px;font-weight:700;color:#6B7280;white-space:nowrap;">Action</th>
                </tr>
            </thead>
            <tbody id="sessionsTbody">
                <tr><td colspan="8" style="padding:40px;text-align:center;color:#9CA3AF;font-size:13px;">⏳ Chargement…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Empty state -->
<div id="emptyState" style="display:none;text-align:center;padding:60px 20px;">
    <div style="font-size:48px;margin-bottom:12px;">🔐</div>
    <p style="font-weight:700;color:#374151;font-size:14px;margin:0 0 6px;">Aucune session enregistrée</p>
    <p style="font-size:12px;color:#9CA3AF;margin:0;">Les connexions apparaîtront ici automatiquement.</p>
</div>

<script>
let allSessions = [];

async function loadSessions() {
    document.getElementById('sessionsTbody').innerHTML = '<tr><td colspan="8" style="padding:40px;text-align:center;color:#9CA3AF;font-size:13px;">⏳ Chargement…</td></tr>';
    const res  = await fetch(APP_URL+'/api/sessions.php?action=list').catch(()=>null);
    if (!res) { showToast('❌ Erreur réseau',false); return; }
    const json = await res.json();
    if (!json.success) { showToast('❌ '+json.error,false); return; }
    allSessions = json.sessions || [];
    applyFilter();
}

function applyFilter() {
    const ft = document.getElementById('filterType').value;
    const fs = document.getElementById('filterStatus').value;

    let data = allSessions;
    if (ft === 'admin')  data = data.filter(s => s.account_type === 'admin');
    if (ft === 'member') data = data.filter(s => s.account_type === 'member');
    if (fs === 'active')   data = data.filter(s => s.is_active == 1);
    if (fs === 'inactive') data = data.filter(s => s.is_active == 0);

    document.getElementById('sessionCount').textContent = data.length + ' session(s)';
    renderTable(data);
}

function renderTable(sessions) {
    const tbody = document.getElementById('sessionsTbody');
    const empty = document.getElementById('emptyState');

    if (!sessions.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="padding:40px;text-align:center;color:#9CA3AF;font-size:13px;">Aucune session correspondante.</td></tr>';
        empty.style.display = 'none';
        return;
    }
    empty.style.display = 'none';

    tbody.innerHTML = sessions.map(s => {
        const name    = (s.account_name || '').trim();
        const initials = name.split(' ').map(w=>w.charAt(0).toUpperCase()).slice(0,2).join('');
        const isActive = s.is_active == 1;
        const isAdmin  = s.account_type === 'admin';

        const avatarBg = isAdmin
            ? 'linear-gradient(135deg,#D97706,#F59E0B)'
            : (s.member_couleur || '#6B7280');

        const loginDt  = formatDt(s.login_at);
        const logoutDt = s.logout_at ? formatDt(s.logout_at) : '—';

        const statusHtml = isActive
            ? `<span style="display:inline-flex;align-items:center;gap:5px;background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;"><span style="width:7px;height:7px;border-radius:50%;background:#10B981;"></span>En ligne</span>`
            : `<span style="display:inline-flex;align-items:center;gap:5px;background:#F3F4F6;color:#6B7280;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;"><span style="width:7px;height:7px;border-radius:50%;background:#D1D5DB;"></span>Déconnecté</span>`;

        const actionHtml = (!isAdmin && isActive)
            ? `<button onclick="disconnectUser(${s.team_member_id},'${escJs(name)}')"
                       style="padding:5px 12px;background:#FEE2E2;color:#DC2626;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;"
                       onmouseover="this.style.background='#FCA5A5'" onmouseout="this.style.background='#FEE2E2'">
                🔌 Déconnecter
               </button>`
            : '<span style="color:#D1D5DB;font-size:12px;">—</span>';

        return `<tr class="table-row" style="border-bottom:1px solid #F9FAFB;">
            <td style="padding:12px 18px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:${avatarBg};display:flex;align-items:center;justify-content:center;color:${isAdmin?'#1C1917':'white'};font-weight:700;font-size:12px;flex-shrink:0;">${escHtml(initials)}</div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#111827;margin:0;">${escHtml(name)}</p>
                        <p style="font-size:11px;color:#9CA3AF;margin:0;">${escHtml(s.account_email||'')}</p>
                    </div>
                </div>
            </td>
            <td style="padding:12px 14px;">
                <span style="font-size:12px;padding:2px 9px;border-radius:999px;font-weight:600;${isAdmin?'background:#FEF3C7;color:#92400E;':'background:#EDE9FE;color:#5B21B6;'}">
                    ${isAdmin?'⚡ Admin':'👤 Membre'}
                </span>
                ${!isAdmin && s.member_poste ? `<p style="font-size:11px;color:#9CA3AF;margin:2px 0 0;">${escHtml(s.member_poste)}</p>` : ''}
            </td>
            <td style="padding:12px 14px;font-size:12.5px;color:#374151;">${escHtml(s.browser||'Inconnu')}</td>
            <td style="padding:12px 14px;font-size:12px;color:#6B7280;font-family:monospace;">${escHtml(s.ip||'—')}</td>
            <td style="padding:12px 14px;font-size:12px;color:#374151;white-space:nowrap;">${loginDt}</td>
            <td style="padding:12px 14px;font-size:12px;color:#9CA3AF;white-space:nowrap;">${logoutDt}</td>
            <td style="padding:12px 14px;">${statusHtml}</td>
            <td style="padding:12px 14px;text-align:right;">${actionHtml}</td>
        </tr>`;
    }).join('');
}

function disconnectUser(teamMemberId, name) {
    ffConfirm(
        'Déconnecter ' + name,
        'Cette personne sera déconnectée lors de sa prochaine action dans l\'application.',
        async () => {
            const res  = await fetch(APP_URL+'/api/sessions.php?action=disconnect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({target_type:'team_member',target_id:teamMemberId})});
            const json = await res.json();
            if (json.success) { showToast('✅ Déconnexion programmée pour '+name); await loadSessions(); }
            else showToast('❌ '+json.error,false);
        }
    );
}

function formatDt(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ','T'));
    if (isNaN(d)) return str;
    const pad = n => String(n).padStart(2,'0');
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function escHtml(s) { if(!s) return ''; const d=document.createElement('div');d.textContent=s;return d.innerHTML; }
function escJs(s) { return s.replace(/'/g,"\\'"); }

loadSessions();
</script>

</div></div></body></html>
