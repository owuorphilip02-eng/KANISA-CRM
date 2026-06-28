<?php
namespace ChurchCRM\Kenya\Mpesa;
require_once __DIR__ . '/../../../Include/Config.php';
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Kenya\Mpesa\MpesaService;
class MpesaCallback
{
    public static function process(): void
    {
        $payment = MpesaService::handleCallback();
        if (!$payment) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'message' => 'Invalid or failed payment']);
            return;
        }
        $fund = DonationFundQuery::create()->findOneByName('Tithe');
        if (!$fund) {
            $fund = DonationFundQuery::create()->findOne();
        }
        $deposit = new Deposit();
        $deposit->setDate(date('Y-m-d'));
        $deposit->setComment('M-Pesa Payment - Receipt: ' . $payment['receipt']);
        $deposit->setEnteredby(1);
        $deposit->setClosed(false);
        $deposit->save();
        $pledge = new Pledge();
        $pledge->setFundid($fund ? $fund->getId() : 1);
        $pledge->setDepid($deposit->getId());
        $pledge->setAmount($payment['amount']);
        $pledge->setMethod('MPESA');
        $pledge->setComment('M-Pesa: ' . $payment['receipt'] . ' from ' . $payment['phone']);
        $pledge->setDate(date('Y-m-d'));
        $pledge->setPledgeOrPayment('Payment');
        $pledge->setDateLastEdited(date('Y-m-d'));
        $pledge->save();

        // Tag this payment as STK Push for analytics
        $pdo = new PDO('mysql:host=localhost;dbname=churchcrm_kenya', 'root', 'root123');
        $stmt = $pdo->prepare("UPDATE pledge_plg SET plg_mpesa_source = 'STK' WHERE plg_plgID = ?");
        $stmt->execute([$pledge->getId()]);
        // Update registration pending table if this was a registration payment
        // Update registration pending table
        $stmt2 = $pdo->prepare("
            UPDATE registration_stk_pending 
            SET status = 'success', mpesa_receipt = ?
            WHERE phone = ? AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt2->execute([$payment['receipt'], $payment['phone']]);

        // Get the pending record to check type and person_id
        $stmt3 = $pdo->prepare("
            SELECT person_id, payment_type, amount FROM registration_stk_pending
            WHERE phone = ? AND status = 'success' AND mpesa_receipt = ?
            LIMIT 1
        ");
        $stmt3->execute([$payment['phone'], $payment['receipt']]);
        $pending = $stmt3->fetch(PDO::FETCH_ASSOC);

        if ($pending && $pending['person_id']) {
            $personId = $pending['person_id'];
            $amount   = $pending['amount'];

            if ($pending['payment_type'] === 'renewal') {
                // Extend membership by 1 year from current expiry or today
                $stmt4 = $pdo->prepare("
                    UPDATE person_per 
                    SET per_membership_expires = DATE_ADD(
                        GREATEST(COALESCE(per_membership_expires, CURDATE()), CURDATE()),
                        INTERVAL 1 YEAR
                    ),
                    per_membership_status = 'active',
                    per_registration_amount = ?
                    WHERE per_ID = ?
                ");
                $stmt4->execute([$amount, $personId]);
            } else {
                // First registration — set expiry 1 year from today
                $stmt4 = $pdo->prepare("
                    UPDATE person_per 
                    SET per_membership_expires = DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
                    per_membership_status = 'active',
                    per_registration_amount = ?
                    WHERE per_ID = ?
                ");
                $stmt4->execute([$amount, $personId]);
            }
        }
    private static function log(array $payment): void
    {
        $logDir = __DIR__ . '/../../../../logs/mpesa/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'mpesa_' . date('Y-m-d') . '.log';
        $entry = date('Y-m-d H:i:s') . ' | Receipt: ' . $payment['receipt'] . ' | Amount: KES ' . $payment['amount'] . ' | Phone: ' . $payment['phone'] . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
MpesaCallback::process();
