<?php
require_once __DIR__ . '/../Include/Config.php';

header('Content-Type: application/json');

$checkoutId = $_GET['checkout_id'] ?? '';

if (empty($checkoutId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing checkout_id']);
    exit;
}

$pdo = Propel\Runtime\Propel::getConnection();
$stmt = $pdo->prepare("
    SELECT status, mpesa_receipt FROM registration_stk_pending 
    WHERE checkout_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$checkoutId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['status' => 'pending']);
    exit;
}

echo json_encode([
    'status'  => $row['status'],
    'receipt' => $row['mpesa_receipt'] ?? null
]);

