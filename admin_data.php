<?php
session_start();
include("db.php");
include("admin-header.php");

// 平台抽成比例：每筆訂單營收的 40%
$commission_rate = 0.4;

// ==========================================================================
// 1. KPI 數據（今日平台營收 / 今日平台抽成 / 平台使用者總數 / 平台商家總數）
// ==========================================================================
$sql_today = "SELECT SUM(revenue) as rev, SUM(order_count) as ord 
              FROM store_daily_stats WHERE record_date = CURDATE()";
$res_today = mysqli_query($conn, $sql_today);
$today_data = mysqli_fetch_assoc($res_today);
$today_revenue    = ($today_data && $today_data['rev'] !== null) ? (int)$today_data['rev'] : 0;
$today_commission = round($today_revenue * $commission_rate);

$sql_users_count = "SELECT COUNT(*) as cnt FROM consumer";
$res = mysqli_query($conn, $sql_users_count);
$total_users = (int)mysqli_fetch_assoc($res)['cnt'];

$sql_stores_count = "SELECT COUNT(*) as cnt FROM store";
$res = mysqli_query($conn, $sql_stores_count);
$total_stores = (int)mysqli_fetch_assoc($res)['cnt'];


// ==========================================================================
// 2. 平台每日營收趨勢（年 / 月 / 週 切換，全平台加總）
// ==========================================================================
$revenue_year_data = [['年份', '總營收']];
$sql_rev_year = "SELECT YEAR(record_date) as period, SUM(revenue) as total 
                  FROM store_daily_stats GROUP BY period ORDER BY period ASC";
$res = mysqli_query($conn, $sql_rev_year);
while ($row = mysqli_fetch_assoc($res)) {
    $revenue_year_data[] = [(string)$row['period'], (int)$row['total']];
}

$revenue_month_data = [['月份', '總營收']];
$sql_rev_month = "SELECT MONTH(record_date) as period, SUM(revenue) as total 
                   FROM store_daily_stats GROUP BY period ORDER BY period ASC";
$res = mysqli_query($conn, $sql_rev_month);
while ($row = mysqli_fetch_assoc($res)) {
    $revenue_month_data[] = [$row['period'] . '月', (int)$row['total']];
}

$revenue_week_data = [['週次', '總營收']];
$sql_rev_week = "SELECT WEEK(record_date) as period, SUM(revenue) as total 
                  FROM store_daily_stats GROUP BY period ORDER BY period ASC";
$res = mysqli_query($conn, $sql_rev_week);
while ($row = mysqli_fetch_assoc($res)) {
    $revenue_week_data[] = ['W' . $row['period'], (int)$row['total']];
}


// ==========================================================================
// 3. 平台每日訂單數量趨勢（每日近 7 日 / 每週，全平台加總）
// ==========================================================================
$order_daily_data = [['日期', '訂單數']];
$sql_order_day = "SELECT record_date, SUM(order_count) as total 
                   FROM store_daily_stats GROUP BY record_date 
                   ORDER BY record_date DESC LIMIT 7";
$res = mysqli_query($conn, $sql_order_day);
$tmp_rows = [];
while ($row = mysqli_fetch_assoc($res)) {
    $tmp_rows[] = [date('m-d', strtotime($row['record_date'])), (int)$row['total']];
}
$order_daily_data = array_merge([['日期', '訂單數']], array_reverse($tmp_rows));

$order_weekly_data = [['週次', '訂單數']];
$sql_order_week = "SELECT WEEK(record_date) as wk, SUM(order_count) as total 
                    FROM store_daily_stats GROUP BY wk ORDER BY wk ASC";
$res = mysqli_query($conn, $sql_order_week);
while ($row = mysqli_fetch_assoc($res)) {
    $order_weekly_data[] = ["第 " . $row['wk'] . " 週", (int)$row['total']];
}


// ==========================================================================
// 4. 平台抽成收入佔比（圓餅圖：各商家貢獻度，Top5 + 其他）
// ==========================================================================
$sql_commission = "SELECT store_name, SUM(revenue) as total_revenue
                    FROM store_daily_stats
                    GROUP BY store_name
                    ORDER BY total_revenue DESC";
$res = mysqli_query($conn, $sql_commission);
$commission_rows = [];
while ($row = mysqli_fetch_assoc($res)) {
    $commission_rows[] = [
        'name'       => $row['store_name'],
        'commission' => round($row['total_revenue'] * $commission_rate)
    ];
}

$pie_data = [['商家', '抽成收入']];
$top_n = 5;
$other_total = 0;
foreach ($commission_rows as $i => $row) {
    if ($i < $top_n) {
        $pie_data[] = [$row['name'], $row['commission']];
    } else {
        $other_total += $row['commission'];
    }
}
if ($other_total > 0) {
    $pie_data[] = ['其他商家', $other_total];
}
if (count($pie_data) === 1) {
    $pie_data[] = ['尚無數據', 0];
}


// ==========================================================================
// 5. 各商家表現比較（長條圖：依總營收排序，取前 10 家）
// ==========================================================================
$bar_data = [['商家', '總營收']];
$sql_bar = "SELECT store_name, SUM(revenue) as total_revenue
            FROM store_daily_stats
            GROUP BY store_name
            ORDER BY total_revenue DESC
            LIMIT 10";
$res = mysqli_query($conn, $sql_bar);
while ($row = mysqli_fetch_assoc($res)) {
    $bar_data[] = [$row['store_name'], (int)$row['total_revenue']];
}
if (count($bar_data) === 1) {
    $bar_data[] = ['尚無數據', 0];
}


// ==========================================================================
// 6. 歷年平台成長表現（使用者 / 商家 累積成長，雙 series 折線圖）
// ==========================================================================
$sql_user_year = "SELECT YEAR(created_at) as yr, COUNT(*) as cnt FROM consumer GROUP BY yr ORDER BY yr ASC";
$res = mysqli_query($conn, $sql_user_year);
$user_by_year = [];
while ($row = mysqli_fetch_assoc($res)) {
    $user_by_year[(int)$row['yr']] = (int)$row['cnt'];
}

$sql_store_year = "SELECT YEAR(created_at) as yr, COUNT(*) as cnt FROM store GROUP BY yr ORDER BY yr ASC";
$res = mysqli_query($conn, $sql_store_year);
$store_by_year = [];
while ($row = mysqli_fetch_assoc($res)) {
    $store_by_year[(int)$row['yr']] = (int)$row['cnt'];
}

$all_years = array_unique(array_merge(array_keys($user_by_year), array_keys($store_by_year)));
sort($all_years);

$growth_data = [['年份', '累計使用者數', '累計商家數']];
$cum_user = 0;
$cum_store = 0;
foreach ($all_years as $yr) {
    $cum_user  += isset($user_by_year[$yr]) ? $user_by_year[$yr] : 0;
    $cum_store += isset($store_by_year[$yr]) ? $store_by_year[$yr] : 0;
    $growth_data[] = [(string)$yr, $cum_user, $cum_store];
}
if (count($growth_data) === 1) {
    $growth_data[] = [(string)date('Y'), $total_users, $total_stores];
}

// 設定當前頁面標記與標題，供 header 判斷
$active_page = 'data';
$page_title  = '數據管理中心';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<style>
  :root {
    --cream:      #f5f4ec;
    --text-dark:  #23342b;
    --text-muted: #5a6e63;
    --green-pale: #8ab8a1;
    --green-deep: #659287;   /* 與 admin-header 一致 */
    --white:      #ffffff;
    --border:     rgba(101, 146, 135, 0.2);
    --shadow:     0 4px 16px rgba(35, 52, 43, 0.05);
  }
  .data-wrapper {
    max-width: 1100px; 
    margin: 0 auto;  
    padding: 20px;
    background: #f7f6f2;
    min-height: calc(100vh - 75px);
    box-sizing: border-box;
    margin-top: 0;
  }
  .page-title { font-size: 2.2rem; font-weight: 700; margin-top: 0; margin-bottom: 4px; color: var(--text-dark);display: flex; align-items: center; gap: 10px; }
  .page-sub { color: var(--text-muted); font-size: 1.3rem; margin-bottom: 25px; }

  /* 📊 KPI 區塊 */
  .kpi-row-grid {
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
    gap: 16px; 
    margin-bottom: 24px;
  }
  .kpi-horizontal-card {
    background: var(--white); border: 1px solid var(--border); border-radius: 14px;
    padding: 20px; box-shadow: var(--shadow); display: flex; flex-direction: column;
    justify-content: center; position: relative; transition: transform 0.2s ease;
  }
  .kpi-h-label { font-size: 1.25rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
  .kpi-h-value { font-size: 2.4rem; font-weight: 700; color: var(--text-dark); }
  .kpi-h-value.highlight { color: #c97a63; }
  .kpi-h-value.percentage { color: var(--green-deep); }
  .kpi-h-value.rating { color: #dba13a; }

  /* 📊 滿版區塊 */
  .full-width-section { width: 100%; margin-bottom: 24px; }

  /* 📊 三小圖表並排橫列 */
  .three-column-grid {
    display: grid; 
    /* 這裡改用 minmax，確保在小螢幕時自動換行 */
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
    gap: 20px; 
    width: 100%;
    margin-bottom: 24px;
  }

  .chart-card {
    background: var(--white); border: 1px solid var(--border); border-radius: 14px;
    padding: 20px; box-shadow: var(--shadow); box-sizing: border-box;
    width: 100%; overflow: hidden; position: relative; transition: box-shadow 0.2s;
  }
  .chart-card:hover { box-shadow: 0 6px 20px rgba(35,52,43,0.1); }

  .zoom-overlay-btn {
    background: rgba(108, 174, 139, 0.1); color: var(--green-deep);
    border: none; border-radius: 6px; padding: 4px 8px; font-size: 1.2rem;
    cursor: pointer; transition: all 0.2s; margin-left: 10px;
  }
  .zoom-overlay-btn:hover { background: var(--green-deep); color: white; }

  .chart-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px;
  }

  .chart-title { font-size: 1.4rem; font-weight: 700; color: var(--green-deep); border-left: 4px solid var(--green-deep); padding-left: 8px; }
  .switch-btn-group { display: flex; gap: 4px; background: var(--cream); padding: 4px; border-radius: 6px; }
  .switch-btn { font-size: 1.05rem; padding: 4px 8px; border-radius: 4px; cursor: pointer; border: none; background: transparent; color: var(--text-muted); }
  .switch-btn.active { background: var(--white); color: var(--green-deep); font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

  .chart-stage { width: 100%; height: 260px; display: block;}

  @media (max-width: 1024px) {
    .kpi-row-grid       { grid-template-columns: repeat(2, 1fr); }
    .three-column-grid  { grid-template-columns: 1fr; }
    .data-wrapper       { padding: 20px 18px; }
  }
  @media (max-width: 600px) {
    .kpi-row-grid { grid-template-columns: 1fr; }
  }

  /* 🔍 Modal 樣式 */
  .chart-modal {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;
  }
  .modal-content {
    background: white; 
    padding: 20px; 
    border-radius: 16px; 
    width: 90%; 
    max-width: 1000px;
    max-height: 80vh; /* 限制最大高度 */
    overflow-y: auto;
  }
  .modal-close { position: absolute; top: 15px; right: 20px; font-size: 2.4rem; cursor: pointer; color: #aaa; }
  .modal-close:hover { color: #333; }
  .modal-chart-stage { width: 100%; height: 450px; }
  .modal-title { font-size: 1.8rem; font-weight: bold; margin-bottom: 20px; color: var(--text-dark); }

  @keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }
</style>

<div class="data-wrapper">

  <div class="welcome-section">
    <h2 style="font-size: 2.2rem; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 10px;">
            <i class="bi bi-bar-chart-line-fill"></i> 平台數據管理中心
        </h2>
    <p class="page-sub">即時掌握平台整體營收、訂單量、抽成獲利分布與歷年成長趨勢。</p>
  </div>

  <div class="kpi-row-grid">
    <div class="kpi-horizontal-card">
      <div class="kpi-h-label">今日平台總營收</div>
      <div class="kpi-h-value" id="anim-revenue">$0</div>
    </div>
    <div class="kpi-horizontal-card">
      <div class="kpi-h-label">今日平台抽成收入 (40%)</div>
      <div class="kpi-h-value highlight" id="anim-commission">$0</div>
    </div>
    <div class="kpi-horizontal-card">
      <div class="kpi-h-label">平台使用者總數</div>
      <div class="kpi-h-value percentage" id="anim-users">0</div>
    </div>
    <div class="kpi-horizontal-card">
      <div class="kpi-h-label">平台商家總數</div>
      <div class="kpi-h-value rating" id="anim-stores">0</div>
    </div>
  </div>

  <div class="full-width-section">
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">
          平台每月營收趨勢 ($)
          <button class="zoom-overlay-btn" onclick="openModal('revenue', '平台營收趨勢 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i> 放大</button>
        </div>
        <div class="switch-btn-group" id="revenue_btns">
          <button id="revenue_year" class="switch-btn" onclick="updateRevenueChart('year', event)">按年</button>
          <button id="revenue_month" class="switch-btn active" onclick="updateRevenueChart('month', event)">按月</button>
          <button id="revenue_week" class="switch-btn" onclick="updateRevenueChart('week', event)">按週</button>
        </div>
      </div>
      <div id="revenue_chart_div" class="chart-stage" style="height: 280px;"></div>
    </div>
  </div>

  <div class="three-column-grid">
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">
          每日訂單數量趨勢
          <button class="zoom-overlay-btn" onclick="openModal('orders', '平台訂單數量趨勢 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
        <div class="switch-btn-group" id="order_btns">
          <button id="order_daily" class="switch-btn active" onclick="updateOrderChart('daily', event)">每日</button>
          <button id="order_weekly" class="switch-btn" onclick="updateOrderChart('weekly', event)">每週</button>
        </div>
      </div>
      <div id="order_chart_div" class="chart-stage"></div>
    </div>

    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">
          平台抽成收入佔比
          <button class="zoom-overlay-btn" onclick="openModal('pie', '平台抽成收入佔比（各商家貢獻度）放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
      </div>
      <div id="pie_chart_div" class="chart-stage"></div>
    </div>

    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">
          各商家表現比較
          <button class="zoom-overlay-btn" onclick="openModal('bar', '各商家表現比較（總營收）放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
      </div>
      <div id="bar_chart_div" class="chart-stage"></div>
    </div>
  </div>

  <div class="full-width-section">
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">
          歷年平台成長表現（累計使用者 / 商家數）
          <button class="zoom-overlay-btn" onclick="openModal('growth', '歷年平台成長表現 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i> 放大</button>
        </div>
      </div>
      <div id="growth_chart_div" class="chart-stage" style="height: 300px;"></div>
    </div>
  </div>

  <div id="chartModal" class="chart-modal" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div id="modalTitle" class="modal-title">圖表放大檢視</div>
      <div id="modal_chart_stage" class="modal-chart-stage"></div>
    </div>
  </div>

</div><!-- /.data-wrapper -->


<script type="text/javascript">
  google.charts.load('current', {'packages': ['corechart']});
  google.charts.setOnLoadCallback(() => {
    setTimeout(drawAllCharts, 200);
  });

  const revenueData = {
    year: <?php echo json_encode($revenue_year_data); ?>,
    month: <?php echo json_encode($revenue_month_data); ?>,
    week: <?php echo json_encode($revenue_week_data); ?>
  };

  const orderData = {
    daily: <?php echo json_encode($order_daily_data); ?>,
    weekly: <?php echo json_encode($order_weekly_data); ?>
  };

  const pieData = <?php echo json_encode($pie_data); ?>;
  const barData = <?php echo json_encode($bar_data); ?>;
  const growthData = <?php echo json_encode($growth_data); ?>;

  let currentRevenueType = 'month';
  let currentOrderType = 'daily';

  function drawAllCharts() {
    const textStyle = { fontName: 'Noto Sans TC', fontSize: 11, color: '#23342b' };
    const chartAnimation = { duration: 1200, easing: 'out', startup: true };

    // --- 1. 平台每日營收趨勢 ---
    const revStage = document.getElementById('revenue_chart_div');
    if (revStage) {
      const chart = new google.visualization.LineChart(revStage);
      chart.draw(google.visualization.arrayToDataTable(revenueData[currentRevenueType]), {
        colors: ['#6cae8b'], fontName: 'Noto Sans TC',
        chartArea: { width: '92%', height: '75%' },
        hAxis: { textStyle: textStyle }, vAxis: { textStyle: textStyle },
        legend: { position: 'none' }, lineWidth: 4, pointsVisible: true, pointSize: 6,
        animation: chartAnimation
      });
    }

    // --- 2. 每日訂單數量趨勢 ---
    const orderStage = document.getElementById('order_chart_div');
    if (orderStage) {
      const chart = new google.visualization.ColumnChart(orderStage);
      chart.draw(google.visualization.arrayToDataTable(orderData[currentOrderType]), {
        colors: ['#8ab8a1'], fontName: 'Noto Sans TC', chartArea: { width: '85%', height: '70%' },
        legend: { position: 'none' }, hAxis: { textStyle: textStyle }, vAxis: { textStyle: textStyle },
        animation: chartAnimation
      });
    }

    // --- 3. 平台抽成收入佔比 ---
    const pieStage = document.getElementById('pie_chart_div');
    if (pieStage) {
      const chart = new google.visualization.PieChart(pieStage);
      chart.draw(google.visualization.arrayToDataTable(pieData), {
        colors: ['#6cae8b', '#8ab8a1', '#a3c9b6', '#c2ded0', '#4e8f70', '#cfcfc2'],
        fontName: 'Noto Sans TC', chartArea: { width: '90%', height: '75%' },
        legend: { position: 'bottom', textStyle: textStyle }, pieHole: 0.3
      });
    }

    // --- 4. 各商家表現比較 ---
    const barStage = document.getElementById('bar_chart_div');
    if (barStage) {
      const chart = new google.visualization.BarChart(barStage);
      chart.draw(google.visualization.arrayToDataTable(barData), {
        colors: ['#6cae8b'], fontName: 'Noto Sans TC', chartArea: { width: '65%', height: '85%' },
        legend: { position: 'none' }, hAxis: { textStyle: textStyle }, vAxis: { textStyle: textStyle },
        animation: chartAnimation
      });
    }

    // --- 5. 歷年平台成長表現 ---
    const growthStage = document.getElementById('growth_chart_div');
    if (growthStage) {
      const chart = new google.visualization.LineChart(growthStage);
      chart.draw(google.visualization.arrayToDataTable(growthData), {
        colors: ['#6cae8b', '#dba13a'], fontName: 'Noto Sans TC', chartArea: { width: '88%', height: '70%' },
        legend: { position: 'top', textStyle: textStyle }, lineWidth: 3, pointsVisible: true,
        hAxis: { textStyle: textStyle }, vAxis: { textStyle: textStyle },
        animation: chartAnimation
      });
    }
  }

  function updateRevenueChart(type, event) {
    if (event) event.stopPropagation();
    currentRevenueType = type;
    drawAllCharts();
    document.querySelectorAll('#revenue_btns .switch-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`revenue_${type}`).classList.add('active');
  }

  function updateOrderChart(type, event) {
    if (event) event.stopPropagation();
    currentOrderType = type;
    drawAllCharts();
    document.querySelectorAll('#order_btns .switch-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`order_${type}`).classList.add('active');
  }

  // 🔍 放大檢視彈窗功能
  function openModal(chartType, titleText) {
    const modal = document.getElementById('chartModal');
    document.getElementById('modalTitle').innerText = titleText;
    modal.style.display = 'flex';

    setTimeout(() => {
      const stage = document.getElementById('modal_chart_stage');
      if (chartType === 'revenue') {
        const chart = new google.visualization.LineChart(stage);
        chart.draw(google.visualization.arrayToDataTable(revenueData[currentRevenueType]), {
          colors: ['#6cae8b'], chartArea: { width: '90%', height: '80%' }, lineWidth: 5, pointsVisible: true, pointSize: 8, legend: { position: 'none' }
        });
      } else if (chartType === 'orders') {
        const chart = new google.visualization.ColumnChart(stage);
        chart.draw(google.visualization.arrayToDataTable(orderData[currentOrderType]), {
          colors: ['#8ab8a1'], chartArea: { width: '90%', height: '80%' }, legend: { position: 'none' }
        });
      } else if (chartType === 'pie') {
        const chart = new google.visualization.PieChart(stage);
        chart.draw(google.visualization.arrayToDataTable(pieData), {
          colors: ['#6cae8b', '#8ab8a1', '#a3c9b6', '#c2ded0', '#4e8f70', '#cfcfc2'],
          chartArea: { width: '85%', height: '80%' }, legend: { position: 'right', textStyle: { fontSize: 14 } }, is3D: true
        });
      } else if (chartType === 'bar') {
        const chart = new google.visualization.BarChart(stage);
        chart.draw(google.visualization.arrayToDataTable(barData), {
          colors: ['#6cae8b'], chartArea: { width: '70%', height: '85%' }, legend: { position: 'none' }
        });
      } else if (chartType === 'growth') {
        const chart = new google.visualization.LineChart(stage);
        chart.draw(google.visualization.arrayToDataTable(growthData), {
          colors: ['#6cae8b', '#dba13a'], chartArea: { width: '88%', height: '80%' }, lineWidth: 4, pointsVisible: true, pointSize: 6, legend: { position: 'top' }
        });
      }
    }, 150);
  }

  function closeModal() {
    document.getElementById('chartModal').style.display = 'none';
  }

  // 📈 數字跑動動畫
  function animateCounter(id, start, end, duration, prefix = '', suffix = '') {
    const obj = document.getElementById(id);
    if (!obj) return;
    let startTimestamp = null;
    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      const currentVal = Math.floor(progress * (end - start) + start);
      obj.innerHTML = prefix + currentVal.toLocaleString() + suffix;
      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };
    window.requestAnimationFrame(step);
  }

  window.addEventListener('DOMContentLoaded', () => {
    animateCounter('anim-revenue', 0, <?php echo $today_revenue; ?>, 1200, '$');
    animateCounter('anim-commission', 0, <?php echo $today_commission; ?>, 1200, '$');
    animateCounter('anim-users', 0, <?php echo $total_users; ?>, 1000);
    animateCounter('anim-stores', 0, <?php echo $total_stores; ?>, 1000);
  });

  window.addEventListener('resize', drawAllCharts);
</script>
</div>
<?php
// 在最後才關閉資料庫連線
mysqli_close($conn);


?>