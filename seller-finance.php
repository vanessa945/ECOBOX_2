<?php
session_start();
include("db.php");

// 模擬登入，若無 Session 則預設為店家 ID = 1
if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = '1'; 
}
$current_id = $_SESSION['store_name'];

// 0. 先抓出這個店名對應的 No (之後所有跟 store_id 比對的地方都用這個數字)
$sql_store_no = "SELECT No FROM store WHERE store_name = '$current_id'";
$res_store_no = mysqli_query($conn, $sql_store_no);
$row_store_no = mysqli_fetch_assoc($res_store_no);
$store_no = $row_store_no ? (int)$row_store_no['No'] : 0;

// 1. 抓取商家名稱 (顯示用)
$store_name = $row_store_no ? $current_id : "未知商家";

// 2. 抓取商家錢包餘額 (可提領金額)
$sql_wallet = "SELECT balance FROM store_wallets WHERE store_id = $store_no";
$res_wallet = mysqli_query($conn, $sql_wallet);
$data_wallet = mysqli_fetch_assoc($res_wallet);
// 若錢包不存在則初始化一個 5000 元測試錢包
if (!$data_wallet) {
    mysqli_query($conn, "INSERT INTO store_wallets (store_id, balance) VALUES ($store_no, 5000.00)");
    $balance = 5000.00;
} else {
    $balance = (float)$data_wallet['balance'];
}

// 3. 處理前端篩選條件 (進帳/支出、時間限制)
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'income'; // 預設看進帳
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'week';   // 預設看這星期

// 根據時間篩選建立 SQL 條件 (目前未實際套用到 $sql_trans，保留供未來擴充)
$time_condition = "";
if ($time_filter == 'week') {
    $time_condition = "AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($time_filter == 'month') {
    $time_condition = "AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($time_filter == 'year') {
    $time_condition = "AND YEAR(created_at) = YEAR(CURDATE())";
}

// ==========================================================================
// 4. 抓取交易流水紀錄 (JOIN consumer 抓消費者姓名、JOIN store 抓商家姓名)
// ==========================================================================
$sql_trans = "SELECT t.*, u.user_name AS consumer_name, s.store_name AS shop_name
              FROM store_transactions t
              LEFT JOIN consumer u ON t.user_id = u.No
              LEFT JOIN store s ON t.store_id = s.No
              WHERE t.store_id = $store_no
              ORDER BY t.created_at DESC";
$res_trans = mysqli_query($conn, $sql_trans);

// 🔍 如果資料庫沒資料，幫你塞入綁定真實 consumer.No 的測試明細
if (mysqli_num_rows($res_trans) == 0 && $time_filter == 'week') {
    if ($type_filter == 'income') {
        // 使用 consumer 表裡真實存在的 No (2 = ab, 3 = 小明)
        mysqli_query($conn, "INSERT INTO store_transactions (store_id, user_id, transaction_type, category, amount, consumer_bank_account, created_at) VALUES 
        ($store_no, 2, 'income', 'consumer_payment', 500.00, '中國信託 (12345)', '2026-05-07 14:20:00'),
        ($store_no, 3, 'income', 'consumer_payment', 80.00, '玉山銀行 (98765)', '2026-05-05 11:15:00')");
    } else {
        mysqli_query($conn, "INSERT INTO store_transactions (store_id, user_id, transaction_type, category, amount, description, created_at) VALUES 
        ($store_no, NULL, 'expense', 'advertising', 1200.00, '5月份平台首頁推薦曝光廣告費', '2026-05-06 09:00:00')");
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
        // A. 扣除錢包餘額 (store_id 是數字，比對用 store_no)
        $new_balance = $balance - $withdraw_amount;
        mysqli_query($conn, "UPDATE store_wallets SET balance = $new_balance WHERE store_id = $store_no");
        
        // B. 寫入提款申請表 (withdraw_requests 欄位是 store_id，不是 store_name)
        mysqli_query($conn, "INSERT INTO withdraw_requests (store_id, amount, bank_code, bank_name, account_number, account_name, status) 
                             VALUES ($store_no, $withdraw_amount, '$bank_code', '$bank_name', '$account_number', '$account_name', 'pending')");
        
        // C. 同步寫入交易流水紀錄（當作一筆支出）
        $desc = "申請提款至 {$bank_name} ({$account_number})，審核中";
        mysqli_query($conn, "INSERT INTO store_transactions (store_id, transaction_type, category, amount, description) 
                             VALUES ($store_no, 'expense', 'withdraw', $withdraw_amount, '$desc')");
        
        // 重新整理頁面變數
        $balance = $new_balance;
        $message = "🎉 提款申請已送出！金額：NT$" . number_format($withdraw_amount) . "，等待管理員審核撥款。";
        
        // 重新抓取交易流水
        $res_trans = mysqli_query($conn, $sql_trans);
    }
}
// 注意：這裡不關閉 $conn，讓 seller_header.php 可以順利使用
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>金流服務 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <style>
    /* 這裡僅保留金流服務區的專屬 CSS，共用的顏色變數與排版已交由 seller_header.php 處理 */
    body {
      background-color: #f7f6f2 !important;
    }

    .finance-container {
      max-width: 800px; 
      margin: 40px auto 40px; /* 為了配合 Header 固定在上方，稍作調整 */
      padding: 0 20px; 
      box-sizing: border-box;
    }

    .wallet-card {
      background: #ffffff; border-radius: 14px; padding: 30px;
      box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05); border: 1px solid rgba(108, 174, 139, 0.2);
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
    }
    .wallet-info .label { font-size: 1.4rem; color: #5a6e63; margin-bottom: 8px; }
    .wallet-info .amount { font-size: 3.2rem; font-weight: 700; color: #c97a63; }
    
    .withdraw-btn {
      background-color: #e87a5d; color: white; border: none; padding: 12px 28px;
      font-size: 1.5rem; font-weight: 700; border-radius: 8px; cursor: pointer;
      transition: background 0.2s; box-shadow: 0 4px 12px rgba(232,122,93,0.2);
    }
    .withdraw-btn:hover { background-color: #d16448; }

    .section-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 16px; color: #23342b; }
    
    .filter-bar {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;
    }
    
    .switch-group { display: flex; gap: 6px; background: rgba(0,0,0,0.05); padding: 4px; border-radius: 8px; }
    .switch-tab {
      text-decoration: none; font-size: 1.3rem; padding: 6px 16px; border-radius: 6px;
      color: #5a6e63; font-weight: 500; transition: all 0.2s;
    }
    .switch-tab.active { background: #ffffff; color: #23342b; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }

    .select-dropdown {
      padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(108, 174, 139, 0.2);
      font-size: 1.3rem; color: #23342b; background: #ffffff; cursor: pointer;
    }

    .transaction-list { display: flex; flex-direction: column; gap: 12px; }
    .transaction-item {
      background: #ffffff; border-radius: 10px; padding: 16px 20px;
      border: 1px solid rgba(108, 174, 139, 0.2); box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
      display: flex; justify-content: space-between; align-items: center;
    }
    .item-left { display: flex; align-items: center; gap: 16px; }
    .item-icon {
      width: 44px; height: 44px; border-radius: 50px; background: #f4f3eb;
      display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    }
    .item-details .title { font-size: 1.45rem; font-weight: 700; margin-bottom: 4px; }
    .item-details .meta { font-size: 1.2rem; color: #5a6e63; }
    .item-right { font-size: 1.8rem; font-weight: 700; }
    .item-right.income { color: #8ab8a1; }
    .item-right.expense { color: #c97a63; }

    .no-data { text-align: center; color: #5a6e63; padding: 40px; font-size: 1.4rem; }

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
    .form-group label { display: block; font-size: 1.25rem; color: #5a6e63; margin-bottom: 6px; }
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
      margin-bottom: 20px; font-size: 1.3rem; box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
    }

    @keyframes popIn {
      from { opacity: 0; transform: translateY(-15px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <?php 
    $link = $conn; 
    include("seller_header.php"); 
  ?>

  <main class="finance-container">
    <h2 style="font-size: 2.2rem; margin-bottom: 5px;">💰 金流帳務服務</h2>
    <p style="color: #5a6e63; font-size: 1.3rem; margin-bottom: 25px;">查看帳目營收流水、執行提領作業與支出控管。</p>

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
          <?php if ($row['transaction_type'] != $type_filter) continue; ?>
          <div class="transaction-item">
            <div class="item-left">
              <div class="item-icon">
                <?php echo $type_filter == 'income' ? '🪙' : '💸'; ?>
              </div>
              <div class="item-details">
                <div class="title">
                  <?php 
                    if($type_filter == 'income') {
                        // 顯示「消費者名字」付款
                        echo htmlspecialchars($row['consumer_name'] ? $row['consumer_name'] : "消費者") . " - 訂單付款明細";
                    } else {
                        // 支出顯示「商家名字」+ 類別
                        $label = $row['category'] == 'withdraw' ? "🏦 提款申請扣款" : "📢 廣告行銷費用支出";
                        echo htmlspecialchars($row['shop_name'] ? $row['shop_name'] : $store_name) . " - " . $label;
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

  <?php mysqli_close($conn); ?>
</body>
</html>