<?php
use ChurchCRM\dto\SystemURLs;
$sRootPath = SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-xl py-4">

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Year</label>
          <select class="form-select" id="filterYear">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
              <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-auto">
          <button class="btn btn-outline-success" id="btnExport"><i class="fa-solid fa-file-excel me-1"></i>Export CSV</button>
          <button class="btn btn-outline-danger ms-2" id="btnPrint"><i class="fa-solid fa-print me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="row mb-4 g-3">
    <div class="col-md-4">
      <div class="card card-sm border-success">
        <div class="card-body text-center">
          <div class="text-success fw-bold fs-5">Total Income (YTD)</div>
          <div class="h3 mt-1" id="ytdIncome">KES 0.00</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm border-danger">
        <div class="card-body text-center">
          <div class="text-danger fw-bold fs-5">Total Expenses (YTD)</div>
          <div class="h3 mt-1" id="ytdExpense">KES 0.00</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm" id="balanceCard">
        <div class="card-body text-center">
          <div class="fw-bold fs-5" id="balanceLabel">Net Balance</div>
          <div class="h3 mt-1" id="ytdBalance">KES 0.00</div>
          <div class="small" id="balanceStatus"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Income vs Expenses Chart -->
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Monthly Income vs Expenses</h5></div>
    <div class="card-body"><div id="incomeExpenseChart" style="min-height:300px;"></div></div>
  </div>

  <!-- Running Balance Chart -->
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Running Balance</h5></div>
    <div class="card-body"><div id="runningChart" style="min-height:250px;"></div></div>
  </div>

  <!-- Fund Balance Table -->
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Fund-by-Fund Balance</h5></div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0" id="fundTable">
        <thead>
          <tr><th>Fund</th><th class="text-end">Income</th><th class="text-end">Expenses</th><th class="text-end">Balance</th><th>Status</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

<script>
const rootPath = '<?= $sRootPath ?>';
let ieChart = null, runChart = null;

function fmt(n) {
  return 'KES ' + parseFloat(n).toLocaleString('en-KE', {minimumFractionDigits: 2});
}

function loadData() {
  const year = document.getElementById('filterYear').value;

  fetch(`${rootPath}/finance/balance/data?year=${year}&_=${Date.now()}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('ytdIncome').textContent  = fmt(data.ytdIncome);
      document.getElementById('ytdExpense').textContent = fmt(data.ytdExpense);
      document.getElementById('ytdBalance').textContent = fmt(Math.abs(data.ytdBalance));

      const balCard   = document.getElementById('balanceCard');
      const balLabel  = document.getElementById('balanceLabel');
      const balStatus = document.getElementById('balanceStatus');
      balCard.className  = 'card card-sm border-' + (data.ytdBalance >= 0 ? 'success' : 'danger');
      balLabel.className = 'fw-bold fs-5 text-' + (data.ytdBalance >= 0 ? 'success' : 'danger');
      document.getElementById('ytdBalance').className = 'h3 mt-1 text-' + (data.ytdBalance >= 0 ? 'success' : 'danger');
      balStatus.textContent = data.ytdBalance >= 0 ? '✅ Surplus' : '⚠️ Deficit';

      const months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const income  = months.map((_, i) => data.monthlyIncome[i+1]  || 0);
      const expense = months.map((_, i) => data.monthlyExpense[i+1] || 0);
      const balance = months.map((_, i) => data.monthlyBalance[i+1] || 0);
      const running = months.map((_, i) => data.runningBalance[i+1] || 0);

      // Income vs Expense chart
      if (ieChart) { ieChart.destroy(); }
      ieChart = new window.ApexCharts(document.getElementById('incomeExpenseChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: true } },
        series: [
          { name: 'Income',  data: income  },
          { name: 'Expense', data: expense },
          { name: 'Net',     data: balance, type: 'line' },
        ],
        colors: ['#2fb344', '#d63939', '#457b9d'],
        xaxis: { categories: months },
        yaxis: { title: { text: 'Amount (KES)' } },
        stroke: { curve: 'smooth', width: [0, 0, 2] },
        plotOptions: { bar: { columnWidth: '50%' } },
        grid: { show: true, borderColor: '#e0e0e0' },
        legend: { position: 'top' },
        tooltip: { y: { formatter: v => fmt(v) } },
      });
      ieChart.render();

      // Running balance chart
      if (runChart) { runChart.destroy(); }
      const runColor = running[running.length-1] >= 0 ? '#2fb344' : '#d63939';
      runChart = new window.ApexCharts(document.getElementById('runningChart'), {
        chart: { type: 'area', height: 250, toolbar: { show: true } },
        series: [{ name: 'Cumulative Balance', data: running }],
        colors: [runColor],
        xaxis: { categories: months },
        yaxis: { title: { text: 'Amount (KES)' } },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        grid: { show: true, borderColor: '#e0e0e0' },
        tooltip: { y: { formatter: v => fmt(v) } },
      });
      runChart.render();

      // Fund table
      const tbody = document.querySelector('#fundTable tbody');
      tbody.innerHTML = '';
      if (!data.fundBalance || data.fundBalance.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No fund data found</td></tr>';
      } else {
        data.fundBalance.forEach(f => {
          const surplus = f.balance >= 0;
          tbody.innerHTML += `<tr>
            <td><strong>${f.fund}</strong></td>
            <td class="text-end text-success">${fmt(f.income)}</td>
            <td class="text-end text-danger">${fmt(f.expense)}</td>
            <td class="text-end fw-bold ${surplus ? 'text-success' : 'text-danger'}">${fmt(Math.abs(f.balance))}</td>
            <td><span class="badge ${surplus ? 'bg-success' : 'bg-danger'}">${surplus ? 'Surplus' : 'Deficit'}</span></td>
          </tr>`;
        });
      }
    });
}

document.getElementById('filterYear').addEventListener('change', loadData);
document.getElementById('btnPrint').addEventListener('click', () => window.print());
document.getElementById('btnExport').addEventListener('click', () => {
  const year = document.getElementById('filterYear').value;
  const rows = [['Fund','Income','Expenses','Balance','Status']];
  document.querySelectorAll('#fundTable tbody tr').forEach(tr => {
    rows.push([...tr.querySelectorAll('td')].map(td => td.textContent.trim()));
  });
  const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'}));
  a.download = `balance-analytics-${year}.csv`;
  a.click();
});

loadData();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>