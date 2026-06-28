<?php
require_once __DIR__ . '/../Include/Config.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\Pledge;

$sRootPath = SystemURLs::getRootPath();
$pdo = new PDO('mysql:host=localhost;dbname=churchcrm_kenya', 'root', 'root123');

// Handle new deposit creation
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_deposit'])) {
    $comment = htmlspecialchars($_POST['comment']);
    $type    = $_POST['type'];
    $date    = $_POST['date'] ?: date('Y-m-d');

    $dep = new Deposit();
    $dep->setDate($date);
    $dep->setComment($comment);
    $dep->setType($type);
    $dep->setEnteredby(1);
    $dep->setClosed(false);
    $dep->save();

    $flash = 'Deposit #' . $dep->getId() . ' created successfully.';
}

// Handle close deposit
if (isset($_GET['close'])) {
    $depId = (int) $_GET['close'];
    $stmt = $pdo->prepare("UPDATE deposit_dep SET dep_Closed = 1 WHERE dep_ID = ?");
    $stmt->execute([$depId]);
    $flash = 'Deposit #' . $depId . ' closed.';
}

// Fetch all deposits with totals
$deposits = $pdo->query("
    SELECT d.dep_ID, d.dep_Date, d.dep_Comment, d.dep_Type, d.dep_Closed,
           COALESCE(SUM(p.plg_amount), 0) as total,
           COUNT(p.plg_plgID) as payment_count
    FROM deposit_dep d
    LEFT JOIN pledge_plg p ON p.plg_depID = d.dep_ID AND p.plg_PledgeOrPayment = 'Payment'
    GROUP BY d.dep_ID
    ORDER BY d.dep_Date DESC, d.dep_ID DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalDeposits  = count($deposits);
$openDeposits   = count(array_filter($deposits, fn($d) => !$d['dep_Closed']));
$closedDeposits = $totalDeposits - $openDeposits;
$grandTotal     = array_sum(array_column($deposits, 'total'));

function kes($amount) {
    return 'KES ' . number_format((float) $amount, ($amount == floor($amount)) ? 0 : 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposits — KanisaCRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
    </style>
</head>
<body class="flex h-screen bg-[#EAEFEA] text-gray-800 antialiased overflow-hidden">

<!-- SIDEBAR -->
<aside class="w-20 flex flex-col items-center py-8 space-y-6 bg-[#EAEFEA] border-r border-gray-200 shrink-0 overflow-y-auto">
    <div class="text-3xl font-bold pb-4">k.</div>
    <a href="<?= $sRootPath ?>/v2/dashboard" class="p-3 text-gray-500 hover:text-black transition" title="Dashboard"><i class="ph ph-house text-xl"></i></a>
    <a href="<?= $sRootPath ?>/event/calendars" class="p-3 text-gray-500 hover:text-black transition" title="Calendar"><i class="ph ph-calendar text-xl"></i></a>
    <a href="<?= $sRootPath ?>/people/list" class="p-3 text-gray-500 hover:text-black transition" title="People"><i class="ph ph-users text-xl"></i></a>
    <a href="<?= $sRootPath ?>/finance/" class="p-3 text-gray-500 hover:text-black transition" title="Finance"><i class="ph ph-chart-line-up text-xl"></i></a>
    <a href="<?= $sRootPath ?>/Kenya/Deposits.php" class="p-3 bg-black text-white rounded-full transition" title="Deposits"><i class="ph ph-archive text-xl"></i></a>
    <a href="<?= $sRootPath ?>/QueryList.php" class="p-3 text-gray-500 hover:text-black transition" title="Reports"><i class="ph ph-database text-xl"></i></a>
    <div class="mt-auto pt-8">
        <a href="<?= $sRootPath ?>/admin/" class="p-3 text-gray-500 hover:text-black transition" title="Admin"><i class="ph ph-gear text-xl"></i></a>
    </div>
</aside>

<main class="flex-1 flex flex-col p-8 overflow-y-auto">

    <!-- TOPNAV -->
    <header class="flex justify-between items-center mb-10">
        <div class="flex space-x-2 bg-white rounded-full p-1 shadow-sm">
            <a href="<?= $sRootPath ?>/v2/dashboard" class="px-6 py-2 rounded-full text-gray-600 hover:bg-gray-100 transition text-sm font-medium flex items-center gap-2"><i class="ph ph-pie-chart text-lg"></i> Dashboard</a>
            <a href="<?= $sRootPath ?>/finance/" class="px-6 py-2 rounded-full text-gray-600 hover:bg-gray-100 transition flex items-center gap-2 text-sm font-medium"><i class="ph ph-chart-line-up text-lg"></i> Finance</a>
            <a href="<?= $sRootPath ?>/Kenya/MpesaTransactions.php" class="px-6 py-2 rounded-full text-gray-600 hover:bg-gray-100 transition flex items-center gap-2 text-sm font-medium"><i class="ph ph-device-mobile text-lg"></i> M-Pesa</a>
            <button class="px-6 py-2 rounded-full bg-black text-white shadow-md flex items-center gap-2 text-sm font-medium">
                <i class="ph ph-archive text-lg"></i> Deposits
            </button>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-gray-600 hover:text-black transition relative">
                <i class="ph ph-bell text-xl"></i>
                <?php if ($openDeposits > 0): ?><span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span><?php endif; ?>
            </button>
            <img class="w-10 h-10 rounded-full object-cover shadow-sm" src="https://i.pravatar.cc/100?img=3" alt="Profile" />
        </div>
    </header>

    <!-- PAGE TITLE -->
    <div class="flex justify-between items-end mb-8">
        <div>
            <p class="text-sm text-gray-500 mb-1 flex items-center gap-2"><i class="ph ph-folder text-lg"></i> Finance &rarr; Deposits</p>
            <h1 class="text-4xl font-light tracking-tight text-gray-900">Deposit Listing</h1>
        </div>
        <button onclick="document.getElementById('newDepositModal').classList.remove('hidden')"
                class="px-5 py-2.5 bg-black text-white rounded-full text-sm font-medium hover:bg-gray-800 transition flex items-center gap-2">
            <i class="ph ph-plus"></i> New Deposit
        </button>
    </div>

    <?php if ($flash): ?>
    <div class="bg-[#A5C9A9] text-gray-900 rounded-2xl px-5 py-3 mb-6 text-sm font-medium flex items-center gap-2">
        <i class="ph ph-check-circle text-lg"></i> <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- STAT STRIP -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-4 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-[#A5C9A9] flex items-center justify-center"><i class="ph ph-coins text-lg"></i></span>
            <div><div class="text-xl font-semibold"><?= kes($grandTotal) ?></div><div class="text-xs text-gray-500">Total Received</div></div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-[#F0F2F5] flex items-center justify-center"><i class="ph ph-archive text-lg"></i></span>
            <div><div class="text-xl font-semibold"><?= $totalDeposits ?></div><div class="text-xs text-gray-500">Total Deposits</div></div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-[#F2F7A1] flex items-center justify-center"><i class="ph ph-lock-open text-lg"></i></span>
            <div><div class="text-xl font-semibold"><?= $openDeposits ?></div><div class="text-xs text-gray-500">Open</div></div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-[#DDEBDB] flex items-center justify-center"><i class="ph ph-lock text-lg"></i></span>
            <div><div class="text-xl font-semibold"><?= $closedDeposits ?></div><div class="text-xs text-gray-500">Closed</div></div>
        </div>
    </div>

    <!-- DEPOSITS TABLE -->
    <div class="bg-white rounded-3xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-medium flex items-center gap-2"><i class="ph ph-list-bullets"></i> All Deposits</h3>
            <div class="flex gap-2">
                <input type="text" id="searchInput" placeholder="Search deposits..."
                       class="bg-[#F0F2F5] rounded-2xl px-4 py-2 text-sm outline-none"
                       onkeyup="filterTable()">
            </div>
        </div>

        <?php if (empty($deposits)): ?>
            <div class="text-center py-12">
                <i class="ph ph-archive text-5xl text-gray-200 block mb-3"></i>
                <p class="text-gray-400 text-sm">No deposits found. Create your first deposit.</p>
            </div>
        <?php else: ?>
        <table class="w-full text-sm" id="depositsTable">
            <thead>
                <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
                    <th class="py-3 pr-4">#</th>
                    <th class="py-3 pr-4">Date</th>
                    <th class="py-3 pr-4">Comment</th>
                    <th class="py-3 pr-4">Type</th>
                    <th class="py-3 pr-4">Payments</th>
                    <th class="py-3 pr-4 text-right">Total</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deposits as $d): ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-[#F7F8FC] transition">
                    <td class="py-3 pr-4 font-mono text-gray-400 text-xs">#<?= $d['dep_ID'] ?></td>
                    <td class="py-3 pr-4"><?= htmlspecialchars($d['dep_Date']) ?></td>
                    <td class="py-3 pr-4 max-w-xs truncate"><?= htmlspecialchars($d['dep_Comment']) ?></td>
                    <td class="py-3 pr-4">
                        <span class="bg-[#F0F2F5] text-xs px-2 py-1 rounded-full"><?= htmlspecialchars($d['dep_Type']) ?></span>
                    </td>
                    <td class="py-3 pr-4 text-gray-500"><?= $d['payment_count'] ?> payments</td>
                    <td class="py-3 pr-4 text-right font-semibold <?= $d['total'] > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= kes($d['total']) ?>
                    </td>
                    <td class="py-3 pr-4">
                        <?php if ($d['dep_Closed']): ?>
                            <span class="bg-[#DDEBDB] text-green-700 text-xs px-3 py-1 rounded-full font-medium">Closed</span>
                        <?php else: ?>
                            <span class="bg-[#F2F7A1] text-yellow-700 text-xs px-3 py-1 rounded-full font-medium">Open</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <div class="flex items-center gap-2">
                            <a href="<?= $sRootPath ?>/DepositSlipEditor.php?DepositSlipID=<?= $d['dep_ID'] ?>"
                               class="w-7 h-7 bg-[#F0F2F5] rounded-full flex items-center justify-center hover:bg-[#E2E8F4] transition" title="Edit">
                                <i class="ph ph-pencil text-xs"></i>
                            </a>
                            <?php if (!$d['dep_Closed']): ?>
                            <a href="?close=<?= $d['dep_ID'] ?>"
                               onclick="return confirm('Close deposit #<?= $d['dep_ID'] ?>?')"
                               class="w-7 h-7 bg-[#F0F2F5] rounded-full flex items-center justify-center hover:bg-[#DDEBDB] transition" title="Close">
                                <i class="ph ph-lock text-xs"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</main>

<!-- NEW DEPOSIT MODAL -->
<div id="newDepositModal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-3xl p-8 shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-semibold text-lg">New Deposit</h3>
            <button onclick="document.getElementById('newDepositModal').classList.add('hidden')"
                    class="w-8 h-8 bg-[#F0F2F5] rounded-full flex items-center justify-center hover:bg-gray-200 transition">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <form method="post" class="space-y-4">
            <input type="hidden" name="new_deposit" value="1">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">Comment / Description</label>
                <input type="text" name="comment" class="w-full bg-[#F0F2F5] rounded-2xl px-4 py-3 text-sm outline-none" placeholder="e.g. Sunday Service Offering" required>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">Type</label>
                <select name="type" class="w-full bg-[#F0F2F5] rounded-2xl px-4 py-3 text-sm outline-none">
                    <option value="Bank">Bank</option>
                    <option value="Cash">Cash</option>
                    <option value="MPESA">M-Pesa</option>
                    <option value="Cheque">Cheque</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full bg-[#F0F2F5] rounded-2xl px-4 py-3 text-sm outline-none">
            </div>
            <button type="submit" class="w-full bg-black text-white rounded-2xl py-3 text-sm font-medium hover:bg-gray-800 transition flex items-center justify-center gap-2">
                <i class="ph ph-plus"></i> Create Deposit
            </button>
        </form>
    </div>
</div>

<script>
function filterTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#depositsTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>