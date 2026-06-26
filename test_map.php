<?php
    session_start();
 
    if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
        header("Location: index.php");
        exit();
    }
 
    $uName = $_SESSION['uName'];
    $role = $_SESSION['login'];
 
    $link = @mysqli_connect('localhost', 'root', '', 'food_waste');
    if (!$link) {
        die("資料庫連線失敗: " . mysqli_connect_error());
    }
    mysqli_set_charset($link, "utf8mb4");
 
    // 💡 核心非同步 AJAX：當卡片點擊愛心時，即時發送 POST 請求寫入或刪除資料庫 (使用 store_name)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
        $s_name = mysqli_real_escape_string($link, $_POST['store_name']);
        $u_name = mysqli_real_escape_string($link, $uName);
        
        // 檢查是否已經存在收藏
        $sql_exists = "SELECT * FROM user_favorites WHERE user_name='$u_name' AND store_name='$s_name'";
        $res_exists = mysqli_query($link, $sql_exists);
        
        if (mysqli_num_rows($res_exists) > 0) {
            // 已存在 ➔ 移除收藏
            mysqli_query($link, "DELETE FROM user_favorites WHERE user_name='$u_name' AND store_name='$s_name'");
            echo json_encode(["status" => "removed"]);
        } else {
            // 不存在 ➔ 新增收藏
            mysqli_query($link, "INSERT INTO user_favorites (user_name, store_name) VALUES ('$u_name', '$s_name')");
            echo json_encode(["status" => "added"]);
        }
        mysqli_close($link);
        exit();
    }

    // 💡 核心安全連動：先查出當前使用者已經收藏了哪些店家的 store_name，讓地圖初始載入時，愛心就能動態著色
    $fav_array = [];
    $sql_check_fav = "SELECT store_name FROM user_favorites WHERE user_name = '" . mysqli_real_escape_string($link, $uName) . "'";
    $res_check_fav = mysqli_query($link, $sql_check_fav);
    if ($res_check_fav) {
        while($f_row = mysqli_fetch_assoc($res_check_fav)){
            $fav_array[] = $f_row['store_name'];
        }
    }

    $sql_stores = "SELECT * FROM store";
    $result_stores = mysqli_query($link, $sql_stores);
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
            --green-deep: #132a13;
            --green-mid: #3a5a40;
            --accent-gold: #b8975a;
            --accent-light:#f3d078;
            --bg-warm: #fbfbfa;
            --text-dark: #2f3e46;
        }
 
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Helvetica Neue", Arial, "Noto Sans TC", "Microsoft JhengHei", sans-serif; }
 
        html, body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-warm);
        }
 
        .page-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
 
        /* --- 導覽列 --- */
        header {
            flex-shrink: 0;
            height: 75px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            background-color: var(--green-deep);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
 
        .nav-left { display: flex; align-items: center; gap: 25px; }
        .hamburger-btn { font-size: 24px; background: none; border: none; color: #ffffff; cursor: pointer; transition: color 0.3s; }
        .hamburger-btn:hover { color: var(--accent-gold); }
        .logo-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
 
        .nav-right { display: flex; align-items: center; gap: 15px; }
        .circle-nav-btn {
            width: 42px; height: 42px; background-color: #4a4a4a; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: #ffffff;
            text-decoration: none; font-size: 20px; transition: all 0.3s ease;
        }
        .circle-nav-btn:hover { background-color: var(--green-mid); transform: scale(1.08); }
 
        /* --- 側邊選單 --- */
        .sidebar {
            position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
            background-color: #ffffff; box-shadow: 5px 0 25px rgba(0,0,0,0.15);
            transition: left 0.4s cubic-bezier(0.25, 1, 0.5, 1); z-index: 1200;
            padding: 30px 20px; display: flex; flex-direction: column;
        }
        .sidebar.active { left: 0; }
        .sidebar-close-btn { align-self: flex-end; font-size: 26px; background: none; border: none; color: var(--text-dark); cursor: pointer; margin-bottom: 30px; }
        .sidebar-menu { list-style: none; }
        .sidebar-item a { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: var(--text-dark); font-size: 17px; font-weight: 500; border-radius: 8px; margin-bottom: 8px; transition: all 0.2s; }
        .sidebar-item a:hover { background-color: var(--bg-warm); color: var(--green-mid); }
        
        /* 完美同步：收藏選取時黃色底的高亮精美樣式 */
        .sidebar-item a.active { 
            background-color: var(--accent-light); color: #000000; font-weight: 700;
            box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35); 
        }

        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
        .sidebar-overlay.active { display: block; }
 
        /* --- 篩選列 --- */
        .filter-bar {
            flex-shrink: 0;
            height: 55px;
            background-color: #d8c397;
            display: flex;
            align-items: center;
            padding: 0 40px;
            gap: 35px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            z-index: 500;
        }
 
        .filter-wrapper { position: relative; display: inline-flex; align-items: center; }
        .dice-btn { background: none; border: none; color: var(--green-deep); font-size: 18px; cursor: pointer; margin-right: 6px; transition: transform 0.3s; }
        .dice-btn:hover { transform: rotate(180deg) scale(1.2); }
 
        .filter-item {
            border: none; border-bottom: 2px solid rgba(19, 42, 19, 0.4); border-radius: 0;
            padding: 4px 4px 6px 4px; font-size: 15px; font-weight: 700; color: var(--green-deep); cursor: pointer; transition: all 0.3s ease;
            display: flex; align-items: center; gap: 5px;
        }
        .filter-item:hover, .filter-wrapper.active .filter-item { border-bottom: 2px solid var(--green-deep); color: #000; }
 
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); left: 0; min-width: 140px;
            background: #ffffff; border: 1px solid #eaeaea; border-radius: 6px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08); list-style: none; display: none; z-index: 2000; overflow: hidden;
        }
        .dropdown-menu li { padding: 10px 15px; font-size: 14px; color: #333; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
        .dropdown-menu li:hover { background: var(--bg-warm); color: var(--green-mid); font-weight: bold; }
        .dropdown-menu li.placeholder { color: #aaa; font-style: italic; cursor: default; }
        .dropdown-menu li.placeholder:hover { background: #fff; font-weight: normal; }
 
        .search-btn-container { display: flex; align-items: center; gap: 12px; margin-left: auto; }
        .search-circle-btn, .reset-circle-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none; transition: all 0.2s;
        }
        .search-circle-btn { background-color: #ffffff; color: #d8c397; }
        .search-circle-btn:hover { transform: scale(1.08); color: var(--green-mid); }
        .reset-circle-btn { background-color: #ffffff; color: #babcbe; display: none; }
        .reset-circle-btn:hover { transform: scale(1.08); color: #e63946; }
 
        /* --- 主內容區 --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            min-height: 0;
            overflow: hidden;
        }
 
        .sidebar-list {
            width: 360px;
            flex-shrink: 0;
            background-color: #ffffff;
            border-right: 1px solid #eef0f2;
            overflow-y: auto;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .sidebar-list::-webkit-scrollbar { width: 6px; }
        .sidebar-list::-webkit-scrollbar-thumb { background-color: #dddddd; border-radius: 4px; }
 
        .restaurant-card-premium {
            background-color: #ffffff; border: 1px solid #eaeaea; border-radius: 8px;
            overflow: hidden; cursor: pointer; position: relative; display: flex; flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); transition: all 0.3s ease; flex-shrink: 0;
        }
        .restaurant-card-premium:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
 
        .card-img-wrapper { width: 100%; height: 160px; overflow: hidden; position: relative; flex-shrink: 0; }
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .restaurant-card-premium:hover .card-img-wrapper img { transform: scale(1.05); }
 
        .card-img-overlay {
            position: absolute; bottom: 0; left: 0; width: 100%;
            padding: 35px 12px 10px 12px;
            background: linear-gradient(to top, rgba(0,0,0,0.72) 0%, transparent 100%);
            color: #ffffff;
        }
        .card-img-overlay .overlay-tag { font-size: 11px; color: var(--accent-gold); font-weight: 700; margin-bottom: 2px; letter-spacing: 0.5px; }
        .card-img-overlay .overlay-name { font-size: 15px; font-weight: 700; color: #ffffff; margin-bottom: 2px; }
        .card-img-overlay .overlay-sub { font-size: 11px; color: rgba(255,255,255,0.78); }
 
        .card-detail-wrapper { padding: 12px 15px 14px 15px; position: relative; background: #ffffff; }
        .res-tag-bottom { font-size: 11px; color: var(--accent-gold); font-weight: 700; margin-bottom: 4px; }
        .restaurant-name-bottom { font-size: 16px; font-weight: 700; color: #111111; margin-bottom: 6px; }
        .res-meta { font-size: 13px; color: #666666; display: flex; justify-content: space-between; align-items: center; padding-right: 28px; }
        .discount-text { color: #e63946; font-weight: 700; }
        .heart-icon { position: absolute; bottom: 14px; right: 14px; font-size: 20px; color: #babcbe; transition: all 0.2s ease; z-index: 10; }
        .heart-icon.liked { color: #e63946 !important; }
 
        .dots-decor { text-align: center; font-size: 20px; color: #ccc; margin: 5px 0; }
 
        .map-area { flex: 1; position: relative; min-width: 0; }
        #map { width: 100%; height: 100%; }
 
        .floating-btn-group { position: absolute; bottom: 30px; left: 30px; display: flex; flex-direction: column; gap: 15px; z-index: 100; }
        .action-btn {
            background-color: #ffffff; color: #222222; border: 2px solid #222222; border-radius: 30px;
            padding: 12px 28px; font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12); transition: all 0.3s;
        }
        .action-btn:hover { background-color: var(--green-deep); color: #ffffff; border-color: var(--green-deep); transform: translateY(-2px); }
    </style>
</head>
<body>
<div class="page-wrapper">
 
    <header>
        <div class="nav-left">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
            <div class="logo-title">EcoBox剩食平台</div>
        </div>
        <div class="nav-right">
            <a href="map.php" class="circle-nav-btn" title="重新整理地圖"><i class="bi bi-geo-alt"></i></a>
            <a href="userhome.php" class="circle-nav-btn" title="回首頁"><i class="fa-solid fa-house"></i></a>
            <a href="logout.php" class="circle-nav-btn" title="登出"><i class="fa-solid fa-user"></i></a>
        </div>
    </header>
 
    <div class="sidebar" id="sidebar">
        <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fa-solid fa-xmark"></i></button>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="favorites.php"><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
            <li class="sidebar-item"><a href="notifications.php"><i class="fa-regular fa-bell"></i> 通知管理</a></li>
            <li class="sidebar-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
            <li class="sidebar-item"><a href="orders.php"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
            <li class="sidebar-item"><a href="help.php"><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
        </ul>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
 
    <div class="filter-bar">
        <div class="filter-wrapper" id="wrapper-food">
            <button type="button" class="dice-btn" id="diceBtn" onclick="rollFoodType(event)" title="隨機挑選">
                <i class="fa-solid fa-dice"></i>
            </button>
            <div class="filter-item" onclick="toggleDropdown('foodMenu', 'wrapper-food')">
                <span id="btn-food">食物類型</span> <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu" id="foodMenu">
                <li onclick="selectOption('food', '飯', 'wrapper-food')">飯</li>
                <li onclick="selectOption('food', '麵', 'wrapper-food')">麵</li>
                <li onclick="selectOption('food', '甜點', 'wrapper-food')">甜點</li>
                <li onclick="selectOption('food', '飲料', 'wrapper-food')">飲料</li>
            </ul>
        </div>
 
        <div class="filter-wrapper" id="wrapper-city">
            <div class="filter-item" onclick="toggleDropdown('cityMenu', 'wrapper-city')">
                <span id="btn-city">選擇縣市</span> <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu" id="cityMenu">
                <li onclick="selectCity('高雄市')">高雄市</li>
                <li onclick="selectCity('臺南市')">臺南市</li>
            </ul>
        </div>
 
        <div class="filter-wrapper" id="wrapper-dist">
            <div class="filter-item" id="distFilterItem" onclick="handleDistClick()" style="opacity:0.5; cursor:not-allowed;">
                <span id="btn-dist">選擇區域</span> <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu" id="distMenu"></ul>
        </div>
 
        <div class="search-btn-container">
            <button class="reset-circle-btn" id="resetBtn" onclick="resetFilter()" title="重置篩選">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <button class="search-circle-btn" onclick="executeFilter()" title="執行篩選">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>
 
    <div class="main-wrapper">
 
        <div class="sidebar-list" id="restaurantList">
            <?php
            $store_index = 1;
            while ($row_store = mysqli_fetch_assoc($result_stores)) {
                $store_img = (!empty($row_store['menu'])) ? $row_store['menu'] : "image/廣告" . (($store_index % 3) + 1) . ".png";
 
                $city = "高雄市"; $dist = "楠梓區"; $food = "飯";
                if (strpos($row_store['address'], '臺南') !== false || strpos($row_store['address'], '台南') !== false) {
                    $city = "臺南市";
                    $dist = (strpos($row_store['address'], '永康') !== false) ? "永康區" : "東區";
                }
                if ($store_index % 3 === 1) $food = "甜點";
                if ($store_index % 3 === 2) $food = "飲料";
                if ($store_index % 3 === 0) $food = "麵";
 
                $intro_short = mb_substr($row_store['intro'], 0, 20);
                
                // 💡 檢查這家店當前使用者是否有收藏 (使用 store_name 欄位比對)
                $is_liked = in_array($row_store['name'], $fav_array) ? "fa-solid fa-heart heart-icon liked" : "fa-regular fa-heart heart-icon";
            ?>
                <div class="restaurant-card-premium"
                     data-city="<?php echo $city; ?>"
                     data-dist="<?php echo $dist; ?>"
                     data-food="<?php echo $food; ?>"
                     onclick="navigateToStore('<?php echo $row_store['id']; ?>')">
 
                    <div class="card-img-wrapper">
                        <img src="<?php echo htmlspecialchars($store_img); ?>" alt="餐廳形象照">
                        <div class="card-img-overlay">
                            <div class="overlay-tag">[ <?php echo $city . " " . $dist; ?> ] · <?php echo $food; ?></div>
                            <div class="overlay-name"><?php echo htmlspecialchars($row_store['name']); ?></div>
                            <div class="overlay-sub"><?php echo htmlspecialchars($intro_short); ?><?php echo mb_strlen($row_store['intro']) > 20 ? '...' : ''; ?></div>
                        </div>
                    </div>
 
                    <div class="card-detail-wrapper">
                        <div class="res-tag-bottom">[ <?php echo $city . " " . $dist; ?> ] · <?php echo $food; ?></div>
                        <div class="restaurant-name-bottom"><?php echo htmlspecialchars($row_store['name']); ?></div>
                        <div class="res-meta">
                            <span><?php echo htmlspecialchars($intro_short); ?><?php echo mb_strlen($row_store['intro']) > 20 ? '...' : ''; ?></span>
                            <span class="discount-text">即時救援中</span>
                        </div>
                        <i class="<?php echo $is_liked; ?>" onclick="toggleHeart(event, this, '<?php echo htmlspecialchars($row_store['name'], ENT_QUOTES); ?>')"></i>
                    </div>
                </div>
            <?php
                $store_index++;
            }
            ?>
            <div class="dots-decor">•••</div>
        </div>
 
        <div class="map-area">
            <div id="map"></div>
            <div class="floating-btn-group">
                <button class="action-btn" onclick="resetFilter()">重置篩選</button>
                <button class="action-btn" onclick="getLocation()">精確定位</button>
            </div>
        </div>
    </div>
 
</div>
 
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    function openSidebar() { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
    hamburgerBtn.addEventListener('click', openSidebar);
    sidebarCloseBtn.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);
 
    const districtMap = {
        '高雄市': ['楠梓區', '三民區', '鼓山區', '苓雅區', '前鎮區', '左營區'],
        '臺南市': ['永康區', '東區', '北區', '南區', '安平區', '仁德區']
    };
 
    let selectedFood = '';
    let selectedCity = '';
    let selectedDist = '';
    let distUnlocked = false;
 
    function toggleDropdown(menuId, wrapperId) {
        const allMenus = document.querySelectorAll('.dropdown-menu');
        const allWrappers = document.querySelectorAll('.filter-wrapper');
        allMenus.forEach(m => { if (m.id !== menuId) m.style.display = 'none'; });
        allWrappers.forEach(w => { if (w.id !== wrapperId) w.classList.remove('active'); });
 
        const menu = document.getElementById(menuId);
        const wrapper = document.getElementById(wrapperId);
        const isOpen = menu.style.display === 'block';
        menu.style.display = isOpen ? 'none' : 'block';
        wrapper.classList.toggle('active', !isOpen);
    }
 
    function selectOption(type, value, wrapperId) {
        if (type === 'food') { selectedFood = value; document.getElementById('btn-food').innerText = value; }
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.filter-wrapper').forEach(w => w.classList.remove('active'));
        document.getElementById('resetBtn').style.display = 'flex';
    }
 
    function selectCity(city) {
        selectedCity = city;
        selectedDist = '';
        document.getElementById('btn-city').innerText = city;
        document.getElementById('btn-dist').innerText = '選擇區域';
 
        const distMenu = document.getElementById('distMenu');
        distMenu.innerHTML = '';
        const districts = districtMap[city] || [];
        districts.forEach(d => {
            const li = document.createElement('li');
            li.textContent = d;
            li.onclick = () => selectDist(d);
            distMenu.appendChild(li);
        });
 
        distUnlocked = true;
        const distItem = document.getElementById('distFilterItem');
        distItem.style.opacity = '1';
        distItem.style.cursor = 'pointer';
 
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.filter-wrapper').forEach(w => w.classList.remove('active'));
        document.getElementById('resetBtn').style.display = 'flex';
    }
 
    function selectDist(dist) {
        selectedDist = dist;
        document.getElementById('btn-dist').innerText = dist;
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.filter-wrapper').forEach(w => w.classList.remove('active'));
        document.getElementById('resetBtn').style.display = 'flex';
    }
 
    function handleDistClick() {
        if (!distUnlocked) { alert('請先選擇縣市！'); return; }
        toggleDropdown('distMenu', 'wrapper-dist');
    }
 
    window.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-wrapper')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            document.querySelectorAll('.filter-wrapper').forEach(w => w.classList.remove('active'));
        }
    });
 
    function rollFoodType(event) {
        event.stopPropagation();
        const options = ['飯', '麵', '甜點', '飲料'];
        const random = options[Math.floor(Math.random() * options.length)];
        selectedFood = random;
        document.getElementById('btn-food').innerText = random;
        document.getElementById('resetBtn').style.display = 'flex';
    }
 
    function executeFilter() {
        const cards = document.querySelectorAll('.restaurant-card-premium');
        let found = 0;
        cards.forEach(card => {
            const match =
                (selectedCity === '' || card.dataset.city === selectedCity) &&
                (selectedDist === '' || card.dataset.dist === selectedDist) &&
                (selectedFood === '' || card.dataset.food === selectedFood);
            card.style.display = match ? 'flex' : 'none';
            if (match) found++;
        });
        if (found === 0) alert('目前這區沒有符合條件的即期剩食，換個口味試試看吧！');
    }
 
    function resetFilter() {
        selectedFood = ''; selectedCity = ''; selectedDist = ''; distUnlocked = false;
        document.getElementById('btn-food').innerText = '食物類型';
        document.getElementById('btn-city').innerText = '選擇縣市';
        document.getElementById('btn-dist').innerText = '選擇區域';
        document.getElementById('distFilterItem').style.opacity = '0.5';
        document.getElementById('distFilterItem').style.cursor = 'not-allowed';
        document.getElementById('resetBtn').style.display = 'none';
        document.querySelectorAll('.restaurant-card-premium').forEach(c => c.style.display = 'flex');
    }
 
    // ── 【修正】點擊愛心時，發送非同步 AJAX 請求，連動資料庫 user_favorites ──
    function toggleHeart(event, el, storeName) {
        event.stopPropagation(); // 阻斷卡片主體點擊跳轉事件
        
        const formData = new FormData();
        formData.append('action', 'toggle_favorite');
        formData.append('store_name', storeName);

        fetch('map.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'added') {
                el.classList.add('liked', 'fa-solid');
                el.classList.remove('fa-regular');
                alert("✨ 已成功加入您的 EcoBox 收藏清單！");
            } else if (data.status === 'removed') {
                el.classList.remove('liked', 'fa-solid');
                el.classList.add('fa-regular');
                alert("💔 已從您的收藏清單中移除。");
            }
        })
        .catch(err => {
            console.error(err);
            alert('系統連線逾時，請稍後再試。');
        });
    }
 
    function navigateToStore(storeId) {
        window.location.href = `store_detail.php?id=${storeId}`;
    }
 
    const map = L.map('map').setView([22.7306, 120.3286], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
 
    const locations = [
        [22.733, 120.325], [22.728, 120.332], [22.735, 120.319], [23.025, 120.254]
    ];
    locations.forEach((pos, i) => {
        L.marker(pos).addTo(map);
    });
 
    function getLocation() {
        if (!navigator.geolocation) { alert('您的瀏覽器不支援定位功能'); return; }
        navigator.geolocation.getCurrentPosition(
            pos => {
                const { latitude: lat, longitude: lng } = pos.coords;
                map.setView([lat, lng], 16);
                L.circle([lat, lng], { radius: 150, color: '#3a5a40', fillColor: '#3a5a40', fillOpacity: 0.25 })
                 .addTo(map).bindPopup('您目前的位置').openPopup();
            },
            () => alert('無法取得定位，請檢查瀏覽器定位權限。')
        );
    }
</script>
</body>
</html>
<?php mysqli_close($link); ?>