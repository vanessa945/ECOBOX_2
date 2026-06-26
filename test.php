<?php
session_start();

// 安全機制：檢查使用者是否已經登入，若沒登入就強制導回登入頁
if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

$uName = $_SESSION['uName'];
$role = $_SESSION['login'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoBox 剩食平台 - 探索附近剩食</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            --green-deep: #132a13;   /* 頂級極濃深綠 */
            --green-mid: #3a5a40;    /* 大地翡翠綠 */
            --accent-gold: #b8975a;  /* 文青金 */
            --bg-warm: #fbfbfa;      /* 清爽米白 */
            --text-dark: #2f3e46;    /* 深碳灰 */
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Arial, "Noto Sans TC", "Microsoft JhengHei", sans-serif;
        }

        body, html {
            height: 100%;
            background-color: var(--bg-warm);
            overflow: hidden; 
        }

        /* --- 頂部高端導覽列 --- */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: var(--green-deep);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            height: 75px;
            position: relative;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .hamburger-btn {
            font-size: 24px;
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .hamburger-btn:hover {
            color: var(--accent-gold);
            transform: scale(1.1);
        }

        .logo-title {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 1px;
        }

        /* 右上角精準還原：圓形深灰底白圖標按鈕群 */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .circle-nav-btn {
            width: 42px;
            height: 42px;
            background-color: #4a4a4a; /* 匹配截圖圓形深灰底色 */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .circle-nav-btn:hover {
            background-color: var(--green-mid);
            color: var(--accent-gold);
            transform: scale(1.08);
        }

        /* --- 側邊欄選單 (完美同步 userhome) --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: 5px 0 25px rgba(0,0,0,0.15);
            transition: left 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: 1100;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-close-btn {
            align-self: flex-end;
            font-size: 26px;
            background: none;
            border: none;
            color: var(--text-dark);
            cursor: pointer;
            margin-bottom: 30px;
            transition: color 0.2s;
        }
        .sidebar-close-btn:hover {
            color: #e63946;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--text-dark);
            font-size: 18px;
            font-weight: 500;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover {
            background-color: var(--bg-warm);
            color: var(--green-mid);
            padding-left: 25px;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* --- 金黃色條件篩選列 --- */
        .filter-bar {
            background-color: #d8c397; 
            height: 55px;
            display: flex;
            align-items: center;
            padding: 0 40px;
            gap: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            z-index: 10;
            position: relative;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--green-deep);
            cursor: pointer;
            padding: 5px 0;
        }

        .search-circle-btn {
            margin-left: auto; 
            width: 38px;
            height: 38px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d8c397;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.2s;
        }
        .search-circle-btn:hover {
            transform: scale(1.05);
            color: var(--green-mid);
        }

        /* --- 主區塊配置 --- */
        .main-wrapper {
            display: flex;
            height: calc(100% - 130px); 
            width: 100%;
        }

        /* 左側餐廳滾動列表區 */
        .sidebar-list {
            width: 360px;
            background-color: #ffffff;
            border-right: 1px solid #eef0f2;
            overflow-y: scroll; /* 強制開啟精美垂直滾動條 */
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 升級版：文青風圖像餐廳卡片 */
        .restaurant-card-premium {
            background-color: #ffffff;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .restaurant-card-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        /* 卡片上方圖片 */
        .card-img-wrapper {
            width: 100%;
            height: 140px;
            overflow: hidden;
            background-color: #f5f5f5;
        }
        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .restaurant-card-premium:hover .card-img-wrapper img {
            transform: scale(1.05);
        }

        /* 卡片下方文字資訊區 */
        .card-detail-wrapper {
            padding: 15px;
            position: relative;
        }

        .res-tag {
            font-size: 11px;
            color: var(--accent-gold);
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .restaurant-name {
            font-size: 18px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 6px;
        }

        .res-meta {
            font-size: 13px;
            color: #666666;
            display: flex;
            justify-content: space-between;
        }

        /* 右下角愛心 */
        .heart-icon {
            position: absolute;
            bottom: 15px;
            right: 15px;
            font-size: 20px;
            color: #babcbe;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
            z-index: 5; /* 確保點擊愛心不會觸發整張卡片跳轉 */
        }
        .heart-icon:hover {
            transform: scale(1.1);
        }

        .dots-decor {
            text-align: center;
            font-size: 20px;
            color: #ccc;
            margin: 10px 0;
        }

        /* 右側地圖區 */
        .map-area {
            flex: 1;
            position: relative;
            height: 100%;
        }

        #map {
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .floating-btn-group {
            position: absolute;
            bottom: 30px;
            right: 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 100;
        }

        .action-btn {
            background-color: #ffffff;
            color: #222222;
            border: 2px solid #222222;
            border-radius: 30px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            transition: all 0.3s;
        }
        .action-btn:hover {
            background-color: var(--green-deep);
            color: #ffffff;
            border-color: var(--green-deep);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <header>
        <div class="nav-left">
            <button class="hamburger-btn" id="hamburgerBtn" title="開啟選單">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="logo-title">EcoBox剩食平台</div>
        </div>
        
        <div class="nav-right">
            <a href="javascript:history.back();" class="circle-nav-btn" title="返回上一頁">
                <i class="fa-solid fa-reply"></i>
            </a>
            <a href="userhome.php" class="circle-nav-btn" title="回首頁">
                <i class="fa-solid fa-house"></i>
            </a>
            <a href="logout.php" class="circle-nav-btn" title="個人賬戶 / 登出">
                <i class="fa-solid fa-user"></i>
            </a>
        </div>
    </header>

    <div class="sidebar" id="sidebar">
        <button class="sidebar-close-btn" id="sidebarCloseBtn" title="關閉選單">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="userhome.php"><i class="fa-solid fa-house"></i> 首頁</a></li>
            <li class="sidebar-item"><a href="stores.php"><i class="fa-solid fa-store"></i> 合作商家</a></li>
            <li class="sidebar-item"><a href="orders.php"><i class="fa-solid fa-cart-shopping"></i> 我的訂單</a></li>
            <li class="sidebar-item"><a href="profile.php"><i class="fa-solid fa-user-gear"></i> 個人設定</a></li>
            <li class="sidebar-item"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> 登出</a></li>
        </ul>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="filter-bar">
        <div class="filter-item"><i class="fa-solid fa-dice"></i> 食物類型 <i class="bi bi-chevron-down"></i></div>
        <div class="filter-item">選擇縣市 <i class="bi bi-chevron-down"></i></div>
        <div class="filter-item">選擇區域 <i class="bi bi-chevron-down"></i></div>
        
        <button class="search-circle-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
    </div>

    <div class="main-wrapper">
        
        <div class="sidebar-list">
            
            <div class="restaurant-card-premium" onclick="navigateToStore(1)">
                <div class="card-img-wrapper">
                    <img src="image/廣告1.png" alt="美味麵包坊">
                </div>
                <div class="card-detail-wrapper">
                    <div class="res-tag">[ 烘焙甜點 ]</div>
                    <div class="restaurant-name">綠色大地手作烘焙</div>
                    <div class="res-meta">
                        <span>剩餘救援：5 份</span>
                        <span style="color: #e63946;">打 6 折</span>
                    </div>
                    <i class="fa-solid fa-heart heart-icon" onclick="toggleHeart(event, this)"></i>
                </div>
            </div>
            
            <div class="restaurant-card-premium" onclick="navigateToStore(2)">
                <div class="card-img-wrapper">
                    <img src="image/廣告2.png" alt="美味健康餐盒">
                </div>
                <div class="card-detail-wrapper">
                    <div class="res-tag">[ 健康餐盒 ]</div>
                    <div class="restaurant-name">永續惜食健康廚房</div>
                    <div class="res-meta">
                        <span>剩餘救援：3 份</span>
                        <span style="color: #e63946;">打 5 折</span>
                    </div>
                    <i class="fa-solid fa-heart heart-icon" onclick="toggleHeart(event, this)"></i>
                </div>
            </div>

            <div class="restaurant-card-premium" onclick="navigateToStore(3)">
                <div class="card-img-wrapper">
                    <img src="image/廣告3.png" alt="文青手搖飲">
                </div>
                <div class="card-detail-wrapper">
                    <div class="res-tag">[ 舒心飲品 ]</div>
                    <div class="restaurant-name">EcoBox 永續茶飲店</div>
                    <div class="res-meta">
                        <span>剩餘救援：8 份</span>
                        <span style="color: #e63946;">買一送一</span>
                    </div>
                    <i class="fa-solid fa-heart heart-icon" onclick="toggleHeart(event, this)"></i>
                </div>
            </div>

            <div class="dots-decor">•••</div>
        </div>

        <div class="map-area">
            <div id="map"></div>

            <div class="floating-btn-group">
                <button class="action-btn" onclick="alert('已關閉推薦商店')">關閉推薦</button>
                <button class="action-btn" onclick="getLocation()">精確定位</button>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // --- 側邊欄雙向控制 (完美對接 userhome) ---
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);


        // --- 直覺點擊卡片跳轉選購商品 ---
        function navigateToStore(storeId) {
            // 帶上餐廳 ID 跳轉至選購頁面
            window.location.href = `store_detail.php?id=${storeId}`;
        }

        // --- 愛心收藏切換互動 ---
        function toggleHeart(event, element) {
            event.stopPropagation(); // 關鍵！阻止事件冒泡，防止點擊愛心時誤觸整張卡片跳轉
            element.classList.toggle('active');
            if (element.classList.contains('active')) {
                element.style.color = '#e63946'; // 變成紅色
            } else {
                element.style.color = '#babcbe'; // 還原灰色
            }
        }


        // --- 初始化 Leaflet 地圖中心點 ---
        const map = L.map('map').setView([22.7306, 120.3286], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; EcoBox'
        }).addTo(map);

        // 在地圖上產生紅色坐標點
        const locations = [
            [22.733, 120.325],
            [22.728, 120.332],
            [22.735, 120.319]
        ];

        locations.forEach((pos, index) => {
            L.marker(pos).addTo(map)
             .bindPopup(`<b>餐廳 ${index + 1}</b><br><a href="store_detail.php?id=${index + 1}" style="color:var(--green-mid);font-weight:bold;text-decoration:none;">點擊進入選購頁面</a>`);
        });

        // 定位 GPS 功能
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 16);
                    L.circle([lat, lng], { radius: 150, color: '#3a5a40', fillColor: '#3a5a40', fillOpacity: 0.2 }).addTo(map)
                     .bindPopup("您的精確定位位置").openPopup();
                }, function() {
                    alert("無法取得您的精確定位，請檢查瀏覽器定位權限。");
                });
            } else {
                alert("您的瀏覽器不支援 GPS 定位功能。");
            }
        }
    </script>
</body>
</html>