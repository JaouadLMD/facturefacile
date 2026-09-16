<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM devis WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);
$devis = $stmt->fetch();
if (!$devis) { header('Location: ' . APP_URL . '/devis.php'); exit; }

$stmt2 = $pdo->prepare("SELECT * FROM devis_items WHERE devis_id = ? ORDER BY id");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll();

$expired = $devis['date_validite'] && strtotime($devis['date_validite']) < time() && !in_array($devis['statut'], ['accepte','refuse']);
$labels  = ['brouillon'=>'Brouillon','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé','expire'=>'Expiré'];

logAction($user['id'], 'print', 'devis', (int)$devis['id'], (string)$devis['numero'], 'Ouverture PDF');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis <?= h($devis['numero']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f1f5f9; color:#1e293b; }
        .page-wrapper { max-width:800px; margin:30px auto; background:white; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.1); }
        .toolbar { background:#0f172a; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
        .toolbar h1 { color:white; font-size:14px; font-weight:600; }
        .btn-print { background:#2563EB; color:white; border:none; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; }
        .btn-back  { background:transparent; color:#94a3b8; border:1px solid #334155; padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; font-family:inherit; text-decoration:none; display:inline-block; }
        .btn-print:hover { background:#1D4ED8; }
        .invoice { padding:48px; }
        .inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
        .inv-logo { font-size:26px; font-weight:800; color:#2563EB; letter-spacing:-0.5px; }
        .inv-logo span { color:#1e293b; }
        .inv-number { text-align:right; }
        .inv-number .num { font-size:18px; font-weight:700; color:#2563EB; }
        .inv-number .date { font-size:12px; color:#64748b; margin-top:4px; }
        .inv-parties { display:grid; grid-template-columns:1fr 1fr; gap:32px; margin-bottom:36px; padding:24px; background:#f8fafc; border-radius:12px; }
        .party-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .party-name  { font-size:15px; font-weight:700; color:#1e293b; margin-bottom:2px; }
        .party-info  { font-size:12px; color:#64748b; line-height:1.6; }
        .inv-status { display:inline-block; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; margin-top:6px; }
        .status-brouillon { background:#f1f5f9; color:#475569; }
        .status-envoye    { background:#dbeafe; color:#1e40af; }
        .status-accepte   { background:#d1fae5; color:#065f46; }
        .status-refuse    { background:#fee2e2; color:#991b1b; }
        .status-expire    { background:#ffedd5; color:#9a3412; }
        .badge-expired { background:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:10px 14px; margin-bottom:24px; font-size:12px; color:#B91C1C; }
        .inv-table { width:100%; border-collapse:collapse; margin-bottom:28px; }
        .inv-table thead tr { border-bottom:2px solid #e2e8f0; }
        .inv-table th { text-align:left; padding:10px 8px; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; }
        .inv-table th:not(:first-child) { text-align:right; }
        .inv-table td { padding:12px 8px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .inv-table td:not(:first-child) { text-align:right; color:#64748b; }
        .inv-table td:last-child { font-weight:600; color:#1e293b; }
        .inv-totals { display:flex; justify-content:flex-end; }
        .totals-box { width:240px; }
        .totals-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .totals-row .label { color:#64748b; }
        .totals-row .value { font-weight:500; color:#1e293b; }
        .totals-final { display:flex; justify-content:space-between; padding:12px 0 0; font-size:16px; font-weight:800; color:#2563EB; }
        .inv-notes { margin-top:28px; padding:16px; background:#f8fafc; border-radius:10px; border-left:3px solid #2563EB; }
        .inv-notes .label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
        .inv-notes p { font-size:12px; color:#475569; }
        .inv-footer { margin-top:40px; padding-top:20px; border-top:1px solid #e2e8f0; text-align:center; }
        .inv-footer p { font-size:11px; color:#94a3b8; }
        .inv-footer .thank { font-size:13px; font-weight:600; color:#475569; margin-bottom:4px; }
        @media print {
            body { background:white; }
            .page-wrapper { margin:0; border-radius:0; box-shadow:none; }
            .toolbar { display:none !important; }
            .invoice { padding:24px; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="toolbar">
        <h1>📋 Devis <?= h($devis['numero']) ?></h1>
        <div style="display:flex;gap:10px;">
            <a href="<?= APP_URL ?>/devis-view.php?id=<?= $devis['id'] ?>" class="btn-back">← Retour</a>
            <button class="btn-print" onclick="downloadPdf()" style="background:#10B981;">⬇️ Télécharger PDF</button>
            <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
        </div>
    </div>

    <div class="invoice">
        <!-- Expired warning (hidden on print) -->
        <?php if ($expired): ?>
        <div class="badge-expired">⚠️ Ce devis a expiré le <?= date('d/m/Y', strtotime($devis['date_validite'])) ?>.</div>
        <?php endif; ?>

        <!-- Header -->
        <div class="inv-header">
            <div>
                <?php
                    $_dLogoPath = $user['logo_path'] ?? '';
                    $_dHasLogo  = !empty($_dLogoPath);
                    $_dHdMode   = (json_decode($user['invoice_columns'] ?? '{}', true) ?: [])['header_display'] ?? 'logo_name';
                    $_dShowName = !$_dHasLogo || $_dHdMode === 'logo_name';
                ?>
                <?php if ($_dHasLogo): ?>
                <img src="<?= h(APP_URL.'/'.ltrim($_dLogoPath,'/')) ?>" alt="Logo" style="max-height:72px;max-width:200px;object-fit:contain;display:block;margin-bottom:6px;">
                <?php endif; ?>
                <?php if ($_dShowName): ?>
                <div class="inv-logo" style="font-size:22px;font-weight:800;color:#2563EB;"><?= h(!empty($user['entreprise']) ? $user['entreprise'] : 'Mon Entreprise') ?></div>
                <?php endif; ?>
            </div>
            <div class="inv-number">
                <div class="num"><?= h($devis['numero']) ?></div>
                <div class="date">Date : <?= date('d/m/Y', strtotime($devis['date_devis'])) ?></div>
                <?php if ($devis['date_validite']): ?>
                <div class="date" style="<?= $expired ? 'color:#DC2626;font-weight:600;' : '' ?>">
                    Valide jusqu'au : <?= date('d/m/Y', strtotime($devis['date_validite'])) ?>
                </div>
                <?php endif; ?>
                <span class="inv-status status-<?= h($devis['statut']) ?>">
                    <?= $labels[$devis['statut']] ?? ucfirst($devis['statut']) ?>
                </span>
            </div>
        </div>

        <!-- Parties -->
        <div class="inv-parties">
            <div>
                <div class="party-label">De</div>
                <div class="party-name"><?= h($user['entreprise'] ?: $user['nom']) ?></div>
                <div class="party-info">
                    <?php if ($user['adresse']): ?><?= h($user['adresse']) ?><br><?php endif; ?>
                    <?php if ($user['ville']): ?><?= h($user['ville']) ?>, <?= h($user['pays'] ?? 'Maroc') ?><br><?php endif; ?>
                    <?php if ($user['telephone']): ?><?= h($user['telephone']) ?><br><?php endif; ?>
                    <?php if ($user['email']): ?><?= h($user['email']) ?><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="party-label">Devis adressé à</div>
                <div class="party-name"><?= h($devis['client_nom']) ?></div>
                <div class="party-info">
                    <?php if ($devis['client_email']): ?><?= h($devis['client_email']) ?><br><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qté</th>
                    <th>Prix U.</th>
                    <th>TVA</th>
                    <th>Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['description']) ?></td>
                    <td><?= number_format((float)$item['quantite'], 2, ',', ' ') ?></td>
                    <td><?= formatMontant((float)$item['prix_unitaire'], $devis['devise']) ?></td>
                    <td><?= $item['tva'] ?>%</td>
                    <td><?= formatMontant((float)$item['total'], $devis['devise']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="inv-totals">
            <div class="totals-box">
                <div class="totals-row">
                    <span class="label">Sous-total HT</span>
                    <span class="value"><?= formatMontant((float)$devis['sous_total'], $devis['devise']) ?></span>
                </div>
                <div class="totals-row">
                    <span class="label">TVA</span>
                    <span class="value"><?= formatMontant((float)$devis['tva_montant'], $devis['devise']) ?></span>
                </div>
                <div class="totals-final">
                    <span>Total TTC</span>
                    <span><?= formatMontant((float)$devis['total'], $devis['devise']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($devis['notes']): ?>
        <div class="inv-notes">
            <div class="label">Notes & Conditions</div>
            <p><?= h($devis['notes']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="inv-footer">
            <p class="thank">Merci pour votre confiance ! 🙏</p>
            <p>Devis établi par <strong><?= h($user['entreprise'] ?: $user['nom']) ?></strong></p>
        </div>
    </div>
</div>

<script>
function downloadPdf() {
    const el = document.querySelector('.page-wrapper');
    const filename = 'Devis-<?= preg_replace('/[^A-Za-z0-9\-_]/', '-', $devis['numero']) ?>.pdf';
    const opt = {
        margin:      [0, 0, 0, 0],
        filename:    filename,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
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
