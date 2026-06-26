<?php
// 啟動連線 Session 紀錄
session_start();
include("db.php");

// 臨時測試專區
if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = 1; 
}
$current_id = $_SESSION['store_name'];

/* ==========================================
   從資料庫抓取：商家名稱與當日銷售資訊
   ========================================== */
$store_sql = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$store_result = mysqli_query($conn, $store_sql);
$store_data = mysqli_fetch_assoc($store_result);
$store_name = $store_data ? $store_data['store_name'] : "未知商家";

// 2. 從真實存在的 store_daily_stats 撈取今天的數據
$sql_today = "SELECT revenue, order_count, new_users FROM store_daily_stats WHERE store_name = '$current_id' AND record_date = CURDATE()";
$result_today = mysqli_query($conn, $sql_today);
$data_today = mysqli_fetch_assoc($result_today);

$today_revenue     = $data_today ? number_format($data_today['revenue']) : "0";
$saved_meals       = $data_today ? $data_today['order_count'] : "0";
$remaining_meals   = "5"; 
$rating            = "4.8";

// 注意：這裡暫時不關閉 $conn，因為 seller_header.php 需要用到它
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>商家後台 - EcoBox 剩食平台</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  
  <style>
    /* 這裡僅保留「儀表板內容」的樣式，header 樣式已由 seller_header.php 管理 */
    body { background-color: #f7f6f2 !important; }
    .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .welcome-section { margin-bottom: 40px; }
    
    /* 公告與面板區塊樣式 */
    .horizontal-notice-bar { background: #fff; padding: 24px; border-radius: 12px; border-left: 6px solid #6cae8b; box-shadow: 0 4px 16px rgba(0,0,0,.05); margin-bottom: 30px; }
    .panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .panel { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.05); }
    .panel-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 20px; padding-left: 10px; border-left: 4px solid #6cae8b; }
    .order-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #eee; }
    .quick-actions { display: flex; gap: 15px; }
    .action-btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; background: #eef4ee; color: #6cae8b; border: 1px solid #6cae8b; }
  </style>
</head>
<body>

  <?php 
    $link = $conn; // 確保 header 能使用資料庫連線
    include("seller_header.php"); 
  ?>

  <main class="dashboard-container">
    <div class="welcome-section">
      <h2>您好，<?php echo htmlspecialchars($store_name); ?>！今天想拯救多少剩食呢？👋</h2>
      <p style="color: #5a6e63;">這裡看您今日的即時店鋪營運指標。</p>
    </div>

    <div class="horizontal-notice-bar">
      <h3>📢 商家重要公告</h3>
      <p>📢 系統公告：本週末因應端午連假，金流結算時間將順延至下週一...<br>
         💡 營運小撇步：下午 16:00 - 17:00 是上班族預訂高峰期！</p>
    </div>

    <div class="panel-grid">
      <div class="panel">
        <div class="panel-title">即時剩食訂單動態</div>
        <div class="order-item">
          <div><strong>#1024 勁辣雞腿堡套餐</strong></div>
          <div style="color: #6cae8b; font-weight:700;">● 已取貨</div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-title">快捷操作</div>
        <div class="quick-actions">
          <a href="seller_products.php" class="action-btn">➕ 上架賸食</a>
          <a href="seller_reviews.php" class="action-btn">💬 回覆評論</a>
        </div>
      </div>
    </div>
  </main>

  <?php mysqli_close($conn); ?>
</body>
</html>