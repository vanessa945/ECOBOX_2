<?php
session_start();
include("db.php");
$page_title = "評論管理";
$active_page = "reviews";
// 請確保有檢查管理員登入狀態
include("admin-header.php");

// 💡 連接至 store_reviews 資料表，並依照評論時間倒序排列
$sql = "SELECT * FROM store_reviews ORDER BY review_date DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("<div style='padding:20px; color:red;'>SQL 查詢錯誤: " . mysqli_error($conn) . "。請檢查資料表名稱是否正確！</div>");
}
$total_reviews = mysqli_num_rows($result);
?>

<style>
    /* ── EcoBox 設計系統：共用與評論卡片專屬樣式 ── */
    :root {
        --green-deep:  #659287; 
        --green-mid:   #3a5a40;
        --accent-gold: #b8975a;
        --bg-white:    #ffffff;
        --text-dark:   #23342b;
        --text-muted:  #5a6e63;
        --danger-red:  #e74c3c;
    }

    .reviews-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* 頂部控制列 (標題與搜尋) */
    .page-header-ctrl {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    /* 搜尋框風格 */
    .search-wrap { 
        position: relative; 
    }
    .search-wrap input {
        padding: 10px 16px 10px 40px;
        border: 1px solid rgba(101, 146, 135, 0.3);
        border-radius: 20px;
        width: 280px;
        outline: none;
        font-size: 15px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(35, 52, 43, 0.05);
    }
    .search-wrap input:focus {
        border-color: var(--green-deep);
        box-shadow: 0 0 0 3px rgba(101, 146, 135, 0.1);
    }
    .search-wrap::before {
        content: '\f002'; /* FontAwesome 放大鏡 */
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute; left: 14px; top: 50%;
        transform: translateY(-50%); 
        font-size: 14px;
        color: var(--text-muted);
    }

    /* 統計晶片 */
    .stat-badge {
        background: rgba(101, 146, 135, 0.15);
        color: var(--green-mid);
        padding: 6px 16px; 
        border-radius: 20px; 
        font-size: 14px; 
        display: inline-block;
        margin-bottom: 20px;
    }
    .stat-badge strong { font-weight: 800; font-size: 15px; }

    /* 評論卡片本體 */
    .review-card {
        background: var(--bg-white);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
        border: 1px solid rgba(101, 146, 135, 0.15);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .review-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(35, 52, 43, 0.1);
    }

    /* 卡片標頭：發訊者資訊與狀態 */
    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .review-info-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    /* 💡 新增：更明顯的星等外框與字體樣式 */
    .rating-stars-box {
        background: #fffdf5; /* 淡淡的黃底凸顯 */
        border: 1px solid #fdebd0;
        padding: 6px 14px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 6px rgba(241, 196, 15, 0.15);
    }
    .rating-stars {
        color: #f39c12; /* 更亮眼的橘黃色 */
        letter-spacing: 3px;
        font-size: 20px; /* 加大字體 */
        text-shadow: 0 1px 1px rgba(0,0,0,0.1);
    }
    .rating-score {
        font-size: 16px;
        font-weight: 800;
        color: #d35400; /* 深橘色分數 */
    }

    /* 評論區塊 */
    .review-content {
        font-size: 15px;
        color: var(--text-dark);
        background: #fdfdfc;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        border-left: 4px solid var(--accent-gold);
        line-height: 1.6;
    }

    /* 回覆區塊 */
    .review-reply-box {
        background: rgba(101, 146, 135, 0.08);
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid var(--green-deep);
        line-height: 1.6;
        font-size: 15px;
    }
    .review-reply-title {
        font-weight: 700;
        color: var(--green-deep);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* 刪除按鈕 (比照你的截圖樣式) */
    .btn-delete {
        background: #fde8e8;
        color: var(--danger-red);
        border: none;
        padding: 6px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: bold;
        transition: 0.2s;
    }
    .btn-delete:hover {
        background: #f9d5d5;
    }

    /* 空狀態 */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
        background: var(--bg-white);
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
    }
</style>

<main class="admin-main" style="max-width: 1100px; margin: 0 auto; padding: 20px;">
    
    <div class="page-header-ctrl">
        <h2 style="font-size: 2.2rem; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fa-regular fa-comment-dots"></i> 評論管理
        </h2>
        <div class="search-wrap">
            <input type="text" id="search-input" placeholder="搜尋評論內容、使用者或商家..." oninput="filterReviews(this.value)">
        </div>
    </div>
    
    <div class="stat-badge">
        總評論數 <strong><?= $total_reviews ?></strong>
    </div>

    <div class="reviews-container">
        <?php if ($total_reviews == 0): ?>
            <div class="empty-state">
                <i class="fa-regular fa-face-smile" style="font-size: 40px; margin-bottom: 15px; color: rgba(101,146,135,0.5);"></i>
                <p style="font-size: 16px;">目前平台還沒有任何評論紀錄喔！</p>
            </div>
        <?php else: ?>
            <?php 
            while ($row = mysqli_fetch_assoc($result)): 
                // 將所有可能被搜尋的文字組合起來放入 data 屬性
                $search_text = htmlspecialchars(strtolower(($row['comment_text'] ?? '') . ' ' . ($row['user_name'] ?? '') . ' ' . ($row['store_name'] ?? '')));
                // 計算星星數
                $rating_val = intval($row['rating'] ?? 0);
                $stars = str_repeat('★', $rating_val) . str_repeat('☆', 5 - $rating_val);
            ?>
                <div class="review-card" data-searchtext="<?= $search_text ?>">
                    
                    <div class="review-header">
                        <div class="review-info-group">
                            
                            <div style="font-weight: bold; font-size: 15px; color: #333; display: flex; align-items: center; flex-wrap: wrap;">
                                👤 消費者：<span style="color:#2c3e50; margin-right: 4px;"><?= htmlspecialchars($row['user_name'] ?? '匿名') ?></span> 
                                <span style="margin: 0 8px; color: #ccc;">|</span>
                                🏪 評論商家：<span style="color: var(--green-mid); font-weight: normal;"><?= htmlspecialchars($row['store_name'] ?? '未知商家') ?></span>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 15px; margin-top: 4px;">
                                <div class="rating-stars-box">
                                    <span class="rating-stars"><?= $stars ?></span>
                                </div>
                                <span style="font-size: 14px; color: #999;"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($row['review_date'] ?? '無日期') ?></span>
                            </div>

                        </div>
                        
                        <button onclick="deleteReview(<?= $row['No'] ?>)" class="btn-delete" title="刪除此評論">
                            刪除
                        </button>
                    </div>

                    <div class="review-content">
                        <?= nl2br(htmlspecialchars($row['comment_text'] ?? '')) ?>
                    </div>
                    
                    <?php if (!empty($row['reply_text'])): ?>
                        <div class="review-reply-box">
                            <div class="review-reply-title">
                                商家回覆 
                                <?php if(!empty($row['reply_date'])): ?>
                                    <span style="font-size: 12px; font-weight: normal; color: var(--text-muted); margin-left: auto;">
                                        <?= htmlspecialchars($row['reply_date']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?= nl2br(htmlspecialchars($row['reply_text'])) ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    // 搜尋過濾功能
    function filterReviews(keyword) {
        const cards = document.querySelectorAll('.review-card');
        const kw = keyword.toLowerCase().trim();
        cards.forEach(card => {
            const text = card.dataset.searchtext || '';
            card.style.display = (!kw || text.includes(kw)) ? '' : 'none';
        });
    }

    // 刪除功能 (根據你的資料表，主鍵應該是 No)
    function deleteReview(id) {
        if (confirm('確定要刪除這則評論嗎？此操作無法復原。')) {
            // 請確保你的 admin_delete.php 接收 id 後有對應 store_reviews 的刪除邏輯
            window.location.href = 'admin_delete.php?id=' + id + '&type=store_review';
        }
    }
</script>

<?php
if(isset($conn)) {
    mysqli_close($conn);
}
?>