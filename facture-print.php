<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT f.*, c.email as c_email FROM factures f LEFT JOIN clients c ON f.client_id=c.id WHERE f.id=? AND f.user_id=?");
$stmt->execute([$id, $user['id']]);
$facture = $stmt->fetch();
if (!$facture) { header('Location: ' . APP_URL . '/factures.php'); exit; }

$stmt2 = $pdo->prepare("SELECT * FROM facture_items WHERE facture_id=? ORDER BY id");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll();

logAction($user['id'], 'print', 'facture', (int)$facture['id'], (string)$facture['numero'], 'PDF / Impression');

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

// Column order + labels
$_invCols = json_decode($user['invoice_columns'] ?? '{}', true) ?: [];
$colOrder  = $_invCols['order'] ?? ['description','qty','price','tva','total'];
$_col = [
    'description' => $_invCols['description'] ?? 'Description',
    'qty'         => $_invCols['qty']         ?? 'Qté',
    'price'       => $_invCols['price']       ?? 'Prix U.',
    'tva'         => $_invCols['tva']         ?? 'TVA %',
    'total'       => $_invCols['total']       ?? 'Total HT',
];

// Footer info
$_fi        = json_decode($user['invoice_footer_info'] ?? '{}', true) ?: [];
$_fiFontSz  = max(9, min(16, (int)($_fi['font_size']  ?? 11)));
$_fiFontClr = $_fi['font_color'] ?? '#64748b';
$_fiSep     = $_fi['separator']  ?? ' · ';
$_invFooter = trim($user['invoice_footer'] ?? '');
$_invHeader = trim($user['invoice_header'] ?? '');
$_logoPath  = $user['logo_path'] ?? '';

$_amountWords = nombreEnLettresFr((float)$facture['total'], $facture['devise']);
$footerBlock  = buildFooterInfoBlock($_fi, (string)$_fiFontSz, $_fiFontClr, $_fiSep);
$smap = ['brouillon'=>'Brouillon','envoyee'=>'Envoyée','payee'=>'Payée','impayee'=>'Impayée','annulee'=>'Annulée'];

$colWidths = ['description'=>'auto','qty'=>'60px','price'=>'110px','tva'=>'70px','total'=>'120px'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture <?= h($facture['numero']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
:root {
    --inv-primary:    <?= h($C['primary']) ?>;
    --inv-text:       <?= h($C['text']) ?>;
    --inv-text-soft:  <?= h($C['text_soft']) ?>;
    --inv-bg:         <?= h($C['bg']) ?>;
    --inv-parties-bg: <?= h($C['parties_bg']) ?>;
    --inv-border:     <?= h($C['border']) ?>;
    --inv-table-alt:  <?= h($C['table_alt']) ?>;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:#f1f5f9; color:var(--inv-text); }
.page-outer { max-width:820px; margin:30px auto; }
.toolbar { background:#0f172a; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; border-radius:12px 12px 0 0; }
.toolbar h1 { color:white; font-size:14px; font-weight:600; }
.btn-print { background:var(--inv-primary); color:white; border:none; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; }
.btn-back  { background:transparent; color:#94a3b8; border:1px solid #334155; padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; font-family:inherit; text-decoration:none; display:inline-block; }
.invoice-doc { background:var(--inv-bg); min-height:1050px; display:flex; flex-direction:column; border-radius:0 0 12px 12px; box-shadow:0 4px 24px rgba(0,0,0,.1); }
.invoice-body { flex:1; padding:40px 48px 24px; }
.invoice-footer-area { padding:0 48px 28px; }
.inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; }
.inv-logo-txt { font-size:22px; font-weight:800; color:var(--inv-primary); }
.inv-num  { font-size:20px; font-weight:800; color:var(--inv-primary); }
.inv-date { font-size:12px; color:var(--inv-text-soft); margin-top:4px; }
.inv-status { display:inline-block; padding:3px 12px; border-radius:999px; font-size:11px; font-weight:600; margin-top:6px; }
.status-payee     { background:#d1fae5; color:#065f46; }
.status-envoyee   { background:#dbeafe; color:#1e40af; }
.status-brouillon { background:#f1f5f9; color:#475569; }
.status-impayee   { background:#fee2e2; color:#991b1b; }
.status-annulee   { background:#ffedd5; color:#9a3412; }
.inv-client-box { padding:18px 22px; background:var(--inv-parties-bg); border-radius:12px; margin-bottom:28px; border:1px solid var(--inv-border); }
.party-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.party-name  { font-size:16px; font-weight:700; color:var(--inv-text); margin-bottom:3px; }
.party-info  { font-size:12px; color:var(--inv-text-soft); line-height:1.6; }
.inv-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
.inv-table thead tr { border-bottom:2px solid var(--inv-border); }
.inv-table th { padding:10px 8px; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.8px; text-align:left; }
.inv-table th:not(:first-child) { text-align:right; }
.inv-table td { padding:11px 8px; font-size:13px; color:var(--inv-text); }
.inv-table tbody tr:nth-child(odd) { background:var(--inv-table-alt); }
.inv-table td:not(:first-child) { text-align:right; color:var(--inv-text-soft); }
.inv-table td:last-child { font-weight:600; color:var(--inv-text); }
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
.inv-notes { margin-bottom:20px; padding:16px; background:var(--inv-parties-bg); border-radius:10px; border-left:3px solid var(--inv-primary); }
.inv-notes .lbl { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.inv-notes p { font-size:12px; color:var(--inv-text-soft); }
.fi-divider { border:none; border-top:1px solid var(--inv-border); margin:0; }
@media print {
    body { background:white; }
    .page-outer { margin:0; max-width:100%; }
    .toolbar { display:none !important; }
    .invoice-doc { border-radius:0; box-shadow:none; min-height:100vh; }
    .invoice-body { padding:28px 36px 16px; }
    .invoice-footer-area { padding:0 36px 20px; }
    * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
}
</style>
</head>
<body>
<div class="page-outer">

<div class="toolbar">
    <h1>📄 <?= h($facture['numero']) ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= APP_URL ?>/facture-view.php?id=<?= $facture['id'] ?>" class="btn-back">← Retour</a>
        <button class="btn-print" onclick="downloadPdf()" style="background:#10B981;">⬇️ Télécharger PDF</button>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    </div>
</div>

<div class="invoice-doc">
<div class="invoice-body">

<?php if (!empty($_invHeader)): ?>
<div style="padding-bottom:16px;margin-bottom:24px;border-bottom:1px solid var(--inv-border);font-size:12px;color:var(--inv-text-soft);white-space:pre-line;"><?= h($_invHeader) ?></div>
<?php endif; ?>

<?php
    $_pHasLogo  = !empty($_logoPath);
    $_pHdMode   = (json_decode($user['invoice_columns'] ?? '{}', true) ?: [])['header_display'] ?? 'logo_name';
    $_pShowName = !$_pHasLogo || $_pHdMode === 'logo_name';
?>
<div class="inv-header">
    <div>
        <?php if ($_pHasLogo): ?>
            <img src="<?= h(APP_URL.'/'.ltrim($_logoPath,'/')) ?>" alt="Logo"
                 style="max-height:72px;max-width:200px;object-fit:contain;display:block;margin-bottom:6px;">
        <?php endif; ?>
        <?php if ($_pShowName): ?>
            <div class="inv-logo-txt"><?= h(!empty($user['entreprise']) ? $user['entreprise'] : 'Mon Entreprise') ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align:right;">
        <div class="inv-num"><?= h($facture['numero']) ?></div>
        <div class="inv-date">Date : <?= date('d/m/Y', strtotime($facture['date_facture'])) ?></div>
        <?php if ($facture['date_echeance']): ?>
        <div class="inv-date">Échéance : <?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></div>
        <?php endif; ?>
        <span class="inv-status status-<?= h($facture['statut']) ?>"><?= $smap[$facture['statut']] ?? ucfirst($facture['statut']) ?></span>
    </div>
</div>

<div class="inv-client-box">
    <div class="party-label">Facturé à</div>
    <div class="party-name"><?= h($facture['client_nom']) ?></div>
    <div class="party-info">
        <?php if ($facture['client_email'] || $facture['c_email']): ?><?= h($facture['client_email'] ?: $facture['c_email']) ?><br><?php endif; ?>
        <?php if ($facture['client_adresse']): ?><?= h($facture['client_adresse']) ?><br><?php endif; ?>
        <?php if ($facture['client_ice']): ?>ICE : <?= h($facture['client_ice']) ?><?php endif; ?>
    </div>
</div>

<table class="inv-table">
    <thead><tr>
        <?php foreach ($colOrder as $ck): ?>
        <th<?= $ck !== 'description' ? ' style="width:'.$colWidths[$ck].'"' : '' ?>><?= h($_col[$ck] ?? $ck) ?></th>
        <?php endforeach; ?>
    </tr></thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <?php foreach ($colOrder as $ck): ?>
            <?php
            switch ($ck) {
                case 'description': echo '<td>'.h($item['description']).'</td>'; break;
                case 'qty':         echo '<td>'.number_format((float)$item['quantite'],2,',',' ').'</td>'; break;
                case 'price':       echo '<td>'.formatMontant((float)$item['prix_unitaire'],$facture['devise']).'</td>'; break;
                case 'tva':         echo '<td>'.$item['tva'].'%</td>'; break;
                case 'total':       echo '<td>'.formatMontant((float)$item['total'],$facture['devise']).'</td>'; break;
            }
            ?>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals-arretee-row">
    <div class="inv-arretee">
        <div class="lbl">Arrêtée</div>
        <p>Arrêtée la présente facture à la somme de :<br><strong><?= h($_amountWords) ?></strong></p>
    </div>
    <div class="totals-box">
        <div class="totals-row"><span class="lbl">Sous-total HT</span><span class="val"><?= formatMontant((float)$facture['sous_total'],$facture['devise']) ?></span></div>
        <div class="totals-row"><span class="lbl">TVA</span><span class="val"><?= formatMontant((float)$facture['tva_montant'],$facture['devise']) ?></span></div>
        <div class="totals-final"><span class="lbl">Total TTC</span><span class="val"><?= formatMontant((float)$facture['total'],$facture['devise']) ?></span></div>
    </div>
</div>

<?php if ($facture['notes']): ?>
<div class="inv-notes"><div class="lbl">Notes</div><p><?= h($facture['notes']) ?></p></div>
<?php endif; ?>

</div><!-- /invoice-body -->
<div class="invoice-footer-area">
    <?php if ($footerBlock || $_invFooter): ?><hr class="fi-divider"><?php endif; ?>
    <?php if ($footerBlock): ?><?= $footerBlock ?><?php endif; ?>
    <?php if ($_invFooter): ?><p style="text-align:center;padding:12px 0 0;font-size:12.5px;color:var(--inv-text-soft);white-space:pre-line;"><?= h($_invFooter) ?></p><?php endif; ?>
</div>
</div><!-- /invoice-doc -->
</div>

<script>
function downloadPdf() {
    const el = document.querySelector('.invoice-doc');
    const btn = document.querySelector('.btn-print');
    const filename = 'Facture-<?= preg_replace('/[^A-Za-z0-9\-_]/', '-', $facture['numero']) ?>.pdf';
    const opt = {
        margin:      [0, 0, 0, 0],
        filename:    filename,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, backgroundColor: '<?= h($C['bg']) ?>' },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save();
}

window.addEventListener('load', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.get('download') === '1') {
        downloadPdf();
        setTimeout(() => { try { window.close(); } catch(e){} }, 4000);
    } else if (!p.get('preview')) {
        setTimeout(() => window.print(), 400);
    }
});
window.addEventListener('afterprint', () => {
    if (window.opener) window.close();
});
</script>
</body>
</html>
