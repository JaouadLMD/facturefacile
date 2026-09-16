<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(f.id) as nb_factures, COALESCE(SUM(f.total),0) as ca_total
    FROM clients c
    LEFT JOIN factures f ON c.id = f.client_id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.nom
");
$stmt->execute([$user['id']]);
$clients = $stmt->fetchAll();

$pageTitle = 'Mes Clients';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;">👥 Mes Clients</h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;"><?= count($clients) ?> client(s)</p>
    </div>
    <button onclick="openModal()" class="btn-primary">+ Nouveau client</button>
</div>

<?php if (empty($clients)): ?>
<div class="card" style="padding:48px;text-align:center;">
    <p style="font-size:40px;margin:0 0 10px;">👥</p>
    <p style="color:var(--text);font-weight:600;font-size:14px;">Aucun client enregistré</p>
    <p style="color:var(--text-soft);font-size:13px;margin:4px 0 0;">Ajoutez vos clients pour les retrouver rapidement lors de la facturation.</p>
    <button onclick="openModal()" class="btn-primary" style="margin-top:14px;">+ Ajouter un client</button>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($clients as $c): ?>
    <div class="card" style="padding:20px;transition:box-shadow .2s,transform .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(0,0,0,0.25)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;border-radius:12px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--primary-2);font-size:18px;flex-shrink:0;">
                    <?= strtoupper(mb_substr($c['nom'], 0, 1)) ?>
                </div>
                <div>
                    <p style="font-weight:600;color:var(--text);margin:0;font-size:14px;"><?= h($c['nom']) ?></p>
                    <?php if ($c['ville']): ?><p style="font-size:12px;color:var(--text-soft);margin:2px 0 0;"><?= h($c['ville']) ?></p><?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:4px;">
                <button onclick='editClient(<?= json_encode($c) ?>)' title="Modifier"
                        style="padding:5px 8px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-mid);font-size:13px;transition:all .15s;" onmouseover="this.style.color='var(--primary-2)';this.style.background='var(--primary-bg)';this.style.borderColor='var(--primary)'" onmouseout="this.style.color='';this.style.background='';this.style.borderColor=''">✏️</button>
                <button onclick="deleteClient(<?= $c['id'] ?>, '<?= h($c['nom']) ?>')" title="Supprimer"
                        style="padding:5px 8px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-mid);font-size:13px;transition:all .15s;" onmouseover="this.style.color='#EF4444';this.style.background='rgba(239,68,68,.1)';this.style.borderColor='rgba(239,68,68,.3)'" onmouseout="this.style.color='';this.style.background='';this.style.borderColor=''">🗑️</button>
            </div>
        </div>
        <?php if ($c['email']): ?>
        <p style="font-size:12.5px;color:var(--text-mid);margin:0 0 4px;">📧 <?= h($c['email']) ?></p>
        <?php endif; ?>
        <?php if ($c['telephone']): ?>
        <p style="font-size:12.5px;color:var(--text-mid);margin:0 0 4px;">📞 <?= h($c['telephone']) ?></p>
        <?php endif; ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;color:var(--text-soft);"><?= $c['nb_factures'] ?> facture(s)</span>
            <span style="font-size:12px;font-weight:700;color:var(--primary-2);"><?= formatMontant((float)$c['ca_total'], $user['devise']??'MAD') ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,0.5);width:100%;max-width:520px;animation:ffPop .2s ease;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-weight:700;color:var(--text);font-size:15px;margin:0;" id="modalTitle">Nouveau client</h3>
            <button onclick="closeModal()" style="background:none;border:none;color:var(--text-soft);font-size:22px;cursor:pointer;line-height:1;">×</button>
        </div>
        <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" id="clientId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Nom *</label>
                    <input type="text" id="fNom" placeholder="Nom du client" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Email</label>
                    <input type="email" id="fEmail" placeholder="email@client.com" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Téléphone</label>
                    <input type="text" id="fTel" placeholder="+212 6 XX XX XX XX" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Ville</label>
                    <input type="text" id="fVille" placeholder="Casablanca" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">ICE</label>
                    <input type="text" id="fIce" placeholder="ICE (entreprises)" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Adresse</label>
                    <input type="text" id="fAdresse" placeholder="Adresse complète" style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Notes</label>
                    <textarea id="fNotes" rows="2" placeholder="Notes internes..." style="width:100%;padding:9px 14px;font-size:13px;resize:none;"></textarea>
                </div>
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;">
            <button onclick="saveClient()" class="btn-primary" style="flex:1;justify-content:center;">💾 Enregistrer</button>
            <button onclick="closeModal()" class="btn-secondary">Annuler</button>
        </div>
    </div>
</div>

<script>

function openModal() {
    document.getElementById('clientId').value = '';
    document.getElementById('modalTitle').textContent = 'Nouveau client';
    ['fNom','fEmail','fTel','fVille','fIce','fAdresse','fNotes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('fNom').focus();
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }

function editClient(client) {
    document.getElementById('clientId').value  = client.id;
    document.getElementById('modalTitle').textContent = 'Modifier le client';
    document.getElementById('fNom').value      = client.nom || '';
    document.getElementById('fEmail').value    = client.email || '';
    document.getElementById('fTel').value      = client.telephone || '';
    document.getElementById('fVille').value    = client.ville || '';
    document.getElementById('fIce').value      = client.ice || '';
    document.getElementById('fAdresse').value  = client.adresse || '';
    document.getElementById('fNotes').value    = client.notes || '';
    document.getElementById('modal').style.display = 'flex';
}

async function saveClient() {
    const nom = document.getElementById('fNom').value.trim();
    if (!nom) { ffAlert('⚠️ Champ requis', 'Le nom du client est obligatoire.', 'warning'); return; }
    const payload = {
        id:        document.getElementById('clientId').value || 0,
        nom,
        email:     document.getElementById('fEmail').value.trim(),
        telephone: document.getElementById('fTel').value.trim(),
        ville:     document.getElementById('fVille').value.trim(),
        ice:       document.getElementById('fIce').value.trim(),
        adresse:   document.getElementById('fAdresse').value.trim(),
        notes:     document.getElementById('fNotes').value.trim(),
    };
    const res  = await fetch(APP_URL+'/api/clients.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const json = await res.json();
    if (json.success) { closeModal(); location.reload(); }
    else showToast('❌ Erreur : '+json.error, false);
}

async function deleteClient(id, nom) {
    ffConfirm('Supprimer le client', `Supprimer le client "${nom}" ?\nSes factures ne seront pas supprimées.`, async () => {
        const res  = await fetch(APP_URL+'/api/clients.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const json = await res.json();
        if (json.success) location.reload();
        else showToast('❌ Erreur lors de la suppression.', false);
    }, true);
}

document.getElementById('modal').addEventListener('click', e => {
    if (e.target === document.getElementById('modal')) closeModal();
});
</script>

</div></div></body></html>
