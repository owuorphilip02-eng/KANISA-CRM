<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// DB connection
$db = new mysqli("localhost", "root", "root123", "churchcrm_kenya");

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name       = $db->real_escape_string($_POST['name']);
    $category   = $db->real_escape_string($_POST['category']);
    $description = $db->real_escape_string($_POST['description']);
    $quantity   = (int)$_POST['quantity'];
    $condition  = $db->real_escape_string($_POST['condition_status']);
    $location   = $db->real_escape_string($_POST['location']);
    $notes      = $db->real_escape_string($_POST['notes']);
    $added_by   = AuthenticationManager::getCurrentUser()->getId();
    $db->query("INSERT INTO inventory_items (name, category, description, quantity, condition_status, location, notes, added_by)
                VALUES ('$name','$category','$description',$quantity,'$condition','$location','$notes',$added_by)");
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    $db->query("DELETE FROM inventory_items WHERE id=$id");
}

// Fetch all items
$result = $db->query("SELECT * FROM inventory_items ORDER BY date_added DESC");
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

// Counts per category
$counts = ['Equipment' => 0, 'Books' => 0, 'Vehicles' => 0, 'Supplies' => 0];
foreach ($items as $item) {
    $counts[$item['category']] += $item['quantity'];
}
?>

    <div class="container-fluid">

        <!-- Stat Cards -->
        <div class="row mb-3 g-2">
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-primary text-white avatar rounded-circle"><i class="fa-solid fa-plug icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= $counts['Equipment'] ?></div>
                                <div class="text-body-secondary">Equipment</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-book icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= $counts['Books'] ?></div>
                                <div class="text-body-secondary">Books</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-warning text-white avatar rounded-circle"><i class="fa-solid fa-car icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= $counts['Vehicles'] ?></div>
                                <div class="text-body-secondary">Vehicles</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="bg-info text-white avatar rounded-circle"><i class="fa-solid fa-box icon"></i></span></div>
                            <div class="col">
                                <div class="fw-medium"><?= $counts['Supplies'] ?></div>
                                <div class="text-body-secondary">Supplies</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Item Form -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-status-top bg-info"></div>
            <div class="card-header py-2">
                <h5 class="mb-0"><i class="fa-solid fa-plus me-2"></i>Add Inventory Item</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Projector">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">— Select —</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Books">Books</option>
                                <option value="Vehicles">Vehicles</option>
                                <option value="Supplies">Supplies</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Condition</label>
                            <select name="condition_status" class="form-select">
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Church Hall">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Brief description">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Any extra notes">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-status-top bg-secondary"></div>
            <div class="card-header py-2">
                <h5 class="mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Inventory Items</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="empty py-5">
                        <div class="empty-icon"><i class="fa-solid fa-boxes-stacked fa-3x text-muted"></i></div>
                        <p class="empty-title mt-3">No items yet</p>
                        <p class="empty-subtitle text-muted">Add your first inventory item above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Condition</th>
                                <th>Location</th>
                                <th>Date Added</th>
                                <th>Notes</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($item['name']) ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'Equipment' => 'bg-primary-lt text-primary',
                                            'Books'     => 'bg-success-lt text-success',
                                            'Vehicles'  => 'bg-warning-lt text-warning',
                                            'Supplies'  => 'bg-info-lt text-info',
                                        ];
                                        $cls = $badges[$item['category']] ?? 'bg-secondary-lt';
                                        ?>
                                        <span class="badge <?= $cls ?>"><?= $item['category'] ?></span>
                                    </td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>
                                        <?php
                                        $cond = ['Good' => 'bg-success', 'Fair' => 'bg-warning', 'Poor' => 'bg-danger'];
                                        $cc = $cond[$item['condition_status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $cc ?>"><?= $item['condition_status'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($item['location'] ?? '—') ?></td>
                                    <td><?= $item['date_added'] ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($item['notes'] ?? '') ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this item?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-ghost-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<?php
$db->close();
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
