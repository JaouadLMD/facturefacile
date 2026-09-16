<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT f.*, c.email as c_email FROM factures f LEFT JOIN clients c ON f.client_id = c.id WHERE f.id = ? AND f.user_id = ?");
$stmt->execute([$id, $user['id']]);
$facture = $stmt->fetch();
if (!$facture) { header('Location: ' . APP_URL . '/factures.php'); exit; }

$stmt2 = $pdo->prepare("SELECT * FROM facture_items WHERE facture_id = ? ORDER BY id");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll();

// Invoice colors
$_ic = json_decode($user['invoice_colors'] ?? '{}', true) ?: [];
$C = [
    'primary'    => $_ic['primary']    ?? '#6366f1',
    'text'       => $_ic['text']       ?? '#1e293b',
    'text_soft'  => $_ic['text_soft']  ?? '#64748b',
    'bg'         => $_ic['bg']         ?? '#ffffff',
    'parties_bg' => $_ic['parties_bg'] ?? '#f8fafc',
    'border'     => $_ic['border']     ?? '#e2e8f0',
    'table_alt'  => $_ic['table_alt']  ?? '#f8fafc',
];

// Footer info
$_fi        = json_decode($user['invoice_footer_info'] ?? '{}', true) ?: [];
$_fiFontSz  = max(9, min(16, (int)($_fi['font_size']  ?? 11)));
$_fiFontClr = $_fi['font_color'] ?? '#64748b';
$_fiSep     = $_fi['separator']  ?? ' · ';
$_invFooter = trim($user['invoice_footer'] ?? '');
$_logoPath  = $user['logo_path'] ?? '';

// Column labels + order
$_invCols = json_decode($user['invoice_columns'] ?? '{}', true) ?: [];
$_col = [
    'description' => $_invCols['description'] ?? 'Description',
    'qty'         => $_invCols['qty']         ?? 'Qté',
    'price'       => $_invCols['price']       ?? 'Prix U.',
    'tva'         => $_invCols['tva']         ?? 'TVA %',
    'total'       => $_invCols['total']       ?? 'Total HT',
];
$colOrder  = $_invCols['order'] ?? array_keys($_col);
$colWidths = ['qty' => '70px', 'price' => '120px', 'tva' => '80px', 'total' => '130px'];

$_invHeader   = trim($user['invoice_header'] ?? '');
$_amountWords = nombreEnLettresFr((float)$facture['total'], $facture['devise']);
$footerBlock  = buildFooterInfoBlock($_fi, (string)$_fiFontSz, $_fiFontClr, $_fiSep);

$smap = ['brouillon'=>'Brouillon','envoyee'=>'Envoyée','payee'=>'Payée','impayee'=>'Impayée','annulee'=>'Annulée'];

$pageTitle = h($facture['numero']);
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<style>
/* Invoice color variables */
:root {
    --inv-primary:    <?= h($C['primary']) ?>;
    --inv-text:       <?= h($C['text']) ?>;
    --inv-text-soft:  <?= h($C['text_soft']) ?>;
    --inv-bg:         <?= h($C['bg']) ?>;
    --inv-parties-bg: <?= h($C['parties_bg']) ?>;
    --inv-border:     <?= h($C['border']) ?>;
    --inv-table-alt:  <?= h($C['table_alt']) ?>;
}

/* ── Invoice document ── */
.invoice-doc {
    background: var(--inv-bg);
    min-height: 1000px;
    display: flex;
    flex-direction: column;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.invoice-body        { flex: 1; padding: 40px 48px 24px; }
.invoice-footer-area { padding: 0 48px 28px; }

/* ── Header ── */
.inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; }
.inv-logo-txt { font-size:22px; font-weight:800; color:var(--inv-primary); letter-spacing:-0.3px; }
.inv-number-box { text-align:right; }
.inv-num  { font-size:20px; font-weight:800; color:var(--inv-primary); }
.inv-date { font-size:12px; color:var(--inv-text-soft); margin-top:4px; }

/* ── Status badges ── */
.inv-status { display:inline-block; padding:3px 12px; border-radius:999px; font-size:11px; font-weight:600; margin-top:6px; }
.status-payee     { background:#d1fae5; color:#065f46; }
.status-envoyee   { background:#dbeafe; color:#1e40af; }
.status-brouillon { background:#f1f5f9; color:#475569; }
.status-impayee   { background:#fee2e2; color:#991b1b; }
.status-annulee   { background:#ffedd5; color:#9a3412; }

/* ── Client box ── */
.inv-client-box { padding:18px 22px; background:var(--inv-parties-bg); border-radius:12px; margin-bottom:28px; border:1px solid var(--inv-border); }
.party-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.party-name  { font-size:16px; font-weight:700; color:var(--inv-text); margin-bottom:3px; }
.party-info  { font-size:12px; color:var(--inv-text-soft); line-height:1.6; }

/* ── Table ── */
.inv-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
.inv-table thead tr { border-bottom:2px solid var(--inv-border); }
.inv-table th { text-align:left; padding:10px 8px; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; }
.inv-table th:not(:first-child) { text-align:right; }
.inv-table td { padding:11px 8px; font-size:13px; color:var(--inv-text); }
.inv-table tbody tr:nth-child(odd) { background:var(--inv-table-alt); }
.inv-table td:not(:first-child) { text-align:right; color:var(--inv-text-soft); }
.inv-table td:last-child { font-weight:600; color:var(--inv-text); }

/* ── Arrêtée + totals side by side ── */
.totals-arretee-row { display:flex; gap:24px; align-items:flex-start; margin-bottom:20px; }
.inv-arretee { flex:1; padding:16px 18px; background:var(--inv-parties-bg); border-radius:10px; border-left:3px solid var(--inv-primary); align-self:center; }
.inv-arretee .lbl { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.inv-arretee p { font-size:12.5px; color:var(--inv-text); font-style:italic; line-height:1.5; }
.totals-box { width:260px; flex-shrink:0; }
.totals-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid var(--inv-border); font-size:13px; }
.totals-row .lbl { color:var(--inv-text-soft); }
.totals-row .val { font-weight:500; color:var(--inv-text); }
.totals-final { display:flex; justify-content:space-between; padding:12px 0 0; }
.totals-final .lbl { font-size:15px; font-weight:800; color:var(--inv-text); }
.totals-final .val { font-size:18px; font-weight:800; color:var(--inv-primary); }

/* ── Notes ── */
.inv-notes { margin-bottom:20px; padding:16px; background:var(--inv-parties-bg); border-radius:10px; border-left:3px solid var(--inv-primary); }
.inv-notes .lbl { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.inv-notes p { font-size:12px; color:var(--inv-text-soft); }

/* ── Footer divider ── */
.fi-divider { border:none; border-top:1px solid var(--inv-border); margin:0; }

/* ── Print ── */
@media print {
    body * { visibility: hidden !important; }
    #invoice-printable, #invoice-printable * { visibility: visible !important; }
    #invoice-printable {
        position: absolute !important;
        top: 0 !important; left: 0 !important; right: 0 !important;
        width: 100% !important;
    }
    .no-print { display: none !important; }
    .invoice-doc {
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
        min-height: 100vh;
    }
    .invoice-body { padding: 28px 40px 16px !important; }
    .invoice-footer-area { padding: 0 40px 20px !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<!-- Breadcrumb -->
<div class="no-print" style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/factures.php" style="color:var(--text-mid);text-decoration:none;">Factures</a>
    <span>›</span>
    <span style="color:var(--text);font-weight:500;"><?= h($facture['numero']) ?></span>
</div>

<!-- Top action bar -->
<div class="no-print" style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;"><?= h($facture['numero']) ?></h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Créée le <?= date('d/m/Y à H:i', strtotime($facture['created_at'])) ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <select id="statutSelect" onchange="updateStatut()"
                style="padding:8px 14px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--bg3);color:var(--text);">
            <?php foreach(['brouillon'=>'Brouillon','envoyee'=>'Envoyée','payee'=>'Payée','impayee'=>'Impayée','annulee'=>'Annulée'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $facture['statut']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="downloadFacturePdf()" class="btn-primary" style="background:#10B981;border-color:#10B981;">⬇️ Télécharger PDF</button>
        <button onclick="printFacture()" class="btn-primary">🖨️ Imprimer</button>
    </div>
</div>

<!-- ══════════════ INVOICE DOCUMENT ══════════════ -->
<div id="invoice-printable">
<div class="invoice-doc">

    <div class="invoice-body">

        <?php if (!empty($_invHeader)): ?>
        <div style="padding-bottom:16px;margin-bottom:24px;border-bottom:1px solid var(--inv-border);font-size:12px;color:var(--inv-text-soft);white-space:pre-line;"><?= h($_invHeader) ?></div>
        <?php endif; ?>

        <!-- Header: logo/name left — invoice number right -->
        <?php
            $_vHasLogo   = !empty($_logoPath);
            $_vHdMode    = $_invCols['header_display'] ?? 'logo_name';
            $_vShowName  = !$_vHasLogo || $_vHdMode === 'logo_name';
        ?>
        <div class="inv-header">
            <div>
                <?php if ($_vHasLogo): ?>
                    <img src="<?= h(APP_URL.'/'.ltrim($_logoPath,'/')) ?>" alt="Logo"
                         style="max-height:72px;max-width:200px;object-fit:contain;display:block;margin-bottom:6px;">
                <?php endif; ?>
                <?php if ($_vShowName): ?>
                    <div class="inv-logo-txt"><?= h(!empty($user['entreprise']) ? $user['entreprise'] : 'Mon Entreprise') ?></div>
                <?php endif; ?>
            </div>
            <div class="inv-number-box">
                <div class="inv-num"><?= h($facture['numero']) ?></div>
                <div class="inv-date">Date : <?= date('d/m/Y', strtotime($facture['date_facture'])) ?></div>
                <?php if ($facture['date_echeance']): ?>
                <div class="inv-date">Échéance : <?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></div>
                <?php endif; ?>
                <span class="inv-status status-<?= h($facture['statut']) ?>">
                    <?= $smap[$facture['statut']] ?? ucfirst($facture['statut']) ?>
                </span>
            </div>
        </div>

        <!-- Client -->
        <div class="inv-client-box">
            <div class="party-label">Facturé à</div>
            <div class="party-name"><?= h($facture['client_nom']) ?></div>
            <div class="party-info">
                <?php if ($facture['client_email'] || $facture['c_email']): ?>
                <?= h($facture['client_email'] ?: $facture['c_email']) ?><br>
                <?php endif; ?>
                <?php if ($facture['client_adresse']): ?><?= h($facture['client_adresse']) ?><br><?php endif; ?>
                <?php if ($facture['client_ice']): ?>ICE : <?= h($facture['client_ice']) ?><?php endif; ?>
            </div>
        </div>

        <!-- Items table (column order from settings) -->
        <table class="inv-table">
            <thead>
                <tr>
                    <?php foreach ($colOrder as $ck): ?>
                    <th<?= isset($colWidths[$ck]) ? ' style="width:' . $colWidths[$ck] . ';"' : '' ?>><?= h($_col[$ck] ?? $ck) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <?php foreach ($colOrder as $ck):
                        switch ($ck) {
                            case 'description': echo '<td>' . h($item['description']) . '</td>'; break;
                            case 'qty':   echo '<td>' . number_format((float)$item['quantite'], 2, ',', ' ') . '</td>'; break;
                            case 'price': echo '<td>' . formatMontant((float)$item['prix_unitaire'], $facture['devise']) . '</td>'; break;
                            case 'tva':   echo '<td>' . (int)$item['tva'] . '%</td>'; break;
                            case 'total': echo '<td>' . formatMontant((float)$item['total'], $facture['devise']) . '</td>'; break;
                        }
                    endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Arrêtée + Totals side by side -->
        <div class="totals-arretee-row">
            <div class="inv-arretee">
                <p>Arrêtée la présente facture à la somme de :<br><strong><?= h($_amountWords) ?></strong></p>
            </div>
            <div class="totals-box">
                <div class="totals-row">
                    <span class="lbl">Sous-total HT</span>
                    <span class="val"><?= formatMontant((float)$facture['sous_total'], $facture['devise']) ?></span>
                </div>
                <div class="totals-row">
                    <span class="lbl">TVA</span>
                    <span class="val"><?= formatMontant((float)$facture['tva_montant'], $facture['devise']) ?></span>
                </div>
                <div class="totals-final">
                    <span class="lbl">Total TTC</span>
                    <span class="val"><?= formatMontant((float)$facture['total'], $facture['devise']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($facture['notes']): ?>
        <div class="inv-notes">
            <div class="lbl">Notes</div>
            <p><?= h($facture['notes']) ?></p>
        </div>
        <?php endif; ?>

    </div><!-- /invoice-body -->

    <!-- Footer area — always at bottom -->
    <div class="invoice-footer-area">
        <?php if ($footerBlock || $_invFooter): ?><hr class="fi-divider"><?php endif; ?>
        <?php if ($footerBlock): ?><?= $footerBlock ?><?php endif; ?>
        <?php if ($_invFooter): ?>
        <p style="text-align:center;padding:12px 0 0;font-size:12.5px;color:var(--inv-text-soft);white-space:pre-line;"><?= h($_invFooter) ?></p>
        <?php endif; ?>
    </div>

</div><!-- /invoice-doc -->
</div><!-- /#invoice-printable -->

<!-- Bottom action bar -->
<div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;align-items:center;">
    <button onclick="downloadFacturePdf()" class="btn-primary" style="background:#10B981;border-color:#10B981;">⬇️ Télécharger PDF</button>
    <button onclick="printFacture()" class="btn-primary">🖨️ Imprimer</button>
    <a href="<?= APP_URL ?>/factures.php" class="btn-secondary">← Retour</a>
    <button onclick="deleteFacture(<?= $facture['id'] ?>)" class="btn-secondary"
            style="margin-left:auto;color:#EF4444;border-color:rgba(239,68,68,.3);">🗑️ Supprimer</button>
</div>

<script>
const STATUT_LABELS = {brouillon:'Brouillon',envoyee:'Envoyée',payee:'Payée',impayee:'Impayée',annulee:'Annulée'};
const FACTURE_ID  = <?= (int)$facture['id'] ?>;
const FACTURE_NUM = '<?= h($facture['numero']) ?>';

async function _logAction(action, details) {
    try {
        await fetch(APP_URL + '/api/log.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action, module:'facture', entity_id: FACTURE_ID, entity_ref: FACTURE_NUM, details: details || ''})
        });
    } catch(e) {}
}

async function printFacture() {
    await _logAction('print', 'Impression');
    window.print();
}

async function downloadFacturePdf() {
    await _logAction('print', 'Téléchargement PDF');
    window.open(APP_URL + '/facture-print.php?id=<?= $facture['id'] ?>&download=1', '_blank');
}

async function updateStatut() {
    const statut = document.getElementById('statutSelect').value;
    const res  = await fetch(APP_URL + '/api/factures.php?action=update_statut', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: FACTURE_ID, statut})
    });
    const json = await res.json();
    if (json.success) showToast('✅ Statut mis à jour : ' + (STATUT_LABELS[statut] || statut));
    else showToast('❌ Erreur lors de la mise à jour', false);
}

async function deleteFacture(id) {
    ffConfirm('Supprimer la facture', 'Supprimer définitivement cette facture ? Cette action est irréversible.', async () => {
        const res  = await fetch(APP_URL + '/api/factures.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const json = await res.json();
        if (json.success) window.location.href = APP_URL + '/factures.php';
        else showToast('❌ Erreur lors de la suppression.', false);
    }, true);
}
</script>

</div></div></body></html>
