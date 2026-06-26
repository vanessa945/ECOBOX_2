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
 
// ── 💡【後端動作】：當消費者提交店家評論時 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $r_store_name = mysqli_real_escape_string($link, $_POST['review_store_name']);
    $r_rating = (int)$_POST['review_rating'];
    $r_comment = mysqli_real_escape_string($link, $_POST['review_comment']);
    $uName_escaped = mysqli_real_escape_string($link, $uName);
    
    // 預設消費者頭像
    $user_avatar = "image/預設頭像.png"; 

    // 將資料新增到 store_reviews 資料表
    $sql_insert_review = "INSERT INTO store_reviews (store_name, user_name, user_avatar, rating, comment_text, review_date) 
                          VALUES ('$r_store_name', '$uName_escaped', '$user_avatar', $r_rating, '$r_comment', NOW())";
    
    if (mysqli_query($link, $sql_insert_review)) {
        echo "<script>alert('✨ 感謝您的評價！評論已成功更新至該商家頁面。'); window.location.href='orders.php';</script>";
    } else {
        echo "<script>alert('評論失敗，請檢查資料欄位。'); window.location.href='orders.php';</script>";
    }
    mysqli_close($link);
    exit();
}

// ── 💡【修正查詢】：撈取消費者的歷史訂單，並自動關聯店家 No 供按鈕使用 ──
// 修正 s.id 為 s.No，以及 s.name 為 s.store_name
$uName_escaped = mysqli_real_escape_string($link, $uName);
$sql_orders = "SELECT o.*, s.No AS store_no 
               FROM orders_history o 
               LEFT JOIN store s ON o.store_name = s.store_name 
               WHERE o.user_name = '$uName_escaped' AND o.status = 2 
               ORDER BY o.No DESC";
$result_orders = mysqli_query($link, $sql_orders);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>歷史訂單 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        /* 💡 這裡只保留歷史訂單頁面專屬的 CSS，共用的交給 shared_header */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        /* ── Main Layout ── */
        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: #132a13; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        /* ── 歷史訂單容器 ── */
        .order-card-group { display: flex; flex-direction: column; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.04); padding: 10px 0; }
        .order-block-unit { border-bottom: 1px solid #eef0f2; padding: 24px 32px; display: flex; flex-direction: column; gap: 16px; }
        .order-block-unit:last-child { border-bottom: none; }

        .order-main-row { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .order-left-info { display: flex; align-items: center; gap: 20px; }
        
        .store-avatar-circle { width: 64px; height: 64px; border-radius: 50%; overflow: hidden; background: #fafafa; border: 1px solid #eef0f2; flex-shrink: 0; }
        .store-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        
        .order-meta-text { display: flex; flex-direction: column; gap: 4px; }
        .order-store-name { font-size: 19px; font-weight: 800; color: #111111; }
        .order-date-price { font-size: 14px; color: #555; font-weight: 500; }
        .order-items-desc { font-size: 14px; color: #888888; }

        .order-btn-actions { display: flex; gap: 12px; }
        .btn-flat-light { background: #f0f0f0; border: none; border-radius: 6px; padding: 8px 18px; font-size: 14px; font-weight: 700; color: #1a1a1a; cursor: pointer; transition: all 0.2s; }
        .btn-flat-light:hover { background: #e2e2e0; }
        .btn-flat-light.active-comment { background: #f3d078; color: #000000; }

        /* ── 評論面板 ── */
        .expand-review-panel { display: none; margin-top: 5px; padding-top: 18px; border-top: 1px dashed #dddddd; align-items: flex-start; gap: 16px; transition: all 0.3s ease; }
        .user-avatar-circle { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 1px solid #eef0f2; background: #e9ecef; flex-shrink: 0; }
        .user-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        
        .review-input-box-wrapper { flex: 1; display: flex; flex-direction: column; gap: 10px; }
        
        /* 星星樣式 */
        .interactive-stars { display: flex; gap: 6px; color: #ccc; font-size: 22px; cursor: pointer; margin-bottom: 2px; }
        .interactive-stars i { transition: color 0.15s; }
        .interactive-stars i.selected { color: #f3d078; }

        /* 輸入區 */
        .comment-input-container { display: flex; align-items: center; border: 1.5px solid #222; border-radius: 4px; padding: 0 14px; background: #fff; width: 100%; height: 42px; position: relative; }
        .comment-text-field { flex: 1; height: 100%; border: none; outline: none; font-size: 14px; font-weight: 500; font-family: inherit; color: #111; background: transparent; }
        
        .input-tool-icon-btn { background: none; border: none; font-size: 20px; color: #333; cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
        .input-tool-icon-btn:hover { transform: scale(1.15); color: #000; }
        
        .emoji-mini-popover { position: absolute; bottom: 110%; right: 40px; background: #fff; border: 1px solid #ccc; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 8px; display: none; gap: 8px; z-index: 10; }
        .emoji-mini-popover span { font-size: 18px; cursor: pointer; transition: transform 0.1s; }
        .emoji-mini-popover span:hover { transform: scale(1.25); }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">
            <div class="page-block-title">
                <i class="fa-regular fa-clipboard" style="color: #3a5a40;"></i> 買家歷史訂單紀錄
            </div>
            
            <div class="order-card-group">
                <?php
                if ($result_orders && mysqli_num_rows($result_orders) > 0) {
                    $u_idx = 0;
                    while ($row_order = mysqli_fetch_assoc($result_orders)) {
                        $formatted_date = date("n月j日", strtotime($row_order['order_date']));
                        $store_img = "image/廣告" . (($u_idx % 3) + 1) . ".png";
                ?>
                        <div class="order-block-unit">
                            <div class="order-main-row">
                                <div class="order-left-info">
                                    <div class="store-avatar-circle"><img src="<?php echo $store_img; ?>" alt="店家頭像"></div>
                                    <div class="order-meta-text">
                                        <span class="order-store-name"><?php echo htmlspecialchars($row_order['store_name']); ?></span>
                                        <span class="order-date-price"><?php echo $formatted_date; ?> · $<?php echo (int)$row_order['total_price']; ?></span>
                                        <span class="order-items-desc"><?php echo htmlspecialchars($row_order['items_desc']); ?></span>
                                    </div>
                                </div>
                                <div class="order-btn-actions">
                                    <button type="button" class="btn-flat-light" id="comment_toggle_btn_<?php echo $u_idx; ?>" onclick="toggleReviewPanel('<?php echo $u_idx; ?>')">評論</button>
                                    <button type="button" class="btn-flat-light" onclick="window.location.href='store_detail.php?id=<?php echo $row_order['store_no']; ?>'">檢視商店</button>
                                </div>
                            </div>

                            <form method="POST" action="orders.php">
                                <input type="hidden" name="action" value="submit_review">
                                <input type="hidden" name="review_store_name" value="<?php echo htmlspecialchars($row_order['store_name']); ?>">
                                <input type="hidden" name="review_rating" id="score_input_<?php echo $u_idx; ?>" value="0">

                                <div class="expand-review-panel" id="review_panel_<?php echo $u_idx; ?>">
                                    <div class="user-avatar-circle"><img src="image/預設頭像.png" alt="使用者頭像"></div>
                                    <div class="review-input-box-wrapper">
                                        <div class="interactive-stars" id="star_group_<?php echo $u_idx; ?>">
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $u_idx; ?>', 1)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $u_idx; ?>', 2)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $u_idx; ?>', 3)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $u_idx; ?>', 4)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $u_idx; ?>', 5)"></i>
                                        </div>
                                        
                                        <div class="comment-input-container">
                                            <input type="text" name="review_comment" class="comment-text-field" id="comment_input_<?php echo $u_idx; ?>" placeholder="輸入店家評論..." required>
                                            
                                            <button type="button" class="input-tool-icon-btn" onclick="toggleEmojiPopover(event, '<?php echo $u_idx; ?>')"><i class="bi bi-emoji-smile"></i></button>
                                            
                                            <button type="submit" class="input-tool-icon-btn" style="color: #3a5a40;"><i class="bi bi-send-fill"></i></button>

                                            <div class="emoji-mini-popover" id="emoji_pop_<?php echo $u_idx; ?>">
                                                <span onclick="appendEmoji('<?php echo $u_idx; ?>', '👍')">👍</span>
                                                <span onclick="appendEmoji('<?php echo $u_idx; ?>', '😋')">😋</span>
                                                <span onclick="appendEmoji('<?php echo $u_idx; ?>', '❤️')">❤️</span>
                                                <span onclick="appendEmoji('<?php echo $u_idx; ?>', '🌿')">🌿</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                <?php
                        $u_idx++;
                    }
                } else {
                    // 若資料庫內無資料，展示原本的 Demo 區塊
                    $sample_orders = [
                        ["name" => "星巴克高雄楠梓店", "date" => "6月11日", "price" => 500, "items" => "今日剩食惜食盲盒 × 2份"],
                        ["name" => "多那之手作烘焙", "date" => "5月9日", "price" => 350, "items" => "超值驚喜麵包盲盒 × 1份"]
                    ];
                    foreach ($sample_orders as $index => $sample) {
                        $demo_idx = "demo_" . $index;
                ?>
                        <div class="order-block-unit">
                            <div class="order-main-row">
                                <div class="order-left-info">
                                    <div class="store-avatar-circle"><img src="image/廣告<?php echo ($index+1); ?>.png" alt="店家頭像"></div>
                                    <div class="order-meta-text">
                                        <span class="order-store-name"><?php echo $sample['name']; ?></span>
                                        <span class="order-date-price"><?php echo $sample['date']; ?> · $<?php echo $sample['price']; ?></span>
                                        <span class="order-items-desc"><?php echo $sample['items']; ?></span>
                                    </div>
                                </div>
                                <div class="order-btn-actions">
                                    <button type="button" class="btn-flat-light" id="comment_toggle_btn_<?php echo $demo_idx; ?>" onclick="toggleReviewPanel('<?php echo $demo_idx; ?>')">評論</button>
                                    <button type="button" class="btn-flat-light" onclick="alert('導航前往該店家詳情選購頁面')">檢視商店</button>
                                </div>
                            </div>

                            <form method="POST" action="orders.php" onsubmit="event.preventDefault(); alert('✨ 測試成功：Demo 評論數據已寫入！'); window.location.reload();">
                                <input type="hidden" name="review_rating" id="score_input_<?php echo $demo_idx; ?>" value="0">
                                <div class="expand-review-panel" id="review_panel_<?php echo $demo_idx; ?>">
                                    <div class="user-avatar-circle"><img src="image/預設頭像.png" alt="使用者頭像"></div>
                                    <div class="review-input-box-wrapper">
                                        
                                        <div class="interactive-stars" id="star_group_<?php echo $demo_idx; ?>">
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $demo_idx; ?>', 1)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $demo_idx; ?>', 2)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $demo_idx; ?>', 3)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $demo_idx; ?>', 4)"></i>
                                            <i class="fa-regular fa-star" onclick="rateStars('<?php echo $demo_idx; ?>', 5)"></i>
                                        </div>

                                        <div class="comment-input-container">
                                            <input type="text" class="comment-text-field" id="comment_input_<?php echo $demo_idx; ?>" placeholder="輸入店家評論..." required>
                                            
                                            <button type="button" class="input-tool-icon-btn" onclick="toggleEmojiPopover(event, '<?php echo $demo_idx; ?>')"><i class="bi bi-emoji-smile"></i></button>
                                            
                                            <button type="submit" class="input-tool-icon-btn" style="color: #3a5a40;"><i class="bi bi-send-fill"></i></button>
                                            
                                            <div class="emoji-mini-popover" id="emoji_pop_<?php echo $demo_idx; ?>">
                                                <span onclick="appendEmoji('<?php echo $demo_idx; ?>', '👍')">👍</span>
                                                <span onclick="appendEmoji('<?php echo $demo_idx; ?>', '😋')">😋</span>
                                                <span onclick="appendEmoji('<?php echo $demo_idx; ?>', '❤️')">❤️</span>
                                                <span onclick="appendEmoji('<?php echo $demo_idx; ?>', '🌿')">🌿</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                <?php
                    }
                }
                ?>
            </div>
        </div>
    </main>

    <script>
        // 💡 這裡只保留評論展開、星星評分與表情符號的 JS
        
        function toggleReviewPanel(idx) {
            const panel = document.getElementById('review_panel_' + idx);
            const btn = document.getElementById('comment_toggle_btn_' + idx);
            const isOpen = panel.style.display === 'flex';
            
            panel.style.display = isOpen ? 'none' : 'flex';
            btn.classList.toggle('active-comment', !isOpen);

            // 展開時重設星等（預設 0 顆星，全部回歸空心黑框）
            if(!isOpen) {
                rateStars(idx, 0);
            }
        }

        function rateStars(panelIdx, scoreValue) {
            const inputField = document.getElementById('score_input_' + panelIdx);
            if(inputField) inputField.value = scoreValue;

            const stars = document.querySelectorAll('#star_group_' + panelIdx + ' i');
            
            stars.forEach((star, index) => {
                if (index < scoreValue) {
                    star.className = "fa-solid fa-star selected";
                } else {
                    star.className = "fa-regular fa-star";
                }
            });
        }

        function toggleEmojiPopover(event, idx) {
            event.stopPropagation();
            const pop = document.getElementById('emoji_pop_' + idx);
            const isOpen = pop.style.display === 'flex';
            
            document.querySelectorAll('.emoji-mini-popover').forEach(p => p.style.display = 'none');
            pop.style.display = isOpen ? 'none' : 'flex';
        }

        function appendEmoji(idx, emojiChar) {
            const input = document.getElementById('comment_input_' + idx);
            input.value += emojiChar;
            document.getElementById('emoji_pop_' + idx).style.display = 'none';
            input.focus();
        }

        window.addEventListener('click', function() {
            document.querySelectorAll('.emoji-mini-popover').forEach(p => p.style.display = 'none');
        });
    </script>
</body>
</html>
<?php mysqli_close($link); ?>