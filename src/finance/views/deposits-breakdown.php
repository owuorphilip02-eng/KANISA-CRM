<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$con = Propel\Runtime\Propel::getConnection();
$sRootPath = SystemURLs::getRootPath();

// SUMMARY BY PAYMENT TYPE
$stmtByType = $con->query("
    SELECT
        CASE
            WHEN p.plg_mpesa_source = 'PAYBILL' THEN 'M-Pesa Paybill'
            WHEN p.plg_mpesa_source = 'TILL' THEN 'M-Pesa Till'
            WHEN p.plg_mpesa_source = 'STK' THEN 'M-Pesa STK Push'
            WHEN p.plg_mpesa_source = 'MANUAL' THEN 'M-Pesa Manual'
            WHEN d.dep_Type = 'CreditCard' THEN 'Card/Cheque'
            ELSE 'Cash'
        END as payment_type,
        COUNT(DISTINCT d.dep_ID) as deposit_count,
        COALESCE(SUM(p.plg_amount), 0) as total_amount
    FROM deposit_dep d
    LEFT JOIN pledge_plg p ON p.plg_depID = d.dep_ID AND p.plg_PledgeOrPayment = 'Payment'
    GROUP BY payment_type
    ORDER BY total_amount DESC
");
$byType = $stmtByType->fetchAll(PDO::FETCH_ASSOC);

foreach ($byType as &$row) {
    $row['dep_Type'] = $row['payment_type'];
}
unset($row);

// SUMMARY BY MONTH
$stmtByMonth = $con->query("
    SELECT
        DATE_FORMAT(d.dep_Date, '%Y-%m') as month,
        DATE_FORMAT(d.dep_Date, '%b %Y') as month_label,
        CASE
            WHEN p.plg_mpesa_source IS NOT NULL THEN 'M-Pesa'
            WHEN d.dep_Type = 'CreditCard' THEN 'Card/Cheque'
            ELSE 'Cash'
        END as dep_Type,
        COALESCE(SUM(p.plg_amount), 0) as total_amount
    FROM deposit_dep d
    LEFT JOIN pledge_plg p ON p.plg_depID = d.dep_ID AND p.plg_PledgeOrPayment = 'Payment'
    WHERE d.dep_Date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month, dep_Type
    ORDER BY month ASC
");
$byMonth = $stmtByMonth->fetchAll(PDO::FETCH_ASSOC);

// ALL DEPOSITS
$stmtAll = $con->query("
    SELECT
        d.dep_ID,
        d.dep_Date,
        d.dep_Type,
        d.dep_Comment,
        d.dep_Closed,
        COALESCE(SUM(p.plg_amount), 0) as total_amount,
        COUNT(p.plg_plgID) as payment_count
    FROM deposit_dep d
    LEFT JOIN pledge_plg p ON p.plg_depID = d.dep_ID AND p.plg_PledgeOrPayment = 'Payment'
    GROUP BY d.dep_ID
    ORDER BY d.dep_Date DESC
");
$allDeposits = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// M-PESA BREAKDOWN
$stmtMpesa = $con->query("
    SELECT
        plg_mpesa_source,
        COUNT(plg_plgID) as count,
        COALESCE(SUM(plg_amount), 0) as total
    FROM pledge_plg
    WHERE plg_PledgeOrPayment = 'Payment'
    AND plg_mpesa_source IS NOT NULL
    GROUP BY plg_mpesa_source
    ORDER BY total DESC
");
$mpesaData = $stmtMpesa->fetchAll(PDO::FETCH_ASSOC);
$mpesaTotal = array_sum(array_column($mpesaData, 'total'));

// GRAND TOTAL
$grandTotal = array_sum(array_column($byType, 'total_amount'));
$totalCount = array_sum(array_column($byType, 'deposit_count'));

// CHART DATA
$typeLabels  = array_column($byType, 'dep_Type');
$typeAmounts = array_column($byType, 'total_amount');
$typeColors  = ['#2fb344','#4299e1','#f76707','#ae3ec9','#e63946','#17a2b8','#6f42c1'];

$months = [];
foreach ($byMonth as $row) {
    if (!in_array($row['month_label'], $months)) {
        $months[] = $row['month_label'];
    }
}
$monthlyMpesa = [];
$monthlyCash  = [];
$monthlyCard  = [];
foreach ($months as $m) {
    $mpesa = $cash = $card = 0;
    foreach ($byMonth as $row) {
        if ($row['month_label'] === $m) {
            if ($row['dep_Type'] === 'M-Pesa')       $mpesa = (float)$row['total_amount'];
            if ($row['dep_Type'] === 'Cash')          $cash  = (float)$row['total_amount'];
            if ($row['dep_Type'] === 'Card/Cheque')   $card  = (float)$row['total_amount'];
        }
    }
    $monthlyMpesa[] = $mpesa;
    $monthlyCash[]  = $cash;
    $monthlyCard[]  = $card;
}
?>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex align-items-center mb-3">
        <div>
            <h2 class="mb-0">Deposits Breakdown</h2>
            <p class="text-body-secondary mb-0">Full breakdown of all deposits by payment type</p>
        </div>
        <div class="ms-auto">
            <a href="<?= $sRootPath ?>/finance/" class="btn btn-outline-secondary me-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Finance Dashboard
            </a>
            <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i> New Deposit
            </a>
        </div>
    </div>

    <!-- Grand Total + Type Cards -->
    <div class="row mb-3">
        <div class="col-lg-3 col-6">
            <div class="card card-sm bg-success text-white">
                <div class="card-body">
                    <div class="fw-bold fs-3">KES <?= number_format($grandTotal, 2) ?></div>
                    <div class="small">Total All Deposits</div>
                    <div class="small opacity-75"><?= $totalCount ?> deposits</div>
                </div>
            </div>
        </div>
        <?php foreach ($byType as $i => $type): ?>
        <div class="col-lg-3 col-6 mb-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge me-2" style="background-color:<?= $typeColors[$i % count($typeColors)] ?>">
                            <?= htmlspecialchars($type['dep_Type']) ?>
                        </span>
                        <span class="text-body-secondary small"><?= $type['deposit_count'] ?> deposits</span>
                    </div>
                    <div class="fw-bold fs-5">KES <?= number_format($type['total_amount'], 2) ?></div>
                    <div class="text-body-secondary small">
                        <?= $grandTotal > 0 ? round(($type['total_amount'] / $grandTotal) * 100, 1) : 0 ?>% of total
                    </div>
                    <div class="progress mt-2" style="height:4px">
                        <div class="progress-bar" style="width:<?= $grandTotal > 0 ? ($type['total_amount'] / $grandTotal) * 100 : 0 ?>%;background-color:<?= $typeColors[$i % count($typeColors)] ?>"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="row mb-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-pie me-2"></i>Deposits by Type</h3>
                </div>
                <div class="card-body">
                    <div id="donutChart" style="min-height:280px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-bar me-2"></i>Monthly Deposits (Last 12 Months)</h3>
                </div>
                <div class="card-body">
                    <div id="barChart" style="min-height:280px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- M-Pesa Breakdown -->
    <?php if (!empty($mpesaData)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-mobile-screen-button me-2"></i>M-Pesa Breakdown by Source</h3>
            <div class="ms-auto text-body-secondary small">Total M-Pesa: KES <?= number_format($mpesaTotal, 2) ?></div>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $mpesaBadgeColors = [
                    'STK'     => 'bg-green-lt text-green',
                    'PAYBILL' => 'bg-blue-lt text-blue',
                    'TILL'    => 'bg-warning text-dark',
                    'MANUAL'  => 'bg-secondary-lt text-secondary',
                ];
                foreach ($mpesaData as $m):
                    $pct = $mpesaTotal > 0 ? round(($m['total'] / $mpesaTotal) * 100, 1) : 0;
                    $badgeClass = $mpesaBadgeColors[$m['plg_mpesa_source']] ?? 'bg-secondary-lt';
                ?>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <span class="badge <?= $badgeClass ?> mb-2"><?= $m['plg_mpesa_source'] ?></span>
                            <div class="fw-bold">KES <?= number_format($m['total'], 2) ?></div>
                            <div class="text-body-secondary small"><?= $m['count'] ?> payments · <?= $pct ?>% of M-Pesa</div>
                            <div class="progress mt-2" style="height:4px">
                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Deposits Table -->
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title"><i class="fa-solid fa-list me-2"></i>All Deposits</h3>
            <div class="ms-auto">
                <input type="text" id="searchDeposits" class="form-control form-control-sm" placeholder="Search...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0" id="depositsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Comment</th>
                            <th class="text-center">Payments</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allDeposits as $dep): ?>
                        <tr>
                            <td>#<?= $dep['dep_ID'] ?></td>
                            <td><?= date('M j, Y', strtotime($dep['dep_Date'])) ?></td>
                            <td>
                                <?php
                                $typeColorMap = [
                                    'Bank'       => 'bg-green-lt text-green',
                                    'CreditCard' => 'bg-blue-lt text-blue',
                                    'BankDraft'  => 'bg-purple-lt text-purple',
                                ];
                                $badgeClass = $typeColorMap[$dep['dep_Type']] ?? 'bg-secondary-lt';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $dep['dep_Type'] ?></span>
                            </td>
                            <td><?= InputUtils::escapeHTML($dep['dep_Comment'] ?? '') ?></td>
                            <td class="text-center"><?= $dep['payment_count'] ?></td>
                            <td class="text-end fw-bold">KES <?= number_format($dep['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($dep['dep_Closed']): ?>
                                <span class="badge bg-green-lt text-green">Closed</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Open</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= $sRootPath ?>/DepositSlipEditor.php?DepositSlipID=<?= $dep['dep_ID'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Donut Chart
new window.ApexCharts(document.getElementById('donutChart'), {
    chart: { type: 'donut', height: 280 },
    series: <?= json_encode(array_map('floatval', $typeAmounts)) ?>,
    labels: <?= json_encode($typeLabels) ?>,
    colors: <?= json_encode(array_slice($typeColors, 0, count($typeLabels))) ?>,
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    tooltip: { y: { formatter: v => 'KES ' + v.toLocaleString('en-KE', {minimumFractionDigits: 2}) } },
}).render();

// Bar Chart
new window.ApexCharts(document.getElementById('barChart'), {
    chart: { type: 'bar', height: 280, toolbar: { show: true } },
    series: [
        { name: 'M-Pesa',      data: <?= json_encode($monthlyMpesa) ?> },
        { name: 'Cash',        data: <?= json_encode($monthlyCash) ?>  },
        { name: 'Card/Cheque', data: <?= json_encode($monthlyCard) ?>  },
    ],
    colors: ['#2fb344', '#f76707', '#4299e1'],
    xaxis: { categories: <?= json_encode($months) ?> },
    yaxis: {
        title: { text: 'Amount (KES)' },
        labels: { formatter: v => 'KES ' + v.toLocaleString() }
    },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
    stroke: { width: 0 },
    grid: { show: true, borderColor: '#e0e0e0' },
    legend: { position: 'top' },
    tooltip: { y: { formatter: v => 'KES ' + v.toLocaleString('en-KE', {minimumFractionDigits: 2}) } },
}).render();

// Search
document.getElementById('searchDeposits').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#depositsTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>