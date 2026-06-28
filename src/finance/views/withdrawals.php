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
      <div class="card card-sm border-danger">
        <div class="card-body text-center">
          <div class="text-danger fw-bold fs-5">Total Withdrawn (YTD)</div>
          <div class="h3 mt-1" id="ytdTotal">KES 0.00</div>
          <div class="text-muted small" id="ytdCount">0 transactions</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm border-warning">
        <div class="card-body text-center">
          <div class="text-warning fw-bold fs-5">Monthly Average</div>
          <div class="h3 mt-1" id="monthlyAvg">KES 0.00</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-sm border-info">
        <div class="card-body text-center">
          <div class="text-info fw-bold fs-5">Status Breakdown</div>
          <div class="mt-2" id="statusBadges"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4 g-3">
    <!-- Monthly Chart -->
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Monthly Withdrawals</h5></div>
        <div class="card-body"><div id="monthlyChart" style="min-height:300px;"></div></div>
      </div>
    </div>
    <!-- Fund Breakdown -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">By Fund</h5></div>
        <div class="card-body"><div id="fundChart" style="min-height:300px;"></div></div>
      </div>
    </div>
  </div>

  <!-- Top Withdrawals -->
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Top 10 Withdrawals</h5></div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0" id="topTable">
        <thead>
          <tr><th>#</th><th>Date</th><th>Description</th><th>Fund</th><th>Requested By</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

<script>
const rootPath = '<?= $sRootPath ?>';
let monthlyChart = null, fundChart = null;

function fmt(n) {
  return 'KES ' + parseFloat(n).toLocaleString('en-KE', {minimumFractionDigits: 2});
}

function loadData() {
  const year = document.getElementById('filterYear').value;

  fetch(`${rootPath}/finance/withdrawals/data?year=${year}&_=${Date.now()}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('ytdTotal').textContent  = fmt(data.ytd.total || 0);
      document.getElementById('ytdCount').textContent  = (data.ytd.count || 0) + ' transactions';
      document.getElementById('monthlyAvg').textContent = fmt(data.avg.avg || 0);

      const statusColors = { pending: '#f59f00', approved: '#2fb344', rejected: '#d63939' };
      document.getElementById('statusBadges').innerHTML = data.statuses.map(s =>
        `<span class="badge me-1" style="background:${statusColors[s.status] || '#666'}">${s.status}: ${s.count}</span>`
      ).join('');

      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const mData  = months.map((_, i) => data.monthly[i+1] || 0);

      // Monthly bar chart
      if (monthlyChart) { monthlyChart.destroy(); }
      monthlyChart = new window.ApexCharts(document.getElementById('monthlyChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: true } },
        series: [{ name: 'Withdrawals (KES)', data: mData }],
        colors: ['#d63939'],
        xaxis: { categories: months },
        yaxis: { title: { text: 'Amount (KES)' } },
        stroke: { curve: 'smooth', width: 0 },
        plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
        grid: { show: true, borderColor: '#e0e0e0' },
        tooltip: { y: { formatter: v => fmt(v) } },
      });
      monthlyChart.render();

      // Fund doughnut chart
      if (fundChart) { fundChart.destroy(); }
      if (data.funds && data.funds.length > 0) {
        fundChart = new window.ApexCharts(document.getElementById('fundChart'), {
          chart: { type: 'donut', height: 300 },
          series: data.funds.map(f => parseFloat(f.total)),
          labels: data.funds.map(f => f.fund),
          colors: ['#e63946','#f4a261','#2a9d8f','#457b9d','#8338ec','#fb5607','#06d6a0','#118ab2'],
          legend: { position: 'bottom' },
          tooltip: { y: { formatter: v => fmt(v) } },
        });
        fundChart.render();
      } else {
        document.getElementById('fundChart').innerHTML = '<div class="text-center text-muted py-5">No fund data</div>';
      }

      // Top table
      const tbody = document.querySelector('#topTable tbody');
      tbody.innerHTML = '';
      if (!data.top || data.top.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No approved withdrawals found</td></tr>';
      } else {
        data.top.forEach((r, i) => {
          tbody.innerHTML += `<tr>
            <td>${i+1}</td>
            <td>${r.approved_date}</td>
            <td>${r.description}</td>
            <td>${r.fund}</td>
            <td>${r.requester || '—'}</td>
            <td class="text-end fw-bold text-danger">${fmt(r.amount)}</td>
          </tr>`;
        });
      }
    });
}

document.getElementById('filterYear').addEventListener('change', loadData);
document.getElementById('btnPrint').addEventListener('click', () => window.print());
document.getElementById('btnExport').addEventListener('click', () => {
  const year = document.getElementById('filterYear').value;
  const rows = [['#','Date','Description','Fund','Requested By','Amount']];
  document.querySelectorAll('#topTable tbody tr').forEach(tr => {
    rows.push([...tr.querySelectorAll('td')].map(td => td.textContent.trim()));
  });
  const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'}));
  a.download = `withdrawals-${year}.csv`;
  a.click();
});

loadData();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>