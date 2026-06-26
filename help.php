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

// ── 💡【後端動作】：當使用者點擊紙飛機提交聯絡管理者表單時 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_admin') {
    $user_msg = mysqli_real_escape_string($link, $_POST['admin_message']);
    $uName_escaped = mysqli_real_escape_string($link, $uName);

    $sql_insert = "INSERT INTO admin_messages (sender_id, sender_type, message_content, status, created_at) 
                   VALUES ('$uName_escaped', 'user', '$user_msg', 'pending', NOW())";

    if (mysqli_query($link, $sql_insert)) {
        require_once 'mail_helper.php';
        send_email_via_phpmailer(
            'lppei03056319@gmail.com',
            '【新客服提問】消費者 ' . $uName . ' 提出問題',
            "<p>消費者 <b>{$uName}</b> 提出新問題：</p><p>" . nl2br(htmlspecialchars($user_msg)) . "</p>"
        );
        echo "<script>alert('🚀 您的提問已成功送達管理者信箱！我們將會盡快回覆您。'); window.location.href='help.php';</script>";
    } else {
        echo "<script>alert('傳送失敗，請稍後再試。'); window.location.href='help.php';</script>";
    }
    mysqli_close($link);
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>買家幫助中心 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        /* 💡 只保留幫助中心專屬的 CSS，共用的交給 shared_header */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        /* ── Main Panel Layout ── */
        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 820px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        
        /* 頂部搜尋列排版區 */
        .search-block-row { display: flex; justify-content: flex-end; width: 100%; margin-bottom: 10px; }
        .search-box-wrapper { display: flex; align-items: center; border: 1.5px solid #222; border-radius: 25px; background: #fff; width: 280px; height: 38px; padding: 0 15px; }
        .search-input-field { flex: 1; border: none; outline: none; font-size: 14px; font-weight: 500; color: #111; font-family: inherit; }
        .search-trigger-btn { background: none; border: none; font-size: 16px; color: #333; cursor: pointer; display: flex; align-items: center; justify-content: center; padding-right: 5px; }

        /* ── FAQ 卡片大外殼 ── */
        .faq-card-group { display: flex; flex-direction: column; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.04); overflow: hidden; padding: 8px 0; }
        
        .faq-item-unit { border-bottom: 1px solid #eef0f2; display: flex; flex-direction: column; transition: background 0.2s; }
        .faq-item-unit:last-child { border-bottom: none; }
        
        /* 問題列 */
        .faq-question-bar { padding: 20px 32px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; }
        .faq-question-text { font-size: 17px; font-weight: 800; color: #111111; display: flex; align-items: center; gap: 10px; }
        .faq-toggle-icon { font-size: 16px; color: #888888; transition: transform 0.2s ease; }
        
        /* 答案列（預設隱藏） */
        .faq-answer-panel { display: none; padding: 0 32px 20px 60px; font-size: 15px; color: #444; line-height: 1.6; font-weight: 500; }
        
        /* 當啟用手風琴展開時的樣式 */
        .faq-item-unit.open { background-color: #fafafa; }
        .faq-item-unit.open .faq-toggle-icon { transform: rotate(45deg); color: #3a5a40; }
        .faq-item-unit.open .faq-answer-panel { display: block; }
        
        /* 搜尋命中時的亮黃色微高亮 */
        .faq-item-unit.search-match { border-left: 4px solid #f3d078; background-color: #fffdec; }

        /* ── 聯絡管理員區塊 ── */
        .contact-admin-section { display: flex; flex-direction: column; gap: 15px; margin-top: 15px; }
        
        .btn-contact-toggle { background: #f0f0f0; border: none; border-radius: 6px; padding: 10px 24px; font-size: 14px; font-weight: 700; color: #1a1a1a; cursor: pointer; transition: all 0.2s; width: fit-content; }
        .btn-contact-toggle:hover { background: #e2e2e0; }
        .btn-contact-toggle.active-btn { background: #f3d078; color: #000; }

        /* 展開後的聯絡輸入框 */
        .expand-contact-panel { display: none; align-items: flex-start; gap: 16px; animation: fadeIn 0.3s ease; }
        
        .comment-input-container { display: flex; align-items: center; border: 1.5px solid #222; border-radius: 4px; padding: 0 14px; background: #fff; width: 100%; height: 44px; position: relative; }
        .comment-text-field { flex: 1; height: 100%; border: none; outline: none; font-size: 14px; font-weight: 500; font-family: inherit; color: #111; background: transparent; }
        
        .input-tool-icon-btn { background: none; border: none; font-size: 20px; color: #333; cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
        .input-tool-icon-btn:hover { transform: scale(1.15); color: #000; }
        
        .emoji-mini-popover { position: absolute; bottom: 110%; right: 40px; background: #fff; border: 1px solid #ccc; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 8px; display: none; gap: 8px; z-index: 10; }
        .emoji-mini-popover span { font-size: 18px; cursor: pointer; transition: transform 0.1s; }
        .emoji-mini-popover span:hover { transform: scale(1.25); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">
            
            <div class="search-block-row">
                <div class="search-box-wrapper">
                    <button type="button" class="search-trigger-btn" onclick="searchFAQ()"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <input type="text" id="faqSearchInput" class="search-input-field" placeholder="搜尋關鍵字" onkeyup="if(event.key==='Enter') searchFAQ()">
                    <button type="button" id="clearSearchBtn" class="search-trigger-btn" style="display: none; color: #e63946; margin-left: 5px;" onclick="clearFAQSearch()" title="清除搜尋">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="faq-card-group">
                <div class="faq-item-unit" id="faq_0">
                    <div class="faq-question-bar" onclick="toggleFaq(0)">
                        <span class="faq-question-text"><span style="color:#3a5a40; font-size: 20px; font-weight: 900; margin-right: 4px;">Q</span> 帳號設定</span>
                        <i class="fa-solid fa-plus faq-toggle-icon"></i>
                    </div>
                    <div class="faq-answer-panel">
                        A. 您可以前往「個人設定」頁面修改您的登入密碼、聯絡信箱以及綁定的手機號碼。若要變更用戶名稱，請洽線上管理員協助處理。
                    </div>
                </div>

                <div class="faq-item-unit" id="faq_1">
                    <div class="faq-question-bar" onclick="toggleFaq(1)">
                        <span class="faq-question-text"><span style="color:#3a5a40; font-size: 20px; font-weight: 900; margin-right: 4px;">Q</span> 付款問題</span>
                        <i class="fa-solid fa-plus faq-toggle-icon"></i>
                    </div>
                    <div class="faq-answer-panel">
                        A. 平台目前全面提供「到店取貨現金付款」、「線上信用卡/金融卡刷卡」以及「LINE Pay 實時行動支付」。若扣款失敗但購物車已被清空，請立即點擊下方按鈕回報客服。
                    </div>
                </div>

                <div class="faq-item-unit" id="faq_2">
                    <div class="faq-question-bar" onclick="toggleFaq(2)">
                        <span class="faq-question-text"><span style="color:#3a5a40; font-size: 20px; font-weight: 900; margin-right: 4px;">Q</span> 盲盒內含什麼？</span>
                        <i class="fa-solid fa-plus faq-toggle-icon"></i>
                    </div>
                    <div class="faq-answer-panel">
                        A. 剩食盲盒是由合作店家根據當日現場「未售完的即期美味安全食品」進行隨機搭配（如麵包、便當、小蛋糕、健康輕食等），確保新鮮美味，用超值驚喜價挺環保！
                    </div>
                </div>
            </div>

            <div class="contact-admin-section">
                <button type="button" class="btn-contact-toggle" id="contactToggleBtn" onclick="toggleContactPanel()">
                    聯絡管理者
                </button>

                <form method="POST" action="help.php">
                    <input type="hidden" name="action" value="contact_admin">

                    <div class="expand-contact-panel" id="contactPanel">
                        <div class="comment-input-container">
                            <input type="text" name="admin_message" class="comment-text-field" id="adminInput" placeholder="輸入您的問題..." required>
                            
                            <button type="button" class="input-tool-icon-btn" onclick="toggleEmojiPopover(event)"><i class="bi bi-emoji-smile"></i></button>
                            <button type="submit" class="input-tool-icon-btn" style="color: #3a5a40;"><i class="bi bi-send-fill"></i></button>

                            <div class="emoji-mini-popover" id="emojiPopover">
                                <span onclick="appendEmoji('🙋')">🙋</span>
                                <span onclick="appendEmoji('⚠️')">⚠️</span>
                                <span onclick="appendEmoji('❓')">❓</span>
                                <span onclick="appendEmoji('🌿')">🌿</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <script>
        // 💡 這裡只保留幫助中心專屬的 JS 功能（側邊欄控制已刪除）

        // ── FAQ 手風琴單獨展開收合控制 ──
        function toggleFaq(id) {
            const unit = document.getElementById('faq_' + id);
            unit.classList.toggle('open');
        }

        // ── 修正後的關鍵字搜尋（加入即時顯示/隱藏叉叉按鈕） ──
        function searchFAQ() {
            const keyword = document.getElementById('faqSearchInput').value.trim().toLowerCase();
            const units = document.querySelectorAll('.faq-item-unit');
            const clearBtn = document.getElementById('clearSearchBtn');
            
            if (keyword === "") {
                clearFAQSearch();
                return;
            }

            clearBtn.style.display = 'block';

            units.forEach(u => {
                const qText = u.querySelector('.faq-question-text').innerText.toLowerCase();
                const aText = u.querySelector('.faq-answer-panel').innerText.toLowerCase();
                
                if (qText.includes(keyword) || aText.includes(keyword)) {
                    u.classList.add('open', 'search-match'); 
                } else {
                    u.classList.remove('open', 'search-match');
                }
            });
        }

        // ── 點擊叉叉一鍵清除搜尋並還原初始狀態 ──
        function clearFAQSearch() {
            document.getElementById('faqSearchInput').value = ""; // 清空輸入框
            document.getElementById('clearSearchBtn').style.display = 'none'; // 隱藏叉叉
            
            const units = document.querySelectorAll('.faq-item-unit');
            units.forEach(u => {
                u.classList.remove('open', 'search-match'); // 關閉展開與高亮
            });
        }

        // ── 聯絡管理者區塊展開控制 ──
        function toggleContactPanel() {
            const panel = document.getElementById('contactPanel');
            const btn = document.getElementById('contactToggleBtn');
            const isOpen = panel.style.display === 'flex';
            
            panel.style.display = isOpen ? 'none' : 'flex';
            btn.classList.toggle('active-btn', !isOpen);
        }

        // ── 顏文字小工具控制 ──
        function toggleEmojiPopover(event) {
            event.stopPropagation();
            const pop = document.getElementById('emojiPopover');
            pop.style.display = (pop.style.display === 'flex') ? 'none' : 'flex';
        }

        function appendEmoji(emojiChar) {
            const input = document.getElementById('adminInput');
            input.value += emojiChar;
            document.getElementById('emojiPopover').style.display = 'none';
            input.focus();
        }

        window.addEventListener('click', function() {
            document.getElementById('emojiPopover').style.display = 'none';
        });
    </script>
</body>
</html>
<?php mysqli_close($link); ?>