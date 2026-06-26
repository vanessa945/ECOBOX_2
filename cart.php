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

// ── 後端即時庫存檢查 API ──
if (isset($_POST['action']) && $_POST['action'] === 'check_stock') {
    header('Content-Type: application/json');
    $cart_data = json_decode($_POST['cart_items'], true);
    $errors = [];

    if (!empty($cart_data)) {
        foreach ($cart_data as $item) {
            $sName = mysqli_real_escape_string($link, $item['store_name']);
            $qty = (int)$item['quantity'];

            // 用 store_name 查庫存（取該店所有商品庫存加總）
            $sql_stock = "SELECT SUM(quantity) AS current_stock FROM seller_product WHERE store_name = '$sName'";
            $res_stock = mysqli_query($link, $sql_stock);

            if ($res_stock) {
                $row_stock = mysqli_fetch_assoc($res_stock);
                $current_stock = (int)$row_stock['current_stock'];

                if ($current_stock === 0) {
                    $errors[] = "「{$item['store_name']}」的盲盒目前已售完，無法結帳！";
                } elseif ($qty > $current_stock) {
                    $errors[] = "「{$item['store_name']}」的盲盒目前僅剩餘 {$current_stock} 份，您的數量超額，請調整後再試！";
                }
            } else {
                $errors[] = "找不到「{$item['store_name']}」的商品資訊，可能已下架。";
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

// ── 撈取購物車，用 store_name JOIN 取得價格與總庫存 ──
$uName_escaped = mysqli_real_escape_string($link, $uName);
$sql_cart = "SELECT 
                c.No AS cart_no,
                c.store_name,
                c.product_name,
                c.quantity AS cart_qty,
                -- 加入 AND product_name = c.product_name 來精準抓取單價
                (SELECT price FROM seller_product WHERE store_name = c.store_name AND product_name = c.product_name LIMIT 1) AS store_price,
                -- 同理，庫存應該是看「該單一商品」的剩餘數量，而不是整間店的總和
                (SELECT quantity FROM seller_product WHERE store_name = c.store_name AND product_name = c.product_name LIMIT 1) AS max_stock
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
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: #132a13; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        .cart-item-row { 
            background: #ffffff; border-radius: 12px; padding: 20px 28px; 
            display: flex; align-items: center; justify-content: space-between; gap: 24px; 
            box-shadow: 0 4px 16px rgba(0,0,0,.04); transition: all 0.3s ease; 
        }
        .cart-item-row:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        
        .cart-left-block { display: flex; align-items: center; gap: 24px; }
        .cart-index { font-size: 20px; font-weight: 900; color: #3a5a40; min-width: 25px; }
        
        .blindbox-avatar-box { width: 70px; height: 70px; border-radius: 8px; overflow: hidden; background: #f0f0f0; flex-shrink: 0; border: 1px solid #f0f0f0; }
        .blindbox-avatar-box img { width: 100%; height: 100%; object-fit: cover; }

        .store-info-main { display: flex; flex-direction: column; gap: 5px; }
        .store-name { font-size: 18px; font-weight: 800; color: #111111; }
        .store-price { font-size: 16px; font-weight: 700; color: #b8975a; }

        .cart-right-controls { display: flex; align-items: center; gap: 35px; }
        
        .quantity-control-group { display: flex; align-items: center; background: #f1f1f1; border-radius: 6px; padding: 2px; }
        .qty-btn { background: none; border: none; width: 32px; height: 32px; font-size: 16px; cursor: pointer; color: #1a1a1a; border-radius: 4px; transition: background 0.2s; }
        .qty-btn:hover { background: #e0e0e0; }
        .qty-input { width: 45px; text-align: center; border: none; background: transparent; font-size: 16px; font-weight: 700; color: #1a1a1a; pointer-events: none; }

        .trash-btn { background: none; border: none; font-size: 22px; color: #e63946; opacity: 0.6; cursor: pointer; transition: all 0.2s ease; padding: 6px; }
        .trash-btn:hover { opacity: 1; transform: scale(1.15); }

        .cart-footer-bar { 
            background: #ffffff; border-radius: 12px; padding: 22px 32px; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 4px 16px rgba(0,0,0,.04); margin-top: 10px;
        }
        .total-amount-label { font-size: 18px; font-weight: 700; color: #1a1a1a; }
        .total-amount-label span { font-size: 24px; font-weight: 900; color: #e63946; margin-left: 5px; }
        
        .checkout-btn { 
            background: #132a13; color: #fff; border: none; padding: 12px 36px; 
            font-size: 16px; font-weight: 700; border-radius: 8px; cursor: pointer; 
            transition: all 0.3s; box-shadow: 0 4px 12px rgba(19,42,19, 0.2);
        }
        .checkout-btn:hover { background: #3a5a40; transform: translateY(-1px); }

        .no-data { text-align: center; padding: 60px 20px; color: #888888; font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: #b8975a; }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">
            <div class="page-block-title">
                <i class="fa-solid fa-cart-shopping" style="color: #3a5a40;"></i> 您的購物車剩食
            </div>
            
            <div class="cart-list-wrapper">
                <?php
                if ($result_cart && mysqli_num_rows($result_cart) > 0) {
                    $idx = 1;
                    while ($row_cart = mysqli_fetch_assoc($result_cart)) {
                        $price     = !empty($row_cart['store_price']) ? (int)$row_cart['store_price'] : 0;
                        $max_stock = !empty($row_cart['max_stock'])   ? (int)$row_cart['max_stock']   : 0;
                        $current_qty = (int)$row_cart['cart_qty'];
                        // 若購物車數量已超過庫存，自動修正為庫存上限
                        if ($max_stock > 0 && $current_qty > $max_stock) {
                            $current_qty = $max_stock;
                        }
                ?>
                        <div class="cart-item-row" id="cart_row_<?php echo $row_cart['cart_no']; ?>" 
                             data-store-name="<?php echo htmlspecialchars($row_cart['store_name']); ?>" 
                             data-product-name="<?php echo htmlspecialchars($row_cart['product_name'] ?? ''); ?>" 
                             data-price="<?php echo $price; ?>" 
                             data-max-stock="<?php echo $max_stock; ?>">
                            
                            <div class="cart-left-block">
                                <div class="cart-index"><?php echo sprintf("%02d", $idx); ?></div>
                                <div class="blindbox-avatar-box">
                                    <img src="image/盲盒.png" alt="剩食盲盒">
                                </div>
                                <div class="store-info-main">
                                    <span class="store-name"><?php echo htmlspecialchars($row_cart['store_name']); ?> (盲盒)</span>
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
            if (label) label.innerText = '$' + total;
        }

        // ── 點擊數量加減控制 ──
        function changeQty(cartNo, amount) {
            const row = document.getElementById('cart_row_' + cartNo);
            const maxStock = parseInt(row.getAttribute('data-max-stock')) || 0;
            const qtyInput = document.getElementById('qty_' + cartNo);
            let currentQty = parseInt(qtyInput.value) || 1;

            currentQty += amount;

            if (currentQty < 1) {
                currentQty = 1;
            }

            if (maxStock > 0 && currentQty > maxStock) {
                alert(`⚠️ 抱歉！該盲盒目前最多只剩餘 ${maxStock} 份供搶購，無法再往上追加。`);
                currentQty = maxStock;
            }

            qtyInput.value = currentQty;
            calculateTotalPrice();
        }

        // ── 刪除購物車品項 ──
        function deleteCartItem(cartNo, storeName) {
            if (confirm(`確定要將「${storeName}」的盲盒從購物車中移除嗎？`)) {
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
                            if (document.querySelectorAll('.cart-item-row').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    } else {
                        alert('移除失敗：' + data.message);
                    }
                });
            }
        }

        // ── 點擊「前往結帳」時的即時庫存驗證 ──
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

            fetch('cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'checkout.php';
                } else {
                    let alertMsg = "🚨 結帳失敗！商品在庫數量發生變動：\n\n";
                    data.messages.forEach(msg => { alertMsg += msg + "\n"; });
                    alert(alertMsg);
                    window.location.reload();
                }
            });
        }

        window.addEventListener('DOMContentLoaded', calculateTotalPrice);
    </script>
</body>
</html>
<?php mysqli_close($link); ?>