<?php
session_start();

if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

$uName = $_SESSION['uName'];
$role  = $_SESSION['login'];

$link = @mysqli_connect('localhost', 'root', '', 'food_waste');
if (!$link) die("資料庫連線失敗: " . mysqli_connect_error());
mysqli_set_charset($link, "utf8mb4");

// ── 後端即時庫存檢查 API (供結帳與加減數量非同步驗證) ──
if (isset($_POST['action']) && $_POST['action'] === 'check_stock') {
    header('Content-Type: application/json');
    $cart_data = json_decode($_POST['cart_items'], true);
    $errors = [];

    if (!empty($cart_data)) {
        foreach ($cart_data as $item) {
            $sName = mysqli_real_escape_string($link, $item['store_name']);
            $qty = (int)$item['quantity'];

            // 即時去計算商家當前的 seller_product 總剩餘量
            $sql_stock = "SELECT IFNULL(SUM(quantity), 0) AS current_stock FROM seller_product WHERE store_name = '$sName'";
            $res_stock = mysqli_query($link, $sql_stock);
            $row_stock = mysqli_fetch_assoc($res_stock);
            $current_stock = (int)$row_stock['current_stock'];

            if ($qty > $current_stock) {
                $errors[] = "「{$item['store_name']}」目前僅剩餘 {$current_stock} 份，您的購物車數量超額，請調整後再試！";
            }
        }
    }

    if (empty($errors)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "messages" => $errors]);
    }
    mysqli_close($link);
    exit();
}

// ── 處理非同步 AJAX 刪除購物車品項 ──
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['cart_no'])) {
    $cart_no = (int)$_POST['cart_no'];
    $uName_escaped = mysqli_real_escape_string($link, $uName);
    
    $sql_delete = "DELETE FROM cart WHERE No = $cart_no AND user_name = '$uName_escaped'";
    if (mysqli_query($link, $sql_delete)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($link)]);
    }
    mysqli_close($link);
    exit();
}

// ── 撈取當前使用者的購物車資料，並即時整合商家單價與總剩餘庫存 ──
// 註：這裡假設你的 cart 資料表裡面記錄了 store_name 以及 quantity
$uName_escaped = mysqli_real_escape_string($link, $uName);
$sql_cart = "SELECT c.No AS cart_no, c.store_name, c.quantity AS cart_qty, 
            (SELECT price FROM seller_product WHERE store_name = c.store_name LIMIT 1) AS store_price,
            (SELECT IFNULL(SUM(quantity), 0) FROM seller_product WHERE store_name = c.store_name) AS max_stock
            FROM cart c
            WHERE c.user_name = '$uName_escaped' 
            ORDER BY c.No ASC";
$result_cart = mysqli_query($link, $sql_cart);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的購物車 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-deep:  #132a13;
            --green-mid:   #3a5a40;
            --accent-gold: #b8975a;
            --accent-light:#f3d078;
            --bg-warm:     #f7f6f2;
            --bg-white:    #ffffff;
            --text-dark:   #1a1a1a;
            --text-muted:  #888888;
            --border:      #eef0f2;
            --danger:      #e63946;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: var(--bg-warm); color: var(--text-dark); overflow: hidden; }

        /* ── Header ── */
        header {
            height: 75px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; background: var(--green-deep); box-shadow: 0 4px 20px rgba(0,0,0,.15);
            position: relative; z-index: 1000;
        }
        .nav-left  { display: flex; align-items: center; gap: 25px; }
        .hamburger-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; }
        .logo-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
        .nav-right { display: flex; gap: 15px; }
        .circle-nav-btn {
            width: 42px; height: 42px; border-radius: 50%; background: #4a4a4a;
            display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 20px; transition: all 0.3s;
        }
        .circle-nav-btn:hover { background: var(--green-mid); transform: scale(1.08); }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
            background: #fff; box-shadow: 5px 0 25px rgba(0,0,0,.15);
            transition: left .4s cubic-bezier(.25,1,.5,1); z-index: 1200; padding: 30px 20px; display: flex; flex-direction: column;
        }
        .sidebar.active { left: 0; }
        .sidebar-close-btn { align-self: flex-end; font-size: 26px; background: none; border: none; cursor: pointer; margin-bottom: 30px; color: var(--text-dark); }
        .sidebar-menu { list-style: none; }
        .sidebar-item a { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: var(--text-dark); font-size: 17px; font-weight: 500; border-radius: 8px; margin-bottom: 8px; transition: all .2s; }
        .sidebar-item a:hover { background: var(--bg-warm); color: var(--green-mid); }
        .sidebar-item a.active { background-color: var(--accent-light); color: #000000; font-weight: 700; box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35); }

        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
        .sidebar-overlay.active { display: block; }

        /* ── Main Layout ── */
        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: var(--green-deep); display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        /* ── 購物車品項卡片（去粗框、扁平風化） ── */
        .cart-item-row { 
            background: var(--bg-white); border-radius: 12px; padding: 20px 28px; 
            display: flex; align-items: center; justify-content: space-between; gap: 24px; 
            box-shadow: 0 4px 16px rgba(0,0,0,.04); transition: all 0.3s ease; 
        }
        .cart-item-row:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        
        .cart-left-block { display: flex; align-items: center; gap: 24px; }
        .cart-index { font-size: 20px; font-weight: 900; color: var(--green-mid); min-width: 25px; }
        
        /* 固定盲盒圖片盒 */
        .blindbox-avatar-box { width: 70px; height: 70px; border-radius: 8px; overflow: hidden; background: #f0f0f0; flex-shrink: 0; border: 1px solid #f0f0f0; }
        .blindbox-avatar-box img { width: 100%; height: 100%; object-fit: cover; }

        .store-info-main { display: flex; flex-direction: column; gap: 5px; }
        .store-name { font-size: 18px; font-weight: 800; color: #111111; }
        .store-price { font-size: 16px; font-weight: 700; color: var(--accent-gold); }

        .cart-right-controls { display: flex; align-items: center; gap: 35px; }
        
        /* 數量加減器樣式 */
        .quantity-control-group { display: flex; align-items: center; background: #f1f1f1; border-radius: 6px; padding: 2px; }
        .qty-btn { background: none; border: none; width: 32px; height: 32px; font-size: 16px; cursor: pointer; color: var(--text-dark); border-radius: 4px; transition: background 0.2s; }
        .qty-btn:hover { background: #e0e0e0; }
        .qty-input { width: 45px; text-align: center; border: none; background: transparent; font-size: 16px; font-weight: 700; color: var(--text-dark); pointer-events: none; }

        .trash-btn { background: none; border: none; font-size: 22px; color: var(--danger); opacity: 0.6; cursor: pointer; transition: all 0.2s ease; padding: 6px; }
        .trash-btn:hover { opacity: 1; transform: scale(1.15); }

        /* ── 底部結帳總計列 ── */
        .cart-footer-bar { 
            background: var(--bg-white); border-radius: 12px; padding: 22px 32px; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 4px 16px rgba(0,0,0,.04); margin-top: 10px;
        }
        .total-amount-label { font-size: 18px; font-weight: 700; color: var(--text-dark); }
        .total-amount-label span { font-size: 24px; font-weight: 900; color: var(--danger); margin-left: 5px; }
        
        .checkout-btn { 
            background: var(--green-deep); color: #fff; border: none; padding: 12px 36px; 
            font-size: 16px; font-weight: 700; border-radius: 8px; cursor: pointer; 
            transition: all 0.3s; box-shadow: 0 4px 12px rgba(19,42,19, 0.2);
        }
        .checkout-btn:hover { background: var(--green-mid); transform: translateY(-1px); }

        .no-data { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: var(--accent-gold); }
    </style>
</head>
<body>

    <header>
        <div class="nav-left">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
            <div class="logo-title">EcoBox剩食平台</div>
        </div>
        <div class="nav-right">
            <a href="map.php" class="circle-nav-btn" title="剩食地圖平台"><i class="bi bi-geo-alt"></i></a>
            <a href="userhome.php" class="circle-nav-btn" title="回首頁"><i class="fa-solid fa-house"></i></a>
            <a href="logout.php"   class="circle-nav-btn" title="登出"><i class="fa-solid fa-user"></i></a>
        </div>
    </header>

    <div class="sidebar" id="sidebar">
        <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fa-solid fa-xmark"></i></button>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="favorites.php"><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
            <li class="sidebar-item"><a href="notifications.php"><i class="fa-regular fa-bell"></i> 通知管理</a></li>
            <li class="sidebar-item"><a href="cart.php" class="active"><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
            <li class="sidebar-item"><a href="orders.php"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
            <li class="sidebar-item"><a href="help.php"><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
        </ul>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-wrapper">
        <div class="content-container">
            <div class="page-block-title">
                <i class="fa-solid fa-cart-shopping" style="color: var(--green-mid);"></i> 您的購物車剩食
            </div>
            
            <div class="cart-list-wrapper">
                <?php
                if ($result_cart && mysqli_num_rows($result_cart) > 0) {
                    $idx = 1;
                    while ($row_cart = mysqli_fetch_assoc($result_cart)) {
                        $price = !empty($row_cart['store_price']) ? (int)$row_cart['store_price'] : 100; // 防呆預設價
                        $max_stock = (int)$row_cart['max_stock'];
                        $current_qty = (int)$row_cart['cart_qty'];
                ?>
                        <div class="cart-item-row" id="cart_row_<?php echo $row_cart['cart_no']; ?>" 
                             data-store-name="<?php echo htmlspecialchars($row_cart['store_name']); ?>" 
                             data-price="<?php echo $price; ?>" 
                             data-max-stock="<?php echo $max_stock; ?>">
                            
                            <div class="cart-left-block">
                                <div class="cart-index"><?php echo sprintf("%02d", $idx); ?></div>
                                <div class="blindbox-avatar-box">
                                    <img src="image/盲盒.png" alt="剩食盲盒">
                                </div>
                                <div class="store-info-main">
                                    <span class="store-name"><?php echo htmlspecialchars($row_cart['store_name']); ?></span>
                                    <span class="store-price">$<?php echo $price; ?></span>
                                </div>
                            </div>

                            <div class="cart-right-controls">
                                <div class="quantity-control-group">
                                    <button class="qty-btn" onclick="changeQty(<?php echo $row_cart['cart_no']; ?>, -1)">-</button>
                                    <input type="text" class="qty-input" id="qty_<?php echo $row_cart['cart_no']; ?>" value="<?php echo $current_qty; ?>">
                                    <button class="qty-btn" onclick="changeQty(<?php echo $row_cart['cart_no']; ?>, 1)">+</button>
                                </div>
                                
                                <button type="button" class="trash-btn" onclick="deleteCartItem(<?php echo $row_cart['cart_no']; ?>, '<?php echo htmlspecialchars($row_cart['store_name'], ENT_QUOTES); ?>')" title="移除商品">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                <?php
                        $idx++;
                    }
                ?>
                    <div class="cart-footer-bar">
                        <div class="total-amount-label">訂單預計總計:<span id="total_price_label">$0</span></div>
                        <button type="button" class="checkout-btn" onclick="proceedToCheckout()">前往結帳 <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                <?php
                } else {
                ?>
                    <div class="no-data">
                        <i class="fa-solid fa-basket-shopping"></i>
                        購物車內空空如也！<br>快去賸食地圖上搶救美味的剩食吧。
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </main>

    <script>
        // ── 側邊欄抽屜邏輯 ──
        const hamburgerBtn   = document.getElementById('hamburgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const sidebar         = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function openSidebar()  { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // ── 即時動態計算購物車總金額 ──
        function calculateTotalPrice() {
            let total = 0;
            const rows = document.querySelectorAll('.cart-item-row');
            rows.forEach(row => {
                const price = parseInt(row.getAttribute('data-price')) || 0;
                const cartNo = row.id.replace('cart_row_', '');
                const qty = parseInt(document.getElementById('qty_' + cartNo).value) || 0;
                total += price * qty;
            });
            const label = document.getElementById('total_price_label');
            if(label) label.innerText = '$' + total;
        }

        // ── 點擊數量加減控制（包含防超賣實時上限鎖定） ──
        function changeQty(cartNo, amount) {
            const row = document.getElementById('cart_row_' + cartNo);
            const maxStock = parseInt(row.getAttribute('data-max-stock')) || 0;
            const qtyInput = document.getElementById('qty_' + cartNo);
            let currentQty = parseInt(qtyInput.value) || 1;

            currentQty += amount;

            // 限制最低數量不能小於 1
            if (currentQty < 1) {
                currentQty = 1;
            }
            
            // 關鍵限制：不能大於商家在資料庫剩餘的商品總數
            if (currentQty > maxStock) {
                alert(`⚠️ 抱歉！該商家目前最多只剩餘 ${maxStock} 份剩食供搶購，無法再往上追加。`);
                currentQty = maxStock;
            }

            qtyInput.value = currentQty;
            calculateTotalPrice();
        }

        // ── 刪除購物車品項 ──
        function deleteCartItem(cartNo, storeName) {
            if (confirm(`確定要將「${storeName}」從購物車中移除嗎？`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('cart_no', cartNo);

                fetch('cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const targetRow = document.getElementById('cart_row_' + cartNo);
                        targetRow.style.opacity = '0';
                        targetRow.style.transform = 'translateY(15px)';
                        setTimeout(() => {
                            targetRow.remove();
                            calculateTotalPrice();
                            // 如果沒商品了，刷新頁面顯示空空如也
                            if(document.querySelectorAll('.cart-item-row').length === 0){ window.location.reload(); }
                        }, 300);
                    } else { alert('移除失敗：' + data.message); }
                });
            }
        }

        // ── 點擊「前往結帳」時的「多人在線即時防超賣驗證」 ──
        function proceedToCheckout() {
            const rows = document.querySelectorAll('.cart-item-row');
            let cartItems = [];

            rows.forEach(row => {
                const storeName = row.getAttribute('data-store-name');
                const cartNo = row.id.replace('cart_row_', '');
                const qty = document.getElementById('qty_' + cartNo).value;
                cartItems.push({ store_name: storeName, quantity: qty });
            });

            const formData = new FormData();
            formData.append('action', 'check_stock');
            formData.append('cart_items', JSON.stringify(cartItems));

            // 發送即時非同步請求比對最新資料庫庫存
            fetch('cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // 通過驗證，正式核准進入結帳畫面 (可依據你的檔案名稱微調，例如 checkout.php)
                    window.location.href = 'checkout.php';
                } else {
                    // 若這期間有別人捷足先登把剩食買走了，阻斷跳轉並噴出警告
                    let alertMsg = "🚨 結帳失敗！商品在庫數量發生變動：\n\n";
                    data.messages.forEach(msg => { alertMsg += msg + "\n"; });
                    alert(alertMsg);
                    window.location.reload(); // 自動重新整理載入最新庫存
                }
            });
        }

        // 頁面加載完成時先計算一次總價
        window.addEventListener('DOMContentLoaded', calculateTotalPrice);
    </script>
</body>
</html>
<?php mysqli_close($link); ?>