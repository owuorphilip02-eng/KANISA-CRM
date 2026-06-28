<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$con = Propel\Runtime\Propel::getConnection();
$sRootPath = SystemURLs::getRootPath();
$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

// Lazy expiry check — flag expired members
$con->query("
    UPDATE person_per 
    SET per_membership_status = 'expired'
    WHERE per_membership_expires < CURDATE()
    AND per_membership_status = 'active'
");

// Get expiring soon (≤5 days) and already expired
$stmtExpiring = $con->query("
    SELECT 
        per_ID, per_FirstName, per_LastName, per_CellPhone,
        per_membership_expires, per_membership_status,
        per_registration_amount,
        DATEDIFF(per_membership_expires, CURDATE()) as days_left
    FROM person_per
    WHERE per_membership_expires IS NOT NULL
    AND (
        per_membership_status = 'expired'
        OR (per_membership_status = 'active' AND DATEDIFF(per_membership_expires, CURDATE()) <= 5)
    )
    ORDER BY per_membership_expires ASC
");
$members = $stmtExpiring->fetchAll(PDO::FETCH_ASSOC);

$expiredCount  = count(array_filter($members, fn($m) => $m['per_membership_status'] === 'expired'));
$expiringCount = count(array_filter($members, fn($m) => $m['per_membership_status'] !== 'expired'));
?>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex align-items-center mb-3">
        <div>
            <h2 class="mb-0">Membership Expiry</h2>
            <p class="text-body-secondary mb-0">Members with expired or expiring memberships</p>
        </div>
        <div class="ms-auto">
            <a href="<?= $sRootPath ?>/people/dashboard" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Members
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-3">
        <div class="col-6 col-lg-3">
            <div class="card card-sm border-danger">
                <div class="card-body">
                    <div class="fw-bold text-danger fs-3"><?= $expiredCount ?></div>
                    <div class="text-body-secondary small">Expired</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm border-warning">
                <div class="card-body">
                    <div class="fw-bold text-warning fs-3"><?= $expiringCount ?></div>
                    <div class="text-body-secondary small">Expiring in ≤5 days</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-id-card me-2"></i>Members Requiring Action</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($members)): ?>
            <div class="empty py-5">
                <div class="empty-icon"><i class="fa-solid fa-circle-check fa-3x text-success"></i></div>
                <p class="empty-title text-success">All memberships are current!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Phone</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                            <th>Status</th>
                            <th>Renewal Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr id="row-<?= $m['per_ID'] ?>">
                            <td>
                                <a href="<?= $sRootPath ?>/people/view/<?= $m['per_ID'] ?>">
                                    <?= htmlspecialchars($m['per_FirstName'] . ' ' . $m['per_LastName']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($m['per_CellPhone'] ?? '—') ?></td>
                            <td><?= date('M j, Y', strtotime($m['per_membership_expires'])) ?></td>
                            <td>
                                <?php if ($m['per_membership_status'] === 'expired'): ?>
                                <span class="text-danger fw-bold">Expired</span>
                                <?php else: ?>
                                <span class="text-warning fw-bold"><?= $m['days_left'] ?> day<?= $m['days_left'] != 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($m['per_membership_status'] === 'expired'): ?>
                                <span class="badge bg-danger">Expired</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Expiring Soon</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" 
                                    class="form-control form-control-sm renewal-amount" 
                                    style="width:100px"
                                    data-id="<?= $m['per_ID'] ?>"
                                    value="<?= $m['per_registration_amount'] ?? '' ?>"
                                    placeholder="KES">
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success send-renewal-btn"
                                    data-id="<?= $m['per_ID'] ?>"
                                    data-phone="<?= htmlspecialchars($m['per_CellPhone'] ?? '') ?>"
                                    data-name="<?= htmlspecialchars($m['per_FirstName'] . ' ' . $m['per_LastName']) ?>">
                                    <i class="fa-solid fa-mobile-screen-button me-1"></i> Send Renewal
                                </button>
                                <span class="renewal-status-<?= $m['per_ID'] ?> ms-1 small"></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.send-renewal-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const personId = this.dataset.id;
        const phone    = this.dataset.phone;
        const name     = this.dataset.name;
        const amountInput = document.querySelector('.renewal-amount[data-id="' + personId + '"]');
        const amount   = amountInput ? amountInput.value : '';
        const statusEl = document.querySelector('.renewal-status-' + personId);

        if (!phone) {
            statusEl.innerHTML = '<span class="text-danger">No phone number!</span>';
            return;
        }
        if (!amount || parseInt(amount) < 1) {
            statusEl.innerHTML = '<span class="text-danger">Enter renewal amount!</span>';
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Sending...';
        statusEl.innerHTML = '<span class="text-info">STK Push sent...</span>';

        const formData = new FormData();
        formData.append('phone', phone);
        formData.append('amount', amount);
        formData.append('name', name);
        formData.append('person_id', personId);
        formData.append('payment_type', 'renewal');

        fetch('<?= $sRootPath ?>/Kenya/RegistrationStkPush.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                statusEl.innerHTML = '<span class="text-info">Waiting for PIN...</span>';
                pollRenewal(data.checkout_id, personId, this);
            } else {
                statusEl.innerHTML = '<span class="text-danger">' + data.message + '</span>';
                this.disabled = false;
                this.innerHTML = '<i class="fa-solid fa-mobile-screen-button me-1"></i> Send Renewal';
            }
        })
        .catch(() => {
            statusEl.innerHTML = '<span class="text-danger">Network error</span>';
            this.disabled = false;
            this.innerHTML = '<i class="fa-solid fa-mobile-screen-button me-1"></i> Send Renewal';
        });
    });
});

function pollRenewal(checkoutId, personId, btn) {
    let attempts = 0;
    const statusEl = document.querySelector('.renewal-status-' + personId);
    const interval = setInterval(() => {
        attempts++;
        fetch('<?= $sRootPath ?>/Kenya/RegistrationStkStatus.php?checkout_id=' + encodeURIComponent(checkoutId))
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                clearInterval(interval);
                statusEl.innerHTML = '<span class="text-success"><i class="fa-solid fa-check me-1"></i>Renewed! Receipt: ' + (data.receipt || 'N/A') + '</span>';
                btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Renewed';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-success');
                // Fade out row after 3 seconds
                setTimeout(() => {
                    const row = document.getElementById('row-' + personId);
                    if (row) row.style.opacity = '0.3';
                }, 3000);
            } else if (data.status === 'failed' || attempts >= 24) {
                clearInterval(interval);
                statusEl.innerHTML = '<span class="text-danger">Payment ' + (attempts >= 24 ? 'timed out' : 'failed') + '</span>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-mobile-screen-button me-1"></i> Try Again';
            }
        });
    }, 5000);
}
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>