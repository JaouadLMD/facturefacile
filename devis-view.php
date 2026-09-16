<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();
$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM devis WHERE id=? AND user_id=?");
$stmt->execute([$id, $user['id']]);
$devis = $stmt->fetch();
if (!$devis) { header('Location: '.APP_URL.'/devis.php'); exit; }
$stmt2 = $pdo->prepare("SELECT * FROM devis_items WHERE devis_id=? ORDER BY id");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll();
$expired = $devis['date_validite'] && strtotime($devis['date_validite']) < time() && !in_array($devis['statut'],['accepte','refuse']);

$pageTitle = h($devis['numero']);
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/devis.php" style="color:var(--text-mid);text-decoration:none;">Devis</a>
    <span>›</span><span style="color:var(--text);font-weight:500;"><?= h($devis['numero']) ?></span>
</div>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;"><?= h($devis['numero']) ?></h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Créé le <?= date('d/m/Y à H:i', strtotime($devis['created_at'])) ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <select id="statutSel" onchange="updateStatut()"
                style="padding:8px 14px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--bg3);color:var(--text);">
            <?php foreach(['brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé','expire'=>'Expiré'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $devis['statut']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($devis['statut']!=='accepte'): ?>
        <button onclick="convertToFacture(<?= $devis['id'] ?>)" class="btn-primary" style="background:#059669;">⚡ Convertir en facture</button>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/devis-create.php?edit=<?= $devis['id'] ?>" class="btn-secondary">✏️ Modifier</a>
        <button onclick="downloadDevisPdf()" class="btn-secondary" style="background:#10B981;border-color:#10B981;color:white;">⬇️ PDF</button>
        <button onclick="imprimerDevis()" class="btn-secondary">🖨️ Imprimer</button>
    </div>
</div>

<?php if ($expired): ?>
<div style="background:rgba(185,28,28,.1);border:1px solid rgba(185,28,28,.25);border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#EF4444;">
    ⚠️ Ce devis a expiré le <?= date('d/m/Y', strtotime($devis['date_validite'])) ?>.
</div>
<?php endif; ?>

<div class="card" style="overflow:hidden;">
    <!-- Header devis -->
    <div style="padding:24px 28px;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
            <div>
                <p style="font-size:22px;font-weight:800;color:var(--primary-2);margin:0;"><?= h($devis['numero']) ?></p>
                <p style="font-size:13px;color:var(--text-mid);margin:4px 0 0;">Date : <?= date('d/m/Y', strtotime($devis['date_devis'])) ?></p>
                <?php if ($devis['date_validite']): ?>
                <p style="font-size:13px;color:<?= $expired ? '#EF4444' : 'var(--text-mid)' ?>;margin:2px 0 0;">Valide jusqu'au : <?= date('d/m/Y', strtotime($devis['date_validite'])) ?></p>
                <?php endif; ?>
                <div style="margin-top:8px;"><?= statutBadge($devis['statut']) ?></div>
            </div>
            <div style="text-align:right;">
                <p style="font-weight:700;font-size:15px;color:var(--text);margin:0;"><?= h($user['entreprise'] ?: $user['nom']) ?></p>
                <?php if ($user['adresse']): ?><p style="font-size:12.5px;color:var(--text-mid);margin:2px 0;"><?= h($user['adresse']) ?></p><?php endif; ?>
                <?php if ($user['telephone']): ?><p style="font-size:12.5px;color:var(--text-mid);margin:2px 0;"><?= h($user['telephone']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Client -->
    <div style="padding:16px 28px;background:var(--bg3);border-bottom:1px solid var(--border);">
        <p style="font-size:10.5px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.8px;margin:0 0 6px;">Devis adressé à</p>
        <p style="font-size:16px;font-weight:700;color:var(--text);margin:0;"><?= h($devis['client_nom']) ?></p>
        <?php if ($devis['client_email']): ?><p style="font-size:13px;color:var(--text-mid);margin:2px 0;"><?= h($devis['client_email']) ?></p><?php endif; ?>
    </div>

    <!-- Items -->
    <div style="padding:20px 28px;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <th style="padding:10px 8px 10px 0;text-align:left;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;">Description</th>
                    <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;width:60px;">Qté</th>
                    <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;width:120px;">Prix U.</th>
                    <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;width:70px;">TVA</th>
                    <th style="padding:10px 0 10px 8px;text-align:right;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;width:120px;">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr class="table-row" style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 8px 12px 0;font-size:13.5px;color:var(--text);"><?= h($item['description']) ?></td>
                    <td style="padding:12px 8px;text-align:center;font-size:13px;color:var(--text-mid);"><?= $item['quantite']+0 ?></td>
                    <td style="padding:12px 8px;text-align:right;font-size:13px;color:var(--text-mid);"><?= formatMontant((float)$item['prix_unitaire'], $devis['devise']) ?></td>
                    <td style="padding:12px 8px;text-align:right;font-size:13px;color:var(--text-mid);"><?= $item['tva'] ?>%</td>
                    <td style="padding:12px 0 12px 8px;text-align:right;font-size:13px;font-weight:600;color:var(--text);"><?= formatMontant((float)$item['total'], $devis['devise']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totaux -->
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <div style="width:260px;">
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:13px;color:var(--text-mid);">Sous-total HT</span>
                    <span style="font-size:13px;font-weight:500;color:var(--text);"><?= formatMontant((float)$devis['sous_total'], $devis['devise']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:13px;color:var(--text-mid);">TVA</span>
                    <span style="font-size:13px;font-weight:500;color:var(--text);"><?= formatMontant((float)$devis['tva_montant'], $devis['devise']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px 0 4px;">
                    <span style="font-size:15px;font-weight:800;color:var(--text);">Total TTC</span>
                    <span style="font-size:18px;font-weight:800;color:var(--primary-2);"><?= formatMontant((float)$devis['total'], $devis['devise']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($devis['notes']): ?>
        <div style="margin-top:16px;padding:14px;background:var(--bg3);border-radius:10px;border-left:3px solid var(--primary);">
            <p style="font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.8px;margin:0 0 5px;">Notes & Conditions</p>
            <p style="font-size:13px;color:var(--text);margin:0;"><?= h($devis['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div style="padding:16px 28px;border-top:1px solid var(--border);background:var(--bg3);display:flex;gap:10px;flex-wrap:wrap;">
        <button onclick="downloadDevisPdf()" class="btn-primary" style="background:#10B981;border-color:#10B981;">⬇️ Télécharger PDF</button>
        <button onclick="imprimerDevis()" class="btn-primary">🖨️ Imprimer</button>
        <a href="<?= APP_URL ?>/devis.php" class="btn-secondary">← Retour</a>
        <button onclick="confirmDeleteDevis(<?= $devis['id'] ?>,'<?= h($devis['numero']) ?>')" class="btn-secondary" style="margin-left:auto;color:#EF4444;border-color:rgba(239,68,68,.3);">🗑️ Supprimer</button>
    </div>
</div>

<script>
const STATUT_LABELS_D = {brouillon:'Brouillon',envoye:'Envoyé',accepte:'Accepté',refuse:'Refusé',expire:'Expiré'};

function downloadDevisPdf() {
    window.open(APP_URL + '/devis-print.php?id=<?= $devis['id'] ?>&download=1', '_blank');
}
function imprimerDevis() {
    window.open(APP_URL + '/devis-print.php?id=<?= $devis['id'] ?>', '_blank');
}
async function updateStatut() {
    const statut = document.getElementById('statutSel').value;
    const res = await fetch(APP_URL+'/api/devis.php?action=statut',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:<?= $devis['id'] ?>,statut})});
    const json = await res.json();
    if (json.success) showToast('✅ Statut mis à jour : '+(STATUT_LABELS_D[statut]||statut));
    else showToast('❌ Erreur lors de la mise à jour',false);
}
function convertToFacture(id) {
    ffConfirm('Convertir en facture', 'Convertir ce devis en facture ? Le devis sera marqué "Accepté".', async () => {
        const res  = await fetch(APP_URL+'/api/devis.php?action=convert',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const json = await res.json();
        if (json.success) { showToast('✅ Facture '+json.numero+' créée !'); setTimeout(()=>window.location.href=APP_URL+'/facture-view.php?id='+json.facture_id, 1200); }
        else showToast('❌ '+json.error, false);
    });
}
function confirmDeleteDevis(id, num) {
    ffConfirm('Supprimer le devis', `Supprimer définitivement le devis ${num} ? Cette action est irréversible.`, async () => {
        await fetch(APP_URL+'/api/devis.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        window.location.href = APP_URL+'/devis.php';
    }, true);
}
</script>

</div></div></body></html>
