<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success' => false]); exit; }

$user  = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$allowed = ['print', 'view_pdf', 'download', 'open', 'convert', 'view', 'email', 'status_change', 'export', 'create', 'edit'];
$action   = $input['action']    ?? '';
$module   = $input['module']    ?? '';
$eid      = (int)($input['entity_id'] ?? 0);
$eref     = trim($input['entity_ref'] ?? '');
$details  = trim($input['details']    ?? '');

if (in_array($action, $allowed, true) && $module && $eid) {
    logAction($user['id'], $action, $module, $eid, $eref, $details);
}
echo json_encode(['success' => true]);
