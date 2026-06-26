<?php
// 此檔案會被 include 到其他消費者前台頁面中
session_start();
$is_login = (isset($_SESSION['login']) && isset($_SESSION['uName'])) ? true : false;
$link = @mysqli_connect('localhost', 'root', '', 'food_waste');
mysqli_set_charset($link, "utf8mb4");

// 防呆預設資料（精確遵循你的截圖訊息與時間格式）
$uName_for_display = $is_login ? htmlspecialchars($_SESSION['uName']) : "訪客";
?>
<header>
    <div class="nav-left">
        <button class="hamburger-btn" onclick="toggleSidebar('mainSidebar')"><i class="fa-solid fa-bars"></i></button>
        <div class="logo-title">EcoBox剩食平台</div>
    </div>
    
    <div class="nav-right">
        <a href="map.php" class="circle-nav-btn" title="剩食地圖平台"><i class="bi bi-geo-alt"></i></a>
        <a href="userhome.php" class="circle-nav-btn" title="回首頁"><i class="fa-solid fa-house"></i></a>
        
        <?php if ($is_login): ?>
            <button type="button" class="circle-nav-btn profile-nav-btn" onclick="toggleSidebar('profileDrawer')">
                <i class="fa-solid fa-user"></i>
            </button>
        <?php else: ?>
            <a href="login.php" class="circle-nav-btn" title="登入"><i class="fa-solid fa-right-from-bracket"></i></a>
        <?php endif; ?>
    </div>
</header>

<div class="modal-overlay" id="profileDrawerOverlay" onclick="closeSidebar('profileDrawer')"></div>
<div class="profile-drawer-box" id="profileDrawer">
    <div class="drawer-head">
        <button class="drawer-close-btn" onclick="closeSidebar('profileDrawer')">×</button>
    </div>
    <div class="drawer-body">
        <div class="drawer-avatar-section">
            <div class="avatar-large"><img src="image/預設頭像.png" alt="個人頭像"></div>
            <div class="drawer-username"><?php echo $uName_for_display; ?></div>
        </div>
        
        <div class="drawer-action-group">
            <a href="orders.php" class="drawer-link-btn"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a>
            
            <a href="profile_edit.php" class="drawer-link-btn"><i class="fa-solid fa-user-pen"></i> 修改個人資料</a>
            
            <a href="logout.php" class="drawer-link-btn logout-accent-btn"><i class="fa-solid fa-right-from-bracket"></i> 登出 EcoBox</a>
        </div>
    </div>
</div>

<div class="modal-overlay" id="mainSidebarOverlay" onclick="closeSidebar('mainSidebar')"></div>
<div class="sidebar-drawer-box" id="mainSidebar">
    <button class="drawer-close-btn" onclick="closeSidebar('mainSidebar')">×</button>
    <ul class="sidebar-menu">
        <li class="sidebar-item"><a href="favorites.php"><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
        <li class="sidebar-item"><a href="notifications.php" class="active"><i class="fa-regular fa-bell"></i> 通知管理</a></li>
        <li class="sidebar-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
        <li class="sidebar-item"><a href="orders.php"><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
        <li class="sidebar-item"><a href="help.php"><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
    </ul>
</div>

<script>
    function toggleSidebar(sidebarId) {
        document.getElementById(sidebarId).classList.add('active');
        document.getElementById(sidebarId + 'Overlay').classList.add('active');
    }
    function closeSidebar(sidebarId) {
        document.getElementById(sidebarId).classList.remove('active');
        document.getElementById(sidebarId + 'Overlay').classList.remove('active');
    }
</script>

<style>
    /* 這裡貼上你現有的 Header 樣式（例如 green-deep 背景、circle-nav-btn 灰色底等） */
    :root {
        --green-deep:  #132a13; --green-mid:   #3a5a40; --accent-gold: #b8975a; --accent-light:#f3d078;
        --bg-warm:     #f7f6f2; --text-dark:   #1a1a1a; --border:      #eef0f2; --danger:      #e63946;
    }
    
    /* 側邊彈窗專用 CSS（精確還原第一張截圖） */
    .profile-drawer-box, .sidebar-drawer-box {
        position: fixed; top: 0; height: 100%;
        background: #fff; box-shadow: -5px 0 25px rgba(0,0,0,0.1);
        transition: right 0.4s ease, left 0.4s ease; z-index: 1200; display: flex; flex-direction: column; padding: 25px;
    }
    /* 個人圖標選單靠右，漢堡選單靠左 */
    .profile-drawer-box { right: -320px; width: 320px; }
    .profile-drawer-box.active { right: 0; }
    .sidebar-drawer-box { left: -280px; width: 280px; }
    .sidebar-drawer-box.active { left: 0; }
    
    /* 遮罩（毛玻璃效果同步截圖） */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
    .modal-overlay.active { display: block; }
    
    /* 彈窗內容樣式範例 */
    .drawer-head { display: flex; justify-content: flex-end; }
    .drawer-close-btn { font-size: 26px; border: none; background: none; cursor: pointer; color: #aaa; }
    .drawer-close-btn:hover { color: #333; }
    
    .avatar-large { width: 80px; height: 80px; border-radius: 50%; border: 2.5px solid var(--green-mid); overflow: hidden; margin: 0 auto 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .avatar-large img { width: 100%; height: 100%; object-fit: cover; }
    .drawer-username { font-size: 20px; font-weight: 800; color: #111; text-align: center; margin-bottom: 25px; }
    
    .drawer-action-group { display: flex; flex-direction: column; gap: 10px; }
    .drawer-link-btn { display: flex; align-items: center; gap: 12px; padding: 14px 20px; text-decoration: none; color: var(--text-dark); font-size: 16px; font-weight: 500; border-radius: 8px; transition: all 0.2s; background: #fafafa; }
    .drawer-link-btn:hover { background: #f0f0f0; padding-left: 24px; }
    .logout-accent-btn { color: var(--danger) !important; font-weight: 700 !important; }
</style>