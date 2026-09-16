<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();
$devise = $user['devise'] ?? 'MAD';

$apiKey = !empty($user['groq_api_key']) ? $user['groq_api_key'] : (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
if (empty($apiKey) && !empty($user['claude_api_key'])) $apiKey = $user['claude_api_key'];
if (empty($apiKey) && defined('CLAUDE_API_KEY')) $apiKey = CLAUDE_API_KEY;
$hasApiKey = !empty($apiKey);

$clients = $pdo->prepare("SELECT id, nom, email, adresse, ice FROM clients WHERE user_id = ? ORDER BY nom");
$clients->execute([$user['id']]);
$clients = $clients->fetchAll();

$pageTitle = 'Créer avec IA';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<style>
.chat-bubble-user { background: #2563EB; color: white; border-radius: 18px 18px 4px 18px; }
.chat-bubble-ai   { background: white; color: #1e293b; border-radius: 18px 18px 18px 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.typing-dot { animation: bounce 1.2s infinite; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-8px)} }
</style>

<div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:800;color:#111827;margin:0;">🤖 Créer une facture avec l'IA</h1>
    <p style="font-size:13px;color:#6B7280;margin:6px 0 0;">Décrivez votre facture en langage naturel, l'IA fait le reste.</p>
</div>

<?php if (!$hasApiKey): ?>
<div style="margin-bottom:20px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;">
    <span style="font-size:28px;">🔑</span>
    <div style="flex:1;">
        <p style="font-weight:600;color:#92400E;margin:0 0 2px;">Clé API requise</p>
        <p style="color:#B45309;font-size:13px;margin:0;">Configurez votre clé Groq (gratuite) ou Claude dans les paramètres pour utiliser l'IA.</p>
    </div>
    <a href="<?= APP_URL ?>/parametres.php" class="btn-primary" style="background:#D97706;flex-shrink:0;">Configurer →</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Chat panel -->
    <div class="card" style="overflow:hidden;display:flex;flex-direction:column;height:620px;">
        <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;gap:12px;flex-shrink:0;">
            <div style="width:36px;height:36px;background:#EFF6FF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;">🤖</div>
            <div>
                <p style="font-weight:600;color:#111827;font-size:13px;margin:0;">Assistant FactureFacile</p>
                <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                    <div style="width:7px;height:7px;background:#10B981;border-radius:50%;"></div>
                    <span style="font-size:11px;color:#9CA3AF;">En ligne</span>
                </div>
            </div>
        </div>

        <div id="chatMessages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;gap:10px;">
                <div style="width:32px;height:32px;background:#EFF6FF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">🤖</div>
                <div class="chat-bubble-ai" style="padding:12px 16px;font-size:13px;max-width:280px;">
                    <p style="font-weight:600;margin:0 0 6px;">Bonjour <?= h(explode(' ', $user['nom'])[0]) ?> ! 👋</p>
                    <p style="color:#6B7280;font-size:12px;margin:0 0 8px;line-height:1.5;">Décrivez votre facture en langage naturel :</p>
                    <div style="display:flex;flex-direction:column;gap:5px;">
                        <button onclick="fillExample(this)" style="text-align:left;background:#EFF6FF;color:#2563EB;border:none;border-radius:8px;padding:7px 10px;font-size:11.5px;cursor:pointer;">
                            "Facture pour Ahmed, 3 logos à 500 DH"
                        </button>
                        <button onclick="fillExample(this)" style="text-align:left;background:#EFF6FF;color:#2563EB;border:none;border-radius:8px;padding:7px 10px;font-size:11.5px;cursor:pointer;">
                            "Développement site web, Société X, 5000 DH HT"
                        </button>
                        <button onclick="fillExample(this)" style="text-align:left;background:#EFF6FF;color:#2563EB;border:none;border-radius:8px;padding:7px 10px;font-size:11.5px;cursor:pointer;">
                            "Karim : 10h consulting à 200 DH/h + 3 rapports 150 DH"
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div style="padding:12px 16px;border-top:1px solid #F3F4F6;flex-shrink:0;">
            <div style="display:flex;gap:8px;">
                <textarea id="chatInput" placeholder="Ex: Facture pour Ahmed, 3 logos à 500 DH chacun..." rows="2"
                          style="flex:1;padding:10px 14px;border:1px solid #E5E7EB;border-radius:10px;font-size:13px;resize:none;"
                          <?= !$hasApiKey ? 'disabled' : '' ?>></textarea>
                <button id="sendBtn" onclick="sendMessage()" <?= !$hasApiKey ? 'disabled' : '' ?>
                        style="flex-shrink:0;width:40px;height:40px;background:#2563EB;color:white;border:none;border-radius:10px;cursor:pointer;font-size:18px;align-self:flex-end;display:flex;align-items:center;justify-content:center;">
                    ➤
                </button>
            </div>
            <p style="font-size:11px;color:#9CA3AF;margin:6px 0 0;">Entrée pour envoyer · Maj+Entrée pour nouvelle ligne</p>
        </div>
    </div>

    <!-- Invoice preview -->
    <div class="card" id="invoicePreview" style="overflow:hidden;display:flex;flex-direction:column;height:620px;">
        <div id="previewEmpty" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px;">
            <p style="font-size:48px;margin:0 0 12px;">📄</p>
            <h3 style="font-weight:600;color:#374151;margin:0 0 6px;">Aperçu de la facture</h3>
            <p style="color:#9CA3AF;font-size:13px;">Votre facture apparaîtra ici dès que l'IA aura extrait les informations.</p>
        </div>

        <div id="previewContent" style="display:none;flex-direction:column;flex:1;overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <h3 style="font-weight:600;color:#111827;font-size:14px;margin:0;">Aperçu de la facture</h3>
                <span style="background:#DCFCE7;color:#059669;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;">✓ Générée par IA</span>
            </div>

            <div style="flex:1;overflow-y:auto;padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
                    <div>
                        <p style="font-size:10px;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.8px;margin:0 0 3px;">De</p>
                        <p style="font-weight:600;color:#111827;margin:0;"><?= h($user['entreprise'] ?: $user['nom']) ?></p>
                        <?php if ($user['adresse']): ?><p style="font-size:12px;color:#6B7280;margin:2px 0 0;"><?= h($user['adresse']) ?></p><?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                        <p style="font-size:18px;font-weight:800;color:#2563EB;margin:0;" id="prevNumero">FAC-XXXX</p>
                        <p style="font-size:12px;color:#6B7280;margin:2px 0 0;" id="prevDate"><?= date('d/m/Y') ?></p>
                    </div>
                </div>

                <div style="background:#F9FAFB;border-radius:10px;padding:14px;margin-bottom:16px;">
                    <p style="font-size:10px;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.8px;margin:0 0 5px;">Facturé à</p>
                    <p style="font-weight:600;color:#111827;margin:0;" id="prevClient">–</p>
                    <p style="font-size:12px;color:#6B7280;margin:2px 0 0;" id="prevClientEmail"></p>
                    <?php if (!empty($clients)): ?>
                    <select id="clientSelector" onchange="selectClient()" style="margin-top:8px;width:100%;padding:7px 10px;border:1px solid #E5E7EB;border-radius:8px;font-size:12px;background:white;">
                        <option value="">-- Associer à un client existant --</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" data-nom="<?= h($c['nom']) ?>" data-email="<?= h($c['email']) ?>">
                            <?= h($c['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #E5E7EB;">
                            <th style="padding:8px 4px 8px 0;text-align:left;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;">Description</th>
                            <th style="padding:8px 4px;text-align:center;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;">Qté</th>
                            <th style="padding:8px 4px;text-align:right;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;">P.U.</th>
                            <th style="padding:8px 4px;text-align:right;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;">TVA</th>
                            <th style="padding:8px 0 8px 4px;text-align:right;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="prevItems"></tbody>
                </table>

                <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                    <div style="width:220px;">
                        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
                            <span style="color:#6B7280;">Sous-total HT</span>
                            <span style="font-weight:500;" id="prevSousTotal">0</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
                            <span style="color:#6B7280;">TVA</span>
                            <span style="font-weight:500;" id="prevTVA">0</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0 4px;font-size:14px;font-weight:800;">
                            <span>Total TTC</span>
                            <span style="color:#2563EB;" id="prevTotal">0</span>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:4px;">Date d'échéance</label>
                        <input type="date" id="dateEcheance" style="width:100%;padding:7px 10px;border:1px solid #E5E7EB;border-radius:8px;font-size:12px;">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:4px;">Statut</label>
                        <select id="statutSelect" style="width:100%;padding:7px 10px;border:1px solid #E5E7EB;border-radius:8px;font-size:12px;">
                            <option value="brouillon">Brouillon</option>
                            <option value="envoyee">Envoyée</option>
                            <option value="payee">Payée</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="padding:14px 20px;border-top:1px solid #F3F4F6;display:flex;gap:8px;flex-shrink:0;">
                <button onclick="saveFacture()" class="btn-primary" style="flex:1;justify-content:center;">💾 Enregistrer</button>
                <button onclick="resetPreview()" class="btn-secondary">✕</button>
            </div>
        </div>
    </div>
</div>

<script>
const DEVISE  = '<?= h($devise) ?>';
let currentInvoiceData = null;
let selectedClientId   = null;

document.getElementById('chatInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

function fillExample(btn) {
    document.getElementById('chatInput').value = btn.textContent.replace(/^"|"$/g, '').trim();
    document.getElementById('chatInput').focus();
}

function addMsg(role, text, isHTML=false) {
    const c = document.getElementById('chatMessages');
    const w = document.createElement('div');
    w.style.cssText = `display:flex;gap:10px;${role==='user'?'flex-direction:row-reverse':''}`;
    const av = document.createElement('div');
    av.style.cssText = 'width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;'+(role==='user'?'background:#2563EB;color:white;':'background:#EFF6FF;');
    av.textContent = role==='user'?'👤':'🤖';
    const b = document.createElement('div');
    b.className = role==='user'?'chat-bubble-user':'chat-bubble-ai';
    b.style.cssText = 'padding:10px 14px;font-size:13px;max-width:280px;';
    if (isHTML) b.innerHTML=text; else b.textContent=text;
    w.appendChild(av); w.appendChild(b); c.appendChild(w);
    c.scrollTop = c.scrollHeight;
    return b;
}

function showTyping() {
    const c = document.getElementById('chatMessages');
    const w = document.createElement('div');
    w.id = 'typingIndicator'; w.style.cssText = 'display:flex;gap:10px;';
    w.innerHTML = `<div style="width:32px;height:32px;background:#EFF6FF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">🤖</div>
    <div class="chat-bubble-ai" style="padding:10px 14px;display:flex;gap:6px;align-items:center;">
        <div class="typing-dot" style="width:7px;height:7px;background:#2563EB;border-radius:50%;"></div>
        <div class="typing-dot" style="width:7px;height:7px;background:#2563EB;border-radius:50%;"></div>
        <div class="typing-dot" style="width:7px;height:7px;background:#2563EB;border-radius:50%;"></div>
    </div>`;
    c.appendChild(w); c.scrollTop=c.scrollHeight;
}

function removeTyping() { document.getElementById('typingIndicator')?.remove(); }

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    document.getElementById('sendBtn').disabled = true;
    addMsg('user', msg);
    showTyping();
    try {
        const res  = await fetch(APP_URL+'/api/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg})});
        const json = await res.json();
        removeTyping();
        if (!json.success) { addMsg('ai','❌ '+json.error); }
        else {
            currentInvoiceData = json.data; selectedClientId = null;
            const txt = json.data.message_confirmation || `Facture pour ${json.data.client_nom} – Total : ${fmtMontant(json.data.total)}`;
            addMsg('ai','✅ '+txt+'\n\nVérifiez l\'aperçu et cliquez "Enregistrer".');
            renderPreview(json.data);
        }
    } catch(e) { removeTyping(); addMsg('ai','❌ Erreur de connexion.'); }
    document.getElementById('sendBtn').disabled = false;
    input.focus();
}

function fmtMontant(v) { return parseFloat(v).toLocaleString('fr-MA',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+DEVISE; }

function renderPreview(data) {
    document.getElementById('previewEmpty').style.display = 'none';
    const pc = document.getElementById('previewContent');
    pc.style.display = 'flex';
    document.getElementById('prevClient').textContent = data.client_nom||'–';
    document.getElementById('prevClientEmail').textContent = '';
    document.getElementById('prevDate').textContent = new Date().toLocaleDateString('fr-FR');
    const tbody = document.getElementById('prevItems');
    tbody.innerHTML = '';
    (data.items||[]).forEach(item => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #F9FAFB';
        tr.innerHTML = `<td style="padding:8px 4px 8px 0;color:#374151;">${escHtml(item.description)}</td>
            <td style="padding:8px 4px;text-align:center;color:#6B7280;">${item.quantite}</td>
            <td style="padding:8px 4px;text-align:right;color:#6B7280;">${fmtMontant(item.prix_unitaire)}</td>
            <td style="padding:8px 4px;text-align:right;color:#6B7280;">${item.tva}%</td>
            <td style="padding:8px 0 8px 4px;text-align:right;font-weight:600;color:#111827;">${fmtMontant(item.total)}</td>`;
        tbody.appendChild(tr);
    });
    document.getElementById('prevSousTotal').textContent = fmtMontant(data.sous_total);
    document.getElementById('prevTVA').textContent       = fmtMontant(data.tva_montant);
    document.getElementById('prevTotal').textContent     = fmtMontant(data.total);
}

function selectClient() {
    const sel = document.getElementById('clientSelector');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    selectedClientId = opt.value;
    document.getElementById('prevClient').textContent = opt.dataset.nom;
    document.getElementById('prevClientEmail').textContent = opt.dataset.email;
    if (currentInvoiceData) currentInvoiceData.client_nom = opt.dataset.nom;
}

async function saveFacture() {
    if (!currentInvoiceData) return;
    const btn = event.target; btn.disabled=true; btn.textContent='⏳...';
    const payload = {
        ...currentInvoiceData,
        client_id:     selectedClientId,
        date_facture:  new Date().toISOString().split('T')[0],
        date_echeance: document.getElementById('dateEcheance').value||null,
        statut:        document.getElementById('statutSelect').value,
    };
    try {
        const res  = await fetch(APP_URL+'/api/factures.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const json = await res.json();
        if (json.success) { addMsg('ai','🎉 Facture '+json.numero+' enregistrée !'); window.location.href=APP_URL+'/facture-view.php?id='+json.facture_id; }
        else { alert('Erreur : '+json.error); btn.disabled=false; btn.textContent='💾 Enregistrer'; }
    } catch(e) { alert('Erreur réseau.'); btn.disabled=false; }
}

function resetPreview() {
    currentInvoiceData = null;
    document.getElementById('previewEmpty').style.display = 'flex';
    document.getElementById('previewContent').style.display = 'none';
}

function escHtml(s) { const d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML; }
</script>

</div></div></body></html>
