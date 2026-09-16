<?php
function formatMontant(float $montant, string $devise = 'MAD'): string {
    return number_format($montant, 2, ',', ' ') . ' ' . htmlspecialchars($devise);
}

function genererNumeroFacture(int $userId): string {
    global $pdo;
    $annee = date('Y');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=? AND YEAR(date_facture)=?");
    $stmt->execute([$userId, $annee]);
    return 'FAC-' . $annee . '-' . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
}

function genererNumeroDevis(int $userId): string {
    global $pdo;
    $annee = date('Y');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND YEAR(date_devis)=?");
    $stmt->execute([$userId, $annee]);
    return 'DEV-' . $annee . '-' . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
}

function statutBadge(string $statut): string {
    $map = [
        'brouillon' => ['background:#F3F4F6;color:#6B7280',   'Brouillon'],
        'envoyee'   => ['background:#DBEAFE;color:#1D4ED8',   'Envoyée'],
        'payee'     => ['background:#D1FAE5;color:#059669',   'Payée'],
        'impayee'   => ['background:#FEE2E2;color:#DC2626',   'Impayée'],
        'annulee'   => ['background:#FED7AA;color:#EA580C',   'Annulée'],
        'envoye'    => ['background:#DBEAFE;color:#1D4ED8',   'Envoyé'],
        'accepte'   => ['background:#D1FAE5;color:#059669',   'Accepté'],
        'refuse'    => ['background:#FEE2E2;color:#DC2626',   'Refusé'],
        'expire'    => ['background:#FED7AA;color:#EA580C',   'Expiré'],
    ];
    [$style, $label] = $map[$statut] ?? ['background:#F3F4F6;color:#6B7280', ucfirst($statut)];
    return "<span style=\"display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;$style\">$label</span>";
}

/* ────────────────────── Groq API (GRATUIT) ─────────────────────────────── */
function callGroqAPI(string $userMessage, string $systemPrompt, string $apiKey): array {
    if (empty($apiKey)) return ['error' => 'Clé Groq non configurée. Obtenez-en une gratuitement sur console.groq.com'];
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage],
        ],
        'temperature'     => 0.1,
        'max_tokens'      => 1024,
        'response_format' => ['type' => 'json_object'],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => 'Erreur réseau: ' . $err];
    $data = json_decode($response, true);
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Erreur Groq'];
    // Retourner au format compatible Claude
    return ['content' => [['text' => $data['choices'][0]['message']['content'] ?? '']]];
}

/* ────────────────────── Claude API (Anthropic) ─────────────────────────── */
function callClaudeAPI(string $userMessage, string $systemPrompt, string $apiKey): array {
    if (empty($apiKey)) return ['error' => 'Clé Claude non configurée.'];
    $url = 'https://api.anthropic.com/v1/messages';
    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 1024,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMessage]],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => 'Erreur réseau: ' . $err];
    $data = json_decode($response, true);
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Erreur Claude'];
    return $data;
}

/* ────────────────────── Appel IA unifié ───────────────────────────────── */
function callAI(string $message, string $systemPrompt, array $user): array {
    $provider = $user['ai_provider'] ?? 'groq';
    if ($provider === 'claude') {
        $key = !empty($user['claude_api_key']) ? $user['claude_api_key'] : CLAUDE_API_KEY;
        return callClaudeAPI($message, $systemPrompt, $key);
    }
    // Groq par défaut
    $key = !empty($user['groq_api_key']) ? $user['groq_api_key'] : GROQ_API_KEY;
    return callGroqAPI($message, $systemPrompt, $key);
}

/* ────────────────────── Stats dashboard ───────────────────────────────── */
function getStats(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN statut='payee'                THEN total ELSE 0 END) as ca_paye,
            SUM(CASE WHEN statut IN('envoyee','impayee') THEN total ELSE 0 END) as ca_attente,
            SUM(CASE WHEN statut='payee' AND MONTH(date_facture)=MONTH(CURDATE()) AND YEAR(date_facture)=YEAR(CURDATE()) THEN total ELSE 0 END) as ca_mois,
            SUM(CASE WHEN statut='impayee' THEN 1 ELSE 0 END) as impayees,
            SUM(CASE WHEN statut='envoyee' THEN 1 ELSE 0 END) as envoyees,
            COUNT(DISTINCT client_id) as clients_factures
        FROM factures WHERE user_id=?
    ");
    $stmt->execute([$userId]);
    $s = $stmt->fetch();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id=?");
    $stmt2->execute([$userId]);
    $s['clients'] = (int)$stmt2->fetchColumn();

    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND statut NOT IN('refuse','expire')");
    $stmt3->execute([$userId]);
    $s['devis_actifs'] = (int)$stmt3->fetchColumn();

    // Taux de recouvrement
    $s['taux'] = ($s['total'] > 0)
        ? round(($s['ca_paye'] / max($s['ca_paye'] + $s['ca_attente'], 1)) * 100, 1)
        : 0;

    return $s;
}

/* ────────────────────── Notifications non lues ────────────────────────── */
function getNbNotifications(int $userId): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

/* ────────────────────── Messages non lus (chat) ───────────────────────── */
function getNbMessages(int $userId): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE admin_id=? AND is_read=0 AND sender_id!=?");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* ────────────────────── Montant en lettres (français) ─────────────────── */
function _nbLettres(int $n): string {
    if ($n < 0)  return 'moins ' . _nbLettres(-$n);
    if ($n === 0) return 'zéro';
    $unites  = ['','un','deux','trois','quatre','cinq','six','sept','huit','neuf',
                'dix','onze','douze','treize','quatorze','quinze','seize',
                'dix-sept','dix-huit','dix-neuf'];
    $dizaines = ['','','vingt','trente','quarante','cinquante','soixante'];
    $result = '';
    if ($n >= 1000000) {
        $m = intdiv($n, 1000000);
        $result .= _nbLettres($m) . ' million' . ($m > 1 ? 's' : '') . ' ';
        $n %= 1000000;
    }
    if ($n >= 1000) {
        $m = intdiv($n, 1000);
        $result .= ($m === 1 ? 'mille' : _nbLettres($m) . ' mille') . ' ';
        $n %= 1000;
    }
    if ($n >= 100) {
        $m    = intdiv($n, 100);
        $rest = $n % 100;
        $result .= ($m === 1 ? 'cent' : $unites[$m] . ' cent' . ($rest === 0 ? 's' : '')) . ' ';
        $n = $rest;
    }
    if ($n > 0) {
        if ($n < 20) {
            $result .= $unites[$n];
        } else {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($d === 7) {
                $result .= $u === 1 ? 'soixante et onze' : 'soixante-' . $unites[10 + $u];
            } elseif ($d === 8) {
                $result .= $u === 0 ? 'quatre-vingts' : 'quatre-vingt-' . $unites[$u];
            } elseif ($d === 9) {
                $result .= 'quatre-vingt-' . $unites[10 + $u];
            } else {
                if ($u === 1)      $result .= $dizaines[$d] . ' et un';
                elseif ($u === 0)  $result .= $dizaines[$d];
                else               $result .= $dizaines[$d] . '-' . $unites[$u];
            }
        }
    }
    return trim($result);
}

function nombreEnLettresFr(float $montant, string $devise = 'MAD'): string {
    $montant  = round($montant, 2);
    $entier   = (int)floor(abs($montant));
    $centimes = (int)round((abs($montant) - $entier) * 100);
    $dm = [
        'MAD' => ['dirham',         'dirhams',          'centime',  'centimes'],
        'EUR' => ['euro',           'euros',             'centime',  'centimes'],
        'USD' => ['dollar',         'dollars',           'cent',     'cents'],
        'GBP' => ['livre sterling', 'livres sterling',   'penny',    'pence'],
    ];
    $d = $dm[$devise] ?? ['', '', 'centime', 'centimes'];
    $str = _nbLettres($entier) . ' ' . ($entier === 1 ? $d[0] : $d[1]);
    $str .= $centimes > 0
        ? ' et ' . _nbLettres($centimes) . ' ' . ($centimes === 1 ? $d[2] : $d[3])
        : ' zéro ' . $d[2];
    return ucfirst(trim($str));
}

/* ────────────────────── Pied de page facture ──────────────────────────── */
function buildFooterInfoBlock(array $fi, string $sz_str, string $clr, string $sep): string {
    if (empty($fi['show'])) return '';
    $parts = [];
    if (!empty($fi['adresse']))   $parts[] = '📍 ' . htmlspecialchars($fi['adresse'],   ENT_QUOTES);
    if (!empty($fi['telephone'])) $parts[] = '📞 ' . htmlspecialchars($fi['telephone'], ENT_QUOTES);
    if (!empty($fi['email']))     $parts[] = '✉ '  . htmlspecialchars($fi['email'],     ENT_QUOTES);
    if (!empty($fi['site_web']))  $parts[] = '🌐 ' . htmlspecialchars($fi['site_web'],  ENT_QUOTES);
    if (!empty($fi['cnss']))      $parts[] = 'CNSS : '    . htmlspecialchars($fi['cnss'],    ENT_QUOTES);
    if (!empty($fi['ice']))       $parts[] = 'ICE : '     . htmlspecialchars($fi['ice'],     ENT_QUOTES);
    if (!empty($fi['rc']))        $parts[] = 'RC : '      . htmlspecialchars($fi['rc'],      ENT_QUOTES);
    if (!empty($fi['patente']))   $parts[] = 'Patente : ' . htmlspecialchars($fi['patente'], ENT_QUOTES);
    if (empty($parts)) return '';
    $esep = htmlspecialchars($sep, ENT_QUOTES);
    return '<div style="font-size:' . (int)$sz_str . 'px;color:' . htmlspecialchars($clr, ENT_QUOTES)
         . ';text-align:center;padding:10px 0;line-height:1.8;">'
         . implode('<span style="color:#94a3b8;margin:0 5px;">' . $esep . '</span>', $parts)
         . '</div>';
}

/* ────────────────────── Plan display name ─────────────────────────────── */
function planLabel(string $plan): string {
    return in_array($plan, ['pro','starter','business'], true) ? 'Pro' : 'Gratuit';
}

/* ────────────────────── Journal des actions (audit) ───────────────────── */
function logAction(int $userId, string $action, string $module, int $entityId=0, string $entityRef='', string $details=''): void {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare("INSERT INTO audit_logs (user_id,action,module,entity_id,entity_ref,details,ip) VALUES (?,?,?,?,?,?,?)")
            ->execute([$userId, $action, $module, $entityId ?: null, $entityRef, $details, $ip]);
    } catch (\Throwable $e) {}
}

/* ────────────────────── Relances impayées ─────────────────────────────── */
function getFacturesImpayees(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT f.*,
               (SELECT COUNT(*) FROM relances r WHERE r.facture_id=f.id) as nb_relances
        FROM factures f
        WHERE f.user_id=?
          AND f.statut='impayee'
          AND f.date_echeance IS NOT NULL
          AND f.date_echeance < CURDATE()
        ORDER BY f.date_echeance ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
