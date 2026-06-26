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
 
// 💡 修正：前一個頁面傳來的是商家的 No，為了不跟後面的 store_name 搞混，將變數命名為 store_no
$store_no = isset($_GET['id']) ? mysqli_real_escape_string($link, $_GET['id']) : '';
 
// 💡 修正：WHERE 條件改成 No
$sql_store    = "SELECT * FROM store WHERE No = '$store_no'";
$result_store = mysqli_query($link, $sql_store);
$store_data   = mysqli_fetch_assoc($result_store);
if (!$store_data) die("找不到該商家資料！");
 
// 💡 修正：讀取更新後的 store_name 欄位
$store_name         = $store_data['store_name'];
$store_intro        = $store_data['intro'];
$store_name_escaped = mysqli_real_escape_string($link, $store_name);

// ── 【升級版防超賣】：精準針對單一「真實商品」處理加入購物車 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_store_name'])) {
    $b_store_name   = mysqli_real_escape_string($link, $_POST['buy_store_name']);
    $b_product_name = isset($_POST['buy_product_name']) ? mysqli_real_escape_string($link, $_POST['buy_product_name']) : ''; 
    $b_qty          = (int)$_POST['buy_qty'];
    $u_name_escaped = mysqli_real_escape_string($link, $uName);
    
    // 🛡️ 步驟 1：查詢「這項特定商品」目前到底還剩幾份
    $sql_stock = "SELECT quantity AS max_stock FROM seller_product WHERE store_name = '$b_store_name' AND product_name = '$b_product_name'";
    $res_stock = mysqli_query($link, $sql_stock);
    $row_stock = mysqli_fetch_assoc($res_stock);
    $max_stock = $row_stock ? (int)$row_stock['max_stock'] : 0;

    // 🛡️ 步驟 2：檢查購物車內是不是已經有「這項特定商品」了
    $sql_check_cart = "SELECT * FROM cart WHERE user_name = '$u_name_escaped' AND store_name = '$b_store_name' AND product_name = '$b_product_name'";
    $res_check_cart = mysqli_query($link, $sql_check_cart);
    
    if (mysqli_num_rows($res_check_cart) > 0) {
        $row_cart = mysqli_fetch_assoc($res_check_cart);
        $current_cart_qty = (int)$row_cart['quantity'];
        
        $new_qty = $current_cart_qty + $b_qty;
        
        if ($new_qty > $max_stock) {
            $allow_add = $max_stock - $current_cart_qty;
            if ($allow_add > 0) {
                echo "<script>alert('⚠️ 加入失敗！您的購物車裡已經有 {$current_cart_qty} 份，但該盲盒目前總共只剩 {$max_stock} 份。您最多只能再加 {$allow_add} 份！'); window.history.back();</script>";
            } else {
                echo "<script>alert('⚠️ 購物車內的數量已達該盲盒庫存上限 ({$max_stock} 份) ，無法再追加囉！'); window.history.back();</script>";
            }
            mysqli_close($link);
            exit();
        } else {
            mysqli_query($link, "UPDATE cart SET quantity = $new_qty WHERE user_name = '$u_name_escaped' AND store_name = '$b_store_name' AND product_name = '$b_product_name'");
            echo "<script>alert('✅ 成功將盲盒放入購物車囉！'); window.location.href='cart.php';</script>";
        }
        
    } else {
        if ($b_qty > $max_stock) {
            echo "<script>alert('⚠️ 加入失敗！該盲盒庫存僅剩 {$max_stock} 份，無法提供 {$b_qty} 份。'); window.history.back();</script>";
            mysqli_close($link);
            exit();
        } else {
            mysqli_query($link, "INSERT INTO cart (user_name, store_name, product_name, quantity) VALUES ('$u_name_escaped', '$b_store_name', '$b_product_name', $b_qty)");
            echo "<script>alert('✅ 成功將盲盒放入購物車囉！'); window.location.href='cart.php';</script>";
        }
    }
    
    mysqli_close($link);
    exit();
}
 
// 評分統計
$sql_rating    = "SELECT AVG(rating) as avg_score, COUNT(*) as total_reviews FROM store_reviews WHERE store_name = '$store_name_escaped'";
$result_rating = mysqli_query($link, $sql_rating);
$rating_data   = mysqli_fetch_assoc($result_rating);
$avg_score     = !empty($rating_data['avg_score']) ? round($rating_data['avg_score'], 1) : 0.0;
$total_reviews = $rating_data['total_reviews'];
 
// 商品
$sql_products    = "SELECT * FROM seller_product WHERE store_name = '$store_name_escaped' ORDER BY No DESC";
$result_products = mysqli_query($link, $sql_products);
 
// 評論
$sql_reviews    = "SELECT * FROM store_reviews WHERE store_name = '$store_name_escaped' ORDER BY No DESC";
$result_reviews = mysqli_query($link, $sql_reviews);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?> — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">
 
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; }
 
        /* ── Page layout ── */
        .page-body { max-width: 1060px; margin: 36px auto 60px; padding: 0 24px; display: flex; flex-direction: column; gap: 32px; }
 
        /* ── 商家資訊卡 ── */
        .store-hero {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,.06);
            display: flex; align-items: center; overflow: hidden; min-height: 140px;
            padding: 24px 32px; gap: 24px;
        }
        .store-avatar-area {
            width: 80px; height: 80px; border-radius: 50%; border: 2.5px solid #3a5a40;
            overflow: hidden; background: #f0f0f0; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .store-avatar-area img { width: 100%; height: 100%; object-fit: cover; }
        .store-hero-body { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .store-main-name { font-size: 26px; font-weight: 900; color: #132a13; letter-spacing: 0.5px; }
        .store-meta { display: flex; flex-direction: column; gap: 4px; }
        .store-intro-text { font-size: 15px; font-weight: 500; color: #555; }
        .store-rating-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 2px;}
        .stars-display { display: flex; gap: 3px; }
        .stars-display .fa-star        { color: #f3d078; font-size: 14px; }
        .stars-display .fa-star.empty  { color: #ddd; }
        .score-num  { font-size: 18px; font-weight: 900; color: #1a1a1a; }
        .score-sub  { font-size: 13px; color: #888888; }
 
        /* ── Section header ── */
        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .section-header-line { flex: 1; height: 2px; background: #e0e0e0; }
        .section-title { font-size: 18px; font-weight: 900; color: #132a13; white-space: nowrap; letter-spacing: .5px; }
 
        /* ── Product cards ── */
        .product-list { display: flex; flex-direction: column; gap: 16px; }
        .product-card {
            background: #ffffff; border-radius: 10px; box-shadow: 0 1px 8px rgba(0,0,0,.05);
            display: flex; align-items: center; gap: 0; overflow: hidden; transition: box-shadow .25s, transform .25s;
        }
        .product-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.10); transform: translateY(-2px); }
 
        .product-img-area {
            width: 180px; height: 140px; flex-shrink: 0; background: #f5f4ee; display: flex; align-items: center; justify-content: center;
            border-right: 1px solid #e0e0e0; position: relative; overflow: hidden;
        }
        .product-img-area img { width: 100%; height: 100%; object-fit: cover; opacity: 1; }
        .blindbox-label {
            position: absolute; bottom: 0; left: 0; right: 0; text-align: center; font-size: 11px; font-weight: 700; color: #fff; letter-spacing: 1px;
            background: rgba(19, 42, 19, 0.75); padding: 4px 0;
        }
 
        /* 商品資訊 */
        .product-body { flex: 1; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; justify-content: center; }
        .product-name { font-size: 21px; font-weight: 800; color: #1a1a1a; margin-bottom: 2px; }
        .product-desc { font-size: 13px; color: #666; margin-bottom: 4px; } /* 💡 新增描述欄位 CSS */
        .allergy-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
        .allergy-label { font-size: 12px; color: #888888; font-weight: 500; }
        .allergy-tag { background: #fff4e6; color: #c0620a; border: 1px solid #f5c97a; font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 12px; }
        .allergy-tag.none { background: #f0f0f0; color: #aaa; border-color: #e0e0e0; }
        .product-qty-row { font-size: 12px; color: #b8975a; font-weight: 700; margin-top: 2px; }
 
        /* ── 右側橫向流線動作區 ── */
        .product-action-area {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 20px; padding: 20px 28px; border-left: 1px solid #e0e0e0;
            background: #fafafa; min-width: 400px; height: 140px; flex-shrink: 0;
        }
        .price-display { font-size: 28px; font-weight: 900; color: #1a1a1a; white-space: nowrap; margin-right: auto; }
        .price-display span { font-size: 14px; font-weight: 500; color: #888888; margin-left: 2px; }
 
        .quantity-controller { display: flex; align-items: center; border: 1.5px solid #ccc; border-radius: 6px; overflow: hidden; background: #fff; height: 38px; flex-shrink: 0; }
        .qty-btn { background: none; border: none; width: 32px; height: 100%; font-size: 16px; cursor: pointer; color: #1a1a1a; }
        .qty-btn:hover { background: #f0f0f0; }
        .qty-input { width: 36px; height: 100%; text-align: center; font-size: 15px; font-weight: 700; border: none; border-left: 1.5px solid #ccc; border-right: 1.5px solid #ccc; outline: none; }
 
        .add-cart-btn {
            height: 38px; background: #132a13; color: #fff; border: none; border-radius: 6px; padding: 0 16px;
            font-size: 13px; font-weight: 700; cursor: pointer; transition: background .2s; display: flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; flex-shrink: 0;
        }
        .add-cart-btn:hover { background: #3a5a40; }
 
        /* ── Reviews section ── */
        .reviews-wrapper { background: #ffffff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,.06); overflow: hidden; }
        .reviews-list { padding: 8px 0; }
        .review-item { display: flex; gap: 18px; padding: 22px 32px; border-bottom: 1px solid #e0e0e0; }
        .review-item:last-child { border-bottom: none; }
        .reviewer-avatar { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; background: #e9ecef; overflow: hidden; border: 2px solid #e0e0e0; }
        .reviewer-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-initial { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #3a5a40; color: #fff; font-size: 18px; font-weight: 700; }
 
        .review-body { flex: 1; }
        .review-top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .reviewer-name { font-size: 15px; font-weight: 700; }
        .review-date { font-size: 12px; color: #888888; }
        .review-stars  { display: flex; gap: 2px; margin-bottom: 8px; }
        .review-stars i { font-size: 13px; color: #f3d078; }
        .review-stars i.empty { color: #ddd; }
        .review-text   { font-size: 14px; color: #444; line-height: 1.7; }
 
        .reply-bubble { margin-top: 12px; background: #f5f9f5; border-left: 3px solid #3a5a40; padding: 10px 14px; border-radius: 0 6px 6px 0; }
        .reply-bubble-head { font-size: 12px; font-weight: 700; color: #132a13; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .reply-bubble-text { font-size: 13px; color: #555; line-height: 1.6; }
        .no-data { text-align: center; padding: 40px 20px; color: #888888; font-size: 15px; }
    </style>
</head>
<body>
 
<?php include 'shared_header.php'; ?>
 
<div class="page-body">
 
    <div class="store-hero">
        <div class="store-avatar-area">
            <img src="image/預設店家頭像.png" alt="商家頭像">
        </div>
        
        <div class="store-hero-body">
            <div class="store-main-name"><?php echo htmlspecialchars($store_name); ?></div>
            <div class="store-meta">
                <div class="store-intro-text"><?php echo htmlspecialchars($store_intro); ?></div>
                <div class="store-rating-row">
                    <div class="stars-display">
                        <?php
                        $full = floor($avg_score);
                        for ($i = 1; $i <= 5; $i++) {
                            $cls = ($i <= $full) ? 'fa-solid fa-star' : 'fa-regular fa-star empty';
                            echo "<i class=\"$cls\"></i>";
                        }
                        ?>
                    </div>
                    <span class="score-num"><?php echo $avg_score; ?></span>
                    <span class="score-sub">(<?php echo $total_reviews; ?> 則評分)</span>
                </div>
            </div>
        </div>
    </div>
 
    <div>
        <div class="section-header">
            <div class="section-title">🛍 今日上架剩食</div>
            <div class="section-header-line"></div>
        </div>
 
        <div class="product-list">
        <?php
        if (mysqli_num_rows($result_products) > 0):
            $p_idx = 0;
            while ($row = mysqli_fetch_assoc($result_products)):
                $allergy_raw = !empty($row['allergy']) ? $row['allergy'] : '';
                $allergy_arr = $allergy_raw ? array_map('trim', explode(',', $allergy_raw)) : [];
                $max_quantity = (int)$row['quantity'];
        ?>
            <form method="POST" action="store_detail.php?id=<?php echo urlencode($store_no); ?>">
                <input type="hidden" name="buy_store_name" value="<?php echo htmlspecialchars($store_name); ?>">
                <input type="hidden" name="buy_product_name" value="<?php echo htmlspecialchars($row['product_name']); ?>">
                
                <div class="product-card">
                    <div class="product-img-area">
                        <?php if (!empty($row['product_img'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($row['product_img']); ?>" alt="商品圖">
                        <?php else: ?>
                            <img src="image/盲盒.png" alt="驚喜盲盒">
                        <?php endif; ?>
                        <div class="blindbox-label">✦ 驚喜盲盒 ✦</div>
                    </div>
     
                    <div class="product-body">
                        <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                        
                        <?php if (!empty($row['product_desc'])): ?>
                            <div class="product-desc"><?php echo htmlspecialchars($row['product_desc']); ?></div>
                        <?php endif; ?>
     
                        <div class="allergy-row">
                            <span class="allergy-label"><i class="fa-solid fa-triangle-exclamation"></i> 可能含：</span>
                            <?php if (!empty($allergy_arr)): ?>
                                <?php foreach ($allergy_arr as $a): ?>
                                    <span class="allergy-tag"><?php echo htmlspecialchars($a); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="allergy-tag none">無特定過敏原標示</span>
                            <?php endif; ?>
                        </div>
     
                        <div class="product-qty-row">
                            <i class="fa-solid fa-box-open"></i> 今日限量 <?php echo $max_quantity; ?> 份
                        </div>
                    </div>
     
                    <div class="product-action-area">
                        <div class="price-display">
                            $<?php echo (int)$row['price']; ?><span>元</span>
                        </div>
                        
                        <div class="quantity-controller">
                            <button type="button" class="qty-btn" onclick="alterQty(<?php echo $p_idx; ?>, -1)">−</button>
                            <input  name="buy_qty" class="qty-input" id="qty_<?php echo $p_idx; ?>" value="1" readonly>
                            <button type="button" class="qty-btn" onclick="alterQty(<?php echo $p_idx; ?>, 1, <?php echo $max_quantity; ?>)">＋</button>
                        </div>
                        
                        <button type="submit" class="add-cart-btn">
                            <i class="fa-solid fa-cart-plus"></i> 加購物車
                        </button>
                    </div>
                </div>
            </form>
        <?php
                $p_idx++;
            endwhile;
        else:
        ?>
            <div class="no-data">
                <i class="fa-regular fa-face-sad-tear"></i>
                今日該商家尚未上架剩食品項，明天再來看看吧！
            </div>
        <?php endif; ?>
        </div>
    </div>
 
    <div>
        <div class="section-header">
            <div class="section-title">💬 饕客評價 (<?php echo $total_reviews; ?>)</div>
            <div class="section-header-line"></div>
        </div>
 
        <div class="reviews-wrapper">
            <div class="reviews-list">
            <?php
            if (mysqli_num_rows($result_reviews) > 0):
                while ($rev = mysqli_fetch_assoc($result_reviews)):
                    $initials = mb_substr($rev['user_name'], 0, 1);
            ?>
                <div class="review-item">
                    <div class="reviewer-avatar">
                        <?php if (!empty($rev['user_avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($rev['user_avatar']); ?>" alt="頭像">
                        <?php else: ?>
                            <div class="avatar-initial"><?php echo htmlspecialchars($initials); ?></div>
                        <?php endif; ?>
                    </div>
 
                    <div class="review-body">
                        <div class="review-top-row">
                            <span class="reviewer-name"><?php echo htmlspecialchars($rev['user_name']); ?></span>
                            <span class="review-date"><?php echo htmlspecialchars($rev['review_date']); ?></span>
                        </div>
                        <div class="review-stars">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $cls = ($i <= $rev['rating']) ? 'fa-solid fa-star' : 'fa-regular fa-star empty';
                                echo "<i class=\"$cls\"></i>";
                            }
                            ?>
                        </div>
                        <div class="review-text"><?php echo htmlspecialchars($rev['comment_text']); ?></div>
 
                        <?php if (!empty($rev['reply_text'])): ?>
                        <div class="reply-bubble">
                            <div class="reply-bubble-head">
                                <span>🌿 店家回覆</span>
                                <span><?php echo htmlspecialchars($rev['reply_date']); ?></span>
                            </div>
                            <div class="reply-bubble-text"><?php echo htmlspecialchars($rev['reply_text']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <div class="no-data">
                    <i class="fa-regular fa-comment-dots"></i>
                    目前尚無評論，成為第一位留下心得的饕客吧！
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
 
</div>
 
<script>
    function alterQty(idx, delta, max = 99) {
        const input = document.getElementById('qty_' + idx);
        let val = parseInt(input.value) + delta;
        if (val < 1) val = 1;
        if (val > max) { val = max; alert('⚠️ 已達今日該商家剩食盲盒的供應上限囉！'); }
        input.value = val;
    }
</script>
</body>
</html>
<?php mysqli_close($link); ?>