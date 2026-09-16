<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

// Plan limit check (skip when editing an existing devis)
$editId = (int)($_GET['edit'] ?? 0);
if ($editId === 0) {
    $limits = getPlanLimits($user['plan'] ?? 'gratuit');
    if ($limits['devis'] < 999999) {
        $stmtDC = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND MONTH(date_devis)=MONTH(CURDATE()) AND YEAR(date_devis)=YEAR(CURDATE())");
        $stmtDC->execute([$user['id']]);
        if ((int)$stmtDC->fetchColumn() >= $limits['devis']) {
            header('Location: ' . APP_URL . '/plans.php?msg=limit');
            exit;
        }
    }
}

// Si édition
$editDevis = null; $editItems = [];
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM devis WHERE id=? AND user_id=?");
    $stmt->execute([$editId, $user['id']]);
    $editDevis = $stmt->fetch();
    if ($editDevis) {
        $stmt2 = $pdo->prepare("SELECT * FROM devis_items WHERE devis_id=? ORDER BY id");
        $stmt2->execute([$editId]);
        $editItems = $stmt2->fetchAll();
    }
}

$stmtC = $pdo->prepare("SELECT id,nom,email,adresse,ice FROM clients WHERE user_id=? ORDER BY nom");
$stmtC->execute([$user['id']]);
$clients = $stmtC->fetchAll();
$devise  = $user['devise'] ?? 'MAD';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/devis.php" style="color:var(--text-mid);text-decoration:none;">Devis</a>
    <span>›</span>
    <span style="color:var(--text);font-weight:500;"><?= $editDevis ? 'Modifier '.$editDevis['numero'] : 'Nouveau devis' ?></span>
</div>

<h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 20px;">📋 <?= $editDevis ? 'Modifier le devis' : 'Nouveau devis' ?></h1>

<div class="card" style="overflow:hidden;">
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">

        <!-- Client -->
        <div>
            <h3 style="font-size:13px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Client</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Client existant</label>
                    <select id="clientSelect" onchange="fillClient()" style="width:100%;padding:9px 14px;font-size:13px;">
                        <option value="">-- Choisir un client --</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" data-nom="<?= h($c['nom']) ?>" data-email="<?= h($c['email']) ?>" data-adresse="<?= h($c['adresse']) ?>" data-ice="<?= h($c['ice']) ?>"
                            <?= ($editDevis && $editDevis['client_id']==$c['id']) ? 'selected' : '' ?>>
                            <?= h($c['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Nom du client *</label>
                    <input type="text" id="clientNom" value="<?= h($editDevis['client_nom']??'') ?>" placeholder="Nom du client"
                           style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Email client</label>
                    <input type="email" id="clientEmail" value="<?= h($editDevis['client_email']??'') ?>" placeholder="email@client.com"
                           style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Statut</label>
                    <select id="statut" style="width:100%;padding:9px 14px;font-size:13px;">
                        <?php foreach(['brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($editDevis['statut']??'brouillon')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Dates -->
        <div>
            <h3 style="font-size:13px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Dates</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Date du devis *</label>
                    <input type="date" id="dateDevis" value="<?= $editDevis['date_devis']??date('Y-m-d') ?>"
                           style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Date de validité</label>
                    <input type="date" id="dateValidite" value="<?= $editDevis['date_validite']??date('Y-m-d', strtotime('+30 days')) ?>"
                           style="width:100%;padding:9px 14px;font-size:13px;">
                </div>
            </div>
        </div>

        <!-- Items -->
        <div>
            <h3 style="font-size:13px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Prestations / Produits</h3>
            <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
                <thead style="background:var(--bg3);border-bottom:1px solid var(--border);">
                    <tr>
                        <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--text-mid);text-transform:uppercase;">Description</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:var(--text-mid);text-transform:uppercase;width:70px;">Qté</th>
                        <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:600;color:var(--text-mid);text-transform:uppercase;width:120px;">Prix U.</th>
                        <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:600;color:var(--text-mid);text-transform:uppercase;width:80px;">TVA %</th>
                        <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:600;color:var(--text-mid);text-transform:uppercase;width:120px;">Total</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>
            <button onclick="addItem()" style="width:100%;padding:9px;border:1.5px dashed var(--primary);border-radius:10px;background:none;color:var(--primary-2);font-size:13px;font-weight:600;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.background='var(--primary-bg)'" onmouseout="this.style.background='none'">
                + Ajouter une ligne
            </button>

        </div>

        <!-- Totaux -->
        <div style="display:flex;justify-content:flex-end;">
            <div style="width:260px;background:var(--bg3);border-radius:12px;padding:16px;border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:13px;color:var(--text-mid);">Sous-total HT</span>
                    <span style="font-size:13px;font-weight:600;" id="totalHT">0,00 <?= h($devise) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:13px;color:var(--text-mid);">TVA</span>
                    <span style="font-size:13px;font-weight:600;" id="totalTVA">0,00 <?= h($devise) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 0 4px;">
                    <span style="font-size:14px;font-weight:700;">Total TTC</span>
                    <span style="font-size:16px;font-weight:800;color:var(--primary-2);" id="totalTTC">0,00 <?= h($devise) ?></span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div>
            <label style="font-size:12px;color:var(--text-mid);display:block;margin-bottom:5px;">Notes / Conditions</label>
            <textarea id="notes" rows="3" placeholder="Conditions de paiement, délais de livraison..."
                      style="width:100%;padding:9px 14px;font-size:13px;resize:none;"><?= h($editDevis['notes']??'') ?></textarea>
        </div>
    </div>

    <div style="padding:16px 24px;border-top:1px solid var(--border);background:var(--bg3);display:flex;gap:10px;">
        <button onclick="saveDevis()" class="btn-primary">💾 Enregistrer le devis</button>
        <a href="<?= APP_URL ?>/devis.php" class="btn-secondary">Annuler</a>
    </div>
</div>

<script>
const DEVISE  = '<?= h($devise) ?>';
const TVA_DEF = <?= (float)($user['tva_defaut']??20) ?>;
const EDIT_ID = <?= $editId ?>;
let itemCount = 0;

function fillClient() {
    const opt = document.getElementById('clientSelect').options[document.getElementById('clientSelect').selectedIndex];
    if (!opt.value) return;
    document.getElementById('clientNom').value   = opt.dataset.nom   || '';
    document.getElementById('clientEmail').value = opt.dataset.email || '';
}

function addItem(desc='',qte=1,pu=0,tva=TVA_DEF) {
    const idx = itemCount++;
    const tr  = document.createElement('tr');
    tr.id = 'item-'+idx;
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML = `
        <td style="padding:8px 6px;"><input type="text" value="${escHtml(desc)}" placeholder="Description du service..."
            style="width:100%;padding:8px 10px;font-size:13px;" oninput="calc()"></td>
        <td style="padding:8px 4px;"><input type="number" value="${qte}" min="0.01" step="0.01"
            style="width:100%;padding:8px 6px;font-size:13px;text-align:center;" oninput="calc()"></td>
        <td style="padding:8px 4px;"><input type="number" value="${pu}" min="0" step="0.01"
            style="width:100%;padding:8px 6px;font-size:13px;text-align:right;" oninput="calc()"></td>
        <td style="padding:8px 4px;"><input type="number" value="${tva}" min="0" max="100" step="0.5"
            style="width:100%;padding:8px 6px;font-size:13px;text-align:right;" oninput="calc()"></td>
        <td style="padding:8px 4px;text-align:right;"><span class="row-total" style="font-size:13px;font-weight:600;color:var(--text);">0,00 ${DEVISE}</span></td>
        <td style="padding:8px 4px;text-align:center;"><button onclick="this.closest('tr').remove();calc()" style="background:none;border:none;color:#EF4444;font-size:18px;cursor:pointer;">×</button></td>`;
    document.getElementById('itemsBody').appendChild(tr);
    calc();
}

function calc() {
    let ht=0, tvaTot=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const ins = tr.querySelectorAll('input');
        const q   = parseFloat(ins[1]?.value)||0;
        const p   = parseFloat(ins[2]?.value)||0;
        const t   = parseFloat(ins[3]?.value)||0;
        const sub = q*p; ht+=sub; tvaTot+=sub*t/100;
        const sp = tr.querySelector('.row-total');
        if (sp) sp.textContent = fmt(sub);
    });
    document.getElementById('totalHT').textContent  = fmt(ht);
    document.getElementById('totalTVA').textContent = fmt(tvaTot);
    document.getElementById('totalTTC').textContent = fmt(ht+tvaTot);
}

function fmt(v) { return v.toLocaleString('fr-MA',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+DEVISE; }
function escHtml(s) { const d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML; }

async function saveDevis() {
    const rows = document.querySelectorAll('#itemsBody tr');
    if (!rows.length) { ffAlert('⚠️ Champ requis', 'Ajoutez au moins une prestation.', 'warning'); return; }
    const nom = document.getElementById('clientNom').value.trim();
    if (!nom) { ffAlert('⚠️ Champ requis', 'Le nom du client est obligatoire.', 'warning'); return; }
    const items = [];
    rows.forEach(tr => {
        const ins = tr.querySelectorAll('input');
        items.push({ description:ins[0].value.trim(), quantite:parseFloat(ins[1].value)||1, prix_unitaire:parseFloat(ins[2].value)||0, tva:parseFloat(ins[3].value)||0, total:(parseFloat(ins[1].value)||1)*(parseFloat(ins[2].value)||0) });
    });
    const payload = {
        id: EDIT_ID, client_id: document.getElementById('clientSelect').value||null,
        client_nom: nom, client_email: document.getElementById('clientEmail').value.trim(),
        date_devis: document.getElementById('dateDevis').value, date_validite: document.getElementById('dateValidite').value||null,
        statut: document.getElementById('statut').value, notes: document.getElementById('notes').value.trim(), devise: DEVISE, items
    };
    const btn = event.target; btn.disabled=true; btn.textContent='⏳ Enregistrement...';
    const res  = await fetch(APP_URL+'/api/devis.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const json = await res.json();
    if (json.success) window.location.href=APP_URL+'/devis-view.php?id='+json.devis_id;
    else if (json.limit) { window.location.href=APP_URL+'/plans.php?msg=limit'; }
    else { showToast('❌ '+json.error, false); btn.disabled=false; btn.textContent='💾 Enregistrer le devis'; }
}

// Initialisation
<?php if ($editItems): ?>
<?php foreach ($editItems as $item): ?>
addItem('<?= addslashes($item['description']) ?>',<?= $item['quantite'] ?>,<?= $item['prix_unitaire'] ?>,<?= $item['tva'] ?>);
<?php endforeach; ?>
<?php else: ?>
addItem();
<?php endif; ?>
</script>

</div></div></body></html>
