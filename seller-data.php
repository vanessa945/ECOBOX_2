<?php
session_start();
include("db.php");

if (!isset($_SESSION['store_name'])) {
    header("Location: index.php"); // 沒登入就踢回登入頁
    exit();
}
$current_id = $_SESSION['store_name'];

// 1. 抓取商家名稱
$sql_store = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$res_store = mysqli_query($conn, $sql_store);
$data_store = mysqli_fetch_assoc($res_store);
$store_name = $data_store ? $data_store['store_name'] : "未知商家";


// ==========================================================================
// 2. 歷史銷售數據抓取（年、月、雙週）
// ==========================================================================
$sales_year_data = [['年份', '總銷售額']];
$sql_year = "SELECT YEAR(record_date) as yr, SUM(revenue) as total FROM store_daily_stats WHERE store_name = '$current_id' GROUP BY YEAR(record_date) ORDER BY yr ASC";
$res_year = mysqli_query($conn, $sql_year);
while($row = mysqli_fetch_assoc($res_year)) {
    $sales_year_data[] = [(string)$row['yr'], (int)$row['total']];
}
if (count($sales_year_data) === 1) $sales_year_data[] = [(string)date('Y'), 0];

$sales_month_data = [['月份', '總銷售額']];
$sql_month = "SELECT MONTH(record_date) as mon, SUM(revenue) as total 
              FROM store_daily_stats 
              WHERE store_name = '$current_id' 
              GROUP BY YEAR(record_date), MONTH(record_date) 
              ORDER BY YEAR(record_date) ASC, mon ASC";
$res_month = mysqli_query($conn, $sql_month);
while($row = mysqli_fetch_assoc($res_month)) {
    $sales_month_data[] = [$row['mon'] . "月", (int)$row['total']];
}
if (count($sales_month_data) === 1) $sales_month_data[] = [date('n') . "月", 0];

$sales_week_data = [['週次', '總銷售額']];
$sql_week = "SELECT WEEK(record_date) as wk, SUM(revenue) as total 
             FROM store_daily_stats 
             WHERE store_name = '$current_id' 
             GROUP BY wk 
             ORDER BY wk ASC";
$res_week = mysqli_query($conn, $sql_week);
while($row = mysqli_fetch_assoc($res_week)) {
    $sales_week_data[] = ["W" . $row['wk'], (int)$row['total']];
}
if (count($sales_week_data) === 1) $sales_week_data[] = ["W" . date('W'), 0];


// ==========================================================================
// 3. 總訂單數數據抓取（每日、每週）
// ==========================================================================
$order_daily_data = [['日期', '訂單數']];
$sql_order_day = "SELECT record_date, order_count FROM store_daily_stats WHERE store_name = '$current_id' ORDER BY record_date DESC LIMIT 7";
$res_order_day = mysqli_query($conn, $sql_order_day);
while($row = mysqli_fetch_assoc($res_order_day)) {
    $date_formatted = date('m-d', strtotime($row['record_date']));
    $order_daily_data[] = [$date_formatted, (int)$row['order_count']];
}
$order_daily_data = array_merge([['日期', '訂單數']], array_reverse(array_slice($order_daily_data, 1)));
if (count($order_daily_data) === 1) $order_daily_data[] = [date('m-d'), 0];

$order_weekly_data = [['週次', '訂單數']];
$sql_order_wk = "SELECT WEEK(record_date) as wk, SUM(order_count) as total FROM store_daily_stats WHERE store_name = '$current_id' GROUP BY WEEK(record_date) ORDER BY wk ASC";
$res_order_wk = mysqli_query($conn, $sql_order_wk);
while($row = mysqli_fetch_assoc($res_order_wk)) {
    $order_weekly_data[] = ["第 " . $row['wk'] . " 週", (int)$row['total']];
}
if (count($order_weekly_data) === 1) $order_weekly_data[] = ["第 " . date('W') . " 週", 0];


// ==========================================================================
// 4. 餐廳用戶購買成長數
// ==========================================================================
$user_growth_data = [['年份', '購買用戶成長數']];
$sql_user = "SELECT YEAR(record_date) as yr, SUM(new_users) as total FROM store_daily_stats WHERE store_name = '$current_id' GROUP BY YEAR(record_date) ORDER BY yr ASC";
$res_user = mysqli_query($conn, $sql_user);
while($row = mysqli_fetch_assoc($res_user)) {
    $user_growth_data[] = [(string)$row['yr'], (int)$row['total']];
}
if (count($user_growth_data) === 1) $user_growth_data[] = [(string)date('Y'), 0];


// ==========================================================================
// 5. 今日即時營運指標數據
// ==========================================================================
$sql_today = "SELECT revenue, order_count FROM store_daily_stats WHERE store_name = '$current_id' AND record_date = CURDATE()";
$res_today = mysqli_query($conn, $sql_today);
$today_data = mysqli_fetch_assoc($res_today);

$today_revenue     = $today_data ? (int)$today_data['revenue'] : 0;
$today_saved       = $today_data ? (int)$today_data['order_count'] : 0; 
$today_remaining   = 5; 
$today_rating      = 4.8;

$total_meals = $today_saved + $today_remaining;
$completion_rate = $total_meals > 0 ? round(($today_saved / $total_meals) * 100) : 0;

// 準備給 JS 用的純資料列（去掉標題列）
$sales_year_rows  = array_slice($sales_year_data, 1);
$sales_month_rows = array_slice($sales_month_data, 1);
$sales_week_rows  = array_slice($sales_week_data, 1);
$order_daily_rows  = array_slice($order_daily_data, 1);
$order_weekly_rows = array_slice($order_weekly_data, 1);
$user_growth_rows  = array_slice($user_growth_data, 1);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>數據中心 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  
  <!-- 引入圖示庫，確保側邊欄與 Modal 彈窗的圖示能顯示 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  
  <style>
    /*:root {
      --cream: #e3e2d4;       
      --green-pale: #8ab8a1;  
      --green-deep: #6cae8b;  
      --white: #ffffff;       
      --text-dark: #23342b;   
      --text-muted: #5a6e63;  
      --border: rgba(108, 174, 139, 0.2); 
      --shadow: 0 4px 16px rgba(35, 52, 43, 0.05); 
    }*/

    body {
      background-color: #f7f6f2 !important;
      color: var(--text-dark);
      font-family: 'Noto Sans TC', sans-serif;
      margin: 0; padding: 0;
    }

    .data-container {
      max-width: 1280px; 
      margin: 40px auto 40px; /* 這裡原本的 margin-top: 90px 會由 seller_header.php 的 padding-top: 75px 處理，稍微調整 */
      padding: 0 20px; 
      box-sizing: border-box;
    }

    .page-title { font-size: 2.4rem; font-weight: 700; margin-bottom: 5px; }
    .page-sub { color: var(--text-muted); font-size: 1.4rem; margin-bottom: 25px; }

    .kpi-row-grid {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;
    }
    .kpi-horizontal-card {
      background: var(--white); border: 1px solid var(--border); border-radius: 14px;
      padding: 20px; box-shadow: var(--shadow); display: flex; flex-direction: column;
      justify-content: center; position: relative; transition: transform 0.2s ease;
    }
    .kpi-horizontal-card.clickable { cursor: pointer; }
    .kpi-horizontal-card.clickable:hover { transform: translateY(-3px); border-color: #dba13a; }

    .kpi-h-label { font-size: 1.3rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
    .kpi-h-value { font-size: 2.6rem; font-weight: 700; color: var(--text-dark); }
    .kpi-h-value.highlight { color: #c97a63; }
    .kpi-h-value.percentage { color: var(--green-deep); }
    .kpi-h-value.rating { color: #dba13a; }

    .full-width-section { width: 100%; margin-bottom: 24px; }

    .three-column-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; width: 100%;
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

    .chart-title { font-size: 1.5rem; font-weight: 700; color: var(--green-deep); border-left: 4px solid var(--green-deep); padding-left: 8px; }
    .switch-btn-group { display: flex; gap: 4px; background: var(--cream); padding: 4px; border-radius: 6px; }
    .switch-btn { font-size: 1.1rem; padding: 4px 8px; border-radius: 4px; cursor: pointer; border: none; background: transparent; color: var(--text-muted); }
    .switch-btn.active { background: var(--white); color: var(--green-deep); font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

    .chart-stage { width: 100%; height: 260px; } 

    @media (max-width: 1024px) {
      .kpi-row-grid { grid-template-columns: repeat(2, 1fr); }
      .three-column-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
      .kpi-row-grid { grid-template-columns: 1fr; }
    }

    .chart-modal {
      display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;
    }
    .modal-content {
      background: white; padding: 30px; border-radius: 16px; width: 85%; max-width: 900px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; animation: fadeIn 0.3s ease;
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

  <script type="text/javascript">
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(() => {
      setTimeout(drawAllCharts, 200);
    });

    // ── 把 PHP 資料列（不含標題）傳給 JS ──
    const salesRows = {
      year:  <?php echo json_encode($sales_year_rows); ?>,
      month: <?php echo json_encode($sales_month_rows); ?>,
      week:  <?php echo json_encode($sales_week_rows); ?>
    };
    const orderRows = {
      daily:  <?php echo json_encode($order_daily_rows); ?>,
      weekly: <?php echo json_encode($order_weekly_rows); ?>
    };
    const userRows = <?php echo json_encode($user_growth_rows); ?>;

    let currentSalesType = 'month';
    let currentOrderType = 'daily';

    // ── 工具函式：建立 string + number 的 DataTable ──
    function makeTable(labelX, labelY, rows) {
      const dt = new google.visualization.DataTable();
      dt.addColumn('string', labelX);
      dt.addColumn('number', labelY);
      dt.addRows(rows);
      return dt;
    }

    const textStyle = { fontName: 'Noto Sans TC', fontSize: 11, color: '#23342b' };
    const chartAnimation = { duration: 1200, easing: 'out', startup: true };

    function drawAllCharts() {
      // --- 1. 總銷售額走勢 ---
      const salesStage = document.getElementById('sales_chart_div');
      if (salesStage) {
        const chart = new google.visualization.LineChart(salesStage);
        chart.draw(makeTable('期間', '總銷售額', salesRows[currentSalesType]), {
          colors: ['#6cae8b'], fontName: 'Noto Sans TC',
          chartArea: { width: '92%', height: '75%' },
          hAxis: { textStyle }, vAxis: { textStyle },
          legend: { position: 'none' }, lineWidth: 4, pointsVisible: true, pointSize: 6,
          animation: chartAnimation
        });
      }

      // --- 2. 總訂單數分佈 ---
      const orderStage = document.getElementById('order_chart_div');
      if (orderStage) {
        const chart = new google.visualization.ColumnChart(orderStage);
        chart.draw(makeTable('期間', '訂單數', orderRows[currentOrderType]), {
          colors: ['#8ab8a1'], fontName: 'Noto Sans TC',
          chartArea: { width: '85%', height: '70%' },
          legend: { position: 'none' }, hAxis: { textStyle }, vAxis: { textStyle },
          animation: chartAnimation
        });
      }

      // --- 3. 顧客購買人數成長 ---
      const userStage = document.getElementById('user_chart_div');
      if (userStage) {
        const chart = new google.visualization.LineChart(userStage);
        chart.draw(makeTable('年份', '購買用戶成長數', userRows), {
          colors: ['#4e8f70'], fontName: 'Noto Sans TC',
          chartArea: { width: '85%', height: '70%' },
          legend: { position: 'none' }, lineWidth: 3, pointsVisible: true,
          hAxis: { textStyle }, vAxis: { textStyle },
          animation: chartAnimation
        });
      }

      // --- 4. 熱銷剩食比例（圓餅圖，寫死測試資料）---
      const pieStage = document.getElementById('pie_chart_div');
      if (pieStage) {
        const chart = new google.visualization.PieChart(pieStage);
        chart.draw(google.visualization.arrayToDataTable([
          ['項目', '份數'],
          ['勁辣雞腿堡套餐', 340],
          ['大薯分享盒', 220],
          ['雙層牛肉吉事堡', 180]
        ]), {
          colors: ['#6cae8b', '#8ab8a1', '#a3c9b6'], fontName: 'Noto Sans TC',
          chartArea: { width: '90%', height: '75%' },
          legend: { position: 'bottom', textStyle }, pieHole: 0.3
        });
      }
    }

    function updateSalesChart(type, event) {
      if (event) event.stopPropagation();
      currentSalesType = type;
      drawAllCharts();
      document.querySelectorAll('#sales_btns .switch-btn').forEach(b => b.classList.remove('active'));
      document.getElementById(`sales_${type}`).classList.add('active');
    }

    function updateOrderChart(type, event) {
      if (event) event.stopPropagation();
      currentOrderType = type;
      drawAllCharts();
      document.querySelectorAll('#order_btns .switch-btn').forEach(b => b.classList.remove('active'));
      document.getElementById(`order_${type}`).classList.add('active');
    }

    // 🔍 放大檢視彈窗
    function openModal(chartType, titleText) {
      const modal = document.getElementById('chartModal');
      document.getElementById('modalTitle').innerText = titleText;
      modal.style.display = 'flex';

      setTimeout(() => {
        const stage = document.getElementById('modal_chart_stage');
        if (chartType === 'sales') {
          const chart = new google.visualization.LineChart(stage);
          chart.draw(makeTable('期間', '總銷售額', salesRows[currentSalesType]), {
            colors: ['#6cae8b'], chartArea: { width: '90%', height: '80%' },
            lineWidth: 5, pointsVisible: true, pointSize: 8, legend: { position: 'none' }
          });
        } else if (chartType === 'orders') {
          const chart = new google.visualization.ColumnChart(stage);
          chart.draw(makeTable('期間', '訂單數', orderRows[currentOrderType]), {
            colors: ['#8ab8a1'], chartArea: { width: '90%', height: '80%' },
            legend: { position: 'none' }
          });
        } else if (chartType === 'users') {
          const chart = new google.visualization.LineChart(stage);
          chart.draw(makeTable('年份', '購買用戶成長數', userRows), {
            colors: ['#4e8f70'], chartArea: { width: '90%', height: '80%' },
            lineWidth: 4, pointsVisible: true, pointSize: 6, legend: { position: 'none' }
          });
        } else if (chartType === 'pie') {
          const chart = new google.visualization.PieChart(stage);
          chart.draw(google.visualization.arrayToDataTable([
            ['項目', '份數'],
            ['勁辣雞腿堡套餐', 340],
            ['大薯分享盒', 220],
            ['雙層牛肉吉事堡', 180]
          ]), {
            colors: ['#6cae8b', '#8ab8a1', '#a3c9b6', '#c2ded0'],
            chartArea: { width: '85%', height: '80%' },
            legend: { position: 'right', textStyle: { fontSize: 14 } }, is3D: true
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
        if (progress < 1) window.requestAnimationFrame(step);
      };
      window.requestAnimationFrame(step);
    }

    window.addEventListener('DOMContentLoaded', () => {
      animateCounter('anim-revenue', 0, <?php echo $today_revenue; ?>, 1200, '$');
      animateCounter('anim-rate', 0, <?php echo $completion_rate; ?>, 1000, '', '%');
    });

    window.addEventListener('resize', drawAllCharts);
  </script>
</head>
<body>

  <!-- 💡 引入共用的 Header 與側邊欄 -->
  <?php 
    $link = $conn; 
    include("seller_header.php"); 
  ?>

  <main class="data-container">
    <div class="welcome-section">
      <h2 class="page-title">📈 數據營運中心</h2>
      <p class="page-sub">即時掌握店鋪銷售動態、顧客增長與賸食拯救指標。</p>
    </div>

    <div class="kpi-row-grid">
      <div class="kpi-horizontal-card">
        <div class="kpi-h-label">今日即時營收</div>
        <div class="kpi-h-value" id="anim-revenue">$0</div>
      </div>
      <div class="kpi-horizontal-card">
        <div class="kpi-h-label">今日完售率 (進度)</div>
        <div class="kpi-h-value percentage" id="anim-rate">0%</div>
      </div>
      <div class="kpi-horizontal-card">
        <div class="kpi-h-label">已拯救 / 剩餘</div>
        <div class="kpi-h-value" style="font-size: 2rem;">
          <span style="color:var(--green-deep);"><?php echo $today_saved; ?> 份</span> / 
          <span class="highlight"><?php echo $today_remaining; ?> 份</span>
        </div>
      </div>
      <div class="kpi-horizontal-card clickable" onclick="window.location.href='seller-reviews.php'" title="點擊查看詳細評論">
        <div class="kpi-h-label">店鋪當前評分 (點擊跳轉)</div>
        <div class="kpi-h-value rating">★ <?php echo $today_rating; ?></div>
      </div>
    </div>

    <div class="full-width-section">
      <div class="chart-card">
        <div class="chart-header">
          <div class="chart-title">
            總銷售額走勢 ($)
            <button class="zoom-overlay-btn" onclick="openModal('sales', '總銷售額走勢 ($) 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i> 放大</button>
          </div>
          <div class="switch-btn-group" id="sales_btns">
            <button id="sales_year" class="switch-btn" onclick="updateSalesChart('year', event)">按年</button>
            <button id="sales_month" class="switch-btn active" onclick="updateSalesChart('month', event)">按月</button>
            <button id="sales_week" class="switch-btn" onclick="updateSalesChart('week', event)">按雙週</button>
          </div>
        </div>
        <div id="sales_chart_div" class="chart-stage" style="height: 280px;"></div>
      </div>
    </div>

    <div class="three-column-grid">
      <div class="chart-card">
        <div class="chart-header">
          <div class="chart-title">
            總訂單數分佈
            <button class="zoom-overlay-btn" onclick="openModal('orders', '總訂單數分佈 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
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
            店鋪顧客成長趨勢
            <button class="zoom-overlay-btn" onclick="openModal('users', '店鋪顧客購買人數成長趨勢 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          </div>
        </div>
        <div id="user_chart_div" class="chart-stage"></div>
      </div>

      <div class="chart-card">
        <div class="chart-header">
          <div class="chart-title">
            熱銷剩食商品比例
            <button class="zoom-overlay-btn" onclick="openModal('pie', '熱銷剩食商品比例 放大檢視')"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          </div>
        </div>
        <div id="pie_chart_div" class="chart-stage"></div>
      </div>
    </div>
  </main>

  <div id="chartModal" class="chart-modal" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div id="modalTitle" class="modal-title">圖表放大檢視</div>
      <div id="modal_chart_stage" class="modal-chart-stage"></div>
    </div>
  </div>

  <?php mysqli_close($conn); ?>
</body>
</html>