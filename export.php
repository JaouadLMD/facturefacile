<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();

requirePlan('export');

// Stats for display
$stmtF = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=?"); $stmtF->execute([$user['id']]);
$stmtD = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=?");    $stmtD->execute([$user['id']]);
$stmtC = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id=?");  $stmtC->execute([$user['id']]);
$stmtL = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=?"); $stmtL->execute([$user['id']]);

$nbF = $stmtF->fetchColumn();
$nbD = $stmtD->fetchColumn();
$nbC = $stmtC->fetchColumn();
$nbL = $stmtL->fetchColumn();

$pageTitle = 'Exporter mes données';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:#111827;margin:0;">⬇️ Exporter mes données</h1>
        <p style="font-size:12px;color:#6B7280;margin:4px 0 0;">Téléchargez vos données en format CSV compatible Excel (UTF-8)</p>
    </div>
</div>

<!-- Info banner -->
<div style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border:1.5px solid #FDE68A;border-radius:14px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;">
    <div style="font-size:28px;">💡</div>
    <div>
        <p style="font-weight:600;color:#92400E;margin:0 0 3px;font-size:14px;">Format CSV compatible Microsoft Excel</p>
        <p style="font-size:12px;color:#B45309;margin:0;">Les fichiers sont encodés en UTF-8 avec BOM, séparateur point-virgule. Ouvrez directement dans Excel ou Google Sheets.</p>
    </div>
</div>

<!-- Export cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">

    <!-- Factures -->
    <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#DBEAFE,#BFDBFE);display:flex;align-items:center;justify-content:center;font-size:26px;">📄</div>
            <div>
                <p style="font-weight:700;color:#111827;font-size:15px;margin:0;">Factures</p>
                <p style="font-size:12px;color:#6B7280;margin:2px 0 0;"><?= number_format($nbF) ?> entrée(s)</p>
            </div>
        </div>
        <p style="font-size:12px;color:#9CA3AF;margin:0 0 16px;line-height:1.5;">Numéro, client, dates, montants HT/TVA/TTC, statut, devise, notes.</p>
        <button onclick="dlExport('factures','📄 Factures')" class="btn-primary" style="width:100%;justify-content:center;">
            ⬇️ Télécharger factures.csv
        </button>
    </div>

    <!-- Devis -->
    <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#EDE9FE,#DDD6FE);display:flex;align-items:center;justify-content:center;font-size:26px;">📋</div>
            <div>
                <p style="font-weight:700;color:#111827;font-size:15px;margin:0;">Devis</p>
                <p style="font-size:12px;color:#6B7280;margin:2px 0 0;"><?= number_format($nbD) ?> entrée(s)</p>
            </div>
        </div>
        <p style="font-size:12px;color:#9CA3AF;margin:0 0 16px;line-height:1.5;">Numéro, client, dates, validité, montants, statut, notes.</p>
        <button onclick="dlExport('devis','📋 Devis')" class="btn-primary" style="width:100%;justify-content:center;background:linear-gradient(135deg,#7C3AED,#9333EA);">
            ⬇️ Télécharger devis.csv
        </button>
    </div>

    <!-- Clients -->
    <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#D1FAE5,#A7F3D0);display:flex;align-items:center;justify-content:center;font-size:26px;">👥</div>
            <div>
                <p style="font-weight:700;color:#111827;font-size:15px;margin:0;">Clients</p>
                <p style="font-size:12px;color:#6B7280;margin:2px 0 0;"><?= number_format($nbC) ?> entrée(s)</p>
            </div>
        </div>
        <p style="font-size:12px;color:#9CA3AF;margin:0 0 16px;line-height:1.5;">Nom, email, téléphone, ville, adresse, ICE, nombre de factures, CA total.</p>
        <button onclick="dlExport('clients','👥 Clients')" class="btn-primary" style="width:100%;justify-content:center;background:linear-gradient(135deg,#059669,#10B981);">
            ⬇️ Télécharger clients.csv
        </button>
    </div>

    <!-- Historique -->
    <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#FEF3C7,#FDE68A);display:flex;align-items:center;justify-content:center;font-size:26px;">📜</div>
            <div>
                <p style="font-weight:700;color:#111827;font-size:15px;margin:0;">Historique</p>
                <p style="font-size:12px;color:#6B7280;margin:2px 0 0;"><?= number_format($nbL) ?> entrée(s)</p>
            </div>
        </div>
        <p style="font-size:12px;color:#9CA3AF;margin:0 0 16px;line-height:1.5;">Journal complet des actions : date, heure, module, action, référence, IP.</p>
        <?php if ($planLimits['historique']): ?>
        <button onclick="dlExport('historique','📜 Historique')" class="btn-primary" style="width:100%;justify-content:center;background:linear-gradient(135deg,#D97706,#F59E0B);">
            ⬇️ Télécharger historique.csv
        </button>
        <?php else: ?>
        <button onclick="ffAlert('⭐ Plan requis','L\'export de l\'historique est disponible à partir du plan Pro.','warning')" class="btn-secondary" style="width:100%;justify-content:center;opacity:.6;">
            🔒 Réservé plan Pro
        </button>
        <?php endif; ?>
    </div>

</div>

<!-- Tout exporter -->
<div class="card" style="padding:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;">
        <p style="font-weight:700;color:#111827;font-size:15px;margin:0 0 3px;">📦 Tout exporter</p>
        <p style="font-size:12px;color:#6B7280;margin:0;">Téléchargez chaque fichier en un clic dans l'ordre.</p>
    </div>
    <button onclick="dlAll()" class="btn-primary" style="gap:8px;">
        ⬇️ Télécharger tous les fichiers
    </button>
</div>

<!-- Progress overlay -->
<div id="dlOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9990;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:20px;padding:32px 36px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.2);min-width:300px;">
        <div style="font-size:40px;margin-bottom:12px;">⬇️</div>
        <p id="dlLabel" style="font-weight:700;color:#111827;font-size:16px;margin:0 0 8px;"></p>
        <p style="font-size:13px;color:#6B7280;margin:0 0 16px;">Téléchargement en cours…</p>
        <div style="height:6px;background:#F3F4F6;border-radius:999px;overflow:hidden;">
            <div id="dlBar" style="height:100%;background:linear-gradient(135deg,#D97706,#F59E0B);transition:width .4s;width:0%;"></div>
        </div>
    </div>
</div>

<script>
function dlExport(type, label) {
    document.getElementById('dlLabel').textContent = label;
    document.getElementById('dlBar').style.width = '30%';
    document.getElementById('dlOverlay').style.display = 'flex';

    // Create hidden iframe for download (stays on current page)
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = APP_URL + '/api/export.php?type=' + type;
    document.body.appendChild(iframe);

    setTimeout(() => { document.getElementById('dlBar').style.width = '100%'; }, 400);
    setTimeout(() => {
        document.getElementById('dlOverlay').style.display = 'none';
        document.getElementById('dlBar').style.width = '0%';
        document.body.removeChild(iframe);
        showToast('✅ ' + label + ' exporté avec succès !');
    }, 2000);
}

async function dlAll() {
    const types = [
        ['factures','📄 Factures'],
        ['devis','📋 Devis'],
        ['clients','👥 Clients'],
        <?= $planLimits['historique'] ? "['historique','📜 Historique']," : '' ?>
    ];
    for (let i = 0; i < types.length; i++) {
        const [type, label] = types[i];
        document.getElementById('dlLabel').textContent = label;
        document.getElementById('dlBar').style.width = ((i+1)/types.length*100) + '%';
        document.getElementById('dlOverlay').style.display = 'flex';
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = APP_URL + '/api/export.php?type=' + type;
        document.body.appendChild(iframe);
        await new Promise(r => setTimeout(r, 1800));
        document.body.removeChild(iframe);
    }
    document.getElementById('dlOverlay').style.display = 'none';
    document.getElementById('dlBar').style.width = '0%';
    showToast('✅ Tous les fichiers ont été téléchargés !');
}
</script>

</div></div></body></html>
