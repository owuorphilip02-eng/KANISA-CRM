<?php
require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\Kenya\Mpesa\MpesaService;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;

$sPageTitle = 'M-Pesa Collect';
$sPageSubtitle = 'Send STK Push payment request';
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('M-Pesa Collect')],
]);

$funds = DonationFundQuery::create()->filterByActive('true')->orderByName()->find();

$response = null;
$error    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone       = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $amount      = (int) ($_POST['amount'] ?? 0);
    $fund        = $_POST['fund'] ?? 'Tithe';
    $member_name = trim($_POST['member_name'] ?? '');

    if (str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    }

    if (strlen($phone) !== 12 || $amount < 1) {
        $error = 'Please enter a valid phone number and amount.';
    } else {
        $response = MpesaService::stkPush(
            $phone, $amount,
            $member_name ?: 'KANISA',
            $fund . ' - ' . date('M Y')
        );
    }
}

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

    <div class="container-fluid">
        <div class="row">

            <!-- Form -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-status-top bg-success"></div>
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa-solid fa-mobile-screen-button me-2"></i>Request M-Pesa Payment (STK Push)</h5>
                    </div>
                    <div class="card-body">

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible mb-4">
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($response): ?>
                            <?php if (($response['ResponseCode'] ?? '') === '0'): ?>
                                <div class="alert alert-success alert-dismissible mb-4">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    <strong><i class="fa-solid fa-check-circle me-2"></i>STK Push Sent Successfully!</strong>
                                    <div class="text-muted small mt-1">Member will receive an M-Pesa PIN prompt on their phone.</div>
                                    <div class="text-muted small">Checkout ID: <code><?= htmlspecialchars($response['CheckoutRequestID']) ?></code></div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger alert-dismissible mb-4">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    <i class="fa-solid fa-circle-xmark me-2"></i><?= htmlspecialchars($response['ResponseDescription'] ?? 'Unknown error') ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <form method="post">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Member Name</label>
                                    <input type="text" name="member_name" class="form-control"
                                           placeholder="e.g. John Kamau"
                                           value="<?= htmlspecialchars($_POST['member_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" required
                                           placeholder="0712345678"
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    <div class="form-hint">Format: 07xxxxxxxx or 2547xxxxxxxx</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" min="1" class="form-control" required
                                           placeholder="e.g. 1000"
                                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Fund <span class="text-danger">*</span></label>
                                    <div class="row g-2">
                                        <?php foreach ($funds as $f):
                                            $selected = (($_POST['fund'] ?? 'Tithe') === $f->getName());
                                            ?>
                                            <div class="col-6 col-md-4 col-lg-3">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="fund" value="<?= htmlspecialchars($f->getName()) ?>" class="form-selectgroup-input" <?= $selected ? 'checked' : '' ?>>
                                                    <span class="form-selectgroup-label w-100 text-center">
                                                <?= htmlspecialchars($f->getName()) ?>
                                            </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-paper-plane me-1"></i>Send M-Pesa Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="col-lg-4">

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-status-top bg-info"></div>
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa-solid fa-circle-info me-2"></i>How STK Push Works</h5>
                    </div>
                    <div class="card-body">
                        <div class="steps steps-counter">
                            <div class="step-item">Enter the member's phone number and amount</div>
                            <div class="step-item">Member receives a PIN prompt on their phone</div>
                            <div class="step-item">Member enters their M-Pesa PIN to confirm</div>
                            <div class="step-item">Payment is automatically recorded in KanisaCRM</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa-solid fa-link me-2"></i>Quick Links</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?= SystemURLs::getRootPath() ?>/Kenya/MpesaTransactions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-receipt me-2 text-body-secondary"></i>View Transactions</span>
                            <i class="fa-solid fa-chevron-right text-body-secondary"></i>
                        </a>
                        <a href="<?= SystemURLs::getRootPath() ?>/finance/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-chart-line me-2 text-body-secondary"></i>Finance Dashboard</span>
                            <i class="fa-solid fa-chevron-right text-body-secondary"></i>
                        </a>
                        <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-archive me-2 text-body-secondary"></i>View Deposits</span>
                            <i class="fa-solid fa-chevron-right text-body-secondary"></i>
                        </a>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <strong>Sandbox Mode</strong><br>
                    <small>Currently connected to Safaricom <strong>sandbox</strong>. No real money moves. Switch to production when you have a live Paybill/Till number.</small>
                </div>

            </div>
        </div>
    </div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
