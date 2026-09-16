<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user       = getCurrentUser();
$isMember   = isTeamMember();
$memberId   = getTeamMemberId();
$planLimits = getPlanLimits($user['plan'] ?? 'gratuit');
$canTeam    = $planLimits['team_members'] > 0;

// Load all active team members in this workspace
$stmt = $pdo->prepare("SELECT id, nom, prenom, poste, couleur FROM team_members WHERE user_id=? AND statut='actif' ORDER BY nom");
$stmt->execute([$user['id']]);
$allMembers = $stmt->fetchAll();

// Who am I?
$myType  = $isMember ? 'team_member' : 'user';
$myId    = $isMember ? $memberId     : $user['id'];
$_tmSelf = $isMember ? getTeamMemberData() : null;
$myNom   = $isMember && $_tmSelf ? trim(($_tmSelf['prenom']??'').' '.($_tmSelf['nom']??'')) : $user['nom'];

$adminInitials = strtoupper(mb_substr($user['nom'], 0, 1));

$pageTitle = 'Messagerie';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<style>
/* Layout */
.chat-wrap { display:flex; height:calc(100vh - 84px); border-radius:16px; overflow:hidden; background:white; box-shadow:0 2px 12px rgba(0,0,0,.07); border:1px solid #E5E7EB; }

/* Left panel */
.chat-left { width:270px; background:#F8F9FB; border-right:1px solid #E5E7EB; display:flex; flex-direction:column; flex-shrink:0; }
.chat-left-header { padding:14px 16px; border-bottom:1px solid #E5E7EB; display:flex; align-items:center; justify-content:space-between; }
.section-label { padding:8px 16px 4px; font-size:9.5px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:.8px; }
.conv-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; border-left:3px solid transparent; transition:all .13s; position:relative; }
.conv-item:hover { background:#EEF0F4; }
.conv-item.active { background:white; border-left-color:#D97706; }
.conv-item.active .conv-name { color:#D97706; font-weight:700; }
.conv-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
.conv-name { font-size:13px; font-weight:600; color:#1F2937; margin:0; }
.conv-preview { font-size:11.5px; color:#9CA3AF; margin:1px 0 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px; }
.unread-badge { background:#EF4444; color:white; font-size:9px; font-weight:700; padding:1px 5px; border-radius:999px; margin-left:auto; flex-shrink:0; }
.add-btn { width:28px; height:28px; border-radius:8px; border:none; background:#FFFBEB; color:#D97706; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .13s; }
.add-btn:hover { background:#FDE68A; }

/* Main */
.chat-main { flex:1; display:flex; flex-direction:column; min-width:0; background:#F0F2F5; }
.chat-topbar { background:white; padding:12px 18px; border-bottom:1px solid #E5E7EB; display:flex; align-items:center; gap:12px; flex-shrink:0; }
.chat-tabs { display:flex; gap:4px; margin-left:auto; }
.chat-tab { padding:5px 12px; border-radius:7px; border:none; background:transparent; font-size:12px; font-weight:500; color:#9CA3AF; cursor:pointer; transition:all .13s; }
.chat-tab.active { background:#FFFBEB; color:#D97706; font-weight:700; }

/* Messages */
.msg-area { flex:1; overflow-y:auto; padding:18px 20px; display:flex; flex-direction:column; gap:14px; scroll-behavior:smooth; }
.msg-row { display:flex; align-items:flex-end; gap:8px; }
.msg-row.own { flex-direction:row-reverse; }
.msg-avatar { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; flex-shrink:0; }
.msg-content { display:flex; flex-direction:column; max-width:68%; }
.msg-content.own { align-items:flex-end; }
.msg-meta { font-size:10.5px; color:#9CA3AF; margin-bottom:3px; display:flex; align-items:center; gap:5px; }
.msg-meta.own { flex-direction:row-reverse; }
.msg-sender { font-weight:600; color:#374151; font-size:11px; }
.bubble { padding:9px 13px; border-radius:18px; font-size:13.5px; line-height:1.55; word-break:break-word; max-width:100%; }
.bubble-other { background:white; color:#1F2937; border-radius:18px 18px 18px 4px; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.bubble-self  { background:linear-gradient(135deg,#D97706,#F59E0B); color:#1C1917; border-radius:18px 18px 4px 18px; font-weight:500; }
.bubble-bot   { background:#E8E8E8; color:#374151; border-radius:18px 18px 18px 4px; font-size:13px; }
.bubble-admin { background:linear-gradient(135deg,#1C1917,#3C3735); color:#F5F5F4; border-radius:18px 18px 4px 18px; }

/* Date sep */
.date-sep { display:flex; align-items:center; gap:12px; padding:2px 0; }
.date-sep::before, .date-sep::after { content:''; flex:1; height:1px; background:#E5E7EB; }
.date-sep span { font-size:10.5px; color:#9CA3AF; font-weight:600; white-space:nowrap; }

/* Typing */
.typing-wrap { padding:5px 20px; display:none; }
.typing-indicator { display:inline-flex; align-items:center; gap:6px; background:white; border-radius:16px; padding:7px 12px; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.typing-dots span { display:inline-block; width:5px; height:5px; border-radius:50%; background:#9CA3AF; animation:typBounce 1.4s infinite ease-in-out; }
.typing-dots span:nth-child(2) { animation-delay:.2s; }
.typing-dots span:nth-child(3) { animation-delay:.4s; }
@keyframes typBounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-4px)} }

/* Footer */
.chat-footer { background:white; border-top:1px solid #E5E7EB; padding:10px 14px; flex-shrink:0; }
.input-wrap { display:flex; align-items:flex-end; gap:7px; background:#F0F2F5; border-radius:14px; padding:7px 9px 7px 13px; }
.msg-textarea { flex:1; border:none; background:transparent; font-size:13.5px; font-family:inherit; resize:none; outline:none; max-height:100px; overflow-y:auto; line-height:1.5; color:#1F2937; }
.msg-textarea::placeholder { color:#9CA3AF; }
.input-icon { width:32px; height:32px; border-radius:9px; border:none; background:transparent; cursor:pointer; font-size:17px; display:flex; align-items:center; justify-content:center; transition:background .13s; flex-shrink:0; }
.input-icon:hover { background:rgba(0,0,0,.06); }
.send-btn { width:36px; height:36px; border-radius:11px; border:none; background:linear-gradient(135deg,#D97706,#F59E0B); color:#1C1917; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 2px 8px rgba(217,119,6,.35); transition:all .13s; }
.send-btn:hover { transform:scale(1.07); }

/* Attach */
.attach-bar { background:#FFFBEB; border:1.5px dashed #FDE68A; border-radius:10px; padding:7px 12px; display:none; align-items:center; gap:9px; margin-bottom:7px; }

/* Emoji */
.emoji-panel { position:absolute; bottom:calc(100% + 6px); left:0; background:white; border:1.5px solid #E5E7EB; border-radius:14px; padding:10px; box-shadow:0 8px 30px rgba(0,0,0,.12); width:290px; z-index:200; display:none; }
.e-grid { display:grid; grid-template-columns:repeat(8,1fr); gap:2px; }
.e-btn { border:none; background:none; font-size:18px; cursor:pointer; padding:3px; border-radius:5px; }
.e-btn:hover { background:#F3F4F6; transform:scale(1.1); }

/* Members panel */
.members-panel { flex:1; overflow-y:auto; padding:14px; display:none; flex-direction:column; gap:8px; }
.member-card { display:flex; align-items:center; gap:10px; padding:10px; background:#F8F9FB; border-radius:10px; }

/* File card */
.file-card-msg { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.25); border-radius:9px; padding:7px 11px; margin-top:5px; text-decoration:none; color:inherit; font-size:11.5px; border:1px solid rgba(255,255,255,0.3); }
.file-card-msg.dark { background:#F3F4F6; color:#374151; border-color:#E5E7EB; }

/* Empty state */
.empty-conv { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#9CA3AF; text-align:center; padding:40px; }

/* Plan gate */
.plan-gate { background:linear-gradient(135deg,#FFFBEB,#FEF3C7); border:2px solid #FDE68A; border-radius:16px; padding:40px; text-align:center; flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; }

/* Create group modal */
#createGroupModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9995; align-items:center; justify-content:center; }
#createGroupBox { background:white; border-radius:20px; width:90%; max-width:480px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.color-dot { width:28px; height:28px; border-radius:50%; cursor:pointer; border:3px solid transparent; transition:all .13s; }
.color-dot.selected { border-color:#1C1917; transform:scale(1.15); }

/* Notification flash */
@keyframes notifFlash { 0%,100%{background:white}50%{background:#FFFBEB} }
.new-msg-flash { animation:notifFlash .5s ease 2; }

/* Mobile */
@media (max-width:768px) {
    .chat-wrap { height:calc(100vh - 74px); flex-direction:column; border-radius:12px; }
    .chat-left { width:100%; max-height:200px; border-right:none; border-bottom:1px solid #E5E7EB; }
    .msg-content { max-width:82%; }
}
@media (max-width:480px) {
    .chat-tabs { display:none; }
    .bubble { font-size:13px; }
}
</style>

<div class="chat-wrap">

<!-- ══ LEFT PANEL ══ -->
<div class="chat-left">
    <div class="chat-left-header">
        <div>
            <h2 style="font-size:14px;font-weight:800;color:#111827;margin:0;">💬 Messagerie</h2>
            <p style="font-size:11px;color:#9CA3AF;margin:0;"><?= count($allMembers) ?> membre(s) actif(s)</p>
        </div>
    </div>

    <div style="flex:1;overflow-y:auto;" id="convList">

        <!-- Groups section -->
        <?php if ($canTeam || $isMember): ?>
        <div class="section-label" style="display:flex;align-items:center;justify-content:space-between;padding-right:12px;">
            <span>📢 Groupes</span>
            <button class="add-btn" onclick="openCreateGroup()" title="Nouveau groupe">+</button>
        </div>
        <div id="groupsList"></div>
        <?php endif; ?>

        <!-- DMs section -->
        <div class="section-label">💬 Messages directs</div>
        <div id="dmList">
            <?php if (!$isMember): ?>
            <!-- Bot (admin only) -->
            <div class="conv-item" id="convBot" onclick="switchConv('bot',null,'🤖 Assistant IA','FactureFacile Bot','linear-gradient(135deg,#1C1917,#3C3735)','🤖','#F5F5F4')">
                <div class="conv-avatar" style="background:linear-gradient(135deg,#1C1917,#3C3735);">🤖</div>
                <div style="flex:1;min-width:0;">
                    <p class="conv-name" style="color:#6B7280;">Assistant IA</p>
                    <p class="conv-preview">Questions sur vos factures…</p>
                </div>
            </div>
            <!-- Members as DM targets -->
            <?php foreach ($allMembers as $m):
                $ini = strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1));
                $col = $m['couleur'] ?: '#D97706';
                $nm  = h(trim($m['prenom'].' '.$m['nom']));
            ?>
            <div class="conv-item" id="dmTarget_<?= $m['id'] ?>"
                 onclick="startDm('team_member',<?= $m['id'] ?>,'<?= addslashes($nm) ?>','<?= h($col) ?>','<?= $ini ?>')">
                <div class="conv-avatar" style="background:<?= h($col) ?>;color:white;"><?= h($ini) ?></div>
                <div style="flex:1;min-width:0;">
                    <p class="conv-name" style="color:#374151;"><?= $nm ?></p>
                    <p class="conv-preview"><?= h(ucfirst($m['poste'])) ?></p>
                </div>
                <span class="unread-badge" id="dmTargetUnread_<?= $m['id'] ?>" style="display:none;">0</span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <!-- Team member sees admin + other members -->
            <div class="conv-item" id="dmTarget_admin"
                 onclick="startDm('user',<?= $user['id'] ?>,'<?= addslashes(h($user['nom'])) ?>','linear-gradient(135deg,#1C1917,#3C3735)','<?= $adminInitials ?>')">
                <div class="conv-avatar" style="background:linear-gradient(135deg,#1C1917,#3C3735);color:#F59E0B;"><?= $adminInitials ?></div>
                <div style="flex:1;min-width:0;">
                    <p class="conv-name" style="color:#374151;"><?= h($user['nom']) ?></p>
                    <p class="conv-preview">Administration</p>
                </div>
                <span class="unread-badge" id="dmTargetUnread_admin" style="display:none;">0</span>
            </div>
            <?php foreach ($allMembers as $m):
                if ($m['id'] === $memberId) continue;
                $ini = strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1));
                $col = $m['couleur'] ?: '#D97706';
                $nm  = h(trim($m['prenom'].' '.$m['nom']));
            ?>
            <div class="conv-item" id="dmTarget_<?= $m['id'] ?>"
                 onclick="startDm('team_member',<?= $m['id'] ?>,'<?= addslashes($nm) ?>','<?= h($col) ?>','<?= $ini ?>')">
                <div class="conv-avatar" style="background:<?= h($col) ?>;color:white;"><?= h($ini) ?></div>
                <div style="flex:1;min-width:0;">
                    <p class="conv-name" style="color:#374151;"><?= $nm ?></p>
                    <p class="conv-preview"><?= h(ucfirst($m['poste'])) ?></p>
                </div>
                <span class="unread-badge" id="dmTargetUnread_<?= $m['id'] ?>" style="display:none;">0</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Questions for bot -->
        <div id="quickQBox" style="padding:8px;border-top:1px solid #F3F4F6;display:none;">
            <p style="font-size:10px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.6px;margin:0 0 4px;">💡 Questions rapides</p>
            <?php foreach ([
                "Combien de factures impayées j'ai ?"=>"Impayées ?",
                "Quel est mon CA cette année ?"=>"CA ?",
                "Combien de clients j'ai ?"=>"Clients ?",
            ] as $full=>$short): ?>
            <button onclick="fillMsg('<?= addslashes($full) ?>')" style="display:block;width:100%;text-align:left;background:none;border:none;font-size:11px;color:#D97706;cursor:pointer;padding:2px 0;text-decoration:underline;">• <?= $short ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="chat-main">

    <!-- Header -->
    <div class="chat-topbar">
        <div id="hdrAvatar" style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#D97706,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:18px;color:#1C1917;font-weight:700;flex-shrink:0;">💬</div>
        <div style="flex:1;min-width:0;">
            <p id="hdrName" style="font-size:13.5px;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Sélectionnez une conversation</p>
            <span id="hdrStatus" style="font-size:11px;color:#9CA3AF;"></span>
        </div>
        <div class="chat-tabs" id="chatTabs" style="display:none;">
            <button class="chat-tab active" onclick="setTab('messages',this)">Messages</button>
            <button class="chat-tab" onclick="setTab('members',this)">Membres</button>
        </div>
        <!-- Group actions (admin only) -->
        <div id="groupActions" style="display:none;gap:6px;">
            <button onclick="openGroupSettings()" style="padding:5px 10px;border:1px solid #E5E7EB;border-radius:8px;background:white;font-size:12px;cursor:pointer;color:#374151;">⚙️ Gérer</button>
        </div>
    </div>

    <!-- Empty state -->
    <div class="empty-conv" id="emptyState">
        <div style="font-size:48px;margin-bottom:12px;">💬</div>
        <p style="font-weight:700;color:#374151;font-size:14px;margin:0 0 6px;">Bienvenue dans la messagerie</p>
        <p style="font-size:12px;margin:0;">Sélectionnez une conversation ou créez un groupe.</p>
    </div>

    <!-- Messages area -->
    <div id="msgArea" class="msg-area" style="display:none;"></div>

    <!-- Typing -->
    <div class="typing-wrap" id="typingWrap" style="display:none;">
        <div class="typing-indicator">
            <span style="font-size:11px;color:#6B7280;font-weight:500;" id="typingName"></span>
            <div class="typing-dots"><span></span><span></span><span></span></div>
        </div>
    </div>

    <!-- Members panel (group tab) -->
    <div class="members-panel" id="membersPanel"></div>

    <!-- Plan gate -->
    <?php if (!$canTeam && !$isMember): ?>
    <div class="plan-gate" style="display:flex;" id="planGate">
        <div style="font-size:48px;margin-bottom:14px;">💬</div>
        <h3 style="font-size:17px;font-weight:800;color:#92400E;margin:0 0 8px;">Messagerie d'équipe — Plan Starter requis</h3>
        <p style="font-size:13px;color:#B45309;margin:0 0 20px;max-width:360px;">Créez des groupes et communiquez avec votre équipe à partir du plan Starter (49 MAD/mois).</p>
        <a href="<?= APP_URL ?>/parametres.php#plans" class="btn-primary">⭐ Voir les plans</a>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="chat-footer" id="chatFooter" style="display:none;">
        <div class="attach-bar" id="attachBar">
            <span id="attachIcon" style="font-size:20px;">📎</span>
            <div style="flex:1;min-width:0;">
                <p id="attachName" style="font-size:12px;font-weight:600;color:#374151;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></p>
                <p id="attachSize" style="font-size:11px;color:#9CA3AF;margin:0;"></p>
            </div>
            <button onclick="clearAttach()" style="border:none;background:none;color:#9CA3AF;cursor:pointer;font-size:16px;padding:0 4px;">×</button>
        </div>
        <div style="position:relative;">
            <div class="emoji-panel" id="emojiPanel">
                <div class="e-grid" id="emojiGrid"></div>
            </div>
            <div class="input-wrap">
                <textarea id="msgInput" class="msg-textarea" rows="1" placeholder="Écrire un message…"
                    oninput="autoResize(this)"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
                <button class="input-icon" onclick="toggleEmoji()">😊</button>
                <button class="input-icon" onclick="document.getElementById('fileIn').click()">📎</button>
                <input type="file" id="fileIn" style="display:none;" onchange="handleFile(this)">
                <button class="send-btn" onclick="sendMsg()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ══ CREATE GROUP MODAL ══ -->
<div id="createGroupModal" onclick="if(event.target===this)closeCreateGroup()">
<div id="createGroupBox">
    <div style="padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h3 style="font-size:15px;font-weight:800;color:#111827;margin:0;">🏷️ Nouveau groupe</h3>
            <p style="font-size:12px;color:#9CA3AF;margin:2px 0 0;">Créer une discussion de groupe</p>
        </div>
        <button onclick="closeCreateGroup()" style="border:none;background:none;font-size:22px;color:#9CA3AF;cursor:pointer;padding:0;">×</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:16px;">
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Nom du groupe *</label>
            <input type="text" id="cgName" placeholder="Ex: Projet Alpha, Marketing, Dev…"
                   style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">Couleur</label>
            <div style="display:flex;gap:10px;">
                <?php foreach (['#D97706','#0891B2','#059669','#7C3AED','#DC2626','#1C1917'] as $i=>$c): ?>
                <div class="color-dot <?= $i===0?'selected':'' ?>" style="background:<?= $c ?>;" data-color="<?= $c ?>" onclick="selectColor(this)"></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">Membres (vous êtes inclus automatiquement)</label>
            <div id="cgMembersList" style="display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;border:1.5px solid #E5E7EB;border-radius:10px;padding:8px;">
                <!-- Admin always -->
                <label style="display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:13px;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background=''">
                    <input type="checkbox" value="user:<?= $user['id'] ?>" checked disabled style="accent-color:#D97706;width:15px;height:15px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#D97706,#F59E0B);display:flex;align-items:center;justify-content:center;color:#1C1917;font-weight:700;font-size:11px;"><?= $adminInitials ?></div>
                    <span><?= h($user['nom']) ?> <em style="color:#9CA3AF;font-size:11px;">Admin</em></span>
                </label>
                <?php foreach ($allMembers as $m):
                    $ini = strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1));
                    $col = $m['couleur'] ?: '#D97706';
                    $nm  = trim($m['prenom'].' '.$m['nom']);
                ?>
                <label style="display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:13px;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background=''">
                    <input type="checkbox" value="team_member:<?= $m['id'] ?>" class="cg-member-cb" style="accent-color:#D97706;width:15px;height:15px;" <?= ($m['id'] === $memberId) ? 'checked disabled' : '' ?>>
                    <div style="width:28px;height:28px;border-radius:50%;background:<?= h($col) ?>;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:11px;"><?= h($ini) ?></div>
                    <span><?= h($nm) ?> <em style="color:#9CA3AF;font-size:11px;"><?= h(ucfirst($m['poste'])) ?></em></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div style="padding:14px 24px;border-top:1px solid #F3F4F6;display:flex;gap:10px;">
        <button onclick="createGroup()" class="btn-primary" style="flex:1;justify-content:center;">✅ Créer le groupe</button>
        <button onclick="closeCreateGroup()" class="btn-secondary">Annuler</button>
    </div>
</div>
</div>

<!-- ══ GROUP SETTINGS MODAL ══ -->
<div id="groupSettingsModal" onclick="if(event.target===this)closeGroupSettings()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9994;align-items:center;justify-content:center;">
<div style="background:white;border-radius:20px;width:90%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-size:15px;font-weight:800;color:#111827;margin:0;">⚙️ Paramètres du groupe</h3>
        <button onclick="closeGroupSettings()" style="border:none;background:none;font-size:22px;color:#9CA3AF;cursor:pointer;">×</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:16px;">
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Nom du groupe</label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="gsName" style="flex:1;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;">
                <button onclick="renameGroup()" class="btn-primary" style="white-space:nowrap;">Renommer</button>
            </div>
        </div>
        <div>
            <p style="font-size:12px;font-weight:600;color:#374151;margin:0 0 8px;">Membres</p>
            <div id="gsMembers" style="display:flex;flex-direction:column;gap:6px;max-height:180px;overflow-y:auto;"></div>
        </div>
        <div>
            <p style="font-size:12px;font-weight:600;color:#374151;margin:0 0 8px;">Ajouter un membre</p>
            <div style="display:flex;gap:8px;">
                <select id="gsAddMember" style="flex:1;padding:8px 10px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:12.5px;">
                    <option value="">— Choisir —</option>
                    <?php foreach ($allMembers as $m): $nm = trim($m['prenom'].' '.$m['nom']); ?>
                    <option value="team_member:<?= $m['id'] ?>"><?= h($nm) ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="addMemberToGroup()" class="btn-secondary">+ Ajouter</button>
            </div>
        </div>
    </div>
    <div style="padding:14px 24px;border-top:1px solid #F3F4F6;">
        <button onclick="deleteGroup()" style="background:#FEE2E2;color:#DC2626;border:none;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;width:100%;">🗑️ Supprimer ce groupe</button>
    </div>
</div>
</div>

<script>
const IS_MEMBER   = <?= $isMember ? 'true' : 'false' ?>;
const MY_ID       = <?= $myId ?>;
const MY_TYPE     = '<?= $myType ?>';
const MY_NOM      = '<?= addslashes(h($myNom)) ?>';
const ADMIN_ID    = <?= $user['id'] ?>;
const ADMIN_NOM   = '<?= addslashes(h($user['nom'])) ?>';
const ALL_MEMBERS = <?= json_encode(array_map(fn($m)=>['id'=>$m['id'],'nom'=>trim($m['prenom'].' '.$m['nom']),'initials'=>strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1)),'col'=>$m['couleur']??'#D97706'],$allMembers)) ?>;
const CAN_TEAM    = <?= $canTeam ? 'true' : 'false' ?>;

/* ─── State ─── */
let activeConvType = null; // 'group' | 'bot' | 'dm'
let activeGroupId  = null; // group id for group/DM, null for bot
let lastMsgId      = 0;
let prevDate       = null;
let totalUnread    = 0;
let pendingFile    = null;
let loadedGroups   = [];
let pollTimer      = null;

/* ─── Emoji ─── */
const EMOJIS = ['😀','😄','😅','😂','🤣','😊','😇','😍','😎','🤩','😏','😔','😢','😤','😡','😱','🥳','🤔','🤗','👋','🙌','🎉','✅','❌','💯','🔥','⭐','💼','📄','📋','👍','👎','❤️','💡','🎯','✨','🚀','⚡','🔑','💰'];
function renderEmojis() {
    document.getElementById('emojiGrid').innerHTML = EMOJIS.map(e=>`<button class="e-btn" onclick="insertEmoji('${e}')">${e}</button>`).join('');
}
function toggleEmoji() {
    const p = document.getElementById('emojiPanel');
    if (!p.style.display || p.style.display==='none') { renderEmojis(); p.style.display='block'; }
    else p.style.display='none';
}
function insertEmoji(e) {
    const ta = document.getElementById('msgInput'), s = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0,s)+e+ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = s+e.length;
    ta.focus();
}
document.addEventListener('click', e => {
    if (!e.target.closest('#emojiPanel') && !e.target.closest('.input-icon')) {
        const p = document.getElementById('emojiPanel'); if(p) p.style.display='none';
    }
});

/* ─── File ─── */
function fmtSize(b){return b<1024?b+' o':b<1048576?(b/1024).toFixed(1)+' Ko':(b/1048576).toFixed(1)+' Mo';}
function fIcon(mime){if(!mime)return'📎';if(mime.startsWith('image/'))return'🖼️';if(mime==='application/pdf')return'📄';if(mime.includes('word'))return'📝';if(mime.includes('excel')||mime.includes('spreadsheet')||mime==='text/csv')return'📊';return'📎';}
async function handleFile(input) {
    const file = input.files[0]; if(!file) return;
    const fd = new FormData(); fd.append('file', file);
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=upload',{method:'POST',body:fd});
    const json = await res.json();
    if (!json.success) { showToast('❌ '+json.error,false); input.value=''; return; }
    pendingFile = json;
    document.getElementById('attachIcon').textContent = fIcon(json.file_type);
    document.getElementById('attachName').textContent = json.file_name;
    document.getElementById('attachSize').textContent = fmtSize(json.file_size);
    document.getElementById('attachBar').style.display = 'flex';
    input.value = '';
}
function clearAttach() { pendingFile=null; document.getElementById('attachBar').style.display='none'; }

/* ─── Groups loading ─── */
async function loadGroups() {
    if (!CAN_TEAM && !IS_MEMBER) return;
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=list').catch(()=>null);
    if (!res) return;
    const json = await res.json().catch(()=>null);
    if (!json || !json.success) return;
    loadedGroups = json.groups || [];
    renderGroupsList();
    renderDmUnreads();
}

function renderGroupsList() {
    const gList = document.getElementById('groupsList');
    if (!gList) return;
    const groups = loadedGroups.filter(g => !g.is_dm);
    if (!groups.length) {
        gList.innerHTML = '<div style="padding:8px 16px;font-size:12px;color:#9CA3AF;font-style:italic;">Aucun groupe. Créez-en un !</div>';
        return;
    }
    gList.innerHTML = groups.map(g => {
        const initials = g.name.charAt(0).toUpperCase();
        const isActive = activeConvType==='group' && activeGroupId===g.id;
        const preview  = g.last_preview ? g.last_preview.slice(0,30)+(g.last_preview.length>30?'…':'') : 'Aucun message';
        return `<div class="conv-item ${isActive?'active':''}" id="groupItem_${g.id}"
                     onclick="switchConv('group',${g.id},'${g.name.replace(/'/g,"\\'")}','${(g.member_count||0)+' membre(s)'}','${g.couleur}','${initials}','white')">
            <div class="conv-avatar" style="background:${g.couleur};color:white;">${initials}</div>
            <div style="flex:1;min-width:0;">
                <p class="conv-name">${escHtml(g.name)}</p>
                <p class="conv-preview">${escHtml(preview)}</p>
            </div>
            ${g.unread > 0 ? `<span class="unread-badge">${g.unread}</span>` : ''}
        </div>`;
    }).join('');
}

function renderDmUnreads() {
    // Update unread badges on DM targets based on loaded DM groups
    const dms = loadedGroups.filter(g => g.is_dm);
    // Reset all
    document.querySelectorAll('[id^="dmTargetUnread_"]').forEach(el => el.style.display='none');
    dms.forEach(g => {
        if (g.unread <= 0) return;
        const otherId = g.dm_other_id;
        const otherType = g.dm_other_type;
        const key = otherType === 'user' ? 'admin' : otherId;
        const badge = document.getElementById(`dmTargetUnread_${key}`);
        if (badge) { badge.textContent = g.unread; badge.style.display=''; }
    });
}

function escHtml(s) { const d=document.createElement('div');d.textContent=s;return d.innerHTML; }

/* ─── Start DM ─── */
async function startDm(memberType, memberId, name, couleur, initials) {
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=start_dm',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({member_type:memberType,member_id:memberId})});
    const json = await res.json();
    if (!json.success) { showToast('❌ '+json.error,false); return; }
    switchConv('dm', json.group_id, name, '', couleur, initials, 'white');
}

/* ─── Switch conversation ─── */
function switchConv(type, groupId, name, status, avatarBg, avatarContent, avatarColor) {
    activeConvType = type;
    activeGroupId  = groupId;
    lastMsgId      = 0;
    prevDate       = null;

    // Update header
    const av = document.getElementById('hdrAvatar');
    av.style.background = avatarBg;
    av.style.color       = avatarColor || 'white';
    av.textContent       = avatarContent;
    document.getElementById('hdrName').textContent   = name;
    document.getElementById('hdrStatus').textContent = status;

    // Show/hide tabs and group actions
    const isGroup = type === 'group';
    document.getElementById('chatTabs').style.display    = isGroup ? 'flex' : 'none';
    document.getElementById('groupActions').style.display = (isGroup && !IS_MEMBER) ? 'flex' : 'none';
    document.getElementById('quickQBox').style.display    = type==='bot' ? 'block' : 'none';

    // Active conv item
    document.querySelectorAll('.conv-item').forEach(el=>el.classList.remove('active'));
    const key = type==='group' ? `groupItem_${groupId}` : type==='bot' ? 'convBot' : `convDM_${groupId}`;
    const el  = document.getElementById(key); if(el) el.classList.add('active');

    // Show messages tab
    setTab('messages', document.querySelector('.chat-tab'));

    // Show areas
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('msgArea').style.display    = 'flex';
    document.getElementById('chatFooter').style.display = 'block';
    document.getElementById('msgArea').innerHTML = '';

    // Restart poll
    clearInterval(pollTimer);
    loadMessages(true);
    pollTimer = setInterval(() => loadMessages(false), 3000);
}

/* ─── Tabs ─── */
function setTab(tab, btn) {
    document.querySelectorAll('.chat-tab').forEach(b=>b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    document.getElementById('msgArea').style.display       = tab==='messages' ? 'flex' : 'none';
    document.getElementById('membersPanel').style.display  = tab==='members'  ? 'flex' : 'none';
    document.getElementById('chatFooter').style.display    = tab==='messages' ? 'block' : 'none';
    if (tab==='members') loadGroupMembers();
}

/* ─── Load messages ─── */
function apiUrl() {
    if (activeConvType === 'bot') {
        return APP_URL+'/api/chat.php?action=list&since='+lastMsgId;
    }
    return APP_URL+'/api/chat_groups.php?action=messages&group_id='+activeGroupId+'&since='+lastMsgId;
}

function isMine(m) {
    if (IS_MEMBER) return m.sender_type==='team_member' && parseInt(m.sender_team_id)===MY_ID;
    return m.sender_type==='user' || m.sender_type==='admin';
}

function getSenderInfo(m) {
    if (m.sender_type==='bot')   return {name:'Assistant IA',bg:'linear-gradient(135deg,#1C1917,#3C3735)',txt:'🤖',col:'white'};
    if (m.sender_type==='user'||m.sender_type==='admin') {
        return {name:ADMIN_NOM+(m.sender_type==='admin'?' (Admin)':''),bg:'linear-gradient(135deg,#1C1917,#3C3735)',txt:ADMIN_NOM.charAt(0).toUpperCase(),col:'#F59E0B'};
    }
    if (m.sender_type==='team_member') {
        const info = ALL_MEMBERS.find(m2=>m2.id===parseInt(m.sender_team_id));
        if (info) return {name:info.nom,bg:info.col,txt:info.initials,col:'white'};
        return {name:m.sender_nom||'Membre',bg:'#D97706',txt:'?',col:'white'};
    }
    return {name:m.sender_nom||'?',bg:'#9CA3AF',txt:'?',col:'white'};
}

function renderDate(dtStr) {
    if (!dtStr) return '';
    const parts = dtStr.split(' ');
    const [d,mo,y] = parts[0].split('/');
    const dt = new Date(`${y}-${mo}-${d}`);
    const today = new Date(); const yest = new Date(); yest.setDate(today.getDate()-1);
    if (dt.toDateString()===today.toDateString()) return "Aujourd'hui";
    if (dt.toDateString()===yest.toDateString())  return "Hier";
    return dt.toLocaleDateString('fr-FR',{day:'numeric',month:'long'});
}

function buildBubble(m) {
    const mine   = isMine(m);
    const sender = getSenderInfo(m);
    const isBot  = m.sender_type==='bot';
    let bubCls = mine ? 'bubble-self' : (isBot ? 'bubble-bot' : (m.sender_type==='admin'?'bubble-admin':'bubble-other'));

    let content = '';
    if (m.message) {
        const esc = m.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
        content += esc.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>');
    }
    if (m.file_name) {
        const isImg = m.file_type && m.file_type.startsWith('image/');
        if (isImg) {
            content += `<br><a href="${APP_URL}/${m.file_path}" target="_blank" style="display:block;margin-top:5px;"><img src="${APP_URL}/${m.file_path}" style="max-width:180px;border-radius:9px;display:block;"></a>`;
        } else {
            const dc = mine?'':'dark';
            content += `<a href="${APP_URL}/${m.file_path}" target="_blank" class="file-card-msg ${dc}"><span style="font-size:19px;">${fIcon(m.file_type)}</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;">${m.file_name}</span><span style="opacity:.6;font-size:10px;">${fmtSize(m.file_size||0)}</span></a>`;
        }
    }
    const myAv = IS_MEMBER
        ? `<div class="msg-avatar" style="background:${ALL_MEMBERS.find(m=>m.id===MY_ID)?.col||'#D97706'};color:white;">${MY_NOM.charAt(0).toUpperCase()}</div>`
        : `<div class="msg-avatar" style="background:linear-gradient(135deg,#D97706,#F59E0B);color:#1C1917;">${ADMIN_NOM.charAt(0).toUpperCase()}</div>`;

    return `<div class="msg-row ${mine?'own':''}">
        ${mine ? '' : `<div class="msg-avatar" style="background:${sender.bg};color:${sender.col};font-size:${isBot?'15':'11'}px;">${sender.txt}</div>`}
        <div class="msg-content ${mine?'own':''}">
            ${mine
                ? `<div class="msg-meta own"><span>${m.heure||''}</span><span>Vous</span></div>`
                : `<div class="msg-meta"><span class="msg-sender">${sender.name}</span><span>${m.heure||''}</span></div>`}
            <div class="bubble ${bubCls}">${content}</div>
        </div>
        ${mine ? myAv : ''}
    </div>`;
}

async function loadMessages(isInit=false) {
    if (!activeConvType) return;
    const res  = await fetch(apiUrl()).catch(()=>null);
    if (!res) return;
    const json = await res.json().catch(()=>null);
    if (!json || !json.success || !json.messages.length) return;

    const area = document.getElementById('msgArea');
    const atBot = area.scrollHeight - area.scrollTop - area.clientHeight < 80;
    let hasNew = false, newSender = '';

    json.messages.forEach(m => {
        const msgDate = m.datetime_full ? m.datetime_full.split(' ')[0] : '';
        if (msgDate !== prevDate) {
            prevDate = msgDate;
            area.insertAdjacentHTML('beforeend', `<div class="date-sep"><span>${renderDate(m.datetime_full)}</span></div>`);
        }
        area.insertAdjacentHTML('beforeend', buildBubble(m));
        lastMsgId = Math.max(lastMsgId, m.id);
        if (!isMine(m)) { hasNew=true; newSender=getSenderInfo(m).name; if(!isInit) totalUnread++; }
    });

    if (atBot || isInit) area.scrollTop = area.scrollHeight;

    if (hasNew && !isInit) {
        playNotifSound();
        setTabBadge(totalUnread);
        if (document.hidden) {
            showBrowserNotif('💬 '+newSender, json.messages[json.messages.length-1]?.message?.slice(0,60)||'Nouveau fichier');
        }
        document.getElementById('hdrName').classList.add('new-msg-flash');
        setTimeout(()=>document.getElementById('hdrName').classList.remove('new-msg-flash'),1000);
    }

    // Refresh group unread badges
    if (!isInit) await loadGroups();
}

/* ─── Send ─── */
async function sendMsg() {
    const ta  = document.getElementById('msgInput');
    const msg = ta.value.trim();
    if (!msg && !pendingFile) return;

    let url, payload;
    if (activeConvType === 'bot') {
        url     = APP_URL+'/api/chat.php?action=send';
        payload = {message: msg};
    } else {
        url     = APP_URL+'/api/chat_groups.php?action=send';
        payload = {group_id: activeGroupId, message: msg};
    }
    if (pendingFile) Object.assign(payload, {file_name:pendingFile.file_name, file_path:pendingFile.file_path, file_type:pendingFile.file_type, file_size:pendingFile.file_size});

    ta.value = ''; ta.style.height = ''; clearAttach();
    await fetch(url, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    await loadMessages(false);
    document.getElementById('msgArea').scrollTop = document.getElementById('msgArea').scrollHeight;
}

function fillMsg(t) { document.getElementById('msgInput').value=t; document.getElementById('msgInput').focus(); }
function autoResize(ta) { ta.style.height='auto'; ta.style.height=Math.min(ta.scrollHeight,100)+'px'; }

/* ─── Group members panel ─── */
async function loadGroupMembers() {
    if (!activeGroupId) return;
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=members&group_id='+activeGroupId);
    const json = await res.json();
    if (!json.success) return;
    const panel = document.getElementById('membersPanel');
    panel.innerHTML = json.members.map(m => `
        <div class="member-card">
            <div style="width:38px;height:38px;border-radius:50%;background:${m.couleur};display:flex;align-items:center;justify-content:center;color:${m.type==='user'?'#1C1917':'white'};font-weight:700;font-size:13px;flex-shrink:0;">${m.initials}</div>
            <div style="flex:1;">
                <p style="font-size:13px;font-weight:600;color:#111827;margin:0;">${escHtml(m.name)}</p>
                <p style="font-size:11px;color:#9CA3AF;margin:0;">${m.type==='user'?'Administrateur':'Membre'}</p>
            </div>
        </div>
    `).join('');
}

/* ─── Create group ─── */
let selectedColor = '#D97706';
function selectColor(el) {
    document.querySelectorAll('.color-dot').forEach(d=>d.classList.remove('selected'));
    el.classList.add('selected');
    selectedColor = el.dataset.color;
}
function openCreateGroup() {
    document.getElementById('cgName').value = '';
    document.getElementById('createGroupModal').style.display = 'flex';
    document.getElementById('cgName').focus();
}
function closeCreateGroup() { document.getElementById('createGroupModal').style.display = 'none'; }

async function createGroup() {
    const name = document.getElementById('cgName').value.trim();
    if (!name) { showToast('❌ Nom requis',false); return; }

    const checked = [...document.querySelectorAll('.cg-member-cb:checked')];
    const members = checked.map(cb => {
        const [t,id] = cb.value.split(':');
        return {type:t, id:parseInt(id)};
    });

    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name, couleur:selectedColor, members})});
    const json = await res.json();
    if (!json.success) { showToast('❌ '+json.error,false); return; }
    closeCreateGroup();
    showToast('✅ Groupe créé !');
    await loadGroups();
    switchConv('group', json.id, name, members.length+1+' membre(s)', selectedColor, name.charAt(0).toUpperCase(), 'white');
}

/* ─── Group settings ─── */
let settingsGroupId = null;
async function openGroupSettings() {
    if (!activeGroupId) return;
    settingsGroupId = activeGroupId;
    const g = loadedGroups.find(g=>g.id===activeGroupId);
    document.getElementById('gsName').value = g ? g.name : '';

    // Load members
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=members&group_id='+activeGroupId);
    const json = await res.json();
    const panel = document.getElementById('gsMembers');
    if (json.success) {
        panel.innerHTML = json.members.map(m => `
            <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:#F8F9FB;border-radius:8px;">
                <div style="width:28px;height:28px;border-radius:50%;background:${m.couleur};display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:11px;">${m.initials}</div>
                <span style="flex:1;font-size:12.5px;font-weight:500;">${escHtml(m.name)}</span>
                ${m.type!=='user' ? `<button onclick="removeMemberFromGroup('${m.type}',${m.id})" style="border:none;background:none;color:#9CA3AF;cursor:pointer;font-size:16px;">×</button>` : ''}
            </div>
        `).join('');
    }

    document.getElementById('groupSettingsModal').style.display = 'flex';
}
function closeGroupSettings() { document.getElementById('groupSettingsModal').style.display = 'none'; }

async function renameGroup() {
    const name = document.getElementById('gsName').value.trim();
    if (!name) return;
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=rename',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({group_id:settingsGroupId,name})});
    const json = await res.json();
    if (json.success) { showToast('✅ Groupe renommé'); await loadGroups(); closeGroupSettings(); document.getElementById('hdrName').textContent=name; }
    else showToast('❌ '+json.error,false);
}

async function addMemberToGroup() {
    const val = document.getElementById('gsAddMember').value;
    if (!val) return;
    const [t,id] = val.split(':');
    const res  = await fetch(APP_URL+'/api/chat_groups.php?action=add_member',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({group_id:settingsGroupId,member_type:t,member_id:parseInt(id)})});
    const json = await res.json();
    if (json.success) { showToast('✅ Membre ajouté'); openGroupSettings(); await loadGroups(); }
    else showToast('❌ '+json.error,false);
}

async function removeMemberFromGroup(type, id) {
    ffConfirm('Retirer ce membre','Voulez-vous retirer ce membre du groupe ?',async()=>{
        const res  = await fetch(APP_URL+'/api/chat_groups.php?action=remove_member',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({group_id:settingsGroupId,member_type:type,member_id:id})});
        const json = await res.json();
        if (json.success) { showToast('✅ Membre retiré'); openGroupSettings(); await loadGroups(); }
        else showToast('❌ '+json.error,false);
    });
}

async function deleteGroup() {
    ffConfirm('Supprimer le groupe','Tous les messages seront perdus. Cette action est irréversible.',async()=>{
        const res  = await fetch(APP_URL+'/api/chat_groups.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({group_id:settingsGroupId})});
        const json = await res.json();
        if (json.success) {
            closeGroupSettings();
            showToast('✅ Groupe supprimé');
            activeConvType=null; activeGroupId=null;
            document.getElementById('msgArea').style.display='none';
            document.getElementById('chatFooter').style.display='none';
            document.getElementById('emptyState').style.display='flex';
            await loadGroups();
        } else showToast('❌ '+json.error,false);
    },true);
}

/* ─── Visibility & unread reset ─── */
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { totalUnread=0; setTabBadge(0); }
});

/* ─── Init ─── */
async function init() {
    await loadGroups();
    // Auto-open first group if exists
    const firstGroup = loadedGroups.find(g=>!g.is_dm);
    if (firstGroup) {
        switchConv('group', firstGroup.id, firstGroup.name, firstGroup.member_count+' membre(s)', firstGroup.couleur, firstGroup.name.charAt(0).toUpperCase(), 'white');
    } else if (!IS_MEMBER) {
        switchConv('bot', null, '🤖 Assistant IA', 'FactureFacile Bot', 'linear-gradient(135deg,#1C1917,#3C3735)', '🤖', '#F5F5F4');
    }
    // Refresh groups list every 8 seconds
    setInterval(loadGroups, 8000);
}

init();
</script>

</div></div></body></html>
