<?php
session_start();
include("db.php");

// 模擬登入，若無 Session 則預設為店家 ID = 1
if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = '1'; 
}
$current_id = $_SESSION['store_name'];

// 1. 抓取商家名稱
$sql_store = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$res_store = mysqli_query($conn, $sql_store);
$data_store = mysqli_fetch_assoc($res_store);
$store_name = $data_store ? $data_store['store_name'] : "未知商家";

// 2. 抓取商家錢包餘額 (可提領金額)
$sql_wallet = "SELECT balance FROM store_wallets WHERE store_id = '$current_id'";
$res_wallet = mysqli_query($conn, $sql_wallet);
$data_wallet = mysqli_fetch_assoc($res_wallet);
// 若錢包不存在則初始化一個 0 元錢包
if (!$data_wallet) {
    mysqli_query($conn, "INSERT INTO store_wallets (store_id, balance) VALUES ('$current_id', 5000.00)"); // 預設給 5000 測試
    $balance = 5000.00;
} else {
    $balance = (float)$data_wallet['balance'];
}

// 3. 處理前端篩選條件 (進帳/支出、時間限制)
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'income'; // 預設看進帳
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'week';   // 預設看這星期

// 根據時間篩選建立 SQL 條件
$time_condition = "";
if ($time_filter == 'week') {
    $time_condition = "AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($time_filter == 'month') {
    $time_condition = "AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($time_filter == 'year') {
    $time_condition = "AND YEAR(created_at) = YEAR(CURDATE())";
}

// ==========================================================================
// 4. 抓取交易流水紀錄 (改用 LEFT JOIN 連接 users 表抓取姓名)
// ==========================================================================
$sql_trans = "SELECT t.*, u.user_name 
              FROM store_transactions t
              LEFT JOIN consumer u ON t.user_id = u.No
              WHERE t.store_id = $current_id 
              ORDER BY t.created_at DESC";
$res_trans = mysqli_query($conn, $sql_trans);

// 🔍 如果資料庫沒資料，幫你塞入綁定 user_id 的新測試明細
if (mysqli_num_rows($res_trans) == 0 && $time_filter == 'week') {
    if ($type_filter == 'income') {
        // 🟢 修正：將數字 1, 2 改為對應你 users 表的帳號字串 '小石', '了了'
        mysqli_query($conn, "INSERT INTO store_transactions (store_id, user_id, transaction_type, category, amount, consumer_bank_account, created_at) VALUES 
        ('$current_id', '小石', 'income', 'consumer_payment', 500.00, '中國信託 (12345)', '2026-05-07 14:20:00'),
        ('$current_id', '了了', 'income', 'consumer_payment', 80.00, '玉山銀行 (98765)', '2026-05-05 11:15:00')");
    } else {
        mysqli_query($conn, "INSERT INTO store_transactions (store_id, user_id, transaction_type, category, amount, description, created_at) VALUES 
        ('$current_id', NULL, 'expense', 'advertising', 1200.00, '5月份平台首頁推薦曝光廣告費', '2026-05-06 09:00:00')");
    }
    // 重新抓取
    $res_trans = mysqli_query($conn, $sql_trans);
}

// 5. 處理提款表單送出 (POST)
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $withdraw_amount = (float)$_POST['withdraw_amount'];
    $bank_code = mysqli_real_escape_string($conn, $_POST['bank_code']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
    $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);

    if ($withdraw_amount <= 0) {
        $message = "❌ 提領金額必須大於 0 元！";
    } elseif ($withdraw_amount > $balance) {
        $message = "❌ 餘額不足，無法提領！";
    } else {
        // A. 扣除錢包餘額
        $new_balance = $balance - $withdraw_amount;
        mysqli_query($conn, "UPDATE store_wallets SET balance = $new_balance WHERE store_name = '$current_id'");
        
        // B. 寫入提款申請表
        mysqli_query($conn, "INSERT INTO withdraw_requests (store_name, amount, bank_code, bank_name, account_number, account_name, status) 
                             VALUES ('$current_id', $withdraw_amount, '$bank_code', '$bank_name', '$account_number', '$account_name', 'pending')");
        
        // C. 同步寫入交易流水紀錄（當作一筆支出）
        $desc = "申請提款至 {$bank_name} ({$account_number})，審核中";
        mysqli_query($conn, "INSERT INTO store_transactions (store_name, transaction_type, category, amount, description) 
                             VALUES ('$current_id', 'expense', 'withdraw', $withdraw_amount, '$desc')");
        
        // 重新整理頁面變數
        $balance = $new_balance;
        $message = "🎉 提款申請已送出！金額：NT$" . number_format($withdraw_amount) . "，等待管理員審核撥款。";
        
        // 重新抓取交易流水
        $res_trans = mysqli_query($conn, $sql_trans);
    }
}
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>金流服務 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    :root {
      --cream: #e3e2d4;      
      --green-pale: #8ab8a1;  
      --green-deep: #6cae8b;  
      --white: #ffffff;       
      --text-dark: #23342b;   
      --text-muted: #5a6e63;  
      --border: rgba(108, 174, 139, 0.2); 
      --shadow: 0 4px 16px rgba(35, 52, 43, 0.05); 
    }

    body {
      background-color: var(--cream) !important;
      color: var(--text-dark);
      font-family: 'Noto Sans TC', sans-serif;
      margin: 0; padding: 0;
    }

    /* 🟢 新增：側邊選單 CSS 樣式與動畫控制 */
    .full-menu {
      position: fixed; top: 0; left: 0; width: 300px; height: 100vh;
      background-color: #6cae8b !important; z-index: 9999; padding: 30px 24px;
      box-sizing: border-box; display: block; 
      transform: translateX(-100%); transition: transform 0.3s ease-in-out;
      box-shadow: 5px 0 15px rgba(0,0,0,0.2);
    }
    #menu-checkbox:checked ~ .full-menu { transform: translateX(0); }
    #menu-checkbox { display: none; }
    .menu-toggle { cursor: pointer; font-size: 2rem; color: white; margin-right: 15px; }

    .full-menu .menu-content { display: flex; flex-direction: column; gap: 30px; }
    .menu-store-profile { padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 10px; }
    .menu-store-name { color: #ffffff; font-size: 1.8rem; font-weight: 700; margin: 0 0 8px 0; }
    .menu-store-badge { display: inline-block; background-color: rgba(255,255,255,0.2); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 1.2rem; }
    .full-menu .menu-main-title { color: rgba(255,255,255,0.6); font-size: 1.4rem; }
    .full-menu .menu-item { font-size: 1.6rem; color: #ffffff; text-decoration: none; display: block; padding: 12px 10px; border-radius: 8px; }
    .full-menu .menu-item:hover { background-color: rgba(255, 255, 255, 0.2) !important; color: #ffffff !important; }
    .full-menu .menu-item.active { background-color: rgba(255, 255, 255, 0.3); font-weight: bold; }
    .full-menu .menu-close { position: absolute; top: 20px; right: 20px; color: #ffffff; font-size: 2rem; cursor: pointer; }

    .finance-container {
      max-width: 800px; margin: 90px auto 40px; padding: 0 20px; box-sizing: border-box;
    }

    /* 💰 錢包頂部卡片 */
    .wallet-card {
      background: var(--white); border-radius: 14px; padding: 30px;
      box-shadow: var(--shadow); border: 1px solid var(--border);
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
    }
    .wallet-info .label { font-size: 1.4rem; color: var(--text-muted); margin-bottom: 8px; }
    .wallet-info .amount { font-size: 3.2rem; font-weight: 700; color: #c97a63; }
    
    .withdraw-btn {
      background-color: #e87a5d; color: white; border: none; padding: 12px 28px;
      font-size: 1.5rem; font-weight: 700; border-radius: 8px; cursor: pointer;
      transition: background 0.2s; box-shadow: 0 4px 12px rgba(232,122,93,0.2);
    }
    .withdraw-btn:hover { background-color: #d16448; }

    /* 📊 交易紀錄排版 */
    .section-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 16px; color: var(--text-dark); }
    
    .filter-bar {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;
    }
    
    .switch-group { display: flex; gap: 6px; background: rgba(0,0,0,0.05); padding: 4px; border-radius: 8px; }
    .switch-tab {
      text-decoration: none; font-size: 1.3rem; padding: 6px 16px; border-radius: 6px;
      color: var(--text-muted); font-weight: 500; transition: all 0.2s;
    }
    .switch-tab.active { background: var(--white); color: var(--text-dark); font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }

    .select-dropdown {
      padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border);
      font-size: 1.3rem; color: var(--text-dark); background: var(--white); cursor: pointer;
    }

    /* 📜 交易列表項目 */
    .transaction-list { display: flex; flex-direction: column; gap: 12px; }
    .transaction-item {
      background: var(--white); border-radius: 10px; padding: 16px 20px;
      border: 1px solid var(--border); box-shadow: var(--shadow);
      display: flex; justify-content: space-between; align-items: center;
    }
    .item-left { display: flex; align-items: center; gap: 16px; }
    .item-icon {
      width: 44px; height: 44px; border-radius: 50px; background: #f4f3eb;
      display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    }
    .item-details .title { font-size: 1.45rem; font-weight: 700; margin-bottom: 4px; }
    .item-details .meta { font-size: 1.2rem; color: var(--text-muted); }
    .item-right { font-size: 1.8rem; font-weight: 700; }
    .item-right.income { color: #8ab8a1; }
    .item-right.expense { color: #c97a63; }

    .no-data { text-align: center; color: var(--text-muted); padding: 40px; font-size: 1.4rem; }

    /* 🔍 彈窗 Modal 樣式 */
    .modal-overlay {
      display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;
    }
    .modal-box {
      background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 450px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2); animation: popIn 0.25s ease;
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-title { font-size: 1.6rem; font-weight: bold; margin: 0; }
    .modal-close { font-size: 1.8rem; cursor: pointer; color: #aaa; }
    .modal-close:hover { color: #333; }

    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 1.25rem; color: var(--text-muted); margin-bottom: 6px; }
    .form-control {
      width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;
      box-sizing: border-box; font-size: 1.35rem;
    }
    .submit-btn {
      width: 100%; background-color: #6cae8b; color: white; border: none; padding: 12px;
      border-radius: 6px; font-size: 1.4rem; font-weight: bold; cursor: pointer; margin-top: 10px;
    }
    .submit-btn:hover { background-color: #599876; }

    .alert-msg {
      background: #fff; border-left: 4px solid #6cae8b; padding: 12px; border-radius: 4px;
      margin-bottom: 20px; font-size: 1.3rem; box-shadow: var(--shadow);
    }

    @keyframes popIn {
      from { opacity: 0; transform: translateY(-15px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <input type="checkbox" id="menu-checkbox">

  <header class="seller-header" style="background: #6cae8b !important;">
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
          <a href="seller-finance.php" class="menu-item active">💰 金流服務</a>
          <a href="seller-help.php" class="menu-item">❓ 賣家幫助中心</a>
        </div>
      </div>
    </div>
  </div>

  <main class="finance-container">
    <h2 style="font-size: 2.2rem; margin-bottom: 5px;">💰 金流帳務服務</h2>
    <p style="color: var(--text-muted); font-size: 1.3rem; margin-bottom: 25px;">查看帳目營收流水、執行提領作業與支出控管。</p>

    <?php if($message != ""): ?>
      <div class="alert-msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="wallet-card">
      <div class="wallet-info">
        <div class="label">可提領金額 (實際收益)</div>
        <div class="amount">NT$ <?php echo number_format($balance, 2); ?></div>
      </div>
      <button class="withdraw-btn" onclick="openWithdrawModal()">提款</button>
    </div>

    <div class="section-title">交易紀錄</div>
    <div class="filter-bar">
      <div class="switch-group">
        <a href="?type=income&time=<?php echo $time_filter; ?>" class="switch-tab <?php echo $type_filter=='income'?'active':''; ?>">進帳</a>
        <a href="?type=expense&time=<?php echo $time_filter; ?>" class="switch-tab <?php echo $type_filter=='expense'?'active':''; ?>">支出</a>
      </div>

      <select class="select-dropdown" onchange="location = this.value;">
        <option value="?type=<?php echo $type_filter; ?>&time=week" <?php echo $time_filter=='week'?'selected':''; ?>>這星期</option>
        <option value="?type=<?php echo $type_filter; ?>&time=month" <?php echo $time_filter=='month'?'selected':''; ?>>這個月</option>
        <option value="?type=<?php echo $type_filter; ?>&time=year" <?php echo $time_filter=='year'?'selected':''; ?>>今年度</option>
      </select>
    </div>

    <div class="transaction-list">
      <?php if(mysqli_num_rows($res_trans) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($res_trans)): ?>
          <div class="transaction-item">
            <div class="item-left">
              <div class="item-icon">
                <?php echo $type_filter == 'income' ? '🪙' : '💸'; ?>
              </div>
              <div class="item-details">
                <div class="title">
                  <?php 
                    if($type_filter == 'income') {
                        echo htmlspecialchars($row['user_name'] ? $row['user_name'] : "消費者") . " - 訂單付款明細";
                    } else {
                        // 支出根據類別顯示
                        echo $row['category'] == 'withdraw' ? "🏦 提款申請扣款" : "📢 廣告行銷費用支出";
                    }
                  ?>
                </div>
                <div class="meta">
                  日期：<?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?> 
                  <?php if($type_filter == 'income'): ?>
                     | 帳號：<?php echo htmlspecialchars($row['consumer_bank_account']); ?>
                  <?php else: ?>
                     | 備註：<?php echo htmlspecialchars($row['description']); ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="item-right <?php echo $type_filter; ?>">
              <?php echo $type_filter == 'income' ? '+' : '-'; ?>NT$<?php echo number_format($row['amount']); ?>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-data">📭 目前尚無符合條件的交易紀錄。</div>
      <?php endif; ?>
    </div>
  </main>

  <div id="withdrawModal" class="modal-overlay" onclick="closeWithdrawModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h3 class="modal-title">🏦 輸入提款銀行資訊</h3>
        <span class="modal-close" onclick="closeWithdrawModal()">&times;</span>
      </div>
      
      <form method="POST" action="">
        <input type="hidden" name="action" value="withdraw">
        
        <div class="form-group">
          <label>提領金額 (NT$)</label>
          <input type="number" name="withdraw_amount" class="form-control" max="<?php echo $balance; ?>" min="1" placeholder="最多可領 <?php echo (int)$balance; ?>" required>
        </div>
        <div class="form-group">
          <label>銀行代碼 (如: 007)</label>
          <input type="text" name="bank_code" class="form-control" placeholder="請輸入三碼銀行代號" required>
        </div>
        <div class="form-group">
          <label>銀行名稱</label>
          <input type="text" name="bank_name" class="form-control" placeholder="如: 第一銀行 楠梓分行" required>
        </div>
        <div class="form-group">
          <label>銀行帳號</label>
          <input type="text" name="account_number" class="form-control" placeholder="請輸入完整收款帳號" required>
        </div>
        <div class="form-group">
          <label>帳戶戶名</label>
          <input type="text" name="account_name" class="form-control" placeholder="請輸入收款人姓名" required>
        </div>
        
        <button type="submit" class="submit-btn">確認送出申請</button>
      </form>
    </div>
  </div>

  <script>
    function openWithdrawModal() {
      document.getElementById('withdrawModal').style.display = 'flex';
    }
    function closeWithdrawModal() {
      document.getElementById('withdrawModal').style.display = 'none';
    }
  </script>
</body>
</html>