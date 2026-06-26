<?php
session_start();
include("db.php");
// 請確保有檢查管理員登入狀態
include("admin-header.php");
?>

<style>
    /* ── 問題中心專屬卡片樣式 ── */
    .questions-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .msg-card {
        background: var(--bg-white, #ffffff);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
        border: 1px solid rgba(101, 146, 135, 0.15);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .msg-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(35, 52, 43, 0.1);
    }

    .msg-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }

    .msg-sender-info {
        font-size: 15px;
        color: var(--text-muted, #5a6e63);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .msg-sender-info strong {
        color: var(--text-dark, #23342b);
        font-size: 16px;
    }

    .msg-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .badge-pending {
        background: #fff0e6;
        color: #d97706; /* 橘黃色警告感 */
    }

    .badge-replied {
        background: #e6f4ea;
        color: var(--green-mid, #3a5a40);
    }

    .msg-content {
        font-size: 16px;
        color: var(--text-dark, #23342b);
        background: #fdfdfc;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        border-left: 4px solid var(--accent-gold, #b8975a);
        line-height: 1.6;
    }

    .msg-content-title {
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--accent-gold, #b8975a);
    }

    .msg-reply-box {
        background: rgba(101, 146, 135, 0.08); /* 淡綠色背景 */
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid var(--green-deep, #659287);
        line-height: 1.6;
    }

    .msg-reply-title {
        font-weight: 700;
        color: var(--green-deep, #659287);
        margin-bottom: 8px;
    }

    .reply-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .reply-form textarea {
        width: 100%;
        height: 80px;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        resize: vertical;
        font-family: inherit;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s;
    }

    .reply-form textarea:focus {
        border-color: var(--green-deep, #659287);
        box-shadow: 0 0 0 3px rgba(101, 146, 135, 0.1);
    }

    .reply-btn {
        align-self: flex-start;
        background: var(--green-deep, #659287);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .reply-btn:hover {
        background: var(--green-mid, #3a5a40);
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-muted, #5a6e63);
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
    }
</style>

<main class="admin-main" style="max-width: 1100px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 25px;">
        <h2 style="font-size: 2.2rem; color: #23342b; margin: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-comments"></i> 問題中心管理
        </h2>
    </div>
    
    <div class="questions-container">
        <?php
        $res = mysqli_query($conn, "SELECT * FROM admin_messages ORDER BY created_at DESC");
        
        if (mysqli_num_rows($res) > 0) {
            // 使用 while 搭配 HTML 分離寫法，程式碼更乾淨
            while ($row = mysqli_fetch_assoc($res)):
                $is_replied = ($row['status'] == 'replied');
                
                // 設定狀態樣式與文字
                $badge_class = $is_replied ? 'badge-replied' : 'badge-pending';
                $badge_icon  = $is_replied ? '<i class="fa-solid fa-check-circle"></i>' : '<i class="fa-solid fa-clock"></i>';
                $badge_text  = $is_replied ? '已回覆' : '待處理';
                
                // 轉換發訊者類型為中文 (視你的資料庫設定調整)
                $sender_tw = ($row['sender_type'] == 'store') ? '商家' : '消費者';
        ?>
            <div class="msg-card">
                
                <div class="msg-header">
                    <div class="msg-sender-info">
                        <strong><i class="fa-solid fa-user-tag"></i> <?= htmlspecialchars($sender_tw) ?> (ID: <?= htmlspecialchars($row['sender_id']) ?>)</strong>
                        <span><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($row['created_at']) ?></span>
                    </div>
                    <div class="msg-badge <?= $badge_class ?>">
                        <?= $badge_icon ?> <?= $badge_text ?>
                    </div>
                </div>

                <div class="msg-content">
                    <div class="msg-content-title"><i class="fa-solid fa-circle-question"></i> 問題內容</div>
                    <?= nl2br(htmlspecialchars($row['message_content'])) ?>
                </div>
                
                <?php if ($is_replied): ?>
                    <div class="msg-reply-box">
                        <div class="msg-reply-title"><i class="fa-solid fa-reply-all"></i> 管理員回覆</div>
                        <?= nl2br(htmlspecialchars($row['admin_reply'])) ?>
                    </div>
                <?php else: ?>
                    <form action="reply_action.php" method="POST" class="reply-form">
                        <input type="hidden" name="msg_id" value="<?= htmlspecialchars($row['message_id']) ?>">
                        <textarea name="reply" placeholder="請輸入您的回覆內容..." required></textarea>
                        <button type="submit" class="reply-btn">
                            <i class="fa-solid fa-paper-plane"></i> 送出回覆
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        <?php 
            endwhile; 
        } else { 
        ?>
            <div class="empty-state">
                <i class="fa-regular fa-face-smile" style="font-size: 40px; margin-bottom: 10px; color: var(--green-pale);"></i>
                <p>目前沒有任何問題記錄，一切運作良好！</p>
            </div>
        <?php 
        } 
        ?>
    </div>
</main>

<?php
// 在此關閉資料庫
if(isset($conn)) {
    mysqli_close($conn);
}
?>