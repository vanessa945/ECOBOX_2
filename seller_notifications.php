<?php
session_start();
include("db.php");

// 確保商家已登入，假設 session 存有 store_id
$current_store_id = $_SESSION['store_id'] ?? 1; // 建議使用 ID 確保資料庫關聯正確

// 撈取該商家的所有通知
$sql = "SELECT * FROM seller_notifications WHERE store_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_store_id);
$stmt->execute();
$result = $stmt->get_result();

// 將通知依據類型分類到不同的陣列中
$notifications = ['account' => [], 'order' => [], 'review' => []];
while ($row = $result->fetch_assoc()) {
    $type = $row['type']; // 假設資料庫 type 欄位為 'account', 'order', 'review'
    if (array_key_exists($type, $notifications)) {
        $notifications[$type][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>EcoBox - 通知中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background-color: #f9f9f9; margin: 0; }
        .container { max-width: 900px; margin: 50px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        /* 頁籤設計 */
        .tabs { display: flex; justify-content: center; gap: 30px; border-bottom: 2px solid #ddd; margin-bottom: 30px; }
        .tab-btn {
            background: none; border: none; font-size: 20px; font-weight: bold; color: #aaa;
            padding: 10px 20px; cursor: pointer; transition: 0.3s;
            position: relative; top: 2px;
        }
        .tab-btn.active { color: #222; border-bottom: 4px solid #5a5a5a; }
        .tab-btn:hover { color: #555; }

        /* 通知列表設計 */
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .notify-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 0; border-bottom: 1px dashed #eee; font-size: 18px;
        }
        .notify-text { flex: 1; color: #333; line-height: 1.5; }
        .notify-time { font-size: 14px; color: #888; margin-left: 20px; min-width: 80px; text-align: right; }
        
        /* 完成按鈕設計 */
        .btn-complete {
            background-color: #659287; color: white; border: none; padding: 8px 16px;
            border-radius: 20px; cursor: pointer; font-size: 15px; font-weight: bold;
            margin-left: 15px; transition: 0.2s;
        }
        .btn-complete:hover { background-color: #4f756c; }
        .btn-complete:disabled { background-color: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>
<?php include("seller_header.php"); ?>

<div class="container">
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('account')">帳款通知</button>
        <button class="tab-btn" onclick="switchTab('order')">訂單通知</button>
        <button class="tab-btn" onclick="switchTab('review')">評論通知</button>
    </div>

    <div id="account" class="tab-content active">
        <?php if(empty($notifications['account'])): ?>
            <p style="text-align:center; color:#999;">目前沒有帳款通知。</p>
        <?php else: ?>
            <?php foreach($notifications['account'] as $index => $n): ?>
            <div class="notify-item">
                <div class="notify-text"><?php echo ($index + 1) . ". " . htmlspecialchars($n['content']); ?></div>
                <div class="notify-time"><?php echo date('a h:i', strtotime($n['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="order" class="tab-content">
        <?php if(empty($notifications['order'])): ?>
            <p style="text-align:center; color:#999;">目前沒有訂單通知。</p>
        <?php else: ?>
            <?php foreach($notifications['order'] as $index => $n): ?>
            <div class="notify-item">
                <div class="notify-text"><?php echo ($index + 1) . ". " . htmlspecialchars($n['content']); ?></div>
                
                <?php if (isset($n['order_id']) && ($n['is_completed'] ?? 0) == 0): ?>
                    <button class="btn-complete" id="btn-<?php echo $n['order_id']; ?>" onclick="markReady(<?php echo $n['order_id']; ?>, this)">完成</button>
                <?php elseif(isset($n['order_id'])): ?>
                    <button class="btn-complete" disabled>已完成</button>
                <?php endif; ?>

                <div class="notify-time"><?php echo date('a h:i', strtotime($n['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="review" class="tab-content">
        <?php if(empty($notifications['review'])): ?>
            <p style="text-align:center; color:#999;">目前沒有評論通知。</p>
        <?php else: ?>
            <?php foreach($notifications['review'] as $index => $n): ?>
            <div class="notify-item">
                <div class="notify-text"><?php echo ($index + 1) . ". " . htmlspecialchars($n['content']); ?></div>
                <div class="notify-time"><?php echo date('a h:i', strtotime($n['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // 頁籤切換邏輯
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // 點擊「完成」發送 AJAX 請求通知消費者
    function markReady(orderId, btnElement) {
        if (!confirm('確定餐點已準備完成，並發送通知給消費者嗎？')) return;

        btnElement.disabled = true;
        btnElement.innerText = "處理中...";

        fetch('ajax_order_ready.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('已成功發送通知給消費者！');
                btnElement.innerText = "已完成";
            } else {
                alert('錯誤：' + data.message);
                btnElement.disabled = false;
                btnElement.innerText = "完成";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('系統發生錯誤，請稍後再試。');
            btnElement.disabled = false;
            btnElement.innerText = "完成";
        });
    }
</script>
</body>
</html>