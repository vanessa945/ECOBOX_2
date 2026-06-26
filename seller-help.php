<?php
session_start();
include("db.php");

if (!isset($_SESSION['store_name'])) { $_SESSION['store_name'] = 1; }
$current_id = $_SESSION['store_name'];

require_once 'mail_helper.php';

// 確保店鋪 ID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'contact') {
    $msg = mysqli_real_escape_string($conn, $_POST['message']);

    $sql_insert = "INSERT INTO admin_messages (sender_id, sender_type, message_content, status, created_at) 
                   VALUES ('$current_id', 'store', '$msg', 'pending', NOW())";

    if (mysqli_query($conn, $sql_insert)) {
        require_once 'mail_helper.php';
        send_email_via_phpmailer(
            'lppei03056319@gmail.com',
            '【新客服提問】商家 ' . $current_id . ' 提出問題',
            "<p>商家 <b>{$current_id}</b> 提出新問題：</p><p>" . nl2br(htmlspecialchars($msg)) . "</p>"
        );
        echo "<script>alert('問題已送出，管理員將盡快回覆！'); window.location.href='seller-help.php';</script>";
        exit;
    } else {
        echo "<script>alert('傳送失敗，請稍後再試');</script>";
    }
}

// 抓取商家名稱
$sql_store = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$res_store = mysqli_query($conn, $sql_store);
$data_store = mysqli_fetch_assoc($res_store);
$store_name = $data_store ? $data_store['store_name'] : "未知商家";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>賣家幫助中心</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="seller.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        /* 共用的變數與排版已交由 seller_header.php 處理，這裡僅保留頁面專屬的設定 */
        body {
            background-color: #f7f6f2 !important;
            color: #23342b;
        }

        /* 幫助中心主體與輸入框樣式 */
        .help-container { max-width: 800px; margin: 40px auto 40px; padding: 20px; } /* 配合 Header 調整 top margin */
        .input-wrapper { display: flex; align-items: center; background: white; border: 1px solid #8ab8a1; border-radius: 30px; padding: 10px 20px; margin-top: 20px; }
        .reply-input { flex: 1; border: none; outline: none; font-size: 1.2rem; }
        .submit-btn { background: #6cae8b; color: white; border: none; border-radius: 50%; width: 45px; height: 45px; cursor: pointer; }

        .sticker-popup {
            display: none; position: absolute; bottom: 45px; right: 0; background: white;
            border: 1px solid #8ab8a1; border-radius: 12px; padding: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15); z-index: 10; width: 220px;
        }
        .sticker-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 2.2rem; text-align: center; }
        .sticker-item { cursor: pointer; transition: transform 0.1s; user-select: none; }
        .sticker-item:hover { transform: scale(1.2); }
        
        .menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .menu-box { 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            background: rgba(255,255,255,0.1); padding: 15px 5px; border-radius: 15px; 
            text-decoration: none; color: white; text-align: center; font-size: 1.2rem; transition: 0.2s; 
        }
        .menu-box:hover, .menu-box.active { background: rgba(255,255,255,0.3); }

        /* 確保貼圖按鈕的容器樣式 */
        .sticker-container { position: relative; display: flex; align-items: center; }
    </style>
</head>
<body>

    <?php 
        $link = $conn; 
        include("seller_header.php"); 
    ?>

    <main class="help-container">
        <h2 style="font-size: 2.2rem; margin-bottom: 20px;">❓ 賣家幫助中心</h2>
        
        <input type="text" id="search-faq" placeholder="🔎 搜尋常見問題關鍵字..." style="width:100%; padding:15px; border-radius:30px; border:1px solid #8ab8a1; margin-bottom:20px; font-size:1.4rem;">

        <div class="faq-scroll-box" style="height: 450px; overflow-y: auto; padding-right: 10px;">
            <?php 
            $faqs = mysqli_query($conn, "SELECT * FROM faq_list");
            while($row = mysqli_fetch_assoc($faqs)): ?>
                <div class="faq-item" style="background: white; padding: 20px; border-radius: 14px; margin-bottom: 15px; border: 1px solid rgba(108,174,139,0.2);">
                    <p style="font-weight:700; font-size:1.5rem; margin:0 0 8px 0;">Q: <?php echo htmlspecialchars($row['question']); ?></p>
                    <p style="color:#5a6e63; font-size:1.3rem; margin:0;">A: <?php echo htmlspecialchars($row['answer']); ?></p>
                </div>
            <?php endwhile; ?>
        </div>

        <h3 style="margin-top: 30px;">聯繫管理者</h3>
        <form action="seller-help.php" method="POST" class="input-wrapper">
            <input type="hidden" name="action" value="contact">
            <input type="text" name="message" class="reply-input" placeholder="輸入您的問題..." required>
            
            <div class="sticker-container">
                <i class="fa-regular fa-face-smile" onclick="toggleStickerBox(event)" style="margin-right:15px; color:#aaa; font-size:1.5rem; cursor:pointer;"></i>
                
                <div id="sticker-popup" class="sticker-popup">
                    <div class="sticker-grid">
                        <div class="sticker-item" onclick="appendSticker('😊')">😊</div>
                        <div class="sticker-item" onclick="appendSticker('🙏')">🙏</div>
                        <div class="sticker-item" onclick="appendSticker('👍')">👍</div>
                        <div class="sticker-item" onclick="appendSticker('✨')">✨</div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="submit-btn"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </main>

    <script>
        document.getElementById('search-faq').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.faq-item').forEach(item => {
                item.style.display = item.innerText.toLowerCase().includes(term) ? 'block' : 'none';
            });
        });

        // 貼圖盒切換邏輯
        function toggleStickerBox(event) {
            event.stopPropagation();
            const popup = document.getElementById('sticker-popup');
            popup.style.display = (popup.style.display === 'block') ? 'none' : 'block';
        }

        // 點擊貼圖填入文字
        function appendSticker(sticker) {
            const input = document.querySelector('input[name="message"]');
            input.value += sticker;
            document.getElementById('sticker-popup').style.display = 'none';
        }

        // 點擊空白處關閉
        document.addEventListener('click', () => {
            const popup = document.getElementById('sticker-popup');
            if (popup) popup.style.display = 'none';
        });
    </script>
    
    <?php mysqli_close($conn); ?>
</body>
</html>