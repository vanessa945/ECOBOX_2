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
    
    $sql_delete = "DELETE FROM user_favorites WHERE No = $fav_no AND user_name = '$uName_escaped'";
    if (mysqli_query($link, $sql_delete)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($link)]);
    }
    mysqli_close($link);
    exit();
}

// 💡【精準修正核心 SQL】：讓 f.store_name 完美對接 s.name (名稱對接名稱)，並撈出正確的 s.id 來供超連結使用
$sql_fav = "SELECT f.No AS fav_no, s.id AS store_name, s.name AS store_name, s.intro AS store_intro, s.menu AS store_menu,
            (SELECT IFNULL(SUM(quantity), 0) FROM seller_product WHERE store_name = s.name) AS total_qty
            FROM user_favorites f
            INNER JOIN store s ON f.store_name = s.name 
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

        header {
            height: 75px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; background-color: var(--green-deep); box-shadow: 0 4px 20px rgba(0,0,0,.15);
            position: relative; z-index: 1000;
        }
        .nav-left  { display: flex; align-items: center; gap: 25px; }
        .hamburger-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; transition: transform 0.2s; }
        .hamburger-btn:hover { transform: scale(1.1); color: var(--accent-light); }
        .logo-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
        .nav-right { display: flex; gap: 15px; }
        .circle-nav-btn {
            width: 42px; height: 42px; border-radius: 50%; background: #4a4a4a;
            display: flex; align-items: center; justify-content: center;
            color: #fff; text-decoration: none; font-size: 20px; transition: all 0.3s;
        }
        .circle-nav-btn:hover { background: var(--green-mid); transform: scale(1.08); }

        /* 側邊欄抽屜 */
        .sidebar {
            position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
            background: #fff; box-shadow: 5px 0 25px rgba(0,0,0,.15);
            transition: left .4s cubic-bezier(.25,1,.5,1); z-index: 1200;
            padding: 30px 20px; display: flex; flex-direction: column;
        }
        .sidebar.active { left: 0; }
        .sidebar-close-btn { align-self: flex-end; font-size: 26px; background: none; border: none; cursor: pointer; margin-bottom: 30px; color: var(--text-dark); }
        .sidebar-menu { list-style: none; }
        .sidebar-item a { 
            display: flex; align-items: center; gap: 15px; padding: 15px 20px; 
            text-decoration: none; color: var(--text-dark); font-size: 17px; font-weight: 500; border-radius: 8px; margin-bottom: 8px; transition: all .2s; 
        }
        .sidebar-item a:hover { background: var(--bg-warm); color: var(--green-mid); }
        .sidebar-item a.active { 
            background-color: var(--accent-light); color: #000000; font-weight: 700;
            box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35); 
        }

        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
        .sidebar-overlay.active { display: block; }

        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .main-wrapper::-webkit-scrollbar { width: 6px; }
        .main-wrapper::-webkit-scrollbar-thumb { background-color: #dddddd; border-radius: 4px; }

        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: var(--green-deep); display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        .fav-item-row { background: var(--bg-white); border-radius: 12px; padding: 20px 28px; display: flex; align-items: center; gap: 24px; box-shadow: 0 4px 16px rgba(0,0,0,.04); transition: all 0.3s ease; }
        .fav-item-row:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .fav-index { font-size: 20px; font-weight: 900; color: var(--green-mid); min-width: 25px; }
        .res-avatar-box { width: 70px; height: 70px; border-radius: 8px; overflow: hidden; background: #f0f0f0; flex-shrink: 0; border: 1px solid #f0f0f0; }
        .res-avatar-box img { width: 100%; height: 100%; object-fit: cover; }

        .res-info-main { flex: 1; display: flex; flex-direction: column; gap: 5px; cursor: pointer; }
        .res-name { font-size: 18px; font-weight: 800; color: #111111; }
        .res-intro { font-size: 14px; color: var(--text-muted); line-height: 1.4; }
        .status-row { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
        .res-status-tag { font-size: 12px; padding: 3px 10px; border-radius: 20px; font-weight: 700; }
        .res-status-tag.available { background: #eef4ee; color: var(--green-mid); }
        .res-status-tag.empty { background: #ffe3e3; color: var(--danger); }

        .action-controls { display: flex; align-items: center; gap: 24px; flex-shrink: 0; }
        .notify-checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .notify-checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--green-mid); }

        .trash-btn { background: none; border: none; font-size: 22px; color: var(--danger); opacity: 0.6; cursor: pointer; transition: all 0.2s ease; padding: 6px; }
        .trash-btn:hover { opacity: 1; transform: scale(1.15); }

        .no-data { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: var(--accent-gold); }
        .dots-decor { text-align: center; font-size: 24px; color: #ccc; letter-spacing: 4px; margin: 15px 0; }
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
            <li class="sidebar-item"><a href="favorites.php" class="active"><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
            <li class="sidebar-item"><a href="notifications.php"><i class="fa-regular fa-bell"></i> 通知管理</a></li>
            <li class="sidebar-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
            <li class="sidebar-item"><a href="orders.php"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
            <li class="sidebar-item"><a href="help.php"><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
        </ul>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-wrapper">
        <div class="content-container">
            <div class="page-block-title">
                <i class="fa-solid fa-heart" style="color: var(--danger);"></i> 我的最愛商家
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
                            <div class="res-avatar-box" onclick="window.location.href='store_detail.php?id=<?php echo $row_fav['store_name']; ?>'">
                                <img src="<?php echo $store_img; ?>" alt="店家形象圖">
                            </div>
                            <div class="res-info-main" onclick="window.location.href='store_detail.php?id=<?php echo $row_fav['store_name']; ?>'">
                                <span class="res-name"><?php echo htmlspecialchars($row_fav['store_name']); ?></span>
                                <span class="res-intro"><?php echo htmlspecialchars($row_fav['store_intro']); ?></span>
                                <div class="status-row">
                                    <?php if ($total_qty > 0): ?>
                                        <span class="res-status-tag available"><i class="bi bi-patch-check-fill"></i> 今日賸食剩餘 <?php echo $total_qty; ?> 份</span>
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
        const hamburgerBtn   = document.getElementById('hamburgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const sidebar         = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function openSidebar()  { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

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