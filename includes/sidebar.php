<?php
$currentPage   = basename($_SERVER['PHP_SELF'], '.php');
$nbMessages    = getNbMessages($user['id'] ?? 0);
$planLimits    = getPlanLimits($user['plan'] ?? 'gratuit');
$userPlan      = $user['plan'] ?? 'gratuit';
$_isMember     = isTeamMember();
$_isPrivMember = isPrivilegedMember();

// Apply user's custom primary color to the whole app
$_ic_sb = json_decode($user['invoice_colors'] ?? '{}', true) ?: [];
$_appPrimary = null;
if (!empty($_ic_sb['primary']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_ic_sb['primary'])) {
    $_appPrimary = $_ic_sb['primary'];
    $_r = hexdec(substr($_appPrimary, 1, 2));
    $_g = hexdec(substr($_appPrimary, 3, 2));
    $_b = hexdec(substr($_appPrimary, 5, 2));
    // Lighter variant for --primary-2
    $_r2 = min(255, (int)($_r + (255-$_r)*0.38));
    $_g2 = min(255, (int)($_g + (255-$_g)*0.38));
    $_b2 = min(255, (int)($_b + (255-$_b)*0.38));
    $_primary2 = sprintf('#%02x%02x%02x', $_r2, $_g2, $_b2);
}

// Display identity
if (isTeamMember() && ($tm = getTeamMemberData())) {
    $_dispNom    = trim(($tm['prenom']??'').' '.($tm['nom']??'')) ?: ($user['nom']??'');
    $_dispEmail  = $tm['email'] ?? ($user['email']??'');
    $_dispPhoto  = !empty($tm['photo_path']) ? APP_URL.'/'.ltrim($tm['photo_path'],'/') : '';
    $_dispColor  = $tm['couleur'] ?? '#7C3AED';
} else {
    $_dispNom    = $user['nom'] ?? '';
    $_dispEmail  = $user['email'] ?? '';
    $_dispPhoto  = !empty($user['photo_path']) ? APP_URL.'/'.ltrim($user['photo_path'],'/') : '';
    $_dispColor  = '#7C3AED';
}
$_dispInit = strtoupper(mb_substr($_dispNom ?: 'U', 0, 1));

// Plan expiry
$_planExpiry  = null;
$_daysLeft    = null;
$_expiryColor = 'var(--primary)';
$_expiryBg    = 'var(--primary-bg)';
if (!isTeamMember() && !empty($user['plan_expires_at']) && $userPlan !== 'gratuit') {
    $_planExpiry = new DateTime($user['plan_expires_at']);
    $_now        = new DateTime();
    $_daysLeft   = (int)$_now->diff($_planExpiry)->days;
    if ($_planExpiry < $_now) { $_daysLeft = 0; }
    if ($_daysLeft > 30)      { $_expiryColor='#8B5CF6'; $_expiryBg='rgba(139,92,246,0.12)'; }
    elseif ($_daysLeft > 15)  { $_expiryColor='#F59E0B'; $_expiryBg='rgba(245,158,11,0.12)'; }
    elseif ($_daysLeft > 7)   { $_expiryColor='#F97316'; $_expiryBg='rgba(249,115,22,0.12)'; }
    else                      { $_expiryColor='#EF4444'; $_expiryBg='rgba(239,68,68,0.12)'; }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle).' – FactureFacile' : 'FactureFacile' ?></title>
<?php if ($_appPrimary): ?>
<style>
:root,[data-theme="dark"],[data-theme="light"]{
    --primary:      <?= h($_appPrimary) ?>;
    --primary-2:    <?= h($_primary2) ?>;
    --primary-bg:   rgba(<?= $_r ?>,<?= $_g ?>,<?= $_b ?>,.15);
    --primary-glow: rgba(<?= $_r ?>,<?= $_g ?>,<?= $_b ?>,.35);
}
</style>
<?php endif; ?>
<!-- Prevent FOUC: set theme from localStorage before styles load -->
<script>
(function(){
    var t=localStorage.getItem('ff-theme')||'dark';
    document.documentElement.setAttribute('data-theme',t);
})();
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{font-family:'Inter',sans-serif;box-sizing:border-box;margin:0;padding:0;}

/* ════════════════════════════════════════
   THEME VARIABLES
════════════════════════════════════════ */
:root,[data-theme="dark"]{
    --bg:         #0E0E16;
    --bg2:        #14141E;
    --bg3:        #1C1C2A;
    --card:       #1A1A28;
    --card2:      #20202E;
    --border:     rgba(255,255,255,0.07);
    --border2:    rgba(255,255,255,0.12);
    --text:       #F0F0FA;
    --text-mid:   #9090B0;
    --text-soft:  #5A5A7A;
    --primary:    #7C3AED;
    --primary-2:  #A78BFA;
    --primary-bg: rgba(124,58,237,0.15);
    --primary-glow:rgba(124,58,237,0.35);
    --green:      #10B981;
    --red:        #EF4444;
    --amber:      #F59E0B;
    --sidebar-w:  210px;
    --radius:     14px;
    --shadow:     0 4px 24px rgba(0,0,0,0.4);
    --overlay-bg: rgba(14,14,22,0.85);
}
[data-theme="light"]{
    --bg:         #F4F6FB;
    --bg2:        #FFFFFF;
    --bg3:        #EEF0F8;
    --card:       #FFFFFF;
    --card2:      #F8F9FC;
    --border:     rgba(0,0,0,0.07);
    --border2:    rgba(0,0,0,0.14);
    --text:       #1B1B2F;
    --text-mid:   #636e8a;
    --text-soft:  #A0A8C0;
    --shadow:     0 2px 16px rgba(0,0,0,0.07);
    --overlay-bg: rgba(244,246,251,0.88);
}

/* ════════════════════════════════════════
   BASE
════════════════════════════════════════ */
body{background:var(--bg);color:var(--text);min-height:100vh;transition:background .25s,color .25s;}

/* ════════════════════════════════════════
   SIDEBAR
════════════════════════════════════════ */
.sidebar{
    width:var(--sidebar-w);min-height:100vh;background:var(--bg2);
    border-right:1px solid var(--border);
    position:fixed;top:0;left:0;bottom:0;
    display:flex;flex-direction:column;z-index:40;
    transition:transform .3s cubic-bezier(.4,0,.2,1);
    overflow:hidden;
}
.sb-logo{
    display:flex;align-items:center;gap:10px;padding:18px 16px 14px;
    border-bottom:1px solid var(--border);flex-shrink:0;
}
.sb-logo-icon{
    width:36px;height:36px;border-radius:10px;
    background:linear-gradient(135deg,#7C3AED,#A78BFA);
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;box-shadow:0 4px 12px var(--primary-glow);
}
.sb-logo-text{font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.3px;}
.sb-logo-plan{font-size:10px;color:var(--text-soft);margin-top:1px;}

.sb-nav{flex:1;overflow-y:auto;padding:10px 10px 6px;scrollbar-width:none;}
.sb-nav::-webkit-scrollbar{display:none;}
.sb-section{font-size:9.5px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:1.2px;padding:10px 8px 4px;}

.nav-item{
    display:flex;align-items:center;gap:10px;
    padding:9px 10px;border-radius:10px;
    color:var(--text-mid);font-size:13px;font-weight:500;
    text-decoration:none;cursor:pointer;
    transition:all .15s;margin:1px 0;position:relative;
    border:none;background:none;width:100%;text-align:left;
}
.nav-item svg{width:18px;height:18px;flex-shrink:0;opacity:.75;transition:opacity .15s;}
.nav-item:hover{background:var(--bg3);color:var(--text);}
.nav-item:hover svg{opacity:1;}
.nav-item.active{
    background:linear-gradient(135deg,rgba(124,58,237,.22),rgba(167,139,250,.12));
    color:var(--primary-2);font-weight:600;
}
.nav-item.active svg{opacity:1;stroke:var(--primary-2);}
.nav-item.active::before{
    content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
    width:3px;height:20px;background:var(--primary);border-radius:0 3px 3px 0;
}
.nav-item.locked-item{opacity:.4;cursor:not-allowed;}
.nav-badge{margin-left:auto;min-width:18px;height:18px;background:#EF4444;border-radius:999px;font-size:9.5px;font-weight:700;color:white;display:flex;align-items:center;justify-content:center;padding:0 4px;}
.nav-lock{margin-left:auto;opacity:.5;font-size:11px;}

/* ════════════════════════════════════════
   SIDEBAR BOTTOM
════════════════════════════════════════ */
.sb-bottom{padding:8px 10px 12px;border-top:1px solid var(--border);flex-shrink:0;}

/* Dark mode toggle */
.dark-toggle{
    display:flex;align-items:center;justify-content:space-between;
    padding:9px 10px;border-radius:10px;cursor:pointer;
    transition:background .15s;
}
.dark-toggle:hover{background:var(--bg3);}
.dark-toggle-left{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:500;color:var(--text-mid);}
.dark-toggle-left svg{width:18px;height:18px;opacity:.75;}
.toggle-switch{
    width:36px;height:20px;border-radius:999px;
    background:var(--bg3);border:1px solid var(--border2);
    position:relative;transition:background .2s;flex-shrink:0;
}
.toggle-switch.on{background:var(--primary);}
.toggle-knob{
    position:absolute;top:2px;left:2px;
    width:14px;height:14px;border-radius:50%;
    background:white;transition:transform .2s;
    box-shadow:0 1px 4px rgba(0,0,0,0.3);
}
.toggle-switch.on .toggle-knob{transform:translateX(16px);}

/* Plan expiry card */
.plan-card{
    border-radius:12px;padding:12px 14px;margin-top:8px;
    position:relative;overflow:hidden;
}
.plan-card-inner{position:relative;z-index:1;}
.plan-close{
    position:absolute;top:8px;right:10px;
    font-size:14px;color:var(--text-soft);cursor:pointer;
    background:none;border:none;line-height:1;z-index:2;
}

/* User profile at bottom */
.sb-user{
    display:flex;align-items:center;gap:9px;
    padding:10px;border-radius:10px;cursor:pointer;
    transition:background .15s;position:relative;margin-top:6px;
}
.sb-user:hover{background:var(--bg3);}
.sb-avatar{
    width:34px;height:34px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:700;color:white;flex-shrink:0;
    background:var(--primary);overflow:hidden;position:relative;
}
.sb-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.sb-avatar-upload{
    position:absolute;inset:0;background:rgba(0,0,0,0.55);
    display:none;align-items:center;justify-content:center;
    border-radius:50%;cursor:pointer;
}
.sb-avatar:hover .sb-avatar-upload{display:flex;}
.sb-user-info{flex:1;min-width:0;}
.sb-user-name{font-size:12.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sb-user-email{font-size:10.5px;color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ════════════════════════════════════════
   MAIN LAYOUT
════════════════════════════════════════ */
.main-content{margin-left:var(--sidebar-w);min-height:100vh;background:var(--bg);transition:background .25s;}

/* TOP BAR */
.topbar{
    background:var(--bg2);border-bottom:1px solid var(--border);
    padding:0 22px;height:62px;display:flex;align-items:center;
    position:sticky;top:0;z-index:30;gap:12px;
    box-shadow:0 2px 16px rgba(0,0,0,0.15);
}
.topbar-search{
    flex:1;max-width:340px;display:flex;align-items:center;gap:8px;
    background:var(--bg3);border:1px solid var(--border);
    border-radius:12px;padding:9px 14px;
}
.topbar-search input{border:none;background:transparent;font-size:13px;color:var(--text);outline:none;width:100%;}
.topbar-search input::placeholder{color:var(--text-soft);}
.topbar-icon{
    width:38px;height:38px;border-radius:11px;
    background:var(--bg3);border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;text-decoration:none;color:var(--text-mid);
    transition:all .15s;flex-shrink:0;position:relative;
}
.topbar-icon:hover{background:var(--primary-bg);border-color:var(--primary);color:var(--primary);}
.topbar-icon svg{width:17px;height:17px;}
.topbar-notif-dot{
    position:absolute;top:-2px;right:-2px;
    width:14px;height:14px;background:#EF4444;
    border-radius:50%;font-size:8px;font-weight:700;
    color:white;display:flex;align-items:center;justify-content:center;
    border:2px solid var(--bg2);
}
.btn-new{
    display:flex;align-items:center;gap:7px;
    padding:9px 18px;border-radius:12px;
    background:var(--primary);color:white;
    font-size:13px;font-weight:700;border:none;
    cursor:pointer;text-decoration:none;
    transition:all .15s;white-space:nowrap;
    box-shadow:0 4px 14px var(--primary-glow);
}
.btn-new:hover{background:#6D28D9;transform:translateY(-1px);}
.topbar-avatar{
    width:36px;height:36px;border-radius:50%;
    background:var(--primary);color:white;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:700;cursor:pointer;
    overflow:hidden;border:2px solid var(--border2);
    transition:border-color .15s;position:relative;flex-shrink:0;
}
.topbar-avatar:hover{border-color:var(--primary);}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;}

/* PAGE BODY */
.page-body{padding:22px 24px;}

/* ════════════════════════════════════════
   CARDS & UTILITIES
════════════════════════════════════════ */
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;border:1px solid var(--border);box-shadow:var(--shadow);}

.btn-primary{background:var(--primary);color:white;padding:9px 18px;border-radius:11px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 14px var(--primary-glow);}
.btn-primary:hover{background:#6D28D9;transform:translateY(-1px);}
.btn-secondary{background:var(--bg3);color:var(--text);padding:9px 16px;border-radius:11px;font-size:13px;font-weight:500;border:1px solid var(--border);cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-secondary:hover{border-color:var(--border2);}
.table-row:hover{background:var(--bg3);}

input,select,textarea{background:var(--bg3);color:var(--text);border:1px solid var(--border);border-radius:10px;}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(124,58,237,.18);}
input::placeholder,textarea::placeholder{color:var(--text-soft);}

/* ════════════════════════════════════════
   LOCKED OVERLAY (Unlock Pro)
════════════════════════════════════════ */
.locked-section{position:relative;overflow:hidden;border-radius:var(--radius);}
.locked-section .locked-content{filter:blur(4px);pointer-events:none;user-select:none;}
.locked-overlay{
    position:absolute;inset:0;z-index:10;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:var(--overlay-bg);backdrop-filter:blur(2px);
    border-radius:var(--radius);gap:12px;padding:20px;
}
.locked-icon{
    width:52px;height:52px;border-radius:16px;
    background:linear-gradient(135deg,var(--primary),var(--primary-2));
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 8px 24px var(--primary-glow);
}
.locked-btn{
    padding:10px 24px;background:white;color:#1B1B2F;
    border-radius:12px;font-size:13px;font-weight:700;
    text-decoration:none;cursor:pointer;border:none;
    box-shadow:0 4px 14px rgba(0,0,0,0.2);transition:all .15s;
    display:inline-block;
}
.locked-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,0.25);}

/* ════════════════════════════════════════
   STATUS BADGES
════════════════════════════════════════ */
.badge-pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;}
.badge-payee   {background:rgba(16,185,129,.15);color:#10B981;}
.badge-envoyee {background:rgba(124,58,237,.15);color:var(--primary-2);}
.badge-impayee {background:rgba(239,68,68,.15);color:#EF4444;}
.badge-brouillon{background:rgba(90,90,122,.15);color:var(--text-soft);}
.badge-annulee {background:rgba(249,115,22,.15);color:#F97316;}

/* Trend badges */
.trend-up{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:rgba(16,185,129,.15);color:#10B981;}
.trend-dn{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:rgba(239,68,68,.15);color:#EF4444;}

/* ════════════════════════════════════════
   MODAL
════════════════════════════════════════ */
#ff-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9998;align-items:center;justify-content:center;}
#ff-modal-box{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:28px;max-width:420px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,.5);text-align:center;animation:ffPop .2s ease;}
.ff-btn-ok    {background:var(--primary);color:white;border:none;padding:10px 28px;border-radius:11px;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 4px 12px var(--primary-glow);}
.ff-btn-cancel{background:var(--bg3);color:var(--text-mid);border:1px solid var(--border);padding:10px 20px;border-radius:11px;font-size:13px;font-weight:500;cursor:pointer;}
.ff-btn-danger{background:#DC2626;color:white;border:none;padding:10px 24px;border-radius:11px;font-weight:700;font-size:13px;cursor:pointer;}

/* Photo upload modal */
#photoModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9997;align-items:center;justify-content:center;}
#photoModalBox{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:28px;max-width:380px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,.5);animation:ffPop .2s ease;}

/* ════════════════════════════════════════
   HAMBURGER (mobile)
════════════════════════════════════════ */
.hamburger-btn{display:none;width:38px;height:38px;border:1px solid var(--border);border-radius:11px;background:var(--bg3);cursor:pointer;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-mid);}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}

/* ════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════ */
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.mobile-open{transform:translateX(0);box-shadow:4px 0 30px rgba(0,0,0,.5);}
    .sidebar-overlay.active{display:block;}
    .main-content{margin-left:0!important;}
    .hamburger-btn{display:flex!important;}
    .topbar{padding:0 14px;}
    .page-body{padding:12px 14px;}
    .topbar-search{display:none!important;}
}
@media(max-width:480px){.btn-new span{display:none;}}
@keyframes ffPop{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .35s ease both;}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <?php
    $_sbLogoPath = $user['logo_path'] ?? '';
    $_sbCompNom  = trim($user['entreprise'] ?? '');
    $_sbPlanBadge = ['gratuit'=>'Gratuit','starter'=>'Pro ★','pro'=>'Pro ★','business'=>'Pro ★'];
    $_sbPlanTxt  = $_sbPlanBadge[$userPlan] ?? strtoupper($userPlan);
    ?>
    <div class="sb-logo">
        <img src="<?= APP_URL ?>/assets/logos/logo.png" alt="FactureFacile"
             style="height:34px;max-width:110px;object-fit:contain;flex-shrink:0;">
        <div style="flex:1;overflow:hidden;">
            <div class="sb-logo-text" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">FactureFacile</div>
            <div class="sb-logo-plan"><?= $_sbPlanTxt ?></div>
        </div>
        <button onclick="closeSidebar()" style="display:none;margin-left:auto;flex-shrink:0;background:none;border:none;color:var(--text-soft);font-size:20px;cursor:pointer;" class="sidebar-close-btn">×</button>
    </div>

    <!-- Nav -->
    <nav class="sb-nav">
        <div class="sb-section">Principal</div>

        <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Overview
        </a>

        <a href="<?= APP_URL ?>/chat-equipe.php" class="nav-item <?= $currentPage==='chat-equipe'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Messagerie
            <?php if($nbMessages>0):?><span class="nav-badge"><?= $nbMessages?></span><?php endif;?>
        </a>

        <div class="sb-section">Facturation</div>

        <a href="<?= APP_URL ?>/facture-create.php" class="nav-item <?= $currentPage==='facture-create'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle facture
        </a>

        <a href="<?= APP_URL ?>/factures.php" class="nav-item <?= in_array($currentPage,['factures','facture-view'])&&$currentPage!=='facture-create'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Factures
        </a>

        <a href="<?= APP_URL ?>/devis.php" class="nav-item <?= in_array($currentPage,['devis','devis-view','devis-create'])?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
            Devis
        </a>

        <a href="<?= APP_URL ?>/clients.php" class="nav-item <?= $currentPage==='clients'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Clients
        </a>

        <?php if(!$_isMember || $_isPrivMember):?>
        <div class="sb-section">Gestion</div>

        <?php $canTeam=$planLimits['equipe']??($planLimits['team_members']>0);?>
        <a href="<?= $canTeam?APP_URL.'/equipes.php':APP_URL.'/plans.php'?>" class="nav-item <?= $currentPage==='equipes'?'active':'' ?> <?= !$canTeam?'locked-item':''?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2a4 4 0 014 4 4 4 0 01-4 4 4 4 0 01-4-4 4 4 0 014-4m0 10c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4"/></svg>
            Équipe
            <?= !$canTeam?'<span class="nav-lock">🔒</span>':''?>
        </a>

        <a href="<?= APP_URL ?>/historique-connexions.php" class="nav-item <?= $currentPage==='historique-connexions'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Sessions
        </a>

        <?php $canHist=$planLimits['historique'];?>
        <a href="<?= $canHist?APP_URL.'/historique.php':APP_URL.'/plans.php'?>" class="nav-item <?= $currentPage==='historique'?'active':'' ?> <?= !$canHist?'locked-item':''?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Cash Flow
            <?= !$canHist?'<span class="nav-lock">🔒</span>':''?>
        </a>

        <?php $canExp=$planLimits['export'];?>
        <a href="<?= $canExp?APP_URL.'/export.php':APP_URL.'/plans.php'?>" class="nav-item <?= $currentPage==='export'?'active':'' ?> <?= !$canExp?'locked-item':''?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Rapport
            <?= !$canExp?'<span class="nav-lock">🔒</span>':''?>
        </a>
        <?php endif;?>

        <?php if(!$_isMember || $_isPrivMember):?>
        <div class="sb-section">Compte</div>

        <a href="<?= APP_URL ?>/profil.php" class="nav-item <?= $currentPage==='profil'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            Mon profil
        </a>

        <a href="<?= APP_URL ?>/plans.php" class="nav-item <?= $currentPage==='plans'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Plans
        </a>

        <a href="<?= APP_URL ?>/parametres.php" class="nav-item <?= in_array($currentPage,['parametres','parametres-facture'])?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Paramètres
        </a>
        <?php endif;?>
    </nav>

    <!-- Bottom -->
    <div class="sb-bottom">

        <!-- Help -->
        <a href="<?= APP_URL ?>/aide.php" class="nav-item <?= $currentPage==='aide'?'active':'' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Aide & informations
        </a>

        <!-- Dark mode toggle -->
        <div class="dark-toggle" onclick="toggleTheme()" id="darkToggleRow">
            <div class="dark-toggle-left">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" id="themeIcon"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <span id="themeLabel">Mode sombre</span>
            </div>
            <div class="toggle-switch on" id="themeToggle">
                <div class="toggle-knob"></div>
            </div>
        </div>

        <!-- Plan card -->
        <?php if(!$_isMember):
        // Normalize display name: starter/business → Pro
        $_planDisplay = in_array($userPlan, ['pro','starter','business'], true) ? 'Pro' : 'Gratuit';
        $_isPlanPro   = $_planDisplay === 'Pro';
        ?>
        <div class="plan-card" id="planCard">
            <button class="plan-close" onclick="document.getElementById('planCard').style.display='none'">×</button>
            <div class="plan-card-inner">
                <?php if(!$_isPlanPro):?>
                <!-- Gratuit → upgrade prompt -->
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
                    <span style="background:var(--text-soft);color:white;font-size:10px;font-weight:700;padding:2px 9px;border-radius:999px;">Gratuit</span>
                </div>
                <p style="font-size:13px;font-weight:700;color:var(--text);margin:0 0 3px;">Passez au plan Pro</p>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 10px;">Factures illimitées, IA, export et bien plus.</p>
                <a href="<?= APP_URL ?>/plans.php" style="display:block;text-align:center;padding:8px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;box-shadow:0 4px 12px rgba(124,58,237,.4);transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    ⭐ Passer Pro — 99 MAD/mois
                </a>
                <?php else:?>
                <!-- Pro → active badge -->
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
                    <span style="background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white;font-size:10px;font-weight:700;padding:2px 9px;border-radius:999px;">✦ Pro</span>
                </div>
                <p style="font-size:13px;font-weight:700;color:var(--primary-2);margin:0 0 3px;">Plan Pro actif</p>
                <p style="font-size:11px;color:var(--text-soft);margin:0 0 10px;">Accès complet à toutes les fonctionnalités.</p>
                <a href="<?= APP_URL ?>/plans.php" style="display:block;text-align:center;padding:8px;background:var(--primary-bg);color:var(--primary-2);border:1px solid rgba(124,58,237,.3);border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;transition:opacity .15s;" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                    Gérer l'abonnement
                </a>
                <?php endif;?>
            </div>
        </div>
        <?php endif;?>

        <!-- User -->
        <a href="<?= APP_URL ?>/profil.php" class="sb-user" style="text-decoration:none;color:inherit;">
            <div class="sb-avatar" style="background:<?= $_dispColor ?>;">
                <?php if($_dispPhoto):?><img src="<?= h($_dispPhoto) ?>" alt=""><?php else: echo $_dispInit; endif;?>
                <span class="sb-avatar-upload" title="Modifier le profil">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </span>
            </div>
            <div class="sb-user-info">
                <div class="sb-user-name"><?= h($_dispNom) ?></div>
                <div class="sb-user-email"><?= h($_dispEmail) ?></div>
            </div>
        </a>

    </div>
</aside>

<!-- Photo upload modal -->
<div id="photoModal" onclick="if(event.target===this)closePhotoModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9997;align-items:center;justify-content:center;">
    <div id="photoModalBox" style="background:var(--card);border:1px solid var(--border);border-radius:20px;padding:28px;max-width:360px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,.5);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <h3 style="font-size:15px;font-weight:700;color:var(--text);margin:0;">Photo de profil</h3>
            <button onclick="closePhotoModal()" style="background:none;border:none;color:var(--text-soft);font-size:22px;cursor:pointer;line-height:1;">×</button>
        </div>
        <!-- Current avatar preview -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:14px;margin-bottom:20px;">
            <div id="photoPreview" style="width:80px;height:80px;border-radius:50%;background:<?= $_dispColor ?>;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:white;overflow:hidden;border:3px solid var(--border2);">
                <?php if($_dispPhoto):?><img src="<?= h($_dispPhoto) ?>?t=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: echo $_dispInit; endif;?>
            </div>
            <p style="font-size:12px;color:var(--text-soft);text-align:center;margin:0;">Cliquez ci-dessous pour choisir une photo<br>JPEG, PNG, GIF, WebP · max 3 Mo</p>
        </div>
        <input type="file" id="photoFileInput" accept="image/*" style="display:none;" onchange="uploadProfilePhoto(this)">
        <div style="display:flex;flex-direction:column;gap:8px;">
            <label for="photoFileInput" style="display:block;text-align:center;padding:10px;background:var(--primary);color:white;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px var(--primary-glow);">📷 Choisir une photo</label>
            <?php if($_dispPhoto):?>
            <button onclick="deleteProfilePhoto()" style="padding:10px;background:var(--bg3);color:var(--red);border:1px solid rgba(239,68,68,.3);border-radius:11px;font-size:13px;font-weight:600;cursor:pointer;" id="photoDeleteBtn">🗑️ Supprimer la photo</button>
            <?php else:?>
            <button style="padding:10px;background:var(--bg3);color:var(--text-soft);border:1px solid var(--border);border-radius:11px;font-size:13px;font-weight:600;cursor:default;display:none;" id="photoDeleteBtn">🗑️ Supprimer la photo</button>
            <?php endif;?>
        </div>
    </div>
</div>

<!-- ══════════════════ MAIN WRAPPER ══════════════════ -->
<div class="main-content">

<?php if(empty($hideTopbar)):?>
<!-- TOP BAR -->
<div class="topbar">
    <button class="hamburger-btn" onclick="openSidebar()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <!-- Search -->
    <div class="topbar-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Rechercher factures, clients…" onkeydown="if(event.key==='Enter'){window.location=APP_URL+'/factures.php?q='+encodeURIComponent(this.value);}">
    </div>
    <div style="flex:1;"></div>
    <!-- New Invoice CTA -->
    <a href="<?= APP_URL ?>/facture-create.php" class="btn-new">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Nouvelle facture</span>
    </a>
    <!-- Icons -->
    <a href="<?= APP_URL ?>/chat-equipe.php" class="topbar-icon" id="chatNavBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        <?php if($nbMessages>0):?><span class="topbar-notif-dot"><?= $nbMessages?></span><?php endif;?>
    </a>
    <?php if(!$_isMember || $_isPrivMember):?>
    <a href="<?= APP_URL ?>/parametres.php" class="topbar-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
    </a>
    <?php endif;?>
    <!-- User avatar -->
    <div class="topbar-avatar" onclick="openPhotoModal()">
        <?php if($_dispPhoto):?><img src="<?= h($_dispPhoto) ?>"><?php else: echo $_dispInit; endif;?>
    </div>
</div>
<?php endif;?>

<!-- PAGE BODY -->
<div class="page-body">
<!-- MODAL -->
<div id="ff-modal-overlay" onclick="if(event.target===this)_ffClose()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9998;align-items:center;justify-content:center;">
    <div id="ff-modal-box">
        <div id="ff-modal-icon" style="font-size:40px;margin-bottom:10px;"></div>
        <div id="ff-modal-title" style="font-size:16px;font-weight:800;color:var(--text);margin:0 0 8px;"></div>
        <div id="ff-modal-msg"   style="font-size:13px;color:var(--text-mid);margin:0 0 24px;line-height:1.7;"></div>
        <div id="ff-modal-btns" style="display:flex;gap:10px;justify-content:center;"></div>
    </div>
</div>

<script>
const APP_URL   = '<?= APP_URL ?>';
const USER_PLAN = '<?= $userPlan ?>';

/* ── Theme ── */
function toggleTheme(){
    const cur  = document.documentElement.getAttribute('data-theme');
    const next = cur==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',next);
    localStorage.setItem('ff-theme',next);
    _updateThemeUI(next);
}
function _updateThemeUI(t){
    const isDark = t==='dark';
    const sw = document.getElementById('themeToggle');
    const lb = document.getElementById('themeLabel');
    const ic = document.getElementById('themeIcon');
    if(sw){ sw.classList.toggle('on',isDark); }
    if(lb){ lb.textContent = isDark?'Mode sombre':'Mode clair'; }
    if(ic){ ic.innerHTML = isDark
        ? '<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>'
        : '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'; }
}
// Init UI to match current theme
_updateThemeUI(localStorage.getItem('ff-theme')||'dark');

/* ── Sidebar ── */
function openSidebar(){
    document.getElementById('sidebar').classList.add('mobile-open');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.querySelector('.sidebar-close-btn').style.display='block';
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

/* ── Modal ── */
function _ffClose(){ document.getElementById('ff-modal-overlay').style.display='none'; }
function ffAlert(title,msg,type='info'){
    const icons={info:'ℹ️',warning:'⚠️',success:'✅',error:'❌'};
    document.getElementById('ff-modal-icon').textContent  = icons[type]||'ℹ️';
    document.getElementById('ff-modal-title').textContent = title;
    document.getElementById('ff-modal-msg').textContent   = msg;
    document.getElementById('ff-modal-btns').innerHTML    = '<button class="ff-btn-ok" onclick="_ffClose()">OK</button>';
    document.getElementById('ff-modal-overlay').style.display='flex';
}
function ffConfirm(title,msg,onYes,danger=false){
    document.getElementById('ff-modal-icon').textContent  = danger?'🗑️':'❓';
    document.getElementById('ff-modal-title').textContent = title;
    document.getElementById('ff-modal-msg').textContent   = msg;
    const okCls = danger?'ff-btn-danger':'ff-btn-ok';
    document.getElementById('ff-modal-btns').innerHTML=`<button class="${okCls}" id="ffConfirmOk">Confirmer</button><button class="ff-btn-cancel" onclick="_ffClose()">Annuler</button>`;
    document.getElementById('ffConfirmOk').onclick=()=>{_ffClose();onYes&&onYes();};
    document.getElementById('ff-modal-overlay').style.display='flex';
}

/* ── Toast ── */
function showToast(msg,ok=true){
    let t=document.getElementById('ff-toast');
    if(!t){t=document.createElement('div');t.id='ff-toast';t.style.cssText='position:fixed;bottom:24px;right:24px;color:white;padding:12px 20px;border-radius:14px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.25);transition:opacity .3s;max-width:320px;pointer-events:none;';document.body.appendChild(t);}
    t.style.background=ok?'#059669':'#DC2626';
    t.textContent=msg;t.style.opacity='1';t.style.display='block';
    clearTimeout(t._t);t._t=setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.style.display='none',300);},3500);
}

/* ── Profile photo ── */
function openPhotoModal(){ document.getElementById('photoModal').style.display='flex'; }
function closePhotoModal(){ document.getElementById('photoModal').style.display='none'; }

async function uploadProfilePhoto(input){
    if(!input.files[0]) return;
    const fd=new FormData(); fd.append('photo',input.files[0]);
    showToast('⏳ Téléchargement en cours…');
    const res  = await fetch(APP_URL+'/api/user_settings.php?action=upload_photo',{method:'POST',body:fd});
    const json = await res.json();
    if(json.success){
        showToast('✅ Photo mise à jour');
        // Update all avatar displays
        const url = json.photo_url+'?t='+Date.now();
        document.querySelectorAll('.sb-avatar,.topbar-avatar,#photoPreview').forEach(el=>{
            const existing = el.querySelector('img');
            if(existing){ existing.src=url; }
            else{ el.innerHTML='<img src="'+url+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'; }
        });
        const btn=document.getElementById('photoDeleteBtn');
        if(btn) btn.style.display='block';
        closePhotoModal();
    } else { showToast('❌ '+json.error,false); }
}
async function deleteProfilePhoto(){
    const res  = await fetch(APP_URL+'/api/user_settings.php?action=delete_photo',{method:'POST'});
    const json = await res.json();
    if(json.success){
        showToast('✅ Photo supprimée');
        const init = '<?= $_dispInit ?>';
        document.querySelectorAll('.sb-avatar,.topbar-avatar,#photoPreview').forEach(el=>{
            el.innerHTML=init;
        });
        const btn=document.getElementById('photoDeleteBtn');
        if(btn) btn.style.display='none';
        closePhotoModal();
    }
}

/* ── Sounds & notifs ── */
function playNotifSound(){try{const ctx=new(window.AudioContext||window.webkitAudioContext)();[880,1100].forEach((f,i)=>{const o=ctx.createOscillator(),g=ctx.createGain();o.connect(g);g.connect(ctx.destination);o.type='sine';o.frequency.value=f;g.gain.setValueAtTime(0,ctx.currentTime+i*.12);g.gain.linearRampToValueAtTime(.25,ctx.currentTime+i*.12+.02);g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.12+.25);o.start(ctx.currentTime+i*.12);o.stop(ctx.currentTime+i*.12+.25);});}catch(e){}}
async function requestNotifPermission(){if('Notification' in window&&Notification.permission==='default')await Notification.requestPermission();}
function showBrowserNotif(title,body){if(!('Notification' in window)||Notification.permission!=='granted')return;const n=new Notification(title,{body,icon:APP_URL+'/assets/icon-192.png',silent:true});n.onclick=()=>{window.focus();window.location=APP_URL+'/chat-equipe.php';n.close();};setTimeout(()=>n.close(),6000);}
const _origTitle=document.title;
function setTabBadge(c){document.title=c>0?`(${c}) ${_origTitle}`:_origTitle;}
function toggleMenu(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'||!el.style.display?'block':'none';}
requestNotifPermission();
</script>
<?php
function strftime_fr():string{
    $j=['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $m=['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    return $j[date('w')].' '.date('j').' '.$m[(int)date('n')].' '.date('Y');
}
?>
