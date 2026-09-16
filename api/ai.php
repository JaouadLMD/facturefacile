<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié.']);
    exit;
}

$user  = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message vide.']);
    exit;
}

$systemPrompt = <<<PROMPT
Tu es un assistant de facturation intelligent pour une application SaaS marocaine.
L'utilisateur va décrire une facture en langage naturel (français, arabe ou anglais).
Extrait les informations et retourne UNIQUEMENT un objet JSON valide avec cette structure exacte :

{
  "client_nom": "Nom du client (string)",
  "items": [
    {
      "description": "Description du produit ou service (string)",
      "quantite": 1,
      "prix_unitaire": 0.00,
      "tva": 20
    }
  ],
  "notes": "Notes optionnelles (string, peut être vide)",
  "message_confirmation": "Un message de confirmation en français résumant la facture"
}

Règles importantes :
- Si le client n'est pas mentionné, utilise "Client"
- La TVA par défaut au Maroc est 20% sauf indication contraire
- Si la quantité n'est pas mentionnée, utilise 1
- Convertis les prix en nombre décimal (ex: "500 DH" → 500.00)
- Sépare correctement les différents articles/services s'il y en a plusieurs
- Si l'utilisateur dit "sans TVA" ou "HT" ou "exonéré", mets tva à 0
- Retourne UNIQUEMENT le JSON, sans markdown, sans texte avant ou après
PROMPT;

$response = callAI($message, $systemPrompt, $user);

if (isset($response['error'])) {
    echo json_encode(['success' => false, 'error' => $response['error']]);
    exit;
}

$content = trim($response['content'][0]['text'] ?? '');

// Nettoyer si markdown code block
$content = preg_replace('/^```json\s*/i', '', $content);
$content = preg_replace('/^```\s*/i',     '', $content);
$content = preg_replace('/\s*```$/i',     '', $content);

$data = json_decode($content, true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'L\'IA n\'a pas pu extraire les informations. Essayez de reformuler. Ex: "Facture pour Ali, 2 logos à 300 DH"',
        'raw'     => $content,
    ]);
    exit;
}

// Calculer les totaux
$sousTotalHT = 0;
$totalTVA    = 0;

foreach ($data['items'] as &$item) {
    $item['quantite']      = (float)($item['quantite'] ?? 1);
    $item['prix_unitaire'] = (float)($item['prix_unitaire'] ?? 0);
    $item['tva']           = (float)($item['tva'] ?? 20);
    $item['total']         = round($item['quantite'] * $item['prix_unitaire'], 2);
    $sousTotalHT += $item['total'];
    $totalTVA    += round($item['total'] * $item['tva'] / 100, 2);
}
unset($item);

$data['sous_total']  = round($sousTotalHT, 2);
$data['tva_montant'] = round($totalTVA, 2);
$data['total']       = round($sousTotalHT + $totalTVA, 2);
$data['devise']      = $user['devise'] ?? 'MAD';

echo json_encode(['success' => true, 'data' => $data]);
