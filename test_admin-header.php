<?php
// 1. 自動取得當前網頁的檔名，用來判斷側邊欄哪個按鈕要加上 active
$currentPage = basename($_SERVER['PHP_SELF']);

// 2. 撈取當前登入管理員的資訊
if (!isset($link) && isset($conn)) { $link = $conn; }

// 假設管理員的 Session 是 admin_id (對應資料庫的 id 欄位)
$admin_id_escaped = mysqli_real_escape_string($link, $_SESSION['admin_id'] ?? '系統管理員');

$sql_admin_info = "SELECT id FROM admin WHERE id = '$admin_id_escaped'";
$result_admin_info = @mysqli_query($link, $sql_admin_info);
$admin_info = $result_admin_info ? mysqli_fetch_assoc($result_admin_info) : null;

$admin_display_name = $admin_info['id'] ?? $admin_id_escaped;
?>

<!-- 💡 關鍵修正：補上 FontAwesome 讓漢堡鈕、通知、首頁圖示顯示 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    /* ── 共用變數 ── */
    :root {
        --green-deep:  #659287; 
        --green-mid:   #3a5a40;
        --accent-gold: #b8975a;
        --accent-light:#f3d078;
        --bg-warm:     #e3e2d4;
        --bg-white:    #ffffff;
        --text-dark:   #23342b;
        --text-muted:  #5a6e63;
    }

    /* ── Header ── */
    .admin-header {
        height: 75px; display: flex; justify-content: space-between; align-items: center;
        padding: 0 40px; background: var(--green-deep); box-shadow: 0 4px 20px rgba(0,0,0,.15);
        position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    }
    .header-left  { display: flex; align-items: center; gap: 25px; }
    .hamburger-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; }
    .admin-brand { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
    .admin-badge {
        background: rgba(255,255,255,0.2); color: #fff;
        padding: 4px 14px; border-radius: 20px; font-size: 14px; font-weight: 600;
    }
    .header-right {
        display: flex; gap: 18px; align-items: center;
    }
    .nav-icon {
        font-size: 28px; color: rgba(255,255,255,0.9); text-decoration: none;
        cursor: pointer; transition: all 0.3s ease;
        display: flex; align-items: center; justify-content: center;
        background: none; border: none; padding: 0;
    }
    .nav-icon:hover { color: var(--accent-gold); transform: translateY(-2px); }

    /* ── 側邊欄 (寬度 280px) ── */
    .sidebar {
        box-sizing: border-box; /* 💡 關鍵修正：讓 padding 包含在設定的寬度內 */
        position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
        background: var(--green-deep); box-shadow: 5px 0 25px rgba(0,0,0,.15);
        transition: left .4s cubic-bezier(.25,1,.5,1); z-index: 1200;
        padding: 30px 20px; display: flex; flex-direction: column;
    }
    .sidebar.active { left: 0; }

    .sidebar-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 30px; border-bottom: 2px solid #ffffff; padding-bottom: 15px;
    }
    .sidebar-header div { font-size: 20px; font-weight: 800; color: #ffffff; }
    .sidebar-close-btn { font-size: 26px; background: none; border: none; cursor: pointer; color: #ffffff; margin-bottom: 0; }
    
    .sidebar-menu { list-style: none; padding: 0; margin: 0; }
    .sidebar-item a {
        display: flex; align-items: center; gap: 15px; padding: 15px 20px;
        text-decoration: none; color: #ffffff; font-size: 17px; font-weight: 500;
        border-radius: 8px; margin-bottom: 8px; transition: all .2s;
    }
    .sidebar-item a:hover { background: rgba(255,255,255,0.2); color: #ffffff; }
    .sidebar-item a.active {
        background-color: var(--accent-light); color: #000000;
        font-weight: 700; box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35);
    }
    .sidebar-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,.5);
        backdrop-filter: blur(3px); z-index: 1100; display: none;
    }
    .sidebar-overlay.active { display: block; }

    /* ── 管理者資訊彈窗 ── */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
        z-index: 2000; display: none; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .profile-card {
        background: #fff; width: 320px; padding: 35px 25px; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,.15); position: relative;
        display: flex; flex-direction: column; align-items: center;
        border: 2px solid #000000;
    }
    .modal-close-btn {
        position: absolute; top: 15px; right: 15px; background: none; border: none;
        font-size: 22px; cursor: pointer; color: #4a4a4a;
    }
    .profile-avatar-wrapper {
        width: 100px; height: 100px; background: var(--green-deep); border-radius: 50%;
        display: flex; align-items: center; justify-content: center; margin-bottom: 15px;
    }
    .profile-avatar-wrapper i { font-size: 50px; color: #ffffff; }
    
    .admin-account-text { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 25px; }
    .modal-btn-group { display: flex; width: 100%; justify-content: center; }
    .btn-logout-action {
        background: #eeeeee; color: #000000; border: none; padding: 12px 24px;
        border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer;
        width: 100%; text-align: center; text-decoration: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.2s;
    }
    .btn-logout-action:hover { background: #dddddd; }

    body { padding-top: 75px; background: #f7f6f2;}
  /*  main { background: #544b29; }*/
</style>

<main>
<header class="admin-header">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="header-left">
        <!-- 漢堡鈕因為 FontAwesome 補上，現在會顯示出來了 -->
        <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
        <div class="admin-brand">
            <a href="admin_index.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px;">
                EcoBox 管理後台
            </a>
        </div>
        <span class="admin-badge">管理員：<?php echo htmlspecialchars($admin_display_name); ?></span>
    </div>
    
    <div class="header-right">
        <!-- 通知與首頁按鈕因為 FontAwesome 補上，現在也會顯示了 -->
        <a href="admin_notifications.php" class="nav-icon" title="通知"><i class="fa-solid fa-bell"></i></a>
        <a href="admin_index.php" class="nav-icon" title="回管理首頁"><i class="fa-solid fa-house"></i></a>
        <button class="nav-icon" id="profileBtn" title="管理員帳號資訊">
            <i class="bi bi-person-circle"></i> 
        </button>
    </div>
</header>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div>嗨，<?php echo htmlspecialchars($admin_display_name); ?>！</div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="admin_index.php" <?php if($currentPage == 'admin_index.php') echo 'class="active"'; ?>>
                <i class="fa-solid fa-users"></i> 使用者管理
            </a>
        </li>
        <li class="sidebar-item">
            <a href="admin_stores.php" <?php if($currentPage == 'admin_stores.php') echo 'class="active"'; ?>>
                <i class="fa-solid fa-store"></i> 商家管理
            </a>
        </li>
        <li class="sidebar-item">
            <a href="admin_reviews.php" <?php if($currentPage == 'admin_reviews.php') echo 'class="active"'; ?>>
                <i class="fa-regular fa-comment-dots"></i> 評論管理
            </a>
        </li>
        <li class="sidebar-item">
            <a href="admin_data.php" <?php if($currentPage == 'admin_data.php') echo 'class="active"'; ?>>
                <i class="fa-solid fa-chart-line"></i> 數據管理
            </a>
        </li>
        <li class="sidebar-item">
            <a href="admin_questions.php" <?php if($currentPage == 'admin_questions.php') echo 'class="active"'; ?>>
                <i class="fa-solid fa-headset"></i> 問題中心
            </a>
        </li>
    </ul>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="modal-overlay" id="profileModal">
    <div class="profile-card">
        <button class="modal-close-btn" id="modalCloseBtn"><i class="fa-solid fa-xmark"></i></button>
        <div class="profile-avatar-wrapper">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="admin-account-text">
            帳號：<?php echo htmlspecialchars($admin_display_name); ?>
        </div>
        <div class="modal-btn-group">
            <a href="logout.php" class="btn-logout-action">安全登出</a>
        </div>
    </div>
</div>

<script>
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar()  { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
    if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
    if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    const profileBtn = document.getElementById('profileBtn');
    const profileModal = document.getElementById('profileModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');

    if (profileBtn) profileBtn.addEventListener('click', () => profileModal.classList.add('active'));
    if (modalCloseBtn) modalCloseBtn.addEventListener('click', () => profileModal.classList.remove('active'));
    if (profileModal) {
        profileModal.addEventListener('click', (e) => {
            if (e.target === profileModal) profileModal.classList.remove('active');
        });
    }
</script>
</main>