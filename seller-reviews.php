<?php
session_start();
include("db.php");

if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = 1; 
}
$current_id = $_SESSION['store_name'];

// 1. 抓取商家基本資料
$sql_store = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$res_store = mysqli_query($conn, $sql_store);
$data_store = mysqli_fetch_assoc($res_store);
$store_name = $data_store ? $data_store['store_name'] : "麥當勞-高雄楠梓店";

// 2. 處理商家提交的回覆 (新增或修改皆用此邏輯覆蓋)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $review_id = (int)$_POST['review_id'];
    $reply_text = mysqli_real_escape_string($conn, $_POST['reply_text']);
    
    // 🟢 修正：將 WHERE 條件的 review_id = 修改為 No = 
    $sql_update = "UPDATE store_reviews SET 
                  reply_text = '$reply_text', 
                  reply_date = NOW() 
                  WHERE No = $review_id AND store_name = '$current_id'";
                   
    mysqli_query($conn, $sql_update);
    header("Location: seller-reviews.php");
    exit();
}

// 3. 抓取店鋪所有評論 (最新在最上面)
$sql_reviews = "SELECT r.*, u.user_name, u.user_avatar 
                FROM store_reviews r
                LEFT JOIN consumer u ON r.user_name = u.user_name
                WHERE r.store_name = '$current_id' 
                ORDER BY r.review_date DESC";
$res_reviews = mysqli_query($conn, $sql_reviews);
$reviews_list = [];
while ($row = mysqli_fetch_assoc($res_reviews)) {
    $reviews_list[] = $row;
}

// 注意：這裡不關閉 $conn，讓 seller_header.php 可以順利使用
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>評論管理 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <style>
    /* 這裡僅保留評論區的專屬 CSS，共用的顏色變數與排版已交由 seller_header.php 處理 */
    body {
      background-color: #f7f6f2 !important;
    }

    /* 內容主容器：為了配合 Header 固定在上方，加上 top margin */
    .reviews-container {
      max-width: 900px; 
      margin: 40px auto 40px; 
      padding: 0 20px; 
      box-sizing: border-box;
    }

    .page-title { font-size: 2.4rem; font-weight: 700; margin-bottom: 5px; }
    .page-sub { color: #5a6e63; font-size: 1.4rem; margin-bottom: 25px; }

    /* 📜 評論列表滾動滑桿區域 */
    .reviews-scroll-box {
      max-height: 650px; overflow-y: auto; padding-right: 10px;
      background: transparent;
    }

    /* 自訂滾動條樣式 */
    .reviews-scroll-box::-webkit-scrollbar { width: 8px; }
    .reviews-scroll-box::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); border-radius: 10px; }
    .reviews-scroll-box::-webkit-scrollbar-thumb { background: #8ab8a1; border-radius: 10px; }
    .reviews-scroll-box::-webkit-scrollbar-thumb:hover { background: #6cae8b; }

    /* 💬 評論卡片 */
    .review-card {
      background: #ffffff; border: 1px solid rgba(108, 174, 139, 0.2); border-radius: 14px;
      padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
    }

    /* 消費者區塊 */
    .user-block { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 14px; }
    .user-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #e3e2d4; }
    .user-info { flex-grow: 1; }
    .user-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
    .user-name { font-size: 1.5rem; font-weight: 700; color: #23342b; }
    .review-date { font-size: 1.1rem; color: #5a6e63; }
    
    .stars-row { color: #dba13a; font-size: 1.3rem; }
    .comment-text { font-size: 1.45rem; line-height: 1.6; color: #23342b; margin: 8px 0 4px 0; }

    /* 商家回覆對話框區塊 */
    .reply-area {
      margin-top: 15px; padding-top: 15px; border-top: 1px dashed rgba(108, 174, 139, 0.2);
    }

    /* 已回覆狀態盒子 */
    .seller-reply-box {
      display: flex; align-items: flex-start; gap: 12px; background: rgba(108,174,139,0.08);
      padding: 14px; border-radius: 10px; border-left: 4px solid #6cae8b;
      position: relative;
    }
    .seller-avatar { width: 36px; height: 36px; border-radius: 50%; background: #6cae8b; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; font-weight: bold; flex-shrink: 0; }
    .reply-content { flex-grow: 1; padding-right: 70px; } /* 留空間給修改按鈕 */
    .reply-meta { display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-bottom: 4px; }
    .reply-text-display { font-size: 1.35rem; line-height: 1.5; color: #23342b; word-break: break-all; }

    /* 🟢 修改按鈕樣式 */
    .edit-reply-btn {
      position: absolute; right: 14px; top: 14px; background: transparent;
      border: 1px solid #8ab8a1; color: #5a6e63; padding: 4px 10px;
      border-radius: 6px; cursor: pointer; font-size: 1.1rem; transition: all 0.2s;
    }
    .edit-reply-btn:hover { background: #6cae8b; color: white; border-color: #6cae8b; }

    /* 整合式輸入表單 */
    .reply-form { display: flex; flex-direction: column; gap: 10px; width: 100%; }
    .input-wrapper {
      display: flex; align-items: center; background: #ffffff; border: 1px solid #8ab8a1;
      border-radius: 30px; padding: 4px 8px 4px 16px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
    }
    .reply-input {
      flex-grow: 1; border: none; outline: none; font-size: 1.35rem; padding: 8px 0; font-family: inherit;
    }
    .tool-btn {
      background: transparent; border: none; font-size: 1.8rem; cursor: pointer; padding: 6px;
      color: #5a6e63; transition: color 0.2s; position: relative;
    }
    .tool-btn:hover { color: #dba13a; }
    
    .submit-btn {
      background: #6cae8b; color: white; border: none; border-radius: 50%;
      width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: background 0.2s; font-size: 1.3rem; margin-left: 5px;
    }
    .submit-btn:hover { background: #4e8f70; }

    /* 貼圖彈出盒 */
    .sticker-popup {
      display: none; position: absolute; bottom: 45px; right: 0; background: white;
      border: 1px solid #8ab8a1; border-radius: 12px; padding: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15); z-index: 10; width: 220px;
    }
    .sticker-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 2.2rem; text-align: center; }
    .sticker-item { cursor: pointer; transition: transform 0.1s; user-select: none; }
    .sticker-item:hover { transform: scale(1.2); }
  </style>

  <script>
    // 切換貼圖小盒子的顯示
    function toggleStickerBox(reviewId, event) {
      if(event) event.stopPropagation();
      const popup = document.getElementById('sticker-popup-' + reviewId);
      const isDisplayed = popup.style.display === 'block';
      
      // 先關閉所有其他的貼圖盒
      document.querySelectorAll('.sticker-popup').forEach(p => p.style.display = 'none');
      
      if(!isDisplayed) {
        popup.style.display = 'block';
      }
    }

    // 點擊貼圖填入輸入框
    function appendSticker(reviewId, stickerText) {
      const input = document.getElementById('input-' + reviewId);
      input.value += stickerText;
      document.getElementById('sticker-popup-' + reviewId).style.display = 'none';
      input.focus();
    }

    // 🟢 點擊修改按鈕：隱藏已回覆區塊，顯示輸入表單
    function enableEditReply(reviewId) {
      document.getElementById('display-box-' + reviewId).style.display = 'none';
      document.getElementById('form-box-' + reviewId).style.display = 'block';
      document.getElementById('input-' + reviewId).focus();
    }

    // 點擊全網頁時關閉貼圖小盒子
    document.addEventListener('click', () => {
      document.querySelectorAll('.sticker-popup').forEach(p => p.style.display = 'none');
    });
  </script>
</head>
<body>

  <?php 
    $link = $conn; 
    include("seller_header.php"); 
  ?>

  <main class="reviews-container">
    <div class="welcome-section">
      <h2 class="page-title">💬 顧客評論與回覆管理</h2>
      <p class="page-sub">在這裡查看消費者對店鋪剩食餐點的真實回饋，並進行雙向互動或修改回覆。</p>
    </div>

    <div class="reviews-scroll-box">
      
      <?php if(empty($reviews_list)): ?>
        <div class="review-card" style="text-align: center; color: #5a6e63;">
          目前尚無任何消費者評論。
        </div>
      <?php endif; ?>

      <?php foreach ($reviews_list as $review): ?>
        <div class="review-card">
          <div class="user-block">
            <img src="<?php echo htmlspecialchars($review['user_avatar'] ? $review['user_avatar'] : 'default_user.png'); ?>" class="user-avatar" alt="User">
            <div class="user-info">
              <div class="user-meta">
                <span class="user-name"><?php echo htmlspecialchars($review['user_name']); ?></span>
                <span class="review-date"><?php echo date('Y-m-d H:i', strtotime($review['review_date'])); ?></span>
              </div>
              <div class="stars-row">
                <?php 
                  for ($i = 1; $i <= 5; $i++) {
                      echo $i <= $review['rating'] ? '★' : '☆';
                  }
                ?>
              </div>
              <p class="comment-text"><?php echo htmlspecialchars($review['comment_text']); ?></p>
            </div>
          </div>

          <div class="reply-area">
            
            <?php if (!empty($review['reply_text'])): ?>
              <div class="seller-reply-box" id="display-box-<?php echo $review['No']; ?>">
                <div class="seller-avatar">店</div>
                <div class="reply-content">
                  <div class="reply-meta">
                    <span style="color:#6cae8b;">商家回覆</span>
                    <span style="color:#5a6e63; font-weight:normal; font-size:1.1rem;">
                      <?php echo date('Y-m-d H:i', strtotime($review['reply_date'])); ?>
                    </span>
                  </div>
                  <div class="reply-text-display">
                    <?php echo htmlspecialchars($review['reply_text']); ?>
                  </div>
                </div>
                <button type="button" class="edit-reply-btn" onclick="enableEditReply(<?php echo $review['No']; ?>)">
                  <i class="fa-solid fa-pen"></i> 修改
                </button>
              </div>
            <?php endif; ?>

            <div id="form-box-<?php echo $review['No']; ?>" style="<?php echo !empty($review['reply_text']) ? 'display:none;' : ''; ?>">
              <form action="seller-reviews.php" method="POST" class="reply-form">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="review_id" value="<?php echo $review['No']; ?>">

                <div class="input-wrapper">
                  <input type="text" name="reply_text" id="input-<?php echo $review['No']; ?>" class="reply-input" 
                         placeholder="輸入回覆內容..." value="<?php echo htmlspecialchars($review['reply_text']); ?>" required>
                  
                  <button type="button" class="tool-btn" onclick="toggleStickerBox(<?php echo $review['No']; ?>, event)" title="選擇表情貼圖">
                    😊
                    <div class="sticker-popup" id="sticker-popup-<?php echo $review['No']; ?>" onclick="event.stopPropagation()">
                      <div class="sticker-grid">
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '❤️')">❤️</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '👍')">👍</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '✨')">✨</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '🥰')">🥰</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '🙏')">🙏</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '🍔')">🍔</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '🎉')">🎉</div>
                        <div class="sticker-item" onclick="appendSticker(<?php echo $review['No']; ?>, '🤩')">🤩</div>
                      </div>
                    </div>
                  </button>

                  <button type="submit" class="submit-btn" title="傳送回覆">
                    <i class="fa-solid fa-paper-plane"></i>
                  </button>
                </div>
              </form>
            </div>

          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </main>

  <?php mysqli_close($conn); ?>
</body>
</html>