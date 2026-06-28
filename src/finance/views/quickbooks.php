<?php
use ChurchCRM\dto\SystemURLs;
$sRootPath = SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-xl py-4">

  <!-- Upload Card -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0"><i class="fa-solid fa-file-import me-2"></i>Import QuickBooks File</h4>
    </div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Select File <span class="text-muted">(CSV or QBO)</span></label>
          <input type="file" class="form-control" id="qbfile" accept=".csv,.qbo">
        </div>
        <div class="col-md-3">
          <label class="form-label">Filter Year</label>
          <select class="form-select" id="filterYear">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
              <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Period</label>
          <select class="form-select" id="filterPeriod">
            <option value="yearly">Yearly</option>
            <option value="monthly">Monthly</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Month</label>
          <select class="form-select" id="filterMonth">
            <?php
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            foreach ($months as $i => $m):
            ?>
              <option value="<?= $i+1 ?>" <?= ($i+1) == date('n') ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" id="btnImport"><i class="fa-solid fa-upload me-1"></i>Import File</button>
        <button class="btn btn-outline-secondary" id="btnRefresh"><i class="fa-solid fa-rotate me-1"></i>Refresh Summary</button>
        <button class="btn btn-outline-success" id="btnExcel"><i class="fa-solid fa-file-excel me-1"></i>Export CSV</button>
        <button class="btn btn-outline-danger" id="btnPrint"><i class="fa-solid fa-print me-1"></i>Print</button>
      </div>
      <div id="importAlert" class="mt-3 d-none"></div>
    </div>
  </div>

  <!-- P&L Stat Cards -->
  <div class="row mb-4 g-3">
    <div class="col-md-4">
      <div class="card card-sm border-success">
        <div class="card-body text-center">
          <div class="text-success fw-bold fs-5">Total Income</div>
          <div class="h3 mt-1" id="totalIncome">KES 0.00</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm border-danger">
        <div class="card-body text-center">
          <div class="text-danger fw-bold fs-5">Total Expenses</div>
          <div class="h3 mt-1" id="totalExpense">KES 0.00</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm border-primary">
        <div class="card-body text-center">
          <div class="text-primary fw-bold fs-5">Net Balance</div>
          <div class="h3 mt-1" id="netBalance">KES 0.00</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4 g-3">
    <!-- Monthly Chart -->
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Monthly Income vs Expenses</h5></div>
        <div class="card-body"><div id="monthlyChart" style="min-height:300px;"></div></div>
      </div>
    </div>
    <!-- Fund Breakdown -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Fund Breakdown</h5></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0" id="fundTable">
            <thead><tr><th>Fund</th><th>Income</th><th>Expense</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Recent Transactions</h5></div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0" id="txTable">
        <thead>
          <tr>
            <th>Date</th><th>Type</th><th>Account</th><th>Name</th>
            <th>Memo</th><th>Fund</th><th class="text-end">Amount</th><th>Category</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

<script>
const rootPath = '<?= $sRootPath ?>';
let monthlyChart = null;

function fmt(n) {
  return 'KES ' + parseFloat(n).toLocaleString('en-KE', {minimumFractionDigits: 2});
}

function loadData() {
  const year   = document.getElementById('filterYear').value;
  const period = document.getElementById('filterPeriod').value;
  const month  = document.getElementById('filterMonth').value;

  fetch(`${rootPath}/finance/quickbooks/data?year=${year}&period=${period}&month=${month}&_=${Date.now()}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('totalIncome').textContent  = fmt(data.pl.income || 0);
      document.getElementById('totalExpense').textContent = fmt(data.pl.expense || 0);
      const bal = document.getElementById('netBalance');
      bal.textContent = fmt(data.balance || 0);
      bal.className = 'h3 mt-1 ' + (data.balance >= 0 ? 'text-success' : 'text-danger');

      // Fund table
      const tbody = document.querySelector('#fundTable tbody');
      tbody.innerHTML = '';
      if (!data.funds || Object.keys(data.funds).length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No fund data</td></tr>';
      } else {
        for (const [fund, vals] of Object.entries(data.funds)) {
          tbody.innerHTML += `<tr>
            <td>${fund}</td>
            <td class="text-success">${fmt(vals.income || 0)}</td>
            <td class="text-danger">${fmt(vals.expense || 0)}</td>
          </tr>`;
        }
      }

      // Monthly chart
      const months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const income  = months.map((_, i) => data.monthly[i+1]?.income  || 0);
      const expense = months.map((_, i) => data.monthly[i+1]?.expense || 0);

      if (monthlyChart) { monthlyChart.destroy(); }
      monthlyChart = new window.ApexCharts(document.getElementById('monthlyChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: true } },
        series: [
          { name: 'Income',  data: income  },
          { name: 'Expense', data: expense },
        ],
        colors: ['#2fb344', '#d63939'],
        xaxis: { categories: months },
        yaxis: { title: { text: 'Amount (KES)' } },
        stroke: { curve: 'smooth', width: 0 },
        plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
        grid: { show: true, borderColor: '#e0e0e0' },
        legend: { position: 'top' },
        tooltip: { y: { formatter: v => fmt(v) } },
      });
      monthlyChart.render();

      // Transactions table
      const txBody = document.querySelector('#txTable tbody');
      txBody.innerHTML = '';
      if (!data.transactions || data.transactions.length === 0) {
        txBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No transactions found</td></tr>';
      } else {
        data.transactions.forEach(tx => {
          txBody.innerHTML += `<tr>
            <td>${tx.transaction_date}</td>
            <td>${tx.transaction_type}</td>
            <td>${tx.account}</td>
            <td>${tx.name}</td>
            <td>${tx.memo}</td>
            <td>${tx.fund}</td>
            <td class="text-end fw-bold ${tx.category === 'income' ? 'text-success' : 'text-danger'}">${fmt(tx.amount)}</td>
            <td><span class="badge ${tx.category === 'income' ? 'bg-success' : 'bg-danger'}">${tx.category}</span></td>
          </tr>`;
        });
      }
    });
}

// Import
document.getElementById('btnImport').addEventListener('click', function () {
  const file = document.getElementById('qbfile').files[0];
  if (!file) { alert('Please select a CSV or QBO file first.'); return; }

  const fd = new FormData();
  fd.append('qbfile', file);

  this.disabled = true;
  this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Importing...';

  fetch(`${rootPath}/finance/quickbooks/import`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      const alert = document.getElementById('importAlert');
      alert.className = 'mt-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
      alert.textContent = data.success
        ? `✅ Imported ${data.imported} transactions successfully.`
        : `❌ Import failed: ${data.error}`;
      alert.classList.remove('d-none');
      if (data.success) loadData();
    })
    .finally(() => {
      this.disabled = false;
      this.innerHTML = '<i class="fa-solid fa-upload me-1"></i>Import File';
    });
});

document.getElementById('btnRefresh').addEventListener('click', loadData);
document.getElementById('filterYear').addEventListener('change', loadData);
document.getElementById('filterPeriod').addEventListener('change', loadData);
document.getElementById('filterMonth').addEventListener('change', loadData);
document.getElementById('btnPrint').addEventListener('click', () => window.print());

document.getElementById('btnExcel').addEventListener('click', () => {
  const year = document.getElementById('filterYear').value;
  const rows = [['Date','Type','Account','Name','Memo','Fund','Amount','Category']];
  document.querySelectorAll('#txTable tbody tr').forEach(tr => {
    rows.push([...tr.querySelectorAll('td')].map(td => td.textContent.trim()));
  });
  const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'}));
  a.download = `quickbooks-summary-${year}.csv`;
  a.click();
});

loadData();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>