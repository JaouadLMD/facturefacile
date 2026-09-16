<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user   = getCurrentUser();
$stats  = getStats($user['id']);
$devise = $user['devise'] ?? 'MAD';

// ── CA mensuel : Jan→Déc de l'année courante ──
$_curYear  = (int)date('Y');
$moisNoms  = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

$stmtM = $pdo->prepare("
    SELECT MONTH(date_facture) as m,
           SUM(CASE WHEN statut='payee' THEN total ELSE 0 END) as ca
    FROM factures WHERE user_id=? AND YEAR(date_facture)=?
    GROUP BY m
");
$stmtM->execute([$user['id'], $_curYear]);
$_caByMois = [];
foreach ($stmtM->fetchAll() as $d) { $_caByMois[(int)$d['m']] = (float)$d['ca']; }

$caLabels = $moisNoms;   // Jan, Fév … Déc
$caValues = [];
for ($m = 1; $m <= 12; $m++) { $caValues[] = $_caByMois[$m] ?? 0; }

// ── CA annuel : 5 dernières années ──
$stmtA = $pdo->prepare("
    SELECT YEAR(date_facture) as y,
           SUM(CASE WHEN statut='payee' THEN total ELSE 0 END) as ca
    FROM factures WHERE user_id=? AND YEAR(date_facture) >= ?
    GROUP BY y ORDER BY y
");
$stmtA->execute([$user['id'], $_curYear - 4]);
$_caByYear = [];
foreach ($stmtA->fetchAll() as $d) { $_caByYear[(int)$d['y']] = (float)$d['ca']; }

$caAnnualLabels = [];
$caAnnualValues = [];
for ($y = $_curYear - 4; $y <= $_curYear; $y++) {
    $caAnnualLabels[] = (string)$y;
    $caAnnualValues[] = $_caByYear[$y] ?? 0;
}

// ── Usage mensuel (plan Gratuit) ──
$_dashLimits  = getPlanLimits($user['plan'] ?? 'gratuit');
$_dashGratuit = $_dashLimits['factures'] < 999999;
if ($_dashGratuit) {
    $stmtUF = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=? AND MONTH(date_facture)=MONTH(CURDATE()) AND YEAR(date_facture)=YEAR(CURDATE())");
    $stmtUF->execute([$user['id']]);
    $_dashUsedF = (int)$stmtUF->fetchColumn();
    $stmtUD = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND MONTH(date_devis)=MONTH(CURDATE()) AND YEAR(date_devis)=YEAR(CURDATE())");
    $stmtUD->execute([$user['id']]);
    $_dashUsedD = (int)$stmtUD->fetchColumn();
    $_dashLimF  = $_dashLimits['factures'];
    $_dashLimD  = $_dashLimits['devis'];
}

// ── Répartition par statut (donut) ──
$stmtSt = $pdo->prepare("SELECT statut, COUNT(*) as nb FROM factures WHERE user_id=? GROUP BY statut");
$stmtSt->execute([$user['id']]);
$statutsMap = [];
foreach ($stmtSt->fetchAll() as $r) { $statutsMap[$r['statut']] = (int)$r['nb']; }
$nbEnvoyees = $statutsMap['envoyee']   ?? 0;
$nbPayees   = $statutsMap['payee']     ?? 0;
$nbImpayees = $statutsMap['impayee']   ?? 0;
$nbAnnulees = $statutsMap['annulee']   ?? 0;
$nbBrouillons=$statutsMap['brouillon'] ?? 0;

// ── Factures récentes ──
$stmt2 = $pdo->prepare("
    SELECT f.*, COALESCE(c.nom, f.client_nom) as nom_client
    FROM factures f LEFT JOIN clients c ON f.client_id=c.id
    WHERE f.user_id=? ORDER BY f.created_at DESC LIMIT 6
");
$stmt2->execute([$user['id']]);
$facturesRecentes = $stmt2->fetchAll();

// ── Metrics ──
$impayees   = getFacturesImpayees($user['id']);
$caEncaisse = (float)($stats['ca_paye']    ?? 0);
$caAttente  = (float)($stats['ca_attente'] ?? 0);
$taux       = (int)  ($stats['taux']       ?? 0);

// ── Growth vs mois précédent ──
$stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN statut='payee' THEN total ELSE 0 END),0) FROM factures
    WHERE user_id=? AND YEAR(date_facture)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
      AND MONTH(date_facture)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))");
$stmtPrev->execute([$user['id']]);
$caPrevMois = (float)$stmtPrev->fetchColumn();
$stmtCurr = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN statut='payee' THEN total ELSE 0 END),0) FROM factures
    WHERE user_id=? AND YEAR(date_facture)=YEAR(CURDATE()) AND MONTH(date_facture)=MONTH(CURDATE())");
$stmtCurr->execute([$user['id']]);
$caCurrMois = (float)$stmtCurr->fetchColumn();
$caGrowth   = $caPrevMois > 0 ? round(($caCurrMois - $caPrevMois) / $caPrevMois * 100, 1) : 0;

// ── Top clients ──
$stmt4 = $pdo->prepare("
    SELECT COALESCE(c.nom, f.client_nom) as nom, COUNT(f.id) as nb, SUM(f.total) as ca_total
    FROM factures f LEFT JOIN clients c ON f.client_id=c.id
    WHERE f.user_id=? AND f.statut='payee'
    GROUP BY COALESCE(c.nom,f.client_nom) ORDER BY ca_total DESC LIMIT 4
");
$stmt4->execute([$user['id']]);
$topClients = $stmt4->fetchAll();

$hideTopbar = true;
$pageTitle  = 'Tableau de bord';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<style>
/* ── Dashboard overrides ── */
.page-body { padding: 0 !important; }

/* ── Dashboard header ── */
.dash-hdr {
    padding: 20px 28px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border-bottom: 1px solid var(--border);
    background: var(--bg2);
    position: sticky;
    top: 0;
    z-index: 20;
    box-shadow: 0 2px 20px rgba(0,0,0,0.18);
}
.dash-greeting { font-size: 20px; font-weight: 800; color: var(--text); letter-spacing: -.4px; margin: 0 0 2px; }
.dash-subdate  { font-size: 12px; color: var(--text-soft); margin: 0; }
.dash-hdr-actions { display: flex; align-items: center; gap: 10px; }
.dash-search {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 12px; padding: 9px 14px; min-width: 200px;
}
.dash-search input { border: none; background: transparent; font-size: 13px; color: var(--text); outline: none; width: 100%; }
.dash-search input::placeholder { color: var(--text-soft); }

/* ── Stat grid ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.stat-card-dash {
    background: var(--card); border-radius: 16px;
    border: 1px solid var(--border);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    display: flex; flex-direction: column; gap: 12px;
    transition: transform .2s, box-shadow .2s;
}
.stat-card-dash:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,.25); }
.stat-icon-box {
    width: 42px; height: 42px; border-radius: 13px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-row-top { display: flex; align-items: flex-start; justify-content: space-between; }
.stat-label { font-size: 11.5px; color: var(--text-soft); font-weight: 500; margin: 0 0 4px; }
.stat-value { font-size: 22px; font-weight: 900; color: var(--text); letter-spacing: -.5px; margin: 0 0 6px; line-height: 1.1; }
.stat-value span { font-size: 12px; font-weight: 500; color: var(--text-soft); }

/* ── Charts row ── */
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; }
.chart-card {
    background: var(--card); border-radius: 16px;
    border: 1px solid var(--border);
    padding: 22px 24px;
    box-shadow: var(--shadow);
}
.chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.chart-title  { font-size: 14px; font-weight: 700; color: var(--text); margin: 0; }
.chart-sub    { font-size: 11.5px; color: var(--text-soft); margin: 2px 0 0; }
.chart-tabs   { display: flex; gap: 4px; background: var(--bg3); border-radius: 9px; padding: 3px; }
.chart-tab    { padding: 5px 12px; border-radius: 7px; font-size: 12px; font-weight: 600; color: var(--text-mid); cursor: pointer; border: none; background: transparent; transition: all .15s; }
.chart-tab.active { background: var(--card); color: var(--text); box-shadow: 0 1px 6px rgba(0,0,0,.15); }

/* Donut card */
.donut-wrap { display: flex; flex-direction: column; height: 100%; }
.donut-canvas-wrap { position: relative; width: 180px; height: 180px; margin: 0 auto 16px; flex-shrink: 0; }
.donut-center-label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.donut-legend { display: flex; flex-direction: column; gap: 10px; flex: 1; }
.legend-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.legend-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.legend-name { font-size: 12.5px; color: var(--text-mid); flex: 1; }
.legend-val  { font-size: 13px; font-weight: 700; color: var(--text); }
.legend-pct  { font-size: 11px; color: var(--text-soft); }

/* ── Bottom row ── */
.bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Recent invoices list */
.inv-item { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--border); }
.inv-item:last-child { border-bottom: none; }
.inv-avatar { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.inv-client { font-size: 13px; font-weight: 600; color: var(--text); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
.inv-meta   { font-size: 11px; color: var(--text-soft); margin: 0; }

/* ── Alert impayées ── */
.alert-impayees {
    margin: 0 28px 0;
    background: rgba(239,68,68,.12);
    border: 1px solid rgba(239,68,68,.25);
    border-radius: 14px; padding: 12px 18px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}

/* ── Main wrap padding ── */
.dash-inner { padding: 22px 28px 32px; display: flex; flex-direction: column; gap: 18px; }

/* ── Balance highlight ── */
.balance-box {
    background: linear-gradient(135deg, var(--primary) 0%, #A78BFA 100%);
    border-radius: 14px; padding: 18px 20px;
    position: relative; overflow: hidden; margin-bottom: 16px;
}
.balance-box::before {
    content: ''; position: absolute; top: -30px; right: -30px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.balance-box::after {
    content: ''; position: absolute; bottom: -40px; left: 10px;
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
}

/* ── Quick actions ── */
.qa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.qa-item {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 13px 8px; border-radius: 12px;
    border: 1px solid var(--border); background: var(--bg3);
    text-decoration: none; cursor: pointer;
    transition: all .15s;
}
.qa-item:hover { background: var(--primary-bg); border-color: var(--primary); }
.qa-item.qa-primary { background: var(--primary); border-color: var(--primary); }
.qa-item svg { width: 17px; height: 17px; }
.qa-label { font-size: 11.5px; font-weight: 600; color: var(--text-mid); }
.qa-item:hover .qa-label { color: var(--primary-2); }
.qa-item.qa-primary .qa-label { color: white; }

/* ── Progress bar ── */
.progress-bar { height: 5px; background: var(--bg3); border-radius: 999px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 999px; transition: width .6s ease; }

@media (max-width: 1200px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .charts-row { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
    .bottom-row { grid-template-columns: 1fr; }
    .dash-hdr   { padding: 14px 16px; }
    .dash-inner { padding: 16px 16px 24px; }
    .dash-search{ display: none !important; }
}
@media (max-width: 600px) {
    .stat-grid { grid-template-columns: 1fr; }
}
</style>

<?php
$_accessDenied = $_GET['access_denied'] ?? '';
$_accessLabels = [
    'historique'  => "l'historique des actions",
    'export'      => "l'export de données",
    'equipe'      => "la gestion d'équipe",
    'relances'    => "les relances automatiques",
    'ai'          => "l'assistant IA",
    'settings'    => "les paramètres de l'application",
    'owner_only'  => "la gestion des abonnements",
];
if ($_accessDenied && !isTeamMember()):
?>
<div style="margin:16px 28px 0;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:13px 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span style="font-size:18px;">🔒</span>
    <div style="flex:1;">
        <p style="font-size:13.5px;font-weight:700;color:#EF4444;margin:0 0 2px;">Accès restreint</p>
        <p style="font-size:12.5px;color:var(--text-mid);margin:0;">Votre plan actuel (<strong><?= planLabel($user['plan'] ?? 'gratuit') ?></strong>) ne permet pas d'accéder à <?= $_accessLabels[$_accessDenied] ?? 'cette fonctionnalité' ?>.</p>
    </div>
    <a href="<?= APP_URL ?>/plans.php" style="padding:8px 16px;background:#EF4444;color:white;border-radius:9px;font-size:12.5px;font-weight:700;text-decoration:none;white-space:nowrap;">⭐ Mettre à niveau</a>
</div>
<?php elseif ($_accessDenied && isTeamMember()): ?>
<div style="margin:16px 28px 0;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:13px 18px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:18px;">🔒</span>
    <p style="font-size:13px;color:var(--text-mid);margin:0;">Vous n'avez pas accès à <?= $_accessLabels[$_accessDenied] ?? 'cette fonctionnalité' ?>. Contactez l'administrateur du compte.</p>
</div>
<?php endif; ?>

<!-- ══ DASHBOARD HEADER ══ -->
<div class="dash-hdr">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="hamburger-btn" onclick="openSidebar()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div>
            <p class="dash-greeting">Bonjour, <?= h($_dispNom ?: 'Utilisateur') ?> 👋</p>
            <p class="dash-subdate"><?= date('l d F Y') ?> — Tableau de bord</p>
        </div>
    </div>
    <div class="dash-hdr-actions">
        <div class="dash-search">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Rechercher…" onkeydown="if(event.key==='Enter'){window.location=APP_URL+'/factures.php?q='+encodeURIComponent(this.value);}">
        </div>
        <a href="<?= APP_URL ?>/facture-create.php" class="btn-new">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Nouvelle facture</span>
        </a>
        <a href="<?= APP_URL ?>/chat.php" class="topbar-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <?php if(!empty($nbMessages) && $nbMessages > 0):?>
            <span class="topbar-notif-dot"><?= $nbMessages ?></span>
            <?php endif;?>
        </a>
        <?php if(!isTeamMember() || isPrivilegedMember()):?>
        <a href="<?= APP_URL ?>/parametres.php" class="topbar-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        </a>
        <?php endif;?>
        <div class="topbar-avatar" onclick="openPhotoModal()">
            <?php if($_dispPhoto):?><img src="<?= h($_dispPhoto) ?>"><?php else: echo $_dispInit; endif;?>
        </div>
    </div>
</div>

<?php if(!empty($impayees)):?>
<!-- Alert impayées -->
<div class="alert-impayees">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:50%;background:rgba(239,68,68,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="16" height="16" fill="none" stroke="#EF4444" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div>
            <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;"><?= count($impayees) ?> facture(s) impayée(s) en retard</p>
            <p style="font-size:12px;color:#EF4444;margin:0;">Action requise — envoyez des relances</p>
        </div>
    </div>
    <a href="<?= APP_URL ?>/factures.php?statut=impayee" class="btn-primary" style="font-size:12px;padding:7px 14px;flex-shrink:0;">Voir →</a>
</div>
<?php endif;?>

<div class="dash-inner">

<?php if ($_dashGratuit): ?>
<!-- ══ USAGE BAR (plan Gratuit) ══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
    <?php foreach ([['Factures', $_dashUsedF, $_dashLimF, APP_URL.'/facture-create.php'], ['Devis', $_dashUsedD, $_dashLimD, APP_URL.'/devis-create.php']] as [$_lbl, $_used, $_lim, $_href]): ?>
    <?php $_pct = min(100, round($_used/$_lim*100)); $_full = $_used >= $_lim; ?>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px 18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-size:12.5px;font-weight:600;color:var(--text-mid);"><?= $_lbl ?> ce mois</span>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:13px;font-weight:800;color:<?= $_full ? '#EF4444' : 'var(--text)' ?>;"><?= $_used ?> / <?= $_lim ?></span>
                <?php if ($_full): ?>
                <a href="<?= APP_URL ?>/plans.php" style="font-size:11px;font-weight:700;padding:3px 10px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white;border-radius:99px;text-decoration:none;">⭐ Pro</a>
                <?php else: ?>
                <a href="<?= $_href ?>" style="font-size:11px;font-weight:600;color:var(--primary-2);text-decoration:none;">+ Créer</a>
                <?php endif; ?>
            </div>
        </div>
        <div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= $_pct ?>%;background:<?= $_full ? 'linear-gradient(90deg,#EF4444,#DC2626)' : 'linear-gradient(90deg,var(--primary),var(--primary-2))' ?>;border-radius:99px;transition:width .4s;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ STAT CARDS ══ -->
<div class="stat-grid">

    <!-- CA Encaissé -->
    <div class="stat-card-dash">
        <div class="stat-row-top">
            <div>
                <p class="stat-label">CA Encaissé</p>
                <p class="stat-value"><?= number_format($caEncaisse,0,',',' ') ?> <span><?= h($devise) ?></span></p>
                <?php if($caGrowth >= 0):?>
                <span class="trend-up">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                    +<?= $caGrowth ?>%
                </span>
                <?php else:?>
                <span class="trend-dn">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    <?= $caGrowth ?>%
                </span>
                <?php endif;?>
            </div>
            <div class="stat-icon-box" style="background:var(--primary-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-2)" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
        </div>
        <p style="font-size:11px;color:var(--text-soft);margin:0;">vs mois précédent</p>
    </div>

    <!-- En attente -->
    <div class="stat-card-dash">
        <div class="stat-row-top">
            <div>
                <p class="stat-label">En Attente</p>
                <p class="stat-value"><?= number_format($caAttente,0,',',' ') ?> <span><?= h($devise) ?></span></p>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:rgba(245,158,11,.15);color:#F59E0B;">
                    <?= $stats['impayees'] ?? 0 ?> impayée(s)
                </span>
            </div>
            <div class="stat-icon-box" style="background:rgba(245,158,11,.12);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <p style="font-size:11px;color:var(--text-soft);margin:0;">Factures non réglées</p>
    </div>

    <!-- Total factures -->
    <div class="stat-card-dash">
        <div class="stat-row-top">
            <div>
                <p class="stat-label">Total Factures</p>
                <p class="stat-value"><?= $stats['total'] ?? 0 ?></p>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:rgba(124,58,237,.15);color:var(--primary-2);">
                    <?= $stats['clients'] ?? 0 ?> clients
                </span>
            </div>
            <div class="stat-icon-box" style="background:var(--primary-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-2)" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
        </div>
        <p style="font-size:11px;color:var(--text-soft);margin:0;">Factures émises</p>
    </div>

    <!-- Taux recouvrement -->
    <div class="stat-card-dash">
        <div class="stat-row-top">
            <div>
                <p class="stat-label">Taux Recouvrement</p>
                <p class="stat-value"><?= $taux ?>%</p>
                <div class="progress-bar" style="width:90px;margin-top:2px;">
                    <div class="progress-fill" style="width:<?= min(100,$taux) ?>%;background:<?= $taux>=70?'var(--green)':($taux>=40?'#F59E0B':'#EF4444') ?>;"></div>
                </div>
            </div>
            <div class="stat-icon-box" style="background:rgba(16,185,129,.12);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <p style="font-size:11px;color:var(--text-soft);margin:0;">Paiements reçus / émis</p>
    </div>

</div><!-- /stat-grid -->

<!-- ══ CHARTS ROW ══ -->
<div class="charts-row">

    <!-- Area Chart: CA par mois -->
    <div class="chart-card">
        <div class="chart-header">
            <div>
                <p class="chart-title">Chiffre d'Affaires</p>
                <p class="chart-sub">Janvier → Décembre <?= $_curYear ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:10px;height:10px;border-radius:50%;background:var(--primary);display:inline-block;"></span>
                    <span style="font-size:12px;color:var(--text-soft);">CA Payé</span>
                </div>
                <div class="chart-tabs">
                    <button class="chart-tab active" id="tabMensuel" onclick="switchChartPeriod('mensuel')">Mensuel</button>
                    <button class="chart-tab" id="tabAnnuel" onclick="switchChartPeriod('annuel')">Annuel</button>
                </div>
            </div>
        </div>
        <div style="position:relative;height:220px;">
            <canvas id="caChart"></canvas>
        </div>
        <!-- Sub metrics row -->
        <div style="display:flex;gap:20px;margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
            <div>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 3px;">Ce mois</p>
                <p style="font-size:16px;font-weight:800;color:var(--text);margin:0;"><?= number_format($caCurrMois,0,',',' ') ?> <span style="font-size:11px;color:var(--text-soft);"><?= h($devise) ?></span></p>
            </div>
            <div style="width:1px;background:var(--border);"></div>
            <div>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 3px;">Mois précédent</p>
                <p style="font-size:16px;font-weight:800;color:var(--text);margin:0;"><?= number_format($caPrevMois,0,',',' ') ?> <span style="font-size:11px;color:var(--text-soft);"><?= h($devise) ?></span></p>
            </div>
            <div style="width:1px;background:var(--border);"></div>
            <div>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 3px;">Variation</p>
                <p style="font-size:16px;font-weight:800;color:<?= $caGrowth >= 0 ? 'var(--green)' : '#EF4444' ?>;margin:0;"><?= $caGrowth >= 0 ? '+' : '' ?><?= $caGrowth ?>%</p>
            </div>
            <div style="width:1px;background:var(--border);"></div>
            <div>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 3px;">Total encaissé</p>
                <p style="font-size:16px;font-weight:800;color:var(--text);margin:0;"><?= number_format($caEncaisse,0,',',' ') ?> <span style="font-size:11px;color:var(--text-soft);"><?= h($devise) ?></span></p>
            </div>
        </div>
    </div>

    <!-- Donut Chart: Types de factures -->
    <div class="chart-card">
        <div class="chart-header" style="margin-bottom:14px;">
            <div>
                <p class="chart-title">Répartition Factures</p>
                <p class="chart-sub">Par statut — total <?= ($stats['total'] ?? 0) ?></p>
            </div>
        </div>
        <?php
        $totalFact = $nbEnvoyees + $nbPayees + $nbImpayees + $nbAnnulees + $nbBrouillons;
        $donutColors = ['#7C3AED','#10B981','#EF4444','#F97316','#6B7280'];
        $donutItems  = [
            ['Envoyées',   $nbEnvoyees,  '#7C3AED'],
            ['Payées',     $nbPayees,    '#10B981'],
            ['Impayées',   $nbImpayees,  '#EF4444'],
            ['Annulées',   $nbAnnulees,  '#F97316'],
            ['Brouillons', $nbBrouillons,'#6B7280'],
        ];
        ?>
        <div class="donut-wrap">
            <div class="donut-canvas-wrap">
                <canvas id="typesChart"></canvas>
                <div class="donut-center-label">
                    <p style="font-size:24px;font-weight:900;color:var(--text);margin:0;line-height:1;"><?= $totalFact ?></p>
                    <p style="font-size:11px;color:var(--text-soft);margin:0;">total</p>
                </div>
            </div>
            <div class="donut-legend">
                <?php foreach($donutItems as $di): ?>
                <div class="legend-item" style="opacity:<?= $di[1] === 0 ? '0.45' : '1' ?>;">
                    <span class="legend-dot" style="background:<?= $di[2] ?>;"></span>
                    <span class="legend-name"><?= $di[0] ?></span>
                    <span class="legend-val"><?= $di[1] ?></span>
                    <span class="legend-pct"><?= $totalFact > 0 ? round($di[1]/$totalFact*100) : 0 ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div><!-- /charts-row -->

<!-- ══ BOTTOM ROW ══ -->
<div class="bottom-row">

    <!-- Factures récentes -->
    <div class="chart-card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="font-size:14px;font-weight:700;color:var(--text);margin:0;">Factures récentes</p>
                <p style="font-size:11.5px;color:var(--text-soft);margin:2px 0 0;">Dernières transactions</p>
            </div>
            <a href="<?= APP_URL ?>/factures.php" style="font-size:12.5px;font-weight:700;color:var(--primary-2);text-decoration:none;display:flex;align-items:center;gap:4px;">
                Voir tout
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
        <div style="padding:4px 20px 14px;">
            <?php if(empty($facturesRecentes)):?>
            <div style="text-align:center;padding:32px 0;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--border2)" stroke-width="1.5" style="display:block;margin:0 auto 10px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <p style="font-size:13px;color:var(--text-soft);margin:0;">Aucune facture — <a href="<?= APP_URL ?>/facture-create.php" style="color:var(--primary-2);font-weight:700;text-decoration:none;">Créer →</a></p>
            </div>
            <?php else:?>
            <?php
            $avColors = ['#7C3AED','#10B981','#F59E0B','#EF4444','#8B5CF6','#3B82F6'];
            $stColors  = ['payee'=>'#10B981','envoyee'=>'#7C3AED','brouillon'=>'#6B7280','impayee'=>'#EF4444','annulee'=>'#F97316'];
            $stLabels  = ['payee'=>'Payée','envoyee'=>'Envoyée','brouillon'=>'Brouillon','impayee'=>'Impayée','annulee'=>'Annulée'];
            foreach($facturesRecentes as $fi => $f):
                $clr  = $avColors[$fi % count($avColors)];
                $init = strtoupper(mb_substr($f['nom_client']??'?',0,1));
                $sc   = $stColors[$f['statut']] ?? '#6B7280';
                $sl   = $stLabels[$f['statut']] ?? ucfirst($f['statut']);
            ?>
            <div class="inv-item">
                <div class="inv-avatar" style="background:<?= $clr ?>20;color:<?= $clr ?>;"><?= $init ?></div>
                <div style="flex:1;min-width:0;">
                    <p class="inv-client"><?= h($f['nom_client']??'Client') ?></p>
                    <p class="inv-meta"><?= h($f['numero_facture']??'') ?> · <?= date('d M Y',strtotime($f['date_facture'])) ?></p>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <p style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 3px;"><?= number_format((float)$f['total'],0,',',' ') ?> <span style="font-size:10px;color:var(--text-soft);"><?= h($devise) ?></span></p>
                    <span class="badge-pill badge-<?= $f['statut'] ?>"><?= $sl ?></span>
                </div>
            </div>
            <?php endforeach;?>
            <?php endif;?>
        </div>
    </div>

    <!-- Locked: Cash Flow / Paiements -->
    <div class="locked-section chart-card" style="padding:0;overflow:hidden;min-height:320px;">
        <!-- Blurred background content -->
        <div class="locked-content">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
                <p style="font-size:14px;font-weight:700;color:var(--text);margin:0;">Cash Flow</p>
                <p style="font-size:11.5px;color:var(--text-soft);margin:2px 0 0;">Flux de trésorerie</p>
            </div>
            <div style="padding:20px;">
                <div style="height:200px;display:flex;align-items:flex-end;gap:8px;">
                    <?php foreach([40,65,30,80,55,90,45] as $bh):?>
                    <div style="flex:1;height:<?= $bh ?>%;background:var(--primary-bg);border-radius:6px 6px 0 0;"></div>
                    <?php endforeach;?>
                </div>
            </div>
        </div>
        <!-- Locked overlay -->
        <div class="locked-overlay">
            <div class="locked-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </div>
            <p style="font-size:15px;font-weight:800;color:var(--text);margin:0;text-align:center;">Cash Flow & Paiements</p>
            <p style="font-size:12.5px;color:var(--text-mid);text-align:center;line-height:1.6;max-width:220px;margin:0;">Débloquez le suivi de trésorerie avancé avec un abonnement Pro</p>
            <a href="<?= APP_URL ?>/parametres.php#plans" class="locked-btn">
                🚀 Passer Pro — Débloquer
            </a>
        </div>
    </div>

</div><!-- /bottom-row -->

<!-- ══ QUICK ACTIONS + TOP CLIENTS ROW ══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

    <!-- Quick actions -->
    <div class="chart-card">
        <p style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 14px;">Actions rapides</p>
        <div class="qa-grid">
            <a href="<?= APP_URL ?>/facture-create.php" class="qa-item qa-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span class="qa-label" style="color:white;">Créer facture</span>
            </a>
            <a href="<?= APP_URL ?>/devis.php" class="qa-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary-2)" stroke-width="2" stroke-linecap="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                <span class="qa-label">Devis</span>
            </a>
            <a href="<?= APP_URL ?>/clients.php" class="qa-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary-2)" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                <span class="qa-label">Clients</span>
            </a>
            <a href="<?= APP_URL ?>/chat.php" class="qa-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary-2)" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                <span class="qa-label">Assistant IA</span>
            </a>
        </div>
    </div>

    <!-- Top clients -->
    <div class="chart-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <p style="font-size:14px;font-weight:700;color:var(--text);margin:0;">Top Clients</p>
            <a href="<?= APP_URL ?>/clients.php" style="font-size:12.5px;font-weight:700;color:var(--primary-2);text-decoration:none;">Voir tout →</a>
        </div>
        <?php if(empty($topClients)):?>
        <p style="font-size:12.5px;color:var(--text-soft);text-align:center;padding:20px 0;margin:0;">Aucun client pour le moment</p>
        <?php else:?>
        <?php
        $clrPalette = ['#7C3AED','#10B981','#F59E0B','#EF4444','#3B82F6'];
        foreach($topClients as $ci => $tc): $clr = $clrPalette[$ci%count($clrPalette)];?>
        <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
            <div style="width:34px;height:34px;border-radius:50%;background:<?= $clr ?>20;color:<?= $clr ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;"><?= strtoupper(mb_substr($tc['nom'],0,1)) ?></div>
            <p style="font-size:13px;font-weight:600;color:var(--text);margin:0;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($tc['nom']) ?></p>
            <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;white-space:nowrap;"><?= number_format($tc['ca_total'],0,',',' ') ?> <span style="font-size:10px;color:var(--text-soft);"><?= h($devise) ?></span></p>
        </div>
        <?php endforeach;?>
        <?php endif;?>
    </div>

</div>

</div><!-- /dash-inner -->

<!-- ══ CHART.JS ══ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function(){
// ── Helpers ──
function getCSSVar(name){
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

// ── Area Chart: CA ──
const caLabelsMensuel  = <?= json_encode($caLabels) ?>;
const caValuesMensuel  = <?= json_encode($caValues) ?>;
const caLabelsAnnuel   = <?= json_encode($caAnnualLabels) ?>;
const caValuesAnnuel   = <?= json_encode($caAnnualValues) ?>;
const caLabels = caLabelsMensuel;
const caValues = caValuesMensuel;

const caCtx = document.getElementById('caChart').getContext('2d');
const caGrad = caCtx.createLinearGradient(0, 0, 0, 220);
caGrad.addColorStop(0,  'rgba(124, 58, 237, 0.45)');
caGrad.addColorStop(0.6,'rgba(124, 58, 237, 0.12)');
caGrad.addColorStop(1,  'rgba(124, 58, 237, 0.0)');

const caChart = new Chart(caCtx, {
    type: 'line',
    data: {
        labels: caLabels,
        datasets: [{
            label: 'CA Payé',
            data: caValues,
            fill: true,
            backgroundColor: caGrad,
            borderColor: '#7C3AED',
            borderWidth: 2.5,
            pointBackgroundColor: '#7C3AED',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(26,26,40,0.95)',
                borderColor: 'rgba(124,58,237,0.4)',
                borderWidth: 1,
                titleColor: '#A78BFA',
                bodyColor: '#F0F0FA',
                padding: 12,
                cornerRadius: 10,
                callbacks: {
                    label: function(ctx){
                        return '  ' + Number(ctx.parsed.y).toLocaleString('fr-FR') + ' <?= h($devise) ?>';
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                ticks: { color: '#5A5A7A', font: { size: 11, weight: '500' } }
            },
            y: {
                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                ticks: {
                    color: '#5A5A7A', font: { size: 11 },
                    callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                },
                beginAtZero: true
            }
        }
    }
});

// ── Donut Chart: types de factures ──
const typesData   = [<?= $nbEnvoyees ?>, <?= $nbPayees ?>, <?= $nbImpayees ?>, <?= $nbAnnulees ?>, <?= $nbBrouillons ?>];
const typesLabels = ['Envoyées','Payées','Impayées','Annulées','Brouillons'];
const typesColors = ['#7C3AED','#10B981','#EF4444','#F97316','#6B7280'];
const totalFact   = <?= $totalFact ?>;

const tCtx = document.getElementById('typesChart').getContext('2d');
new Chart(tCtx, {
    type: 'doughnut',
    data: {
        labels: typesLabels,
        datasets: [{
            data: totalFact > 0 ? typesData : [1],
            backgroundColor: totalFact > 0 ? typesColors : ['rgba(90,90,122,0.2)'],
            borderWidth: 0,
            hoverOffset: 6,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: totalFact > 0 ? {
                backgroundColor: 'rgba(26,26,40,0.95)',
                borderColor: 'rgba(124,58,237,0.4)',
                borderWidth: 1,
                titleColor: '#A78BFA',
                bodyColor: '#F0F0FA',
                padding: 10,
                cornerRadius: 10,
            } : { enabled: false }
        }
    }
});

// ── Chart period toggle ──
window.switchChartPeriod = function(period) {
    document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab' + period.charAt(0).toUpperCase() + period.slice(1)).classList.add('active');

    const labels = period === 'annuel' ? caLabelsAnnuel : caLabelsMensuel;
    const values = period === 'annuel' ? caValuesAnnuel : caValuesMensuel;

    caChart.data.labels = labels;
    caChart.data.datasets[0].data = values;
    caChart.update('active');

    // Mettre à jour le sous-titre
    const sub = document.querySelector('.chart-sub');
    if (sub) sub.textContent = period === 'annuel'
        ? 'Évolution sur 5 ans'
        : 'Janvier → Décembre <?= $_curYear ?>';
};

// ── Update chart colors on theme change ──
const _origToggle = window.toggleTheme;
window.toggleTheme = function(){
    if(_origToggle) _origToggle();
    // Chart.js inherits colors from CSS variables, no update needed for gradient-based charts
};

})();
</script>

</div><!-- /page-body -->
</div><!-- /main-content -->
</body>
</html>
