<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
requireSettingsAccess();
$user = getCurrentUser();

requirePlan('equipe');
$planLimits  = getPlanLimits($user['plan'] ?? 'gratuit');
$maxMembers  = $planLimits['team_members'];

$stmt = $pdo->prepare("SELECT * FROM team_members WHERE user_id=? ORDER BY poste,nom");
$stmt->execute([$user['id']]);
$membres = $stmt->fetchAll();

$postes = ['gerant'=>'Gérant','directeur'=>'Directeur','commercial'=>'Commercial',
           'comptable'=>'Comptable','technicien'=>'Technicien','assistant'=>'Assistant',
           'rh'=>'RH','autre'=>'Autre'];

$posteColors = ['gerant'=>'#7C3AED','directeur'=>'#D97706','commercial'=>'#059669',
                'comptable'=>'#D97706','technicien'=>'#0891B2','assistant'=>'#DB2777',
                'rh'=>'#4F46E5','autre'=>'#6B7280'];

$modules = [
    'factures'   => 'Factures',
    'devis'      => 'Devis',
    'clients'    => 'Clients',
    'rapports'   => 'Rapports',
    'historique' => 'Historique',
    'export'     => 'Export',
    'parametres' => 'Paramètres',
];

$permLevels = ['none'=>'Aucun','read'=>'Lecteur','write'=>'Écriture','admin'=>'Tous'];
$permColors = ['none'=>'#9CA3AF','read'=>'#0891B2','write'=>'#059669','admin'=>'#D97706'];
$permIcons  = ['none'=>'🚫','read'=>'👁️','write'=>'✏️','admin'=>'⚡'];

$nbActifs = count(array_filter($membres, fn($m) => $m['statut']==='actif'));
$pageTitle = 'Mon Équipe';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:#111827;margin:0;">👨‍💼 Mon Équipe</h1>
        <p style="font-size:12px;color:#6B7280;margin:4px 0 0;"><?= count($membres) ?> membre(s) · <?= $nbActifs ?> actif(s)
            <?php if ($maxMembers < 999999): ?> · <span style="color:#D97706;font-weight:600;">Max <?= $maxMembers ?> (plan <?= ucfirst($user['plan']) ?>)</span><?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:10px;">
        <?php if ($membres): ?>
        <a href="<?= APP_URL ?>/equipe-stats.php" class="btn-secondary">📊 Statistiques</a>
        <?php endif; ?>
        <?php if ($maxMembers === 0): ?>
        <button onclick="ffAlert('🔒 Plan requis','La gestion d\'équipe nécessite le plan Pro.','warning')" class="btn-secondary" style="opacity:.6;">+ Ajouter un membre</button>
        <?php elseif (count($membres) >= $maxMembers): ?>
        <button onclick="ffAlert('⭐ Limite atteinte','Vous avez atteint la limite de <?= $maxMembers ?> membre(s) pour votre plan.','warning')" class="btn-secondary" style="opacity:.6;">+ Ajouter un membre</button>
        <?php else: ?>
        <button onclick="openModal()" class="btn-primary">+ Ajouter un membre</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($maxMembers === 0): ?>
<!-- Plan upgrade banner -->
<div style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border:2px solid #FDE68A;border-radius:16px;padding:28px;text-align:center;margin-bottom:20px;">
    <div style="font-size:48px;margin-bottom:12px;">🔒</div>
    <h2 style="font-size:18px;font-weight:700;color:#92400E;margin:0 0 8px;">Fonctionnalité réservée aux plans payants</h2>
    <p style="color:#B45309;font-size:13px;margin:0 0 20px;">Ajoutez des collaborateurs, définissez leurs rôles et permissions d'accès à partir du plan Starter.</p>
    <a href="<?= APP_URL ?>/parametres.php#plans" class="btn-primary">⭐ Voir les plans →</a>
</div>
<?php endif; ?>

<?php if ($maxMembers > 0): ?>

<!-- Stat cards par rôle -->
<?php if ($membres): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
    <?php
    $byPoste = [];
    foreach ($membres as $m) $byPoste[$m['poste']] = ($byPoste[$m['poste']]??0)+1;
    foreach ($byPoste as $p=>$nb):
        $col = $posteColors[$p]??'#6B7280';
    ?>
    <div class="stat-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px;">
        <div style="width:38px;height:38px;border-radius:10px;background:<?= $col ?>22;display:flex;align-items:center;justify-content:center;font-size:18px;">👤</div>
        <div>
            <p style="font-size:20px;font-weight:800;color:#111827;margin:0;"><?= $nb ?></p>
            <p style="font-size:11px;color:#6B7280;margin:0;"><?= $postes[$p]??ucfirst($p) ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($membres)): ?>
<div class="card" style="padding:60px;text-align:center;">
    <p style="font-size:48px;margin:0 0 12px;">👥</p>
    <p style="font-weight:600;color:#374151;font-size:15px;margin:0 0 6px;">Aucun membre dans votre équipe</p>
    <p style="color:#9CA3AF;font-size:13px;margin:0 0 16px;">Ajoutez vos collaborateurs et définissez leurs rôles et accès.</p>
    <button onclick="openModal()" class="btn-primary">+ Ajouter le premier membre</button>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
    <?php foreach ($membres as $m):
        $perms = json_decode($m['permissions']??'{}', true) ?: [];
        $col   = $posteColors[$m['poste']] ?? '#6B7280';
        $initials = strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1));
        $hasLogin = !empty($m['email']) && !empty($m['password']);
    ?>
    <div class="card" style="padding:20px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow=''">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,<?= $col ?>,<?= $col ?>cc);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:16px;flex-shrink:0;box-shadow:0 3px 8px <?= $col ?>44;">
                    <?= h($initials) ?>
                </div>
                <div>
                    <p style="font-weight:700;color:#111827;font-size:14px;margin:0;"><?= h(trim($m['prenom'].' '.$m['nom'])) ?></p>
                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:<?= $col ?>20;color:<?= $col ?>;margin-top:3px;">
                        <?= $postes[$m['poste']]??ucfirst($m['poste']) ?>
                    </span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                <div style="display:flex;align-items:center;gap:5px;">
                    <div style="width:7px;height:7px;border-radius:50%;background:<?= $m['statut']==='actif'?'#10B981':'#D1D5DB' ?>;"></div>
                    <span style="font-size:11px;color:#9CA3AF;"><?= $m['statut']==='actif'?'Actif':'Inactif' ?></span>
                </div>
                <?php if ($hasLogin): ?>
                <span style="font-size:10px;background:#D1FAE5;color:#059669;padding:2px 7px;border-radius:6px;font-weight:600;">🔑 Accès activé</span>
                <?php else: ?>
                <span style="font-size:10px;background:#F3F4F6;color:#9CA3AF;padding:2px 7px;border-radius:6px;">Sans connexion</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($m['email']): ?>
        <p style="font-size:12.5px;color:#6B7280;margin:0 0 3px;">📧 <?= h($m['email']) ?><?= $hasLogin ? ' <span style="font-size:10.5px;color:#059669;font-weight:600;">(connexion active)</span>' : '' ?></p>
        <?php endif; ?>
        <?php if ($m['telephone']): ?>
        <p style="font-size:12.5px;color:#6B7280;margin:0 0 3px;">📞 <?= h($m['telephone']) ?></p>
        <?php endif; ?>

        <!-- Permissions -->
        <div style="border-top:1px solid #F3F4F6;padding-top:10px;margin-top:10px;">
            <p style="font-size:10px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;">Accès modules</p>
            <div style="display:flex;flex-wrap:wrap;gap:5px;">
                <?php foreach ($modules as $key=>$label):
                    $lvl = $perms[$key] ?? 'none';
                    if ($lvl === 'none') continue;
                    $bg = $permColors[$lvl].'20';
                    $tc = $permColors[$lvl];
                    $ic = $permIcons[$lvl];
                ?>
                <span style="padding:3px 9px;border-radius:7px;font-size:11px;font-weight:600;background:<?= $bg ?>;color:<?= $tc ?>;display:inline-flex;align-items:center;gap:3px;">
                    <?= $ic ?> <?= $label ?>
                </span>
                <?php endforeach; ?>
                <?php if (empty(array_filter($perms, fn($v)=>$v!=='none'))): ?>
                <span style="font-size:11px;color:#D1D5DB;">Aucun accès configuré</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:14px;">
            <button onclick='editMembre(<?= json_encode($m) ?>)' class="btn-secondary" style="flex:1;justify-content:center;font-size:12px;padding:7px 12px;">✏️ Modifier</button>
            <button onclick="confirmDelete(<?= $m['id'] ?>,'<?= addslashes(h($m['nom'])) ?>')" style="font-size:12px;padding:7px 12px;background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;cursor:pointer;color:#DC2626;transition:all .15s;" onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='#FEF2F2'">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; // maxMembers > 0 ?>

<!-- ══ MODAL MEMBRE ══ -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto;">
    <div style="background:white;border-radius:20px;width:100%;max-width:660px;margin:20px auto;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <!-- Header -->
        <div style="padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;gap:12px;">
            <div id="modalAvatar" style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#D97706,#F59E0B);display:flex;align-items:center;justify-content:center;color:#1C1917;font-weight:800;font-size:18px;">👤</div>
            <div style="flex:1;">
                <h3 style="font-weight:700;color:#111827;font-size:16px;margin:0;" id="modalTitle">Nouveau membre</h3>
                <p style="font-size:12px;color:#9CA3AF;margin:2px 0 0;" id="modalSub">Renseignez les informations du collaborateur</p>
            </div>
            <button onclick="closeModal()" style="background:#F3F4F6;border:none;color:#6B7280;font-size:20px;cursor:pointer;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;line-height:1;">×</button>
        </div>

        <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
            <input type="hidden" id="mId">

            <!-- Section Identité -->
            <div style="background:#FAFAFA;border-radius:12px;padding:16px;">
                <p style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.7px;margin:0 0 12px;">👤 Identité</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Prénom</label>
                        <input type="text" id="mPrenom" placeholder="Prénom"
                               style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Nom *</label>
                        <input type="text" id="mNom" placeholder="Nom de famille"
                               style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Email professionnel</label>
                        <input type="email" id="mEmail" placeholder="email@exemple.com"
                               style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Téléphone</label>
                        <input type="text" id="mTel" placeholder="+212 6 XX XX XX XX"
                               style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Poste / Rôle</label>
                        <select id="mPoste" onchange="applyPostePermissions(this.value)" style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                            <?php foreach ($postes as $v=>$l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Statut</label>
                        <select id="mStatut" style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                            <option value="actif">✅ Actif</option>
                            <option value="inactif">⏸️ Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section Connexion -->
            <div style="background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:12px;padding:16px;">
                <p style="font-size:11px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:.7px;margin:0 0 4px;">🔑 Accès application</p>
                <p style="font-size:12px;color:#B45309;margin:0 0 12px;">L'email professionnel ci-dessus sera utilisé pour la connexion. Définissez un mot de passe pour activer l'accès.</p>
                <div>
                    <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Mot de passe <span id="pwdNote" style="color:#9CA3AF;">(laisser vide = inchangé)</span></label>
                    <input type="password" id="mPassword" placeholder="Nouveau mot de passe (min 6 caractères)…"
                           style="width:100%;padding:9px 12px;border:1.5px solid #FDE68A;border-radius:10px;font-size:13px;background:white;box-sizing:border-box;">
                </div>
            </div>

            <!-- Section Permissions -->
            <div>
                <p style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.7px;margin:0 0 12px;">🛡️ Permissions d'accès</p>
                <div id="permLock" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:#EDE9FE;border:1.5px solid #C4B5FD;border-radius:10px;margin-bottom:10px;">
                    <span style="font-size:18px;">🔒</span>
                    <div>
                        <p style="font-size:12.5px;font-weight:700;color:#5B21B6;margin:0;">Accès complet automatique</p>
                        <p style="font-size:11.5px;color:#7C3AED;margin:0;">Le rôle Gérant / Directeur bénéficie de tous les droits. Non modifiable.</p>
                    </div>
                </div>
                <div style="border:1.5px solid #E5E7EB;border-radius:12px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead style="background:#F9FAFB;">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:#374151;">Module</th>
                                <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:#9CA3AF;width:72px;">🚫 Aucun</th>
                                <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:#0891B2;width:72px;">👁️ Lecture</th>
                                <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:#059669;width:72px;">✏️ Écriture</th>
                                <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:#D97706;width:72px;">⚡ Tous</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $key=>$label): ?>
                            <tr style="border-top:1px solid #F3F4F6;" onmouseover="this.style.background='#FFFBEB'" onmouseout="this.style.background=''">
                                <td style="padding:10px 14px;font-size:13px;color:#374151;font-weight:500;"><?= $label ?></td>
                                <td style="text-align:center;padding:10px 8px;">
                                    <input type="radio" name="perm_<?= $key ?>" value="none" checked style="width:16px;height:16px;accent-color:#9CA3AF;">
                                </td>
                                <td style="text-align:center;padding:10px 8px;">
                                    <input type="radio" name="perm_<?= $key ?>" value="read" style="width:16px;height:16px;accent-color:#0891B2;">
                                </td>
                                <td style="text-align:center;padding:10px 8px;">
                                    <input type="radio" name="perm_<?= $key ?>" value="write" style="width:16px;height:16px;accent-color:#059669;">
                                </td>
                                <td style="text-align:center;padding:10px 8px;">
                                    <input type="radio" name="perm_<?= $key ?>" value="admin" style="width:16px;height:16px;accent-color:#D97706;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="font-size:11px;color:#9CA3AF;margin:6px 0 0;">⚡ <strong>Tous</strong> = accès complet : lecture, création, modification et suppression</p>
            </div>

            <!-- Notes -->
            <div>
                <label style="font-size:12px;color:#374151;font-weight:500;display:block;margin-bottom:5px;">Notes internes</label>
                <textarea id="mNotes" rows="2" placeholder="Responsabilités, remarques..."
                          style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;background:#FAFAFA;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
        </div>

        <div style="padding:16px 24px;border-top:1px solid #F3F4F6;display:flex;gap:10px;background:#FAFAFA;border-radius:0 0 20px 20px;">
            <button onclick="saveMembre()" class="btn-primary" style="flex:1;justify-content:center;">💾 Enregistrer le membre</button>
            <button onclick="closeModal()" class="btn-secondary">Annuler</button>
        </div>
    </div>
</div>

<script>
const MODULES = <?= json_encode(array_keys($modules)) ?>;
const PRIVILEGED_POSTES = ['gerant', 'directeur'];

function applyPostePermissions(poste) {
    const isPrivileged = PRIVILEGED_POSTES.includes(poste);
    const permSection  = document.getElementById('permSection');
    const permLock     = document.getElementById('permLock');
    MODULES.forEach(m => {
        const radios = document.querySelectorAll(`input[name="perm_${m}"]`);
        if (isPrivileged) {
            radios.forEach(r => { r.checked = (r.value === 'admin'); r.disabled = true; });
        } else {
            radios.forEach(r => { r.disabled = false; });
        }
    });
    if (permLock) permLock.style.display = isPrivileged ? 'flex' : 'none';
}

function openModal() {
    document.getElementById('mId').value = '';
    document.getElementById('modalTitle').textContent = 'Nouveau membre';
    document.getElementById('modalSub').textContent   = 'Renseignez les informations du collaborateur';
    document.getElementById('modalAvatar').textContent = '👤';
    document.getElementById('pwdNote').textContent    = '(minimum 6 caractères)';
    ['mPrenom','mNom','mEmail','mTel','mNotes','mPassword'].forEach(id => {
        const el = document.getElementById(id); if(el) el.value = '';
    });
    document.getElementById('mPoste').value  = 'commercial';
    document.getElementById('mStatut').value = 'actif';
    MODULES.forEach(m => { const r = document.querySelector(`input[name="perm_${m}"][value="read"]`); if(r) r.checked=true; });
    applyPostePermissions('commercial');
    document.getElementById('modal').style.display = 'flex';
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }

function editMembre(m) {
    document.getElementById('mId').value         = m.id;
    document.getElementById('mPrenom').value     = m.prenom || '';
    document.getElementById('mNom').value        = m.nom || '';
    document.getElementById('mEmail').value      = m.email || '';
    document.getElementById('mTel').value        = m.telephone || '';
    document.getElementById('mPoste').value      = m.poste || 'commercial';
    document.getElementById('mStatut').value     = m.statut || 'actif';
    document.getElementById('mNotes').value      = m.notes || '';
    document.getElementById('mPassword').value = '';
    document.getElementById('modalTitle').textContent = '✏️ Modifier ' + m.nom;
    document.getElementById('modalSub').textContent   = 'Mettez à jour les informations';
    document.getElementById('pwdNote').textContent    = '(laisser vide = mot de passe inchangé)';

    const perms = m.permissions ? (typeof m.permissions === 'string' ? JSON.parse(m.permissions) : m.permissions) : {};
    MODULES.forEach(mod => {
        const val = perms[mod] || 'none';
        const r = document.querySelector(`input[name="perm_${mod}"][value="${val}"]`);
        if (r) r.checked = true;
    });
    applyPostePermissions(m.poste || 'commercial');
    document.getElementById('modal').style.display = 'flex';
}

async function saveMembre() {
    const nom = document.getElementById('mNom').value.trim();
    if (!nom) { ffAlert('⚠️ Champ requis','Le nom du membre est obligatoire.','warning'); return; }

    const email    = document.getElementById('mEmail').value.trim();
    const password = document.getElementById('mPassword').value;
    if (email && !document.getElementById('mId').value && !password) {
        ffAlert('⚠️ Mot de passe requis','Définissez un mot de passe pour activer l\'accès à l\'application.','warning');
        return;
    }
    if (password && password.length < 6) {
        ffAlert('⚠️ Mot de passe trop court','Le mot de passe doit contenir au moins 6 caractères.','warning');
        return;
    }

    const permissions = {};
    MODULES.forEach(m => {
        const r = document.querySelector(`input[name="perm_${m}"]:checked`);
        permissions[m] = r ? r.value : 'none';
    });

    const payload = {
        id: document.getElementById('mId').value || 0,
        prenom: document.getElementById('mPrenom').value.trim(),
        nom,
        email:      document.getElementById('mEmail').value.trim(),
        telephone:  document.getElementById('mTel').value.trim(),
        poste:      document.getElementById('mPoste').value,
        statut:     document.getElementById('mStatut').value,
        notes:       document.getElementById('mNotes').value.trim(),
        password,
        permissions
    };

    try {
        const res  = await fetch(APP_URL+'/api/teams.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const json = await res.json();
        if (json.success) {
            closeModal();
            showToast('✅ Membre enregistré avec succès !');
            setTimeout(()=>location.reload(), 900);
        } else {
            ffAlert('❌ Erreur', json.error || 'Une erreur est survenue.', 'error');
        }
    } catch(e) {
        ffAlert('❌ Erreur réseau','Impossible de joindre le serveur.','error');
    }
}

function confirmDelete(id, nom) {
    ffConfirm(
        'Supprimer le membre',
        `Voulez-vous vraiment retirer ${nom} de l'équipe ? Cette action est irréversible.`,
        () => deleteMembre(id),
        true
    );
}

async function deleteMembre(id) {
    try {
        const res  = await fetch(APP_URL+'/api/teams.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const json = await res.json();
        if (json.success) { showToast('✅ Membre supprimé'); setTimeout(()=>location.reload(),900); }
        else ffAlert('❌ Erreur', json.error || 'Suppression impossible.', 'error');
    } catch(e) {
        ffAlert('❌ Erreur réseau','Impossible de joindre le serveur.','error');
    }
}

document.getElementById('modal').addEventListener('click', e => {
    if (e.target === document.getElementById('modal')) closeModal();
});
</script>

</div></div></body></html>
