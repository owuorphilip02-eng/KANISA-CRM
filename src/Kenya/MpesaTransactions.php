<?php
require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\view\PageHeader;

$sPageTitle = 'M-Pesa Transactions';
$sPageSubtitle = 'Finance';
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('M-Pesa Transactions')],
]);

$pdo = new PDO('mysql:host=localhost;dbname=churchcrm_kenya', 'root', 'root123');

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_mpesa'])) {
    $amount  = (float) $_POST['amount'];
    $source  = $_POST['source'];
    $fundId  = (int) $_POST['fund_id'];
    $receipt = htmlspecialchars($_POST['receipt']);
    $phone   = htmlspecialchars($_POST['phone']);
    $date    = $_POST['date'] ?: date('Y-m-d');

    $dep = new Deposit();
    $dep->setDate($date);
    $dep->setComment('M-Pesa ' . $source . ' - Receipt: ' . $receipt);
    $dep->setEnteredby(1);
    $dep->setClosed(false);
    $dep->save();

    $plg = new Pledge();
    $plg->setFundid($fundId);
    $plg->setDepid($dep->getId());
    $plg->setAmount($amount);
    $plg->setMethod('MPESA');
    $plg->setComment('M-Pesa: ' . $receipt . ' from ' . $phone);
    $plg->setDate($date);
    $plg->setPledgeOrPayment('Payment');
    $plg->setDateLastEdited(date('Y-m-d'));
    $plg->save();

    $stmt = $pdo->prepare("UPDATE pledge_plg SET plg_mpesa_source = ? WHERE plg_plgID = ?");
    $stmt->execute([$source, $plg->getId()]);

    $flash = $source . ' payment of KES ' . number_format($amount, 2) . ' recorded successfully.';
}

$sql = "
    SELECT p.plg_plgID, p.plg_date, p.plg_amount, p.plg_comment,
           p.plg_PledgeOrPayment, p.plg_mpesa_source, f.fun_Name as fund_name
    FROM pledge_plg p
    LEFT JOIN donationfund_fun f ON p.plg_fundID = f.fun_ID
    WHERE p.plg_method = 'MPESA'
    ORDER BY p.plg_date DESC, p.plg_plgID DESC
";
$transactions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$groups = ['STK' => [], 'PAYBILL' => [], 'TILL' => [], 'OTHER' => []];
$totals = ['STK' => 0, 'PAYBILL' => 0, 'TILL' => 0, 'OTHER' => 0];
$fundTotals = [];
$grandTotal = 0;

foreach ($transactions as $t) {
    $src = $t['plg_mpesa_source'] ?: 'OTHER';
    if (!isset($groups[$src])) $src = 'OTHER';
    $groups[$src][] = $t;
    $totals[$src] += $t['plg_amount'];
    $fund = $t['fund_name'] ?? 'Unknown';
    $fundTotals[$fund] = ($fundTotals[$fund] ?? 0) + $t['plg_amount'];
    $grandTotal += $t['plg_amount'];
}

$funds = $pdo->query("SELECT fun_ID, fun_Name FROM donationfund_fun WHERE fun_Active = 'true' ORDER BY fun_Name")->fetchAll(PDO::FETCH_ASSOC);

function kes($amount) {
    return 'KES ' . number_format((float) $amount, 2);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mpesa_transactions_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Source', 'Receipt', 'Phone', 'Fund', 'Amount (KES)']);
    foreach ($transactions as $t) {
        preg_match('/M-Pesa: (\w+) from (\d+)/', $t['plg_comment'], $m);
        fputcsv($out, [$t['plg_date'], $t['plg_mpesa_source'] ?? 'OTHER', $m[1] ?? 'N/A', $m[2] ?? 'N/A', $t['fund_name'] ?? 'Unknown', $t['plg_amount']]);
    }
    fclose($out);
    exit;
}

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

    <div class="container-fluid">

        <?php if ($flash): ?>
            <div class="alert alert-success alert-dismissible mb-3">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row mb-3 g-2">
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-coins icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= kes($grandTotal) ?></div>
                                <div class="text-body-secondary">Total Received</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-warning text-white avatar rounded-circle"><i class="fa-solid fa-mobile-screen-button icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= kes($totals['STK']) ?></div>
                                <div class="text-body-secondary">STK Push (<?= count($groups['STK']) ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-primary text-white avatar rounded-circle"><i class="fa-solid fa-building-columns icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= kes($totals['PAYBILL']) ?></div>
                                <div class="text-body-secondary">Paybill (<?= count($groups['PAYBILL']) ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-info text-white avatar rounded-circle"><i class="fa-solid fa-store icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= kes($totals['TILL']) ?></div>
                                <div class="text-body-secondary">Till (<?= count($groups['TILL']) ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <!-- Fund Breakdown -->
            <div class="col-lg-4 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-status-top bg-success"></div>
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Breakdown by Fund</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($fundTotals)): ?>
                            <div class="empty py-4"><p class="empty-title">No fund data yet</p></div>
                        <?php else: ?>
                            <table class="table table-vcenter mb-0">
                                <tbody>
                                <?php foreach ($fundTotals as $fund => $total): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fund) ?></td>
                                        <td class="text-end fw-medium text-success"><?= kes($total) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Manual Entry Form -->
            <div class="col-lg-8 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-status-top bg-primary"></div>
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa-solid fa-pen me-2"></i>Record Paybill / Till Payment</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-body-secondary small mb-3">From M-Pesa statement — manual entry until live callback is connected.</p>
                        <form method="post">
                            <input type="hidden" name="manual_mpesa" value="1">
                            <div class="row g-3">
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Source</label>
                                    <select name="source" class="form-select" required>
                                        <option value="PAYBILL">Paybill</option>
                                        <option value="TILL">Till</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="number" name="amount" min="1" class="form-control" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Receipt No.</label>
                                    <input type="text" name="receipt" class="form-control" placeholder="QGT5XYZ" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="2547xxxxxxxx" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Fund</label>
                                    <select name="fund_id" class="form-select" required>
                                        <?php foreach ($funds as $f): ?>
                                            <option value="<?= $f['fun_ID'] ?>"><?= htmlspecialchars($f['fun_Name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="form-control">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Record Payment
                                    </button>
                                    <a href="?export=csv" class="btn btn-outline-secondary ms-2">
                                        <i class="fa-solid fa-file-csv me-1"></i>Export CSV
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-status-top bg-secondary"></div>
            <div class="card-header py-2">
                <h5 class="mb-0"><i class="fa-solid fa-receipt me-2"></i>All Transactions</h5>
            </div>
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-3 pt-2" id="mpesaTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-all">All (<?= count($transactions) ?>)</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-stk">STK Push (<?= count($groups['STK']) ?>)</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-paybill">Paybill (<?= count($groups['PAYBILL']) ?>)</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-till">Till (<?= count($groups['TILL']) ?>)</a></li>
                </ul>
                <div class="tab-content">
                    <?php
                    $tabs = ['all' => $transactions, 'stk' => $groups['STK'], 'paybill' => $groups['PAYBILL'], 'till' => $groups['TILL']];
                    foreach ($tabs as $key => $rows):
                        ?>
                        <div class="tab-pane fade <?= $key === 'all' ? 'show active' : '' ?>" id="tab-<?= $key ?>">
                            <?php if (empty($rows)): ?>
                                <div class="empty py-5"><p class="empty-title">No transactions yet</p></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-hover mb-0">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Source</th>
                                            <th>Receipt</th>
                                            <th>Phone</th>
                                            <th>Fund</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($rows as $t):
                                            preg_match('/M-Pesa: (\w+) from (\d+)/', $t['plg_comment'], $m);
                                            $receipt = $m[1] ?? 'N/A';
                                            $phone = $m[2] ?? 'N/A';
                                            $src = $t['plg_mpesa_source'] ?: 'OTHER';
                                            $badges = ['STK' => 'bg-warning', 'PAYBILL' => 'bg-primary', 'TILL' => 'bg-info', 'OTHER' => 'bg-secondary'];
                                            $badge = $badges[$src] ?? 'bg-secondary';
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($t['plg_date']) ?></td>
                                                <td><span class="badge <?= $badge ?>"><?= $src ?></span></td>
                                                <td><code><?= htmlspecialchars($receipt) ?></code></td>
                                                <td class="text-body-secondary"><?= htmlspecialchars($phone) ?></td>
                                                <td><span class="badge bg-secondary-lt text-secondary"><?= htmlspecialchars($t['fund_name'] ?? 'Unknown') ?></span></td>
                                                <td class="text-end fw-medium text-success">+<?= kes($t['plg_amount']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
