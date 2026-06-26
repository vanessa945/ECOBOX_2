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

// 處理非同步 AJAX 刪除收藏
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['fav_no'])) {
    $fav_no = (int)$_POST['fav_no'];
    $uName_escaped = mysqli_real_escape_string($link, $uName);
    
    // 這裡維持 user_name 進行比對
    $sql_delete = "DELETE FROM user_favorites WHERE No = $fav_no AND user_name = '$uName_escaped'";
    if (mysqli_query($link, $sql_delete)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($link)]);
    }
    mysqli_close($link);
    exit();
}

// 💡【精準修正核心 SQL】：讓 f.store_name 對接 s.store_name，並撈出正確的 s.No 來供超連結使用
$sql_fav = "SELECT f.No AS fav_no, s.No AS store_no, s.store_name, s.intro AS store_intro, s.menu AS store_menu,
            (SELECT IFNULL(SUM(quantity), 0) FROM seller_product WHERE store_name = s.store_name) AS total_qty
            FROM user_favorites f
            INNER JOIN store s ON f.store_name = s.store_name 
            WHERE f.user_name = '" . mysqli_real_escape_string($link, $uName) . "' 
            ORDER BY f.No ASC";
$result_fav = mysqli_query($link, $sql_fav);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的收藏清單 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        /* 💡 這裡只保留收藏清單頁面專屬的 CSS，共用的都給 shared_header 處理了 */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .main-wrapper::-webkit-scrollbar { width: 6px; }
        .main-wrapper::-webkit-scrollbar-thumb { background-color: #dddddd; border-radius: 4px; }

        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: #132a13; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        .fav-item-row { background: #ffffff; border-radius: 12px; padding: 20px 28px; display: flex; align-items: center; gap: 24px; box-shadow: 0 4px 16px rgba(0,0,0,.04); transition: all 0.3s ease; }
        .fav-item-row:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .fav-index { font-size: 20px; font-weight: 900; color: #3a5a40; min-width: 25px; }
        .res-avatar-box { width: 70px; height: 70px; border-radius: 8px; overflow: hidden; background: #f0f0f0; flex-shrink: 0; border: 1px solid #f0f0f0; }
        .res-avatar-box img { width: 100%; height: 100%; object-fit: cover; }

        .res-info-main { flex: 1; display: flex; flex-direction: column; gap: 5px; cursor: pointer; }
        .res-name { font-size: 18px; font-weight: 800; color: #111111; }
        .res-intro { font-size: 14px; color: #888888; line-height: 1.4; }
        .status-row { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
        .res-status-tag { font-size: 12px; padding: 3px 10px; border-radius: 20px; font-weight: 700; }
        .res-status-tag.available { background: #eef4ee; color: #3a5a40; }
        .res-status-tag.empty { background: #ffe3e3; color: #e63946; }

        .action-controls { display: flex; align-items: center; gap: 24px; flex-shrink: 0; }
        .notify-checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .notify-checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #3a5a40; }

        .trash-btn { background: none; border: none; font-size: 22px; color: #e63946; opacity: 0.6; cursor: pointer; transition: all 0.2s ease; padding: 6px; }
        .trash-btn:hover { opacity: 1; transform: scale(1.15); }

        .no-data { text-align: center; padding: 60px 20px; color: #888888; font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: #b8975a; }
        .dots-decor { text-align: center; font-size: 24px; color: #ccc; letter-spacing: 4px; margin: 15px 0; }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">
            <div class="page-block-title">
                <i class="fa-solid fa-heart" style="color: #e63946;"></i> 我的最愛商家
            </div>
            
            <div class="fav-list-wrapper">
                <?php
                if ($result_fav && mysqli_num_rows($result_fav) > 0) {
                    $idx = 1;
                    while ($row_fav = mysqli_fetch_assoc($result_fav)) {
                        $total_qty = (int)$row_fav['total_qty'];
                        // 讀取真實商家圖片，若無則採用防呆隨機圖
                        $store_img = !empty($row_fav['store_menu']) ? $row_fav['store_menu'] : "image/廣告" . (($idx % 3) + 1) . ".png";
                ?>
                        <div class="fav-item-row" id="fav_row_<?php echo $row_fav['fav_no']; ?>">
                            <div class="fav-index"><?php echo sprintf("%02d", $idx); ?></div>
                            <div class="res-avatar-box" onclick="window.location.href='store_detail.php?id=<?php echo $row_fav['store_no']; ?>'">
                                <img src="<?php echo $store_img; ?>" alt="店家形象圖">
                            </div>
                            <div class="res-info-main" onclick="window.location.href='store_detail.php?id=<?php echo $row_fav['store_no']; ?>'">
                                <span class="res-name"><?php echo htmlspecialchars($row_fav['store_name']); ?></span>
                                <span class="res-intro"><?php echo htmlspecialchars($row_fav['store_intro']); ?></span>
                                <div class="status-row">
                                    <?php if ($total_qty > 0): ?>
                                        <span class="res-status-tag available"><i class="bi bi-patch-check-fill"></i> 今日剩食剩餘 <?php echo $total_qty; ?> 份</span>
                                    <?php else: ?>
                                        <span class="res-status-tag empty"><i class="bi bi-x-circle-fill"></i> 今日已全數售罄</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="action-controls">
                                <label class="notify-checkbox-label">
                                    <input type="checkbox" onchange="toggleNotification('<?php echo htmlspecialchars($row_fav['store_name'], ENT_QUOTES); ?>', <?php echo $total_qty; ?>, this)"> 通知
                                </label>
                                <button type="button" class="trash-btn" onclick="deleteFavorite(<?php echo $row_fav['fav_no']; ?>, '<?php echo htmlspecialchars($row_fav['store_name'], ENT_QUOTES); ?>')" title="移除收藏">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                <?php
                        $idx++;
                    }
                    echo '<div class="dots-decor">•••</div>';
                } else {
                ?>
                    <div class="no-data">
                        <i class="fa-regular fa-heart"></i>
                        目前您的收藏清單空空如也！<br>快去賸食地圖上為喜歡的店家點擊愛心吧。
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </main>

    <script>
        // 💡 這裡只保留頁面專屬的 JS 功能（側邊欄控制已刪除）
        
        function toggleNotification(storeName, totalQty, checkbox) {
            if (checkbox.checked) {
                if (totalQty > 0) {
                    alert(`🔔 【EcoBox 實時廣播】\n已成功開啟「${storeName}」的即時通知！\n\n📢 商家最新回報：\n目前店內還有 ${totalQty} 份珍貴的賸食等待您的搶救！快進去看看吧。`);
                } else {
                    alert(`🔔 【EcoBox 實時廣播】\n已成功開啟「${storeName}」的即時通知！\n\n📢 商家最新回報：\n今天暫無剩食上架。若晚點有新增剩食品項，系統將即刻為您推播！`);
                }
            } else {
                alert(`已關閉「${storeName}」的即時賸食廣播通知。`);
            }
        }

        function deleteFavorite(favNo, storeName) {
            if (confirm(`確定要將「${storeName}」從您的愛心收藏清單中移除嗎？`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('fav_no', favNo);

                fetch('favorites.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const targetRow = document.getElementById('fav_row_' + favNo);
                        targetRow.style.opacity = '0';
                        targetRow.style.transform = 'translateY(15px)';
                        setTimeout(() => {
                            targetRow.remove();
                            alert(`已成功將「${storeName}」移出收藏清單！`);
                            if(document.querySelectorAll('.fav-item-row').length === 0){ window.location.reload(); }
                        }, 300);
                    } else { alert('移除失敗：' + data.message); }
                });
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($link); ?>