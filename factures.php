<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$statut = $_GET['statut'] ?? '';
$search = trim($_GET['q'] ?? '');
$where  = "f.user_id=?"; $params = [$user['id']];
if ($statut) { $where .= " AND f.statut=?"; $params[] = $statut; }
if ($search) { $where .= " AND (f.numero LIKE ? OR f.client_nom LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("
    SELECT f.*, COALESCE(c.nom,f.client_nom) as nom_client,
           (SELECT COUNT(*) FROM relances r WHERE r.facture_id=f.id) as nb_relances
    FROM factures f LEFT JOIN clients c ON f.client_id=c.id
    WHERE $where ORDER BY f.created_at DESC
");
$stmt->execute($params);
$factures = $stmt->fetchAll();
$smtpConfigured = !empty($user['smtp_user']) || !empty(SMTP_USER);

$_limits = getPlanLimits($user['plan'] ?? 'gratuit');
$_isGratuit = $_limits['factures'] < 999999;
if ($_isGratuit) {
    $stmtUsage = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=? AND MONTH(date_facture)=MONTH(CURDATE()) AND YEAR(date_facture)=YEAR(CURDATE())");
    $stmtUsage->execute([$user['id']]);
    $_usedFactures = (int)$stmtUsage->fetchColumn();
    $_limitFactures = $_limits['factures'];
}
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:<?= $_isGratuit ? '12px' : '20px' ?>;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;">📄 Mes Factures</h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;"><?= count($factures) ?> facture(s)</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?= APP_URL ?>/facture-create.php" class="btn-secondary">✏️ Manuelle</a>
        <a href="<?= APP_URL ?>/chat.php" class="btn-primary">🤖 Créer avec IA</a>
    </div>
</div>
<?php if ($_isGratuit): ?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;">
    <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
            <span style="font-size:12px;color:var(--text-mid);">Factures ce mois</span>
            <span style="font-size:12px;font-weight:700;color:<?= $_usedFactures >= $_limitFactures ? '#EF4444' : 'var(--text)' ?>;"><?= $_usedFactures ?> / <?= $_limitFactures ?></span>
        </div>
        <div style="height:5px;background:var(--border);border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, round($_usedFactures/$_limitFactures*100)) ?>%;background:<?= $_usedFactures >= $_limitFactures ? '#EF4444' : 'var(--primary)' ?>;border-radius:99px;transition:width 0.3s;"></div>
        </div>
    </div>
    <a href="<?= APP_URL ?>/plans.php" style="font-size:11.5px;font-weight:600;color:var(--primary-2);text-decoration:none;white-space:nowrap;">⭐ Passer Pro</a>
</div>
<?php endif; ?>

<!-- Filtres -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
        <input type="hidden" name="statut" value="<?= h($statut) ?>">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher numéro ou client..."
               style="flex:1;padding:8px 14px;font-size:13px;">
        <button type="submit" class="btn-secondary">🔍</button>
    </form>
    <?php $statuts=[''=>'Toutes','brouillon'=>'Brouillon','envoyee'=>'Envoyées','payee'=>'Payées','impayee'=>'Impayées','annulee'=>'Annulées'];
    foreach ($statuts as $v=>$l):
        $isAct = $statut===$v; ?>
    <a href="?statut=<?= $v ?>&q=<?= urlencode($search) ?>"
       style="padding:8px 14px;border:1px solid <?= $isAct ? 'var(--primary)' : 'var(--border)' ?>;border-radius:10px;font-size:12.5px;font-weight:<?= $isAct ? '700' : '500' ?>;text-decoration:none;color:<?= $isAct ? 'white' : 'var(--text-mid)' ?>;background:<?= $isAct ? 'var(--primary)' : 'var(--bg3)' ?>;transition:all 0.15s;"><?= $l ?></a>
    <?php endforeach; ?>
</div>

<div class="card" style="overflow:hidden;">
    <?php if (empty($factures)): ?>
    <div style="padding:48px;text-align:center;">
        <p style="font-size:40px;margin:0 0 10px;">📄</p>
        <p style="color:var(--text);font-weight:600;font-size:14px;">Aucune facture trouvée</p>
        <a href="<?= APP_URL ?>/chat.php" class="btn-primary" style="display:inline-flex;margin-top:12px;">🤖 Créer avec IA</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead style="background:var(--bg3);border-bottom:1px solid var(--border);">
                <tr>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Numéro</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Client</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Date</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Échéance</th>
                    <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Total TTC</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;">Statut</th>
                    <th style="padding:12px 16px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($factures as $f):
                    $late = $f['date_echeance'] && strtotime($f['date_echeance']) < time() && !in_array($f['statut'],['payee','annulee']);
                ?>
                <tr class="table-row" style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 16px;font-size:13px;font-family:monospace;font-weight:600;color:var(--text-mid);"><?= h($f['numero']) ?></td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:var(--text);"><?= h($f['nom_client']??'–') ?></td>
                    <td style="padding:12px 16px;font-size:12.5px;color:var(--text-mid);"><?= date('d/m/Y',strtotime($f['date_facture'])) ?></td>
                    <td style="padding:12px 16px;font-size:12.5px;color:<?= $late?'#EF4444':'var(--text-mid)' ?>;">
                        <?= $f['date_echeance'] ? date('d/m/Y',strtotime($f['date_echeance'])) : '–' ?>
                        <?= $late ? ' ⚠️' : '' ?>
                    </td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:var(--text);text-align:right;"><?= formatMontant((float)$f['total'],$f['devise']) ?></td>
                    <td style="padding:12px 16px;text-align:center;"><?= statutBadge($f['statut']) ?></td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
                            <a href="<?= APP_URL ?>/facture-view.php?id=<?= $f['id'] ?>" onclick="logAction('view','facture',<?= $f['id'] ?>,'<?= h($f['numero']) ?>','Consultation depuis liste')" style="padding:5px 9px;background:var(--primary-bg);color:var(--primary-2);border-radius:8px;font-size:11.5px;font-weight:600;text-decoration:none;">Voir</a>
                            <button onclick="directDownload(<?= $f['id'] ?>,'<?= h($f['numero']) ?>')" style="padding:5px 9px;background:var(--bg3);color:var(--text-mid);border:1px solid var(--border);border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">⬇️ PDF</button>
                            <?php if (!empty($f['client_email']) && $smtpConfigured): ?>
                            <button onclick="envoyerFacture(<?= $f['id'] ?>,'<?= h($f['client_email']) ?>')"
                                    style="padding:5px 9px;background:rgba(16,185,129,.12);color:#10B981;border:none;border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">📧</button>
                            <?php endif; ?>
                            <?php if ($f['statut']==='impayee' && $f['date_echeance'] && strtotime($f['date_echeance'])<time()): ?>
                            <button onclick="envoyerRelance(<?= $f['id'] ?>,'<?= h($f['client_nom']) ?>')"
                                    title="<?= $f['nb_relances'] ?> relance(s) envoyée(s)"
                                    style="padding:5px 9px;background:rgba(249,115,22,.12);color:#F97316;border:none;border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">
                                🔔<?= $f['nb_relances']>0 ? ' ('.$f['nb_relances'].')' : '' ?>
                            </button>
                            <?php endif; ?>
                            <button onclick="deleteFacture(<?= $f['id'] ?>,'<?= h($f['numero']) ?>')"
                                    style="padding:5px 9px;background:rgba(239,68,68,.12);color:#EF4444;border:none;border-radius:8px;font-size:11.5px;font-weight:600;cursor:pointer;">✕</button>
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
function logAction(action, module, id, ref, details='') {
    try { fetch(APP_URL+'/api/log.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,module,entity_id:id,entity_ref:ref,details})}); } catch(e){}
}
async function logPrint(id, ref) { logAction('print','facture',id,ref,'PDF depuis liste'); }

function directDownload(id, numero) {
    logAction('print','facture',id,numero,'Téléchargement PDF direct');
    showToast('⏳ Préparation du PDF…');
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;border:none;';
    frame.src = APP_URL + '/facture-print.php?id=' + id + '&download=1';
    document.body.appendChild(frame);
    setTimeout(() => { showToast('✅ PDF téléchargé !'); }, 3000);
    setTimeout(() => { try { document.body.removeChild(frame); } catch(e){} }, 15000);
}

function openPdf(id, numero) {
    logPrint(id, numero);
    const old = document.getElementById('pdfOverlay'); if (old) old.remove();
    const overlay = document.createElement('div');
    overlay.id = 'pdfOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:flex;flex-direction:column;';
    overlay.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--card);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap;">
            <span style="font-size:14px;font-weight:700;color:var(--text);">📄 Facture ${numero}</span>
            <div style="flex:1;"></div>
            <button onclick="window.open(APP_URL+'/facture-print.php?id=${id}&download=1','_blank')"
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
        <iframe id="pdfFrame" src="${APP_URL}/facture-print.php?id=${id}&preview=1" style="flex:1;border:none;background:white;"></iframe>`;
    document.body.appendChild(overlay);
}
function deleteFacture(id, num) {
    ffConfirm('Supprimer la facture', `Supprimer définitivement la facture ${num} ? Cette action est irréversible.`, async () => {
        const r = await fetch(APP_URL+'/api/factures.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const j = await r.json();
        if (j.success) location.reload(); else showToast('❌ Erreur suppression', false);
    }, true);
}
async function envoyerFacture(id, email) {
    ffConfirm('Envoyer par email', `Envoyer la facture à ${email} ?`, async () => {
        showToast('📧 Envoi en cours...');
        const r = await fetch(APP_URL+'/api/email.php?action=send_facture',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({facture_id:id})});
        const j = await r.json();
        if (j.success) { showToast('✅ Facture envoyée !'); setTimeout(()=>location.reload(), 2000); }
        else showToast('❌ '+j.error, false);
    });
}
async function envoyerRelance(id, nom) {
    ffConfirm('Envoyer une relance', `Envoyer une relance de paiement à ${nom} ?`, async () => {
        showToast('📧 Envoi de la relance...');
        const r = await fetch(APP_URL+'/api/email.php?action=relance',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({facture_id:id})});
        const j = await r.json();
        if (j.success) { showToast(`✅ Relance envoyée !`); setTimeout(()=>location.reload(), 2000); }
        else showToast('❌ '+j.error, false);
    });
}
</script>

</div></div></body></html>
