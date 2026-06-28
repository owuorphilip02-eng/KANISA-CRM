<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
$currentUserId = AuthenticationManager::getCurrentUser()->getPersonId();
$con = Propel\Runtime\Propel::getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
    $amount = (float) $_POST['amount'];
    $description = InputUtils::sanitizeText($_POST['description']);
    $fund_id = (int) ($_POST['fund_id'] ?? 0);
    $notes = InputUtils::sanitizeText($_POST['notes'] ?? '');

    try {
        $stmt = $con->prepare("
            INSERT INTO fin_requisitions (amount, description, requested_by, requested_date, fund_id, notes)
            VALUES (?, ?, ?, CURDATE(), ?, ?)
        ");
        $result = $stmt->execute([$amount, $description, $currentUserId, $fund_id ?: null, $notes]);
        if ($result) {
            $successMsg = 'Requisition submitted successfully. Awaiting admin approval.';
        } else {
            $errorMsg = 'Insert failed: ' . implode(', ', $stmt->errorInfo());
        }
    } catch (\Exception $e) {
        $errorMsg = 'Error: ' . $e->getMessage();
    }
}

    if ($action === 'approve' && $isAdmin) {
        $id = (int) $_POST['id'];
        $stmt = $con->prepare("
            UPDATE fin_requisitions
            SET status = 'approved', approved_by = ?, approved_date = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([$currentUserId, $id]);
        $successMsg = 'Requisition approved.';
    }

    if ($action === 'reject' && $isAdmin) {
        $id = (int) $_POST['id'];
        $stmt = $con->prepare("
            UPDATE fin_requisitions SET status = 'rejected' WHERE id = ?
        ");
        $stmt->execute([$id]);
        $successMsg = 'Requisition rejected.';
    }
}

// Fetch requisitions
$stmtAll = $con->query("
    SELECT r.*,
           u.usr_UserName as requester_name,
           f.fun_Name as fund_name
    FROM fin_requisitions r
    LEFT JOIN user_usr u ON r.requested_by = u.usr_per_ID
    LEFT JOIN donationfund_fun f ON r.fund_id = f.fun_ID
    ORDER BY r.created_at DESC
");
$requisitions = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Fetch funds for dropdown
$funds = $con->query("SELECT fun_ID, fun_Name FROM donationfund_fun WHERE fun_Active = 'true' ORDER BY fun_Name")->fetchAll(PDO::FETCH_ASSOC);

$sRootPath = SystemURLs::getRootPath();
?>

<div class="container-fluid">
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= $errorMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3">
        <i class="fa-solid fa-check-circle me-2"></i><?= $successMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex align-items-center mb-3">
        <div>
            <h2 class="mb-0">Requisitions</h2>
            <p class="text-body-secondary mb-0">Manage withdrawal requests and approvals</p>
        </div>
        <div class="ms-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequisitionModal">
                <i class="fa-solid fa-plus me-1"></i> New Requisition
            </button>
            <a href="<?= $sRootPath ?>/finance/requisitions/report" class="btn btn-outline-secondary ms-2">
                <i class="fa-solid fa-file-invoice me-1"></i> Generate Report
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <?php
    $pending  = array_filter($requisitions, fn($r) => $r['status'] === 'pending');
    $approved = array_filter($requisitions, fn($r) => $r['status'] === 'approved');
    $rejected = array_filter($requisitions, fn($r) => $r['status'] === 'rejected');
    $totalApprovedAmount = array_sum(array_column(iterator_to_array((function() use ($approved) { foreach($approved as $r) yield $r; })()), 'amount'));
    $totalApprovedAmount = array_sum(array_column($approved, 'amount'));
    ?>
    <div class="row mb-3">
        <div class="col-4">
            <div class="card card-sm border-warning">
                <div class="card-body">
                    <div class="fw-bold text-warning"><?= count($pending) ?></div>
                    <div class="text-body-secondary small">Pending Approval</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card card-sm border-success">
                <div class="card-body">
                    <div class="fw-bold text-success">KES <?= number_format($totalApprovedAmount, 2) ?></div>
                    <div class="text-body-secondary small">Total Approved</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card card-sm border-danger">
                <div class="card-body">
                    <div class="fw-bold text-danger"><?= count($rejected) ?></div>
                    <div class="text-body-secondary small">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requisitions Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-list me-2"></i>All Requisitions</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Fund</th>
                            <th>Requested By</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <?php if ($isAdmin): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requisitions)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-body-secondary py-4">
                                No requisitions yet.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($requisitions as $req): ?>
                        <tr>
                            <td><?= $req['id'] ?></td>
                            <td><?= date('M j, Y', strtotime($req['requested_date'])) ?></td>
                            <td>
                                <div><?= htmlspecialchars($req['description']) ?></div>
                                <?php if ($req['notes']): ?>
                                <small class="text-body-secondary"><?= htmlspecialchars($req['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($req['fund_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($req['requester_name'] ?? '—') ?></td>
                            <td class="text-end fw-bold">KES <?= number_format($req['amount'], 2) ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin && $req['status'] === 'pending'): ?>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Approve this requisition?')">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" class="d-inline ms-1">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                    <button class="btn btn-sm btn-danger" onclick="return confirm('Reject this requisition?')">
                                        <i class="fa-solid fa-times"></i> Reject
                                    </button>
                                </form>
                            </td>
                            <?php elseif ($isAdmin): ?>
                            <td><span class="text-body-secondary small">—</span></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Requisition Modal -->
<div class="modal fade" id="newRequisitionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>New Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount (KES) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="1" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required placeholder="What is this withdrawal for?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fund</label>
                        <select name="fund_id" class="form-select">
                            <option value="">— Select Fund —</option>
                            <?php foreach ($funds as $fund): ?>
                            <option value="<?= $fund['fun_ID'] ?>"><?= htmlspecialchars($fund['fun_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Requisition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
