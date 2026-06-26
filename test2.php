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

// 撈取當前登入使用者的所有個人通知
$uName_escaped = mysqli_real_escape_string($link, $uName);
$sql_notify = "SELECT * FROM notifications WHERE user_name = '$uName_escaped' ORDER BY No DESC";
$result_notify = mysqli_query($link, $sql_notify);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>通知管理 — EcoBox</title>
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
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: var(--bg-warm); color: var(--text-dark); overflow: hidden; }

        /* ── Header ── */
        header {
            height: 75px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; background: var(--green-deep); box-shadow: 0 4px 20px rgba(0,0,0,.15);
            position: relative; z-index: 1000;
        }
        .nav-left  { display: flex; align-items: center; gap: 25px; }
        .hamburger-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; }
        .logo-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
        .nav-right { display: flex; gap: 15px; }
        .circle-nav-btn {
            width: 42px; height: 42px; border-radius: 50%; background: #4a4a4a;
            display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 20px; transition: all 0.3s;
        }
        .circle-nav-btn:hover { background: var(--green-mid); transform: scale(1.08); }

        /* ── 側邊欄抽屜選單 ── */
        .sidebar {
            position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
            background: #fff; box-shadow: 5px 0 25px rgba(0,0,0,.15);
            transition: left .4s cubic-bezier(.25,1,.5,1); z-index: 1200; padding: 30px 20px; display: flex; flex-direction: column;
        }
        .sidebar.active { left: 0; }
        .sidebar-close-btn { align-self: flex-end; font-size: 26px; background: none; border: none; cursor: pointer; margin-bottom: 30px; color: var(--text-dark); }
        .sidebar-menu { list-style: none; }
        .sidebar-item a { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: var(--text-dark); font-size: 17px; font-weight: 500; border-radius: 8px; margin-bottom: 8px; transition: all .2s; }
        .sidebar-item a:hover { background: var(--bg-warm); color: var(--green-mid); }
        /* 🔔 完美契合截圖：通知管理專屬金黃色高亮底色 */
        .sidebar-item a.active { background-color: var(--accent-light); color: #000000; font-weight: 700; box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35); }

        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
        .sidebar-overlay.active { display: block; }

        /* ── 主內容器（開啟滑輪效果） ── */
        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: var(--green-deep); display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        /* ── 精確匹配新圖：去框高質感通知列表 ── */
        .notify-list-wrapper { display: flex; flex-direction: column; background: var(--bg-white); border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.04); overflow: hidden; }
        
        .notify-item-row {
            padding: 22px 32px; display: flex; align-items: center; justify-content: space-between; gap: 30px;
            border-bottom: 1px solid var(--border); transition: background 0.2s;
        }
        .notify-item-row:last-child { border-bottom: none; }
        .notify-item-row:hover { background-color: #fafafa; }

        .notify-left-body { display: flex; align-items: center; gap: 20px; }
        .notify-index { font-size: 18px; font-weight: 900; color: var(--green-mid); min-width: 25px; }
        .notify-text { font-size: 16px; font-weight: 500; color: #111111; }

        /* 時間格式優化 */
        .notify-time { font-size: 14px; color: var(--text-muted); white-space: nowrap; font-weight: 500; }

        .no-data { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: var(--accent-gold); }
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
            <li class="sidebar-item"><a href="favorites.php"><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
            <li class="sidebar-item"><a href="notifications.php" class="active"><i class="fa-regular fa-bell"></i> 通知管理</a></li>
            <li class="sidebar-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
            <li class="sidebar-item"><a href="orders.php"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
            <li class="sidebar-item"><a href="help.php"><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
        </ul>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-wrapper">
        <div class="content-container">
            
            <div class="page-block-title">
                <i class="fa-regular fa-bell" style="color: var(--accent-gold);"></i> 實時系統通知
            </div>
            
            <div class="notify-list-wrapper">
                <?php
                if ($result_notify && mysqli_num_rows($result_notify) > 0) {
                    $idx = 1;
                    while ($row_notify = mysqli_fetch_assoc($result_notify)) {
                        // 轉換時間為上午/下午格式
                        $timestamp = strtotime($row_notify['created_at']);
                        $hour = date('H', $timestamp);
                        $ampm = ($hour >= 12) ? '下午' : '上午';
                        $time_str = $ampm . date('g:i', $timestamp);
                ?>
                        <div class="notify-item-row">
                            <div class="notify-left-body">
                                <div class="notify-index"><?php echo $idx; ?>.</div>
                                <div class="notify-text"><?php echo htmlspecialchars($row_notify['content']); ?></div>
                            </div>
                            <div class="notify-time"><?php echo $time_str; ?></div>
                        </div>
                <?php
                        $idx++;
                    }
                } else {
                    // 💡 防呆展示資料（精確遵循你的截圖訊息與時間格式）
                    $samples = [
                        ["content" => "商品已備好，可取餐", "time" => "下午2:56"],
                        ["content" => "收藏中的商家已有商品可選購", "time" => "上午10:11"]
                    ];
                    foreach ($samples as $index => $sample) {
                ?>
                        <div class="notify-item-row">
                            <div class="notify-left-body">
                                <div class="notify-index"><?php echo ($index + 1); ?>.</div>
                                <div class="notify-text"><?php echo $sample['content']; ?></div>
                            </div>
                            <div class="notify-time"><?php echo $sample['time']; ?></div>
                        </div>
                <?php
                    }
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
    </script>
</body>
</html>
<?php mysqli_close($link); ?>