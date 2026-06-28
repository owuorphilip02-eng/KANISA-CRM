<?php
require_once __DIR__ . '/../Include/Config.php';

use ChurchCRM\Kenya\Mpesa\MpesaService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$phone       = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$amount      = (int) ($_POST['amount'] ?? 0);
$name        = trim($_POST['name'] ?? 'Member Registration');
$personId    = (int) ($_POST['person_id'] ?? 0);
$paymentType = $_POST['payment_type'] ?? 'registration';

if (str_starts_with($phone, '0')) {
    $phone = '254' . substr($phone, 1);
}

if (strlen($phone) !== 12 || $amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number or amount.']);
    exit;
}

$description = ($paymentType === 'renewal' ? 'Renewal' : 'Registration') . ' - ' . date('M Y');

$response = MpesaService::stkPush($phone, $amount, $name, $description);

if (($response['ResponseCode'] ?? '') === '0') {
    $checkoutId = $response['CheckoutRequestID'];

    $pdo = Propel\Runtime\Propel::getConnection();
    $stmt = $pdo->prepare("
        INSERT INTO registration_stk_pending 
        (checkout_id, phone, amount, status, person_id, payment_type)
        VALUES (?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([$checkoutId, $phone, $amount, $personId ?: null, $paymentType]);

    echo json_encode([
        'success'     => true,
        'checkout_id' => $checkoutId,
        'message'     => 'STK Push sent. Ask member to enter M-Pesa PIN.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $response['errorMessage'] ?? $response['ResponseDescription'] ?? 'STK Push failed.'
    ]);
}