<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request.']); exit;
}

if (!icIsEnabled()) {
    echo json_encode(['error' => 'IC assistant is not set up yet. Ask your admin to enable it in Settings.']); exit;
}

$message = trim($_POST['message'] ?? '');
$context = trim($_POST['context'] ?? '');

if ($message === '') {
    echo json_encode(['error' => 'Please type a question first.']); exit;
}
if (mb_strlen($message) > 500) {
    echo json_encode(['error' => 'That message is a bit long — try asking in a shorter way.']); exit;
}

$result = icAskAssistant($message, $context);
echo json_encode($result);
