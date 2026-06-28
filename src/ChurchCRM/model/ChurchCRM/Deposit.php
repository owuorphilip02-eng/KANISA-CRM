<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Deposit as BaseDeposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\PledgeQuery as ChildPledgeQuery;
use ChurchCRM\Reports\PdfDepositReport;
use ChurchCRM\Service\AuthService;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Deposit extends BaseDeposit
{
    public function preDelete(ConnectionInterface $con = null): bool
    {
        $this->getPledges()->delete();

        return true;
    }

    public function getOFX(): \stdClass
    {
        $OFXReturn = new \stdClass();
        if ($this->getPledges()->count() === 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }

        $orgName = 'ChurchCRM Deposit Data';
        $OFXReturn->content = 'OFXHEADER:100' . PHP_EOL .
            'DATA:OFXSGML' . PHP_EOL .
            'VERSION:102' . PHP_EOL .
            'SECURITY:NONE' . PHP_EOL .
            'ENCODING:USASCII' . PHP_EOL .
            'CHARSET:1252' . PHP_EOL .
            'COMPRESSION:NONE' . PHP_EOL .
            'OLDFILEUID:NONE' . PHP_EOL .
            'NEWFILEUID:NONE' . PHP_EOL . PHP_EOL;
        $OFXReturn->content .= '<OFX>';
        $OFXReturn->content .= '<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>' . date('YmdHis.u[O:T]') . '<LANGUAGE>ENG<FI><ORG>' . $orgName . '<FID>12345</FI></SONRS></SIGNONMSGSRSV1>';
        $OFXReturn->content .= '<BANKMSGSRSV1>' .
            '<STMTTRNRS>' .
            '<TRNUID>' .
            '<STATUS>' .
            '<CODE>0' .
            '<SEVERITY>INFO' .
            '</STATUS>';

        foreach ($this->getFundTotals() as $fund) {
            $OFXReturn->content .= '<STMTRS>' .
                '<CURDEF>USD' .
                '<BANKACCTFROM>' .
                '<BANKID>' . $orgName .
                '<ACCTID>' . $fund['Name'] .
                '<ACCTTYPE>SAVINGS' .
                '</BANKACCTFROM>';
            $OFXReturn->content .=
                '<STMTTRN>' .
                '<TRNTYPE>CREDIT' .
                '<DTPOSTED>' . $this->getDate('Ymd') .
                '<TRNAMT>' . $fund['Total'] .
                '<FITID>' .
                '<NAME>' . $this->getComment() .
                '<MEMO>' . $fund['Name'] .
                '</STMTTRN></STMTRS>';
        }

        $OFXReturn->content .= '</STMTTRNRS></BANKTRANLIST></OFX>';
        // Export file
        $OFXReturn->header = 'Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $this->getId() . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.ofx';

        return $OFXReturn;
    }

    private function generateCashDenominations(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $cashDenominations = ['0.01', '0.05', '0.10', '0.25', '0.50', '1.00'];
        $thisReport->pdf->Cell(10, 10, 'Coin', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Counts', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Totals', 1, 2, 'L');
        $thisReport->pdf->SetX($thisReport->curX);
        foreach ($cashDenominations as $denomination) {
            $thisReport->pdf->Cell(10, 10, $denomination, 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 2, 'L');
            $thisReport->pdf->SetX($thisReport->curX);
        }
        $thisReport->pdf->Cell(50, 10, 'Total Coin', 1, 2, 'L');

        $thisReport->curX += 70;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);

        $cashDenominations = ['$1', '$2', '$5', '$10', '$20', '$50', '$100'];
        $thisReport->pdf->Cell(10, 10, 'Bill', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Counts', 1, 0, 'L');
        $thisReport->pdf->Cell(20, 10, 'Totals', 1, 2, 'L');
        $thisReport->pdf->SetX($thisReport->curX);
        foreach ($cashDenominations as $denomination) {
            $thisReport->pdf->Cell(10, 10, $denomination, 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 0, 'L');
            $thisReport->pdf->Cell(20, 10, '', 1, 2, 'L');
            $thisReport->pdf->SetX($thisReport->curX);
        }
        $thisReport->pdf->Cell(50, 10, 'Total Cash', 1, 2, 'L');
    }

    private function generateTotalsByCurrencyType(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit totals by Currency Type');
        $thisReport->pdf->SetFont('Courier', '', 8);
        $thisReport->curY += 4;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Checks: ');
        $thisReport->pdf->write(8, '(' . $this->getCountChecks() . ')');
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, sprintf('%.2f', $this->getTotalChecks()));
        $thisReport->curY += 4;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Cash: ');
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, sprintf('%.2f', $this->getTotalCash()));
    }

    private function generateTotalsByFund(\stdClass $thisReport): void
    {
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit totals by fund');
        $thisReport->pdf->SetFont('Courier', '', 8);

        $thisReport->curY += 4;

        foreach ($this->getFundTotals() as $fund) { //iterate through the defined funds
            $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
            $thisReport->pdf->Write(8, $fund['Name']);
            $amountStr = sprintf('%.2f', $fund['Total']);
            $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, $amountStr);
            $thisReport->curY += 4;
        }
    }

    private function generateQBDepositSlip(\stdClass $thisReport): void
    {
        $thisReport->pdf->addPage();

        $thisReport->QBDepositTicketParameters = json_decode(SystemConfig::getValue('sQBDTSettings'), null, 512, JSON_THROW_ON_ERROR);
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->date1->x, $thisReport->QBDepositTicketParameters->date1->y);
        $thisReport->pdf->Write(8, $this->getDate()->format('Y-m-d'));

        //print_r($thisReport->QBDepositTicketParameters);
        //logically, we print the cash in the first possible key=value pair column
        if ($this->getTotalCash() > 0) {
            $totalCashStr = sprintf('%.2f', $this->getTotalCash());
            $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->leftX + $thisReport->QBDepositTicketParameters->amountOffsetX, $thisReport->QBDepositTicketParameters->topY, $totalCashStr);
        }
        $thisReport->curX = $thisReport->QBDepositTicketParameters->leftX + $thisReport->QBDepositTicketParameters->lineItemInterval->x;
        $thisReport->curY = $thisReport->QBDepositTicketParameters->topY;

        $pledges = PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'sumAmount')
            ->joinFamily(null, Criteria::LEFT_JOIN)
            ->withColumn(FamilyTableMap::COL_FAM_NAME)
            ->find();
        foreach ($pledges as $pledge) {
            // then all of the checks in key-value pairs, in 3 separate columns.  Left to right, then top to bottom.
            if ($pledge->getMethod() === 'CHECK') {
                $thisReport->pdf->printRightJustified($thisReport->curX, $thisReport->curY, $pledge->getCheckNo());
                $thisReport->pdf->printRightJustified($thisReport->curX + $thisReport->QBDepositTicketParameters->amountOffsetX, $thisReport->curY, $pledge->getsumAmount());

                $thisReport->curX += $thisReport->QBDepositTicketParameters->lineItemInterval->x;
                if ($thisReport->curX > $thisReport->QBDepositTicketParameters->max->x) {
                    $thisReport->curX = $thisReport->QBDepositTicketParameters->leftX;
                    $thisReport->curY += $thisReport->QBDepositTicketParameters->lineItemInterval->y;
                }
            }
        }

        $grandTotalStr = sprintf('%.2f', $this->getTotalAmount());
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->subTotal->x, $thisReport->QBDepositTicketParameters->subTotal->y, $grandTotalStr);
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->topTotal->x, $thisReport->QBDepositTicketParameters->topTotal->y, $grandTotalStr);
        $numItemsString = sprintf('%d', ($this->getCountCash() > 0 ? 1 : 0) + $this->getCountChecks());
        $thisReport->pdf->printRightJustified($thisReport->QBDepositTicketParameters->numberOfItems->x, $thisReport->QBDepositTicketParameters->numberOfItems->y, $numItemsString);

        $thisReport->curY = $thisReport->QBDepositTicketParameters->perforationY;
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->titleX, $thisReport->curY);
        $thisReport->pdf->SetFont('Courier', 'B', 20);
        $thisReport->pdf->Write(8, 'Deposit Summary ' . $this->getId());
        $thisReport->pdf->SetFont('Times', '', 10);
        $thisReport->pdf->SetXY($thisReport->QBDepositTicketParameters->date2X, $thisReport->curY);
        $thisReport->pdf->Write(8, $this->getDate()->format('Y-m-d'));

        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x;
        $thisReport->curY += 2 * $thisReport->QBDepositTicketParameters->lineItemInterval->y;

        if (SystemConfig::getBooleanValue('bDisplayBillCounts')) {
            $this->generateCashDenominations($thisReport);
        }

        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x + 125;

        $this->generateTotalsByCurrencyType($thisReport);
        $thisReport->curX = $thisReport->QBDepositTicketParameters->date1->x + 125;
        $thisReport->curY = $thisReport->QBDepositTicketParameters->perforationY + 30;
        $this->generateTotalsByFund($thisReport);

        $thisReport->curY += $thisReport->QBDepositTicketParameters->lineItemInterval->y;
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->Write(8, 'Deposit total');
        $grandTotalStr = sprintf('%.2f', $this->getTotalAmount());
        $thisReport->pdf->printRightJustified($thisReport->curX + 55, $thisReport->curY, $grandTotalStr);
        $thisReport->pdf->SetFont('Courier', '', 8);
    }

    private function generateDepositSummary(\stdClass $thisReport): void
    {
        $thisReport->pdf->addPage();

        // ── Church Letterhead ──────────────────────────────────────────
        $logoPath = SystemURLs::getDocumentRoot() . '/Images/logo-churchcrm-350.png';
        if (is_readable($logoPath)) {
            $thisReport->pdf->Image($logoPath, 12, 6, 18, 18, 'PNG');
        }
        $thisReport->pdf->SetFont('Times', 'B', 14);
        $thisReport->pdf->SetXY(32, 7);
        $thisReport->pdf->Write(6, strtoupper(SystemConfig::getValue('sChurchName')));
        $thisReport->pdf->SetFont('Times', '', 10);
        $thisReport->pdf->SetXY(32, 14);
        $thisReport->pdf->Write(5, 'Diocese of Nairobi');
        $thisReport->pdf->SetFont('Times', '', 9);
        $thisReport->pdf->SetXY(32, 19);
        $thisReport->pdf->Write(5, strtoupper(date('j F Y')));
        $thisReport->pdf->Line(12, 26, 200, 26);
        // ── End Letterhead ─────────────────────────────────────────────

        // ── Deposit Title ──────────────────────────────────────────────
        $thisReport->pdf->SetFont('Times', 'B', 14);
        $thisReport->pdf->SetXY(12, 30);
        $thisReport->pdf->Cell(186, 8, 'DEPOSIT SUMMARY - #' . $this->getId(), 0, 1, 'C');
        $thisReport->pdf->SetFont('Times', '', 10);
        $thisReport->pdf->SetXY(12, 39);
        $thisReport->pdf->Write(6, 'Deposit Date: ' . $this->getDate()->format('d F Y'));
        if (!empty($this->getComment())) {
            $thisReport->pdf->SetXY(12, 45);
            $thisReport->pdf->Write(6, 'Comment: ' . $this->getComment());
        }
        $thisReport->pdf->Line(12, 52, 200, 52);
        // ── End Title ──────────────────────────────────────────────────

        // ── Payments Table Header ──────────────────────────────────────
        $curY = 55;
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetFillColor(220, 220, 220);
        $thisReport->pdf->SetXY(12, $curY);
        $thisReport->pdf->Cell(40, 7, 'FUND', 1, 0, 'L', true);
        $thisReport->pdf->Cell(30, 7, 'METHOD', 1, 0, 'L', true);
        $thisReport->pdf->Cell(75, 7, 'REFERENCE / MEMO', 1, 0, 'L', true);
        $thisReport->pdf->Cell(41, 7, 'AMOUNT (KES)', 1, 1, 'R', true);
        $curY += 7;

        // ── Payments Table Rows ────────────────────────────────────────
        $thisReport->pdf->SetFont('Times', '', 10);
        $grandTotal = 0;

        foreach ($this->getPledges() as $payment) {
            $fund = DonationFundQuery::create()->findOneById($payment->getFundId());
            $fundName = $fund ? $fund->getName() : 'General';
            $method   = $payment->getMethod();
            $memo     = $payment->getComment() ?: $payment->getCheckNo() ?: '';
            $amount   = $payment->getAmount();
            $grandTotal += $amount;

            if (strlen($fundName) > 22) {
                $fundName = mb_substr($fundName, 0, 21) . '.';
            }
            if (strlen($memo) > 48) {
                $memo = mb_substr($memo, 0, 47) . '.';
            }

            $thisReport->pdf->SetXY(12, $curY);
            $thisReport->pdf->Cell(40, 6, $fundName, 1, 0, 'L');
            $thisReport->pdf->Cell(30, 6, $method, 1, 0, 'L');
            $thisReport->pdf->Cell(75, 6, $memo, 1, 0, 'L');
            $thisReport->pdf->Cell(41, 6, number_format($amount, 2), 1, 1, 'R');
            $curY += 6;

            if ($curY >= 250) {
                $thisReport->pdf->addPage();
                $curY = 15;
            }
        }

        // ── Grand Total ────────────────────────────────────────────────
        $curY += 2;
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY(12, $curY);
        $thisReport->pdf->Cell(145, 7, 'TOTAL', 1, 0, 'R');
        $thisReport->pdf->Cell(41, 7, 'KES ' . number_format($grandTotal, 2), 1, 1, 'R');
        $curY += 7;

        // ── Totals by Fund ─────────────────────────────────────────────
        $curY += 6;
        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY(12, $curY);
        $thisReport->pdf->Write(6, 'Totals by Fund:');
        $curY += 7;
        $thisReport->pdf->SetFont('Times', '', 10);
        foreach ($this->getFundTotals() as $fund) {
            $thisReport->pdf->SetXY(12, $curY);
            $thisReport->pdf->Write(6, $fund['Name']);
            $thisReport->pdf->SetXY(120, $curY);
            $thisReport->pdf->Write(6, 'KES ' . number_format($fund['Total'], 2));
            $curY += 6;
        }

        // ── Witness Signatures ─────────────────────────────────────────
        $curY = $thisReport->pdf->GetPageHeight() - 35;
        $thisReport->pdf->SetFont('Times', '', 10);
        foreach (['Witness 1', 'Witness 2', 'Witness 3'] as $witness) {
            $thisReport->pdf->SetXY(12, $curY);
            $thisReport->pdf->Write(6, $witness);
            $thisReport->pdf->Line(30, $curY + 6, 100, $curY + 6);
            $curY += 12;
        }
    }
    private function generateWitnessSignature(\stdClass $thisReport): void
    {
        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;
        $thisReport->curY = $thisReport->pdf->GetPageHeight() - 30;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 1');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 2');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 3');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);
    }

    public function getPDF(): void
    {
        AuthService::requireUserGroupMembership('bFinance');
        $Report = new \stdClass();
        if (count($this->getPledges()) === 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }

        $Report->pdf = new PdfDepositReport();
        $Report->funds = DonationFundQuery::create()->find();

        $this->generateDepositSummary($Report);

        // Export file
        $Report->pdf->Output('ChurchCRM-DepositReport-' . $this->getId() . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    }

    public function getTotalAmount()
    {
        return $this->getVirtualColumn('totalAmount');
    }

    public function getTotalChecks()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CHECK')
            ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'sumAmount')
            ->find()
            ->getColumnValues('sumAmount')[0];
    }

    public function getTotalCash()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CASH')
            ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'sumAmount')
            ->find()
            ->getColumnValues('sumAmount')[0];
    }

    public function getCountChecks()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CHECK')
            ->find()
            ->count();
    }

    public function getCountCash()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CASH')
            ->find()
            ->count();
    }

    public function getFundTotals()
    {
        return PledgeQuery::create()
        ->filterByDepId($this->getId())
        ->groupByFundId()
        ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'Total')
        ->joinDonationFund()
        ->withColumn(DonationFundTableMap::COL_FUN_NAME, 'Name')
        ->orderBy(DonationFundTableMap::COL_FUN_NAME)
        ->select(['Name', 'Total'])
        ->find();
    }

    public function getPledgesJoinAll(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', Criteria::RIGHT_JOIN);
        $query->joinWith('DonationFund', Criteria::RIGHT_JOIN);

        return $this->getPledges($query, $con);
    }

    /**
     * Get the previous deposit (by ID).
     */
    public static function getPreviousDeposit(int $currentId): ?Deposit
    {
        return DepositQuery::create()
            ->filterById($currentId, Criteria::LESS_THAN)
            ->orderById(Criteria::DESC)
            ->findOne();
    }

    /**
     * Get the next deposit (by ID).
     */
    public static function getNextDeposit(int $currentId): ?Deposit
    {
        return DepositQuery::create()
            ->filterById($currentId, Criteria::GREATER_THAN)
            ->orderById(Criteria::ASC)
            ->findOne();
    }
}
