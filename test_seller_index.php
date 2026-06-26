<?php
// 啟動連線 Session 紀錄
session_start();
include("db.php");
// -------------------------------------------------------------------------
// 臨時測試專區：因為登入功能目前不在你這，你可以手動修改數字 (1 或 2)
// 未來跟同學對接時，直接把下面這行刪掉即可！
if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = 1; 
}
// -------------------------------------------------------------------------

$current_id = $_SESSION['store_name'];


/* ==========================================
   從資料庫抓取：商家名稱與當日銷售資訊
   ========================================== */
// 1. 先抓取商家名稱
$store_sql = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$store_result = mysqli_query($conn, $store_sql);
$store_data = mysqli_fetch_assoc($store_result);
$store_name = $store_data ? $store_data['store_name'] : "未知商家";

// 2. 從真實存在的 store_daily_stats 撈取今天的數據 (CURDATE())
$sql_today = "SELECT revenue, order_count, new_users FROM store_daily_stats WHERE store_name = '$current_id' AND record_date = CURDATE()";
$result_today = mysqli_query($conn, $sql_today);
$data_today = mysqli_fetch_assoc($result_today);

// 如果今天有數據就帶入，沒有就給 0 (防止剛過午夜沒人下單時報錯)
$today_revenue     = $data_today ? number_format($data_today['revenue']) : "0";
$saved_meals       = $data_today ? $data_today['order_count'] : "0"; // 訂單數即為拯救份數

// 由於 store_daily_stats 目前沒有剩餘份數與評分欄位，先給予合理的預設值，未來有延伸表再對接
$remaining_meals   = "5"; 
$rating            = "4.8";

// 關閉資料庫連線 
//mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>商家後台 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700&family=Playfair+Display:wght=700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">

  <style>
    <style>
    /* ==========================================================================
       【大清掃】直接在 HTML 內層覆蓋所有舊綠色，換上新主色 #6cae8b
       ========================================================================== */
    :root {
      --cream: #e3e2d4;       /* 質感米白底色 */
      --green-pale: #8ab8a1;  /* 輔助粉綠 */
      --green-deep: #6cae8b;  /* 💡 你的全新優雅主色！全面取代舊的深綠 */
      --white: #ffffff;       
      --text-dark: #23342b;   
      --text-muted: #5a6e63;  
      --border: rgba(108, 174, 139, 0.2); 
      --shadow: 0 4px 16px rgba(35, 52, 43, 0.05); 
    }

    body {
      background-color: var(--cream) !important;
    }

    /* 頂部導覽列：直接換成你的新主色 */
    .seller-header {
      background: #6cae8b !important;
      background-color: #6cae8b !important;
    }

    /* 1. 左側抽屜式選單背景：換成你的新主色 */
    .full-menu {
      position: fixed;
      top: 0;
      left: 0;
      width: 300px; 
      height: 100vh;
      background-color: #6cae8b !important; /* 💡 換掉原本的暗綠色 */
      z-index: 9999;
      padding: 30px 24px;
      box-sizing: border-box;
      display: block; 
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
      box-shadow: 5px 0 15px rgba(0,0,0,0.15);
    }

    /* 當隱藏的 Checkbox 被勾選時，選單滑出 */
    #menu-checkbox:checked ~ .full-menu {
      transform: translateX(0);
    }

    /* 選單內容垂直排列 */
    .full-menu .menu-content {
      display: flex;
      flex-direction: column;
      gap: 30px;
      border: none;
      padding: 0;
    }

    /* 側邊欄內的商家名稱區塊 */
    .menu-store-profile {
      padding-bottom: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.15);
      margin-bottom: 10px;
    }
    .menu-store-name {
      color: #ffffff;
      font-size: 1.8rem;
      font-weight: 700;
      margin: 0 0 8px 0;
    }
    .menu-store-badge {
      display: inline-block;
      background-color: rgba(255,255,255,0.2);
      color: #fff;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 1.2rem;
    }

    .full-menu .menu-main-title {
      color: rgba(255,255,255,0.6);
      font-size: 1.4rem;
      margin-bottom: 15px;
      letter-spacing: 1px;
    }
    .full-menu .menu-item {
      font-size: 1.6rem;
      color: #ffffff !important;
      text-decoration: none;
      display: block;
      padding: 12px 10px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .full-menu .menu-item:hover {
      background-color: rgba(255, 255, 255, 0.2) !important; /* 滑過時半透明白 */
      color: #ffffff !important;
    }

    .full-menu .menu-close {
      position: absolute;
      top: 20px;
      right: 20px;
      color: #ffffff;
      font-size: 2rem;
      cursor: pointer;
    }

    /* 2. 全寬度橫向公告區塊 - 優雅白底配新主色邊框 */
    .horizontal-notice-bar {
      background-color: var(--white) !important; 
      border-left: 6px solid #6cae8b !important; /* 💡 提示條換成新主色 */
      border-top: 1px solid var(--border) !important;
      border-right: 1px solid var(--border) !important;
      border-bottom: 1px solid var(--border) !important;
      padding: 24px;
      border-radius: 12px;
      box-shadow: var(--shadow);
      margin-bottom: 30px; 
      width: 100%;
      box-sizing: border-box;
    }
    .horizontal-notice-bar h3 {
      margin: 0 0 12px 0;
      color: #6cae8b !important; /* 💡 標題換成新主色 */
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .horizontal-notice-bar p {
      margin: 0;
      font-size: 1.5rem;
      color: var(--text-dark); 
      line-height: 1.8;
    }

    /* 3. 下方即時訂單面板標題與快捷按鈕 */
    .panel-title {
      border-left: 4px solid #6cae8b !important; /* 💡 訂單動態的小裝飾條 */
    }
    
    .action-btn {
      background: rgba(108, 174, 139, 0.15) !important; /* 淡淡的新主色底 */
      color: #6cae8b !important;
      border: 1px solid #6cae8b !important;
    }

    .action-btn:hover {
      background: #6cae8b !important; /* 滑過時滿版新主色 */
      color: var(--white) !important;
    }
  </style>
</head>
<body>

  <?php 
    $link = $conn;
    include("seller_header.php"); 
    mysqli_close($conn); 
  ?>

  <input type="checkbox" id="menu-checkbox">

  <header class="seller-header">
    <div class="header-left">
      <label for="menu-checkbox" class="menu-toggle">☰</label>
      <div class="seller-brand">
        <a href="seller_index.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px;">
        🎃 EcoBox 後台
        </a>
      </div>
      <span class="restaurant-badge">🏪 <?php echo htmlspecialchars($store_name); ?></span>
    </div>
    
    <div class="header-right">
      <button class="icon-btn" title="通知">🔔</button>
      <button class="icon-btn" onclick="history.back()" title="上一頁">↩️</button>
      <a href="seller_index.php" class="icon-btn" title="回商家首頁">🏠</a>
      <button class="icon-btn" title="帳號設定">👤</button>
    </div>
  </header>

  <div class="full-menu">
    <label for="menu-checkbox" class="menu-close">✕</label>
    <div class="menu-content">
      
      <div class="menu-store-profile">
        <h2 class="menu-store-name"><?php echo htmlspecialchars($store_name); ?></h2>
        <span class="menu-store-badge">已認證合作夥伴</span>
      </div>

      <div>
        <h3 class="menu-main-title">核心管理</h3>
        <div class="menu-list">
          <a href="seller-products.php" class="menu-item">🍱 商品管理</a>
          <a href="seller-data.php" class="menu-item">📈 數據中心</a>
          <a href="seller-reviews.php" class="menu-item">💬 評論管理</a>
          <a href="seller-finance.php" class="menu-item">💰 金流服務</a>
          <a href="seller-help.php" class="menu-item">❓ 賣家幫助中心</a>
        </div>
      </div>

    </div>
  </div>

  <main class="dashboard-container">
    <div class="welcome-section">
      <h2>您好，<?php echo htmlspecialchars($store_name); ?>！今天想拯救多少剩食呢？👋</h2>
      <p style="color: var(--text-mid);">這裡看您今日的即時店鋪營運指標。</p>
    </div>

    <div class="horizontal-notice-bar">
      <h3>📢 商家重要公告</h3>
      <p>
        📢 系統公告：本週末因應端午連假，金流結算時間將順延至下週一，請各位合作夥伴多加留意。<br>
        💡 營運小撇步：下午 16:00 - 17:00 是上班族預訂晚餐剩食的高峰期，建議提早半小時上架當日即期商品，能有效提升 35% 的完售率喔！
      </p>
    </div>

    <div class="panel-grid">
      <div class="panel">
        <div class="panel-title">即時剩食訂單動態</div>
        <div class="order-item">
          <div><strong>#1024 勁辣雞腿堡套餐</strong> <span style="color:var(--text-muted); font-size:1.3rem;">(10分鐘前)</span></div>
          <div style="color: var(--green-mid); font-weight:700;">● 已取貨</div>
        </div>
        <div class="order-item">
          <div><strong>#1023 大薯 + 雞塊分享盒</strong> <span style="color:var(--text-muted); font-size:1.3rem;">(25分鐘前)</span></div>
          <div style="color: var(--amber); font-weight:700;">⏳ 待取貨</div>
        </div>
        <div class="order-item">
          <div><strong>#1022 雙層牛肉吉事堡</strong> <span style="color:var(--text-muted); font-size:1.3rem;">(1小時前)</span></div>
          <div style="color: var(--green-mid); font-weight:700;">● 已取貨</div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-title">快捷操作</div>
        <div class="quick-actions">
          <a href="seller-products.php" class="action-btn">➕ 上架賸食</a>
          <a href="seller-reviews.php" class="action-btn">💬 回覆評論</a>
        </div>
      </div>
    </div>
  </main>

</body>
</html>