<?php
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Reports\PdfDepositReport;

if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('index.php');
}

// Sanitize input IDs
$rawIds = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $rawIds)));

if (empty($ids)) {
    http_response_code(400);
    echo 'No deposit IDs provided.';
    exit;
}

$pdf = new PdfDepositReport();

foreach ($ids as $depositId) {
    $deposit = DepositQuery::create()
        ->withColumn('(SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_DepID = dep_id)', 'totalAmount')
        ->findPk($depositId);

    if (!$deposit || count($deposit->getPledges()) === 0) {
        continue;
    }

    $pdf->addPage();

    // ── Church Letterhead ──────────────────────────────────────────
    $logoPath = SystemURLs::getDocumentRoot() . '/Images/logo-churchcrm-350.png';
    if (is_readable($logoPath)) {
        $pdf->Image($logoPath, 12, 6, 18, 18, 'PNG');
    }
    $pdf->SetFont('Times', 'B', 14);
    $pdf->SetXY(32, 7);
    $pdf->Write(6, strtoupper(SystemConfig::getValue('sChurchName')));
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY(32, 14);
    $pdf->Write(5, 'Diocese of Nairobi');
    $pdf->SetFont('Times', '', 9);
    $pdf->SetXY(32, 19);
    $pdf->Write(5, strtoupper(date('j F Y')));
    $pdf->Line(12, 26, 200, 26);

    // ── Deposit Title ──────────────────────────────────────────────
    $pdf->SetFont('Times', 'B', 14);
    $pdf->SetXY(12, 30);
    $pdf->Cell(186, 8, 'DEPOSIT SUMMARY - #' . $deposit->getId(), 0, 1, 'C');
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY(12, 39);
    $pdf->Write(6, 'Deposit Date: ' . $deposit->getDate()->format('d F Y'));
    if (!empty($deposit->getComment())) {
        $pdf->SetXY(12, 45);
        $pdf->Write(6, 'Comment: ' . $deposit->getComment());
    }
    $pdf->Line(12, 52, 200, 52);

    // ── Payments Table Header ──────────────────────────────────────
    $curY = 55;
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetXY(12, $curY);
    $pdf->Cell(40, 7, 'FUND', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'METHOD', 1, 0, 'L', true);
    $pdf->Cell(75, 7, 'REFERENCE / MEMO', 1, 0, 'L', true);
    $pdf->Cell(41, 7, 'AMOUNT (KES)', 1, 1, 'R', true);
    $curY += 7;

    // ── Payments Table Rows ────────────────────────────────────────
    $pdf->SetFont('Times', '', 10);
    $grandTotal = 0;

    foreach ($deposit->getPledges() as $payment) {
        $fund = DonationFundQuery::create()->findOneById($payment->getFundId());
        $fundName = $fund ? $fund->getName() : 'General';
        $method   = $payment->getMethod();
        $memo     = $payment->getComment() ?: $payment->getCheckNo() ?: '';
        $amount   = $payment->getAmount();
        $grandTotal += $amount;

        if (strlen($fundName) > 22) $fundName = mb_substr($fundName, 0, 21) . '.';
        if (strlen($memo) > 48) $memo = mb_substr($memo, 0, 47) . '.';

        $pdf->SetXY(12, $curY);
        $pdf->Cell(40, 6, $fundName, 1, 0, 'L');
        $pdf->Cell(30, 6, $method, 1, 0, 'L');
        $pdf->Cell(75, 6, $memo, 1, 0, 'L');
        $pdf->Cell(41, 6, number_format($amount, 2), 1, 1, 'R');
        $curY += 6;

        if ($curY >= 250) {
            $pdf->addPage();
            $curY = 15;
        }
    }

    // ── Grand Total ────────────────────────────────────────────────
    $curY += 2;
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetXY(12, $curY);
    $pdf->Cell(145, 7, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(41, 7, 'KES ' . number_format($grandTotal, 2), 1, 1, 'R');
    $curY += 7;

    // ── Totals by Fund ─────────────────────────────────────────────
    $curY += 6;
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetXY(12, $curY);
    $pdf->Write(6, 'Totals by Fund:');
    $curY += 7;
    $pdf->SetFont('Times', '', 10);
    foreach ($deposit->getFundTotals() as $fund) {
        $pdf->SetXY(12, $curY);
        $pdf->Write(6, $fund['Name']);
        $pdf->SetXY(120, $curY);
        $pdf->Write(6, 'KES ' . number_format($fund['Total'], 2));
        $curY += 6;
    }

    // ── Witness Signatures ─────────────────────────────────────────
    $curY = $pdf->GetPageHeight() - 35;
    $pdf->SetFont('Times', '', 10);
    foreach (['Witness 1', 'Witness 2', 'Witness 3'] as $witness) {
        $pdf->SetXY(12, $curY);
        $pdf->Write(6, $witness);
        $pdf->Line(30, $curY + 6, 100, $curY + 6);
        $curY += 12;
    }
}

$pdf->Output('ChurchCRM-Deposits-Batch-' . date('Ymd') . '.pdf', 'D');