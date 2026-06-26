<?php
session_start();

// 💡 1. 引入寄信與通知模組
include_once("notify_helper.php"); 

if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

$uName = $_SESSION['uName'];
$role  = $_SESSION['login'];

$link = @mysqli_connect('localhost', 'root', '', 'food_waste');
if (!$link) {
    echo json_encode(["status" => "error", "message" => "資料庫連線失敗: " . mysqli_connect_error()]);
    exit();
}
mysqli_set_charset($link, "utf8mb4");

// ── 處理回首頁時，動態清空已結帳購物車，並寫入訂單與寄信 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_cart') {
    $uName_escaped = mysqli_real_escape_string($link, $uName);
    
    // 1. 撈出目前購物車內的所有品項
    $sql_get_cart = "SELECT * FROM cart WHERE user_name = '$uName_escaped'";
    $res_get_cart = mysqli_query($link, $sql_get_cart);

    if (!$res_get_cart) {
        echo json_encode(["status" => "error", "message" => "撈取購物車失敗: " . mysqli_error($link)]);
        exit();
    }

    if (mysqli_num_rows($res_get_cart) === 0) {
        echo json_encode(["status" => "error", "message" => "購物車內無商品，無法結帳。"]);
        exit();
    }

    while ($cart_row = mysqli_fetch_assoc($res_get_cart)) {
        $s_name = mysqli_real_escape_string($link, $cart_row['store_name']);
        // 👇 新增抓取商品名稱
        $p_name = mysqli_real_escape_string($link, $cart_row['product_name']); 
        $qty = (int)$cart_row['quantity'];
        
        // 2. 自動去 seller_product 撈取該商家的價格 (加入 product_name 比對)
        $sql_p_info = "SELECT price FROM seller_product WHERE store_name = '$s_name' AND product_name = '$p_name' LIMIT 1";
        $res_p_info = mysqli_query($link, $sql_p_info);
        $row_p_info = mysqli_fetch_assoc($res_p_info);
        
        $price = $row_p_info ? (int)$row_p_info['price'] : 100;
        $total_price = $price * $qty;
        
        // 固定顯示盲盒名稱與份數
        $items_desc = mysqli_real_escape_string($link, "今日剩食盲盒 × " . $qty . "份餐點");
        
        // 3. 寫入歷史訂單紀錄表 (💡 如果這裡失敗，直接中斷並回傳錯誤訊息，不會清空購物車)
        $sql_ins_order = "INSERT INTO orders_history (user_name, store_name, order_date, items_desc, total_price, status) 
                          VALUES ('$uName_escaped', '$s_name', NOW(), '$items_desc', $total_price, 1)";
        
        if (!mysqli_query($link, $sql_ins_order)) {
            echo json_encode([
                "status" => "error", 
                "message" => "【歷史訂單寫入失敗】錯誤原因: " . mysqli_error($link) . "。 請檢查您的資料表名稱或欄位是否正確！"
            ]);
            exit();
        }

        // ── 寫入商家通知 & 寄信給商家 ──
        $sql_seller = "SELECT email FROM store WHERE store_name = '$s_name' LIMIT 1"; 
        $res_seller = mysqli_query($link, $sql_seller);
        $seller_email = "";
        if ($res_seller && $row_seller = mysqli_fetch_assoc($res_seller)) {
            $seller_email = $row_seller['email'];
        }

        // 💡 [測試用] 強制把收件人改成你自己的信箱！
        //$seller_email = "a1133364@mail.nuk.edu.tw"; 

        $notify_content = "收到來自 {$uName_escaped} 的新訂單：" . stripslashes($items_desc) . "，請著手準備！";

        if (function_exists('add_notification')) {
            add_notification($link, $s_name, 'order', $notify_content, $seller_email);
        }
    } 
    
    // 4. 確認歷史訂單都寫入成功後，才正式清空購物車
    if (!mysqli_query($link, "DELETE FROM cart WHERE user_name = '$uName_escaped'")) {
        echo json_encode(["status" => "error", "message" => "清空購物車失敗: " . mysqli_error($link)]);
        exit();
    }
    
    echo json_encode(["status" => "success"]);
    mysqli_close($link);
    exit();
}

// 撈取當前使用者準備結帳的購物車商品項目
$uName_escaped = mysqli_real_escape_string($link, $uName);
$sql_checkout = "SELECT c.No AS cart_no, c.store_name, c.product_name, c.quantity AS cart_qty, 
                (SELECT price FROM seller_product WHERE store_name = c.store_name AND product_name = c.product_name LIMIT 1) AS store_price
                FROM cart c
                WHERE c.user_name = '$uName_escaped' 
                ORDER BY c.No ASC";
$result_checkout = mysqli_query($link, $sql_checkout);

if (mysqli_num_rows($result_checkout) === 0) {
    echo "<script>alert('您的購物車目前沒有商品，請先挑選剩食！'); window.location.href='map.php';</script>";
    exit();
}

$checkout_items = [];
while ($row = mysqli_fetch_assoc($result_checkout)) {
    $checkout_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確認結帳 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        
        .checkout-box { background: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 16px rgba(0,0,0,.04); }
        .block-heading { font-size: 18px; font-weight: 900; color: #132a13; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

        .checkout-item-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 20px; }
        .item-detail-row { display: flex; align-items: center; justify-content: space-between; padding-bottom: 14px; border-bottom: 1px dashed #eef0f2; }
        .item-detail-row:last-child { border-bottom: none; padding-bottom: 0; }
        
        .item-left { display: flex; align-items: center; gap: 16px; }
        .item-img-mini { width: 50px; height: 50px; border-radius: 6px; overflow: hidden; background: #faf9f6; border: 1px solid #eef0f2; }
        .item-img-mini img { width: 100%; height: 100%; object-fit: cover; }
        
        .item-text-info { display: flex; flex-direction: column; gap: 4px; }
        .res-title { font-size: 16px; font-weight: 800; color: #111; }
        .res-qty { font-size: 13px; color: #888888; font-weight: 700; }
        .res-price-sub { font-size: 18px; font-weight: 900; color: #1a1a1a; }

        .tableware-wrapper { display: flex; align-items: center; gap: 10px; margin-top: 5px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .tableware-wrapper input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #3a5a40; }

        .coupon-section { display: flex; align-items: center; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #eef0f2; }
        .coupon-title { font-size: 15px; font-weight: 700; color: #333; }
        .coupon-select { padding: 6px 12px; font-size: 14px; border: 1.5px solid #ccc; border-radius: 6px; font-family: inherit; font-weight: 700; outline: none; cursor: pointer; color: #3a5a40; }

        .amount-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 20px 0 5px 0; }
        .total-label { font-size: 18px; font-weight: 800; }
        .total-price-big { font-size: 28px; font-weight: 900; color: #e63946; }

        .setting-btn-group { display: flex; gap: 12px; margin-top: 5px; }
        .config-toggle-btn { flex: 1; height: 46px; background: #f0f0f0; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; color: #1a1a1a; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .config-toggle-btn:hover { background: #e2e2e0; }

        .submit-checkout-btn { width: 100%; height: 50px; background: #132a13; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; box-shadow: 0 4px 12px rgba(19,42,19,0.25); transition: all 0.2s; }
        .submit-checkout-btn:hover { background: #3a5a40; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); z-index: 2000; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        
        .modal-card { background: #fff; border-radius: 12px; width: 90%; max-width: 440px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); transform: translateY(-20px); transition: transform 0.3s; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        
        .modal-head { font-size: 18px; font-weight: 900; color: #132a13; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { font-size: 22px; cursor: pointer; color: #aaa; background: none; border: none; }
        .modal-close:hover { color: #333; }
        
        .modal-body { display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; }
        .radio-option { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; cursor: pointer; padding: 10px; border-radius: 6px; border: 1.5px solid #eef0f2; }
        .radio-option input { width: 16px; height: 16px; accent-color: #3a5a40; }
        .modal-confirm-btn { width: 100%; height: 42px; background: #3a5a40; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .modal-confirm-btn:hover { background: #132a13; }
        
        .vat-input { width: 100%; padding: 8px 12px; border: 1.5px solid #ccc; border-radius: 6px; font-size: 14px; display: none; margin-top: 4px; outline: none; }

        .receipt-card { max-width: 550px !important; width: 95% !important; padding: 32px !important; }
        .receipt-table-header { display: flex; justify-content: space-between; font-size: 14px; color: #888888; font-weight: 700; padding-bottom: 8px; border-bottom: 2px solid #1a1a1a; margin-top: 10px; margin-bottom: 12px; }
        .receipt-item-box { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .receipt-row-data { display: flex; justify-content: space-between; font-size: 15px; font-weight: bold; color: #111; }
        
        .receipt-meta-block { border-top: 2px solid #1a1a1a; padding-top: 18px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 25px; }
        .meta-line { display: flex; justify-content: space-between; align-items: center; font-size: 15px; font-weight: 700; }
        .meta-line .meta-label { color: #333; }
        .meta-line .meta-val { color: #000; text-align: right; }
        .status-badge-success { background: #eef4ee; color: #3a5a40; padding: 2px 10px; border-radius: 4px; font-size: 13px; font-weight: 900; }

        .home-redirect-btn { width: 100%; height: 46px; background: #e0e0e0; color: #111111; border: none; border-radius: 6px; font-size: 15px; font-weight: 800; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .home-redirect-btn:hover { background: #d2d2d0; }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">
            
            <section class="checkout-box">
                <div class="block-heading"><i class="fa-solid fa-receipt"></i> 訂單清單確認</div>
                <div class="checkout-item-list">
                    <?php
                    $total_sum = 0;
                    foreach ($checkout_items as $item) {
                        $price = !empty($item['store_price']) ? (int)$item['store_price'] : 100;
                        $qty = (int)$item['cart_qty'];
                        $subtotal = $price * $qty;
                        $total_sum += $subtotal;
                    ?>
                        <div class="item-detail-row">
                            <div class="item-left">
                                <div class="item-img-mini"><img src="image/盲盒.png" alt="剩食盲盒"></div>
                                <div class="item-text-info">
                                    <span class="res-title"><?php echo htmlspecialchars($item['store_name']); ?></span>
                                    <span class="res-qty">今日剩食盲盒 × <?php echo $qty; ?> 份餐點</span>
                                </div>
                            </div>
                            <div class="res-price-sub">$<?php echo $subtotal; ?></div>
                        </div>
                    <?php
                    }
                    ?>
                </div>

                <label class="tableware-wrapper">
                    <input type="checkbox" id="tablewareCheck"> 是否需要餐具
                </label>
            </section>

            <section class="checkout-box">
                <div class="coupon-section">
                    <span class="coupon-title"><i class="fa-solid fa-ticket" style="color: #b8975a;"></i> 選擇優惠券</span>
                    <select class="coupon-select" id="couponSelect" onchange="applyDiscount()">
                        <option value="0" data-type="none">不使用優惠券</option>
                        <option value="20" data-type="minus">新會員折扣 -$20</option>
                        <option value="0.9" data-type="rate">環保節能 9 折</option>
                    </select>
                </div>

                <div class="amount-summary-row">
                    <div class="total-label">總金額</div>
                    <div class="total-price-big">$<span id="finalPriceDisplay"><?php echo $total_sum; ?></span></div>
                </div>

                <div class="setting-btn-group">
                    <button type="button" class="config-toggle-btn" onclick="openModal('paymentModal')">
                        <i class="fa-regular fa-credit-card"></i> 付款方式：<span id="currentPayment">未設定</span>
                    </button>
                    <button type="button" class="config-toggle-btn" onclick="openModal('invoiceModal')">
                        <i class="fa-regular fa-file-alt"></i> 發票設定：<span id="currentInvoice">捐贈發票</span>
                    </button>
                </div>

                <button type="button" class="submit-checkout-btn" onclick="handleFinalCheckout()">
                    確認結帳
                </button>
            </section>

        </div>
    </main>

    <div class="modal-overlay" id="paymentModal">
        <div class="modal-card">
            <div class="modal-head"><span>選擇付款方式</span><button type="button" class="modal-close" onclick="closeModal('paymentModal')">×</button></div>
            <div class="modal-body">
                <label class="radio-option"><input type="radio" name="payOpt" value="信用卡 / 金融卡" checked> 信用卡 / 金融卡</label>
                <label class="radio-option"><input type="radio" name="payOpt" value="LINE Pay"> LINE Pay</label>
                <label class="radio-option"><input type="radio" name="payOpt" value="到店現金付款"> 到店現金付款</label>
            </div>
            <button type="button" class="modal-confirm-btn" onclick="savePaymentSetting()">確認設定</button>
        </div>
    </div>

    <div class="modal-overlay" id="invoiceModal">
        <div class="modal-card">
            <div class="modal-head"><span>發票開立設定</span><button type="button" class="modal-close" onclick="closeModal('invoiceModal')">×</button></div>
            <div class="modal-body">
                <label class="radio-option" onclick="toggleVatInput(false)"><input type="radio" name="invOpt" value="雲端載具發票" checked> 雲端載具發票</label>
                <label class="radio-option" onclick="toggleVatInput(false)"><input type="radio" name="invOpt" value="捐贈發票"> 捐贈發票</label>
                <label class="radio-option" onclick="toggleVatInput(true)"><input type="radio" name="invOpt" value="公司統一編號"> 公司統一編號</label>
                <input type="text" id="vatNumber" class="vat-input" placeholder="請輸入 8 位數公司統編" maxlength="8">
            </div>
            <button type="button" class="modal-confirm-btn" onclick="saveInvoiceSetting()">確認設定</button>
        </div>
    </div>

    <div class="modal-overlay" id="receiptModal">
        <div class="modal-card receipt-card">
            <div class="modal-head">
                <span style="font-size:20px;"><i class="bi bi-check-circle-fill" style="color:#3a5a40;"></i> 訂單已成功送出</span>
            </div>
            
            <div style="font-size:17px; font-weight:800; color:#111; margin-top:5px;" id="receiptStoreHeader">商家名</div>
            <div class="receipt-table-header">
                <div style="width:20%;">數量</div>
                <div style="width:55%;">餐點名</div>
                <div style="width:25%; text-align:right;">價錢</div>
            </div>

            <div class="receipt-item-box">
                <?php foreach ($checkout_items as $item): ?>
                    <div class="receipt-row-data">
                        <div style="width:20%; color:#555;"><?php echo $item['cart_qty']; ?> 份餐點</div>
                        <div style="width:55%;">今日剩食盲盒</div>
                        <div style="width:25%; text-align:right;">$<?php echo ((int)$item['store_price'] * (int)$item['cart_qty']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="receipt-meta-block">
                <div class="meta-line">
                    <span class="meta-label">是否需要餐具</span>
                    <span class="meta-val" id="receiptTableware">不需要餐具</span>
                </div>
                <div class="meta-line">
                    <span class="meta-label">訂單編號</span>
                    <span class="meta-val" style="color:#b8975a; font-weight:bold;">待店家確認此訂單後提供...</span>
                </div>
                <div class="meta-line">
                    <span class="meta-label">訂單狀態</span>
                    <span class="meta-val"><span class="status-badge-success">付款成功，訂單已送出</span></span>
                </div>
                <div class="meta-line">
                    <span class="meta-label">支付方式</span>
                    <span class="meta-val" id="receiptPayment">信用卡</span>
                </div>
                <div class="meta-line" style="font-size:17px; padding-top:8px; border-top:1px dashed #ccc;">
                    <span class="meta-label" style="color:#132a13; font-weight:900;">支付金額</span>
                    <span class="meta-val" style="color:#e63946; font-weight:900; font-size:20px;">$<span id="receiptFinalPrice">0</span></span>
                </div>
            </div>

            <button type="button" class="home-redirect-btn" onclick="clearCartAndGoHome()">
                <i class="fa-solid fa-house"></i> 回首頁
            </button>
        </div>
    </div>

    <script>
        const baseTotal = <?php echo $total_sum; ?>;
        
        function applyDiscount() {
            const select = document.getElementById('couponSelect');
            const selectedOpt = select.options[select.selectedIndex];
            const val = parseFloat(selectedOpt.value);
            const type = selectedOpt.getAttribute('data-type');
            
            let finalPrice = baseTotal;
            if (type === 'minus') { finalPrice = baseTotal - val; } 
            else if (type === 'rate') { finalPrice = Math.round(baseTotal * val); }
            if (finalPrice < 0) finalPrice = 0;
            document.getElementById('finalPriceDisplay').innerText = finalPrice;
        }

        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        function toggleVatInput(show) { document.getElementById('vatNumber').style.display = show ? 'block' : 'none'; }

        function savePaymentSetting() {
            const selected = document.querySelector('input[name="payOpt"]:checked').value;
            document.getElementById('currentPayment').innerText = selected;
            closeModal('paymentModal');
        }

        function saveInvoiceSetting() {
            let selected = document.querySelector('input[name="invOpt"]:checked').value;
            if (selected === '公司統一編號') {
                const vat = document.getElementById('vatNumber').value;
                if(vat.length !== 8) { alert('請填寫完整的 8 位數公司統一編號！'); return; }
                selected = '統編 ' + vat;
            }
            document.getElementById('currentInvoice').innerText = selected;
            closeModal('invoiceModal');
        }

        function handleFinalCheckout() {
            const pay = document.getElementById('currentPayment').innerText;
            if (pay === '未設定') {
                alert('請先點擊按鈕設定您的「付款方式」！');
                openModal('paymentModal');
                return;
            }

            const tablewareStr = document.getElementById('tablewareCheck').checked ? '需要餐具' : '不需要餐具';
            document.getElementById('receiptTableware').innerText = tablewareStr;
            document.getElementById('receiptPayment').innerText = pay;
            
            const finalPrice = document.getElementById('finalPriceDisplay').innerText;
            document.getElementById('receiptFinalPrice').innerText = finalPrice;

            openModal('receiptModal');
        }

        // 💡 點擊回首頁時的偵錯處理
        function clearCartAndGoHome() {
            const formData = new FormData();
            formData.append('action', 'clear_cart');

            fetch('checkout.php', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                // 💡 關鍵修改：先不管 JSON，把 PHP 回傳的東西當成純文字接下來
                const rawText = await res.text(); 
                
                try {
                    // 嘗試解析成 JSON
                    const data = JSON.parse(rawText); 
                    if (data.status === 'success') {
                        window.location.href = 'userhome.php';
                    } else {
                        alert('後端錯誤提示：' + data.message);
                    }
                } catch (err) {
                    // 🚨 如果解析失敗，代表 PHP 噴了警告！我們直接把它印在螢幕上
                    console.error('解析 JSON 失敗，伺服器原始回應：', rawText);
                    document.body.innerHTML = `<div style="padding:30px; background:#fff; color:#e63946; z-index:9999; position:relative;">
                        <h2>🚨 發現 PHP 錯誤！</h2>
                        <p>這就是導致結帳失敗的原因，請看下方的詳細報錯：</p>
                        <hr>
                        <div style="color:#111;">${rawText}</div>
                    </div>`;
                }
            })
            .catch((error) => {
                alert('網路傳輸發生嚴重錯誤，請打開網頁 F12 查看 Console。');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($link); ?>