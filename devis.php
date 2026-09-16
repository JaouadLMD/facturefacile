<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$statut = $_GET['statut'] ?? '';
$search = trim($_GET['q'] ?? '');
$where  = 'd.user_id=?'; $params = [$user['id']];
if ($statut) { $where .= ' AND d.statut=?'; $params[] = $statut; }
if ($search) { $where .= ' AND (d.numero LIKE ? OR d.client_nom LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT d.*,c.nom as c_nom FROM devis d LEFT JOIN clients c ON d.client_id=c.id WHERE $where ORDER BY d.created_at DESC");
$stmt->execute($params);
$devisList = $stmt->fetchAll();

// Clients pour le formulaire
$stmtC = $pdo->prepare("SELECT id,nom,email,adresse,ice FROM clients WHERE user_id=? ORDER BY nom");
$stmtC->execute([$user['id']]);
$clients = $stmtC->fetchAll();
$devise  = $user['devise'] ?? 'MAD';

$_limits = getPlanLimits($user['plan'] ?? 'gratuit');
$_isGratuit = $_limits['devis'] < 999999;
if ($_isGratuit) {
    $stmtUsage = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND MONTH(date_devis)=MONTH(CURDATE()) AND YEAR(date_devis)=YEAR(CURDATE())");
    $stmtUsage->execute([$user['id']]);
    $_usedDevis = (int)$stmtUsage->fetchColumn();
    $_limitDevis = $_limits['devis'];
}
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:<?= $_isGratuit ? '12px' : '20px' ?>;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;">📋 Gestion des Devis</h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;"><?= count($devisList) ?> devis trouvé(s)</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?= APP_URL ?>/devis-create.php" class="btn-primary">+ Nouveau devis</a>
    </div>
</div>
<?php if ($_isGratuit): ?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;">
    <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
            <span style="font-size:12px;color:var(--text-mid);">Devis ce mois</span>
            <span style="font-size:12px;font-weight:700;color:<?= $_usedDevis >= $_limitDevis ? '#EF4444' : 'var(--text)' ?>;"><?= $_usedDevis ?> / <?= $_limitDevis ?></span>
        </div>
        <div style="height:5px;background:var(--border);border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, round($_usedDevis/$_limitDevis*100)) ?>%;background:<?= $_usedDevis >= $_limitDevis ? '#EF4444' : 'var(--primary)' ?>;border-radius:99px;transition:width 0.3s;"></div>
        </div>
    </div>
    <a href="<?= APP_URL ?>/plans.php" style="font-size:11.5px;font-weight:600;color:var(--primary-2);text-decoration:none;white-space:nowrap;">⭐ Passer Pro</a>
</div>
<?php endif; ?>

<!-- Filtres -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
        <input type="hidden" name="statut" value="<?= h($statut) ?>">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher..."
               style="flex:1;padding:8px 14px;font-size:13px;">
        <button type="submit" class="btn-secondary">🔍</button>
    </form>
    <?php $statuts=[''=>'Tous','brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé','expire'=>'Expiré'];
    foreach ($statuts as $v=>$l):
        $isAct = $statut===$v; ?>
    <a href="?statut=<?= $v ?>&q=<?= urlencode($search) ?>"
       style="padding:8px 14px;border:1px solid <?= $isAct ? 'var(--primary)' : 'var(--border)' ?>;border-radius:10px;font-size:12.5px;font-weight:<?= $isAct ? '700' : '500' ?>;text-decoration:none;color:<?= $isAct ? 'white' : 'var(--text-mid)' ?>;background:<?= $isAct ? 'var(--primary)' : 'var(--bg3)' ?>;transition:all 0.15s;"><?= $l ?></a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="overflow:hidden;">
    <?php if (empty($devisList)): ?>
    <div style="padding:48px;text-align:center;">
        <p style="font-size:40px;margin:0 0 10px;">📋</p>
        <p style="color:var(--text);font-weight:600;font-size:14px;">Aucun devis</p>
        <p style="color:var(--text-soft);font-size:12.5px;margin:4px 0 12px;">Créez votre premier devis et convertissez-le en facture en 1 clic.</p>
        <a href="<?= APP_URL ?>/devis-create.php" class="btn-primary" style="display:inline-flex;">+ Créer un devis</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead style="background:var(--bg3);border-bottom:1px solid var(--border);">
                <tr>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Numéro</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Client</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Date</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Validité</th>
                    <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Montant TTC</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Statut</th>
                    <th style="padding:12px 16px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devisList as $d):
                    $expired = $d['date_validite'] && strtotime($d['date_validite']) < time() && !in_array($d['statut'],['accepte','refuse']);
                ?>
                <tr class="table-row" style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 16px;font-size:13px;font-family:monospace;font-weight:600;color:var(--text-mid);"><?= h($d['numero']) ?></td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:var(--text);"><?= h($d['client_nom']?:($d['c_nom']??'–')) ?></td>
                    <td style="padding:12px 16px;font-size:12.5px;color:var(--text-mid);"><?= date('d/m/Y',strtotime($d['date_devis'])) ?></td>
                    <td style="padding:12px 16px;font-size:12.5px;color:<?= $expired?'#EF4444':'var(--text-mid)' ?>;">
                        <?= $d['date_validite'] ? date('d/m/Y',strtotime($d['date_validite'])) : '–' ?>
                        <?= $expired ? ' ⚠️' : '' ?>
                    </td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:var(--text);text-align:right;"><?= formatMontant((float)$d['total'],$d['devise']) ?></td>
                    <td style="padding:12px 16px;text-align:center;"><?= statutBadge($d['statut']) ?></td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <a href="<?= APP_URL ?>/devis-view.php?id=<?= $d['id'] ?>" onclick="logAct('view','devis',<?= $d['id'] ?>,'<?= h($d['numero']) ?>','Consultation depuis liste')" style="padding:5px 10px;background:var(--primary-bg);color:var(--primary-2);border-radius:8px;font-size:11.5px;font-weight:600;text-decoration:none;">Voir</a>
                            <button onclick="directDownloadDevis(<?= $d['id'] ?>,'<?= h($d['numero']) ?>')"
                                    style="padding:5px 10px;background:var(--bg3);color:var(--text-mid);border:1px solid var(--border);border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">⬇️ PDF</button>
                            <?php if ($d['statut'] !== 'accepte'): ?>
                            <button onclick="convertDevis(<?= $d['id'] ?>,'<?= h($d['numero']) ?>')"
                                    style="padding:5px 10px;background:rgba(16,185,129,.12);color:#10B981;border:none;border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">→ Facture</button>
                            <?php endif; ?>
                            <button onclick="deleteDevis(<?= $d['id'] ?>)"
                                    style="padding:5px 10px;background:rgba(239,68,68,.12);color:#EF4444;border:none;border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">✕</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function convertDevis(id, numero) {
    ffConfirm('Convertir en facture', `Convertir le devis ${numero} en facture ? Le devis sera marqué "Accepté".`, async () => {
        const res  = await fetch(APP_URL+'/api/devis.php?action=convert',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const json = await res.json();
        if (json.success) { showToast('✅ Facture '+json.numero+' créée !'); setTimeout(()=>window.location.href=APP_URL+'/facture-view.php?id='+json.facture_id, 1000); }
        else showToast('❌ '+json.error, false);
    });
}
function logAct(action, module, id, ref, details='') {
    try { fetch(APP_URL+'/api/log.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,module,entity_id:id,entity_ref:ref,details})}); } catch(e){}
}

function directDownloadDevis(id, numero) {
    logAct('print','devis',id,numero,'Téléchargement PDF direct');
    showToast('⏳ Préparation du PDF…');
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;border:none;';
    frame.src = APP_URL + '/devis-print.php?id=' + id + '&download=1';
    document.body.appendChild(frame);
    setTimeout(() => { showToast('✅ PDF téléchargé !'); }, 3000);
    setTimeout(() => { try { document.body.removeChild(frame); } catch(e){} }, 15000);
}
function openPdfDevis(id, numero) {
    logAct('print','devis',id,numero,'PDF devis depuis liste');
    const old = document.getElementById('pdfOverlay'); if (old) old.remove();
    const overlay = document.createElement('div');
    overlay.id = 'pdfOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:flex;flex-direction:column;';
    overlay.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--card);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap;">
            <span style="font-size:14px;font-weight:700;color:var(--text);">📋 Devis ${numero}</span>
            <div style="flex:1;"></div>
            <button onclick="window.open(APP_URL+'/devis-print.php?id=${id}&download=1','_blank')"
                style="padding:8px 16px;background:#10B981;color:white;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;">
                ⬇️ Télécharger PDF
            </button>
            <button onclick="document.getElementById('pdfFrame').contentWindow.print()"
                style="padding:8px 16px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;">
                🖨️ Imprimer
            </button>
            <button onclick="document.getElementById('pdfOverlay').remove()"
                style="padding:8px 14px;background:var(--bg3);border:1px solid var(--border);color:var(--text-mid);border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                ✕ Fermer
            </button>
        </div>
        <iframe id="pdfFrame" src="${APP_URL}/devis-print.php?id=${id}&preview=1" style="flex:1;border:none;background:white;"></iframe>`;
    document.body.appendChild(overlay);
}

function deleteDevis(id, num) {
    ffConfirm('Supprimer le devis', `Supprimer définitivement le devis ${num||''} ? Cette action est irréversible.`, async () => {
        const res  = await fetch(APP_URL+'/api/devis.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const json = await res.json();
        if (json.success) location.reload(); else showToast('❌ Erreur suppression', false);
    }, true);
}
</script>

</div></div></body></html>
