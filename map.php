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
 
    // 💡 核心非同步 AJAX：當卡片點擊愛心時，即時發送 POST 請求寫入或刪除資料庫
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
        $s_name = mysqli_real_escape_string($link, $_POST['store_name']);
        $u_name = mysqli_real_escape_string($link, $uName);
        
        // 確保這裡是用 user_name 和 store_name 去比對
        $sql_exists = "SELECT * FROM user_favorites WHERE user_name='$u_name' AND store_name='$s_name'";
        $res_exists = mysqli_query($link, $sql_exists);
        
        if (mysqli_num_rows($res_exists) > 0) {
            mysqli_query($link, "DELETE FROM user_favorites WHERE user_name='$u_name' AND store_name='$s_name'");
            echo json_encode(["status" => "removed"]);
        } else {
            mysqli_query($link, "INSERT INTO user_favorites (user_name, store_name) VALUES ('$u_name', '$s_name')");
            echo json_encode(["status" => "added"]);
        }
        mysqli_close($link);
        exit();
    }

    // 💡 查出當前使用者已經收藏了哪些店家
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
        /* 💡 這裡只保留地圖頁面專屬的 CSS，共用的交給 shared_header */
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
            position: absolute; top: calc(100% + 8px); left: 0; min-width: 140px; max-height: 400px;
            background: #ffffff; border: 1px solid #eaeaea; border-radius: 6px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08); list-style: none; display: none; z-index: 2000; overflow-y: auto;
        }
        .dropdown-menu::-webkit-scrollbar { width: 6px; }
        .dropdown-menu::-webkit-scrollbar-thumb { background-color: #ccc; border-radius: 4px; }
        .dropdown-menu li { padding: 10px 15px; font-size: 14px; color: #333; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
        .dropdown-menu li:hover { background: var(--bg-warm); color: var(--green-mid); font-weight: bold; }
 
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
 
        /* 側邊餐廳列表 (加入縮放動畫) */
        .sidebar-list {
            width: 360px;
            flex-shrink: 0;
            background-color: #ffffff;
            border-right: 1px solid #eef0f2;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1), padding 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .sidebar-list.collapsed {
            width: 0;
            padding-left: 0;
            padding-right: 0;
            border-right: none;
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
        #map { width: 100%; height: 100%; z-index: 1; }
 
        /* 💡 修正圖層：提升 z-index，保證不會被地圖圖層蓋住 */
        .floating-btn-group { 
            position: absolute; bottom: 30px; left: 30px; 
            display: flex; flex-direction: column; gap: 15px; 
            z-index: 9999; /* 絕對置頂 */
        }
        .action-btn {
            background-color: #ffffff; color: #222222; border: 2px solid #222222; border-radius: 30px;
            padding: 12px 28px; font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15); transition: all 0.3s;
        }
        .action-btn:hover { background-color: var(--green-deep); color: #ffffff; border-color: var(--green-deep); transform: translateY(-2px); }
    </style>
</head>
<body>
<div class="page-wrapper">
 
    <?php include 'shared_header.php'; ?>
 
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
            <ul class="dropdown-menu" id="cityMenu"></ul>
        </div>
 
        <div class="filter-wrapper" id="wrapper-dist">
            <div class="filter-item" id="distFilterItem" onclick="handleDistClick()" style="opacity:0.5; cursor:not-allowed;">
                <span id="btn-dist">選擇區域</span> <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu" id="distMenu"></ul>
        </div>
 
        <div class="search-btn-container">
            <button class="reset-circle-btn" id="resetBtn" onclick="resetFilter()" title="清空條件">
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
 
                // 這裡的資料庫位址判斷僅為前端 Demo 用的 fallback，你可依據真實資料庫欄位微調
                $city = "高雄市"; $dist = "楠梓區"; $food = "飯";
                if (strpos($row_store['address'], '臺南') !== false || strpos($row_store['address'], '台南') !== false) {
                    $city = "臺南市";
                    $dist = (strpos($row_store['address'], '永康') !== false) ? "永康區" : "東區";
                }
                if ($store_index % 3 === 1) $food = "甜點";
                if ($store_index % 3 === 2) $food = "飲料";
                if ($store_index % 3 === 0) $food = "麵";
 
                $intro_short = mb_substr($row_store['intro'], 0, 20);
                
                // 💡 修正：使用 $row_store['store_name'] 來檢查是否在收藏清單中
                $is_liked = in_array($row_store['store_name'], $fav_array) ? "fa-solid fa-heart heart-icon liked" : "fa-regular fa-heart heart-icon";
            ?>
                <div class="restaurant-card-premium"
                     data-city="<?php echo $city; ?>"
                     data-dist="<?php echo $dist; ?>"
                     data-food="<?php echo $food; ?>"
                     onclick="navigateToStore('<?php echo $row_store['No']; ?>')">
 
                    <div class="card-img-wrapper">
                        <img src="<?php echo htmlspecialchars($store_img); ?>" alt="餐廳形象照">
                        <div class="card-img-overlay">
                            <div class="overlay-tag">[ <?php echo $city . " " . $dist; ?> ] · <?php echo $food; ?></div>
                            <div class="overlay-name"><?php echo htmlspecialchars($row_store['store_name']); ?></div>
                            <div class="overlay-sub"><?php echo htmlspecialchars($intro_short); ?><?php echo mb_strlen($row_store['intro']) > 20 ? '...' : ''; ?></div>
                        </div>
                    </div>
 
                    <div class="card-detail-wrapper">
                        <div class="res-tag-bottom">[ <?php echo $city . " " . $dist; ?> ] · <?php echo $food; ?></div>
                        <div class="restaurant-name-bottom"><?php echo htmlspecialchars($row_store['store_name']); ?></div>
                        <div class="res-meta">
                            <span><?php echo htmlspecialchars($intro_short); ?><?php echo mb_strlen($row_store['intro']) > 20 ? '...' : ''; ?></span>
                            <span class="discount-text">即時救援中</span>
                        </div>
                        <i class="<?php echo $is_liked; ?>" onclick="toggleHeart(event, this, '<?php echo htmlspecialchars($row_store['store_name'], ENT_QUOTES); ?>')"></i>
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
                <button class="action-btn" id="toggleRecBtn" onclick="toggleRecommendations()">關閉推薦</button>
                <button class="action-btn" onclick="getLocation()">精確定位</button>
            </div>
        </div>
    </div>
 
</div>
 
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // 💡 全台灣縣市與區域完整資料
    const taiwanData = {
        '臺北市': ['中正區', '大同區', '中山區', '松山區', '大安區', '萬華區', '信義區', '士林區', '北投區', '內湖區', '南港區', '文山區'],
        '新北市': ['萬里區', '金山區', '板橋區', '汐止區', '深坑區', '石碇區', '瑞芳區', '平溪區', '雙溪區', '貢寮區', '新店區', '坪林區', '烏來區', '永和區', '中和區', '土城區', '三峽區', '樹林區', '鶯歌區', '三重區', '新莊區', '泰山區', '林口區', '蘆洲區', '五股區', '八里區', '淡水區', '三芝區', '石門區'],
        '桃園市': ['中壢區', '平鎮區', '龍潭區', '楊梅區', '新屋區', '觀音區', '桃園區', '龜山區', '八德區', '大溪區', '復興區', '大園區', '蘆竹區'],
        '臺中市': ['中區', '東區', '南區', '西區', '北區', '北屯區', '西屯區', '南屯區', '太平區', '大里區', '霧峰區', '烏日區', '豐原區', '后里區', '石岡區', '東勢區', '和平區', '新社區', '潭子區', '大雅區', '神岡區', '大肚區', '沙鹿區', '龍井區', '梧棲區', '清水區', '大甲區', '外埔區', '大安區'],
        '臺南市': ['中西區', '東區', '南區', '北區', '安平區', '安南區', '永康區', '歸仁區', '新化區', '左鎮區', '玉井區', '楠西區', '南化區', '仁德區', '關廟區', '龍崎區', '官田區', '麻豆區', '佳里區', '西港區', '七股區', '將軍區', '學甲區', '北門區', '新營區', '後壁區', '白河區', '東山區', '六甲區', '下營區', '柳營區', '鹽水區', '善化區', '大內區', '山上區', '新市區', '安定區'],
        '高雄市': ['楠梓區', '左營區', '鼓山區', '三民區', '鹽埕區', '前金區', '新興區', '苓雅區', '前鎮區', '旗津區', '小港區', '鳳山區', '林園區', '大寮區', '大樹區', '大社區', '仁武區', '鳥松區', '岡山區', '橋頭區', '燕巢區', '田寮區', '阿蓮區', '路竹區', '湖內區', '茄萣區', '永安區', '彌陀區', '梓官區', '旗山區', '美濃區', '六龜區', '甲仙區', '杉林區', '內門區', '茂林區', '桃源區', '那瑪夏區'],
        '基隆市': ['仁愛區', '信義區', '中正區', '中山區', '安樂區', '暖暖區', '七堵區'],
        '新竹市': ['東區', '北區', '香山區'],
        '嘉গ্市': ['東區', '西區'],
        '新竹縣': ['竹北市', '湖口鄉', '新豐鄉', '新埔鎮', '關西鎮', '芎林鄉', '寶山鄉', '竹東鎮', '五峰鄉', '橫山鄉', '尖石鄉', '北埔鄉', '峨眉鄉'],
        '苗栗縣': ['竹南鎮', '頭份市', '三灣鄉', '南庄鄉', '獅潭鄉', '後龍鎮', '通霄鎮', '苑裡鎮', '苗栗市', '造橋鄉', '頭屋鄉', '公館鄉', '大湖鄉', '泰安鄉', '銅鑼鄉', '三義鄉', '西湖鄉', '卓蘭鎮'],
        '彰化縣': ['彰化市', '芬園鄉', '花壇鄉', '秀水鄉', '鹿港鎮', '福興鄉', '線西鄉', '和美鎮', '伸港鄉', '員林市', '社頭鄉', '大村鄉', '埔心鄉', '溪湖鎮', '埔鹽鄉', '芳苑鄉', '大城鄉', '竹塘鄉', '二林鎮', '溪州鄉', '田尾鄉', '埤頭鄉', '田中鎮', '北斗鎮', '二水鄉'],
        '南投縣': ['南投市', '中寮鄉', '草屯鎮', '國姓鄉', '埔里鎮', '仁愛鄉', '名間鄉', '集集鎮', '水里鄉', '魚池鄉', '信義鄉', '竹山鎮', '鹿谷鄉'],
        '雲林縣': ['斗南鎮', '大埤鄉', '虎尾鎮', '土庫鎮', '褒忠鄉', '東勢鄉', '臺西鄉', '崙背鄉', '麥寮鄉', '斗六市', '林內鄉', '古坑鄉', '莿桐鄉', '西螺鎮', '二崙鄉', '北港鎮', '水林鄉', '口湖鄉', '四湖鄉', '元長鄉'],
        '嘉義縣': ['番路鄉', '梅山鄉', '竹崎鄉', '阿里山鄉', '中埔鄉', '大埔鄉', '水上鄉', '鹿草鄉', '太保市', '朴子市', '東石鄉', '六腳鄉', '新港鄉', '民雄鄉', '溪口鄉', '布袋鎮', '義竹鄉'],
        '屏東縣': ['屏東市', '三地門鄉', '霧臺鄉', '瑪家鄉', '九如鄉', '里港鄉', '高樹鄉', '盬埔鄉', '長治鄉', '麟洛鄉', '萬丹鄉', '竹田鄉', '泰武鄉', '來義鄉', '萬巒鄉', '崁頂鄉', '新埤鄉', '南州鄉', '林邊鄉', '東港鎮', '佳冬鄉', '新園鄉', '枋寮鄉', '枋山鄉', '春日鄉', '獅子鄉', '車城鄉', '牡丹鄉', '恆春鎮', '滿州鄉'],
        '宜蘭縣': ['宜蘭市', '頭城鎮', '礁溪鄉', '壯圍鄉', '員山鄉', '羅東鎮', '三星鄉', '大同鄉', '五結鄉', '冬山鄉', '蘇澳鎮', '南澳鄉'],
        '花蓮縣': ['花蓮市', '新城鄉', '秀林鄉', '吉安鄉', '壽豐鄉', '鳳林鎮', '光復鄉', '豐濱鄉', '瑞穗鄉', '萬榮鄉', '玉里鎮', '卓溪鄉', '富里鄉'],
        '臺東縣': ['臺東市', '綠島鄉', '蘭嶼鄉', '延平鄉', '卑南鄉', '鹿野鄉', '大武鄉', '太麻里鄉', '東河鄉', '池上鄉', '成功鎮', '長濱鄉', '海端鄉', '關山鎮'],
        '澎湖縣': ['馬公市', '西嶼鄉', '望安鄉', '七美鄉', '白沙鄉', '湖西鄉'],
        '金門縣': ['金沙鎮', '金湖鎮', '金寧鄉', '金城鎮', '烈嶼鄉', '烏坵鄉'],
        '連江縣': ['南竿鄉', '北竿鄉', '莒光鄉', '東引鄉']
    };
 
    let selectedFood = '';
    let selectedCity = '';
    let selectedDist = '';
    let distUnlocked = false;

    // 頁面載入時動態生成全台灣縣市選單
    window.addEventListener('DOMContentLoaded', () => {
        const cityMenu = document.getElementById('cityMenu');
        Object.keys(taiwanData).forEach(city => {
            const li = document.createElement('li');
            li.textContent = city;
            li.onclick = () => selectCity(city);
            cityMenu.appendChild(li);
        });
    });
 
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
        const districts = taiwanData[city] || [];
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
 
    // 💡 開關左側推薦清單
    function toggleRecommendations() {
        const sidebarList = document.getElementById('restaurantList');
        const btn = document.getElementById('toggleRecBtn');
        sidebarList.classList.toggle('collapsed');
        if (sidebarList.classList.contains('collapsed')) {
            btn.innerText = '開啟推薦';
            btn.style.backgroundColor = 'var(--green-deep)';
            btn.style.color = '#fff';
        } else {
            btn.innerText = '關閉推薦';
            btn.style.backgroundColor = '#ffffff';
            btn.style.color = '#222222';
        }
        
        // 觸發視窗重繪，讓 Leaflet 地圖自動填滿空間
        setTimeout(() => { map.invalidateSize(); }, 400);
    }

    function toggleHeart(event, el, storeName) {
        event.stopPropagation();
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
 
    // 初始化地圖
    const map = L.map('map').setView([22.7306, 120.3286], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
 
    // Demo 預設地標
    // 💡 動態載入店家地標與彈出小視窗 (Popup)
    <?php
    // 將資料庫指標重新歸零，以便再次讀取店家資料給地圖使用
    mysqli_data_seek($result_stores, 0);
    $marker_data = [];
    
    // 預設座標（防呆用，如果你資料庫未來加了 lat 跟 lng 欄位會自動優先使用真實座標）
    $demo_locations = [
        [22.733, 120.325], [22.728, 120.332], [22.735, 120.319], [23.025, 120.254]
    ];
    
    $m_idx = 0;
    while ($row = mysqli_fetch_assoc($result_stores)) {
        $lat = isset($row['lat']) ? $row['lat'] : ($demo_locations[$m_idx][0] ?? 22.7306 + (rand(-15,15)/1000));
        $lng = isset($row['lng']) ? $row['lng'] : ($demo_locations[$m_idx][1] ?? 120.3286 + (rand(-15,15)/1000));
        
        $marker_data[] = [
            'id' => $row['No'],
            'store_name' => htmlspecialchars($row['store_name'], ENT_QUOTES),
            'lat' => $lat,
            'lng' => $lng
        ];
        $m_idx++;
    }
    ?>
    
    // 將 PHP 陣列轉為 JS 可用的 JSON 格式
    const storesData = <?php echo json_encode($marker_data); ?>;
    
    storesData.forEach(store => {
        // 在地圖上建立圖標
        const marker = L.marker([store.lat, store.lng]).addTo(map);
        
        // 💡 修正：這裡變數改為 store.store_name 以對應上面的 PHP 資料庫陣列名稱
        const popupContent = `
            <div style="text-align: center; min-width: 110px; padding: 2px 0;">
                <div style="font-size: 14px; font-weight: 900; color: #132a13; margin-bottom: 12px;">${store.store_name}</div>
                <button onclick="navigateToStore('${store.id}')" style="background: #3a5a40; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; cursor: pointer; width: 100%; font-size: 12px; transition: background 0.2s;" onmouseover="this.style.background='#132a13'" onmouseout="this.style.background='#3a5a40'">
                    <i class="fa-solid fa-store"></i> 檢視商店
                </button>
            </div>
        `;
        
        // 將視窗綁定到地標上
        marker.bindPopup(popupContent);
    });
 
    // 💡 修正：精確定位改用飛越 (flyTo) 與較高的縮放級別 (Zoom 17) 來模擬 Google Maps 體驗
    // 💡 修正：精確定位改用飛越 (flyTo) 與較高的縮放級別 (Zoom 17)，並加上專屬紅色地標
    function getLocation() {
        if (!navigator.geolocation) { alert('您的瀏覽器不支援定位功能'); return; }
        navigator.geolocation.getCurrentPosition(
            pos => {
                const { latitude: lat, longitude: lng } = pos.coords;
                // flyTo 提供滑順的飛行動畫，17 代表較近的街道層級
                map.flyTo([lat, lng], 17, {
                    animate: true,
                    duration: 1.5 // 飛行秒數
                });
                
                // 📍 自訂一個帶有陰影的紅色地標圖示
                const redIcon = L.divIcon({
                    html: '<i class="fa-solid fa-location-dot fa-3x" style="color: #e63946; filter: drop-shadow(0px 4px 4px rgba(0,0,0,0.3));"></i>',
                    className: 'custom-red-marker',
                    iconSize: [30, 42],
                    iconAnchor: [15, 42],
                    popupAnchor: [0, -35]
                });

                // 將原本的 L.circle 替換為 L.marker，並套用剛剛設定好的紅色圖示
                L.marker([lat, lng], { icon: redIcon })
                 .addTo(map).bindPopup('📍 您目前的位置').openPopup();
            },
            () => alert('無法取得定位，請檢查瀏覽器定位權限。')
        );
    }
</script>
</body>
</html>
<?php mysqli_close($link); ?>