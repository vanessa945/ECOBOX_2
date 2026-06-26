<?php
// 1. 自動取得當前網頁的檔名 (例如: notifications.php)，用來判斷側邊欄哪個按鈕要加上 active
$currentPage = basename($_SERVER['PHP_SELF']);

$uName_escaped = mysqli_real_escape_string($link, $_SESSION['uName']);
// 2. 撈取當前登入消費者的個人資訊供彈窗使用
// (假設主檔案引入此檔前，已建立好 $link 與 $uName_escaped)
$sql_user = "SELECT user_name, email FROM consumer WHERE id = '$uName_escaped' OR email = '$uName_escaped'";
$result_user = @mysqli_query($link, $sql_user);
$user_info = $result_user ? mysqli_fetch_assoc($result_user) : null;

$user_display_name = (!empty($user_info['user_name'])) ? $user_info['user_name'] : $uName_escaped;
$user_email = $user_info['email'] ?? '';
?>

<style>
    /* ── 共用變數 ── */
    :root {
        /*--green-deep:  #132a13;*/
        --green-deep:  #659287;
        --green-mid:   #3a5a40;
        --accent-gold: #b8975a;
        --accent-light:#f3d078;
        --bg-warm:     #f7f6f2;
        --bg-white:    #ffffff;
        --text-dark:   #1a1a1a;
        --text-muted:  #888888;
        --border:      #eef0f2;
    }

    /* ── 共用 Header ── */
    header {
        height: 75px; display: flex; justify-content: space-between; align-items: center;
        padding: 0 40px; background: var(--green-deep); box-shadow: 0 4px 20px rgba(0,0,0,.15);
        position: relative; z-index: 1000;
    }
    .nav-left  { display: flex; align-items: center; gap: 25px; }
    .hamburger-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; }
    .logo-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 1px; }
    .nav-right { 
        display: flex; 
        gap: 18px;
        align-items: center; 
    }
    .nav-icon {
        font-size: 30px;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .nav-icon:hover {
        color: var(--accent-gold); 
        transform: translateY(-2px);
    }
    /* ── 共用側邊欄 ── */
    .sidebar {
        position: fixed; top: 0; left: -280px; width: 280px; height: 100%;
        background: #659287; box-shadow: 5px 0 25px rgba(0,0,0,.15);
        transition: left .4s cubic-bezier(.25,1,.5,1); z-index: 1200; padding: 30px 20px; display: flex; flex-direction: column;
    }
    /* 側邊欄頂部姓名 */
    .sidebar-header div {
        font-size: 20px; 
        font-weight: 800; 
        color: #ffffff !important;  /* 改成白色 */
    }
    .sidebar.active { left: 0; }
    .sidebar-close-btn { align-self: flex-end; font-size: 26px; background: none; border: none; cursor: pointer; margin-bottom: 30px; color: #ffffff; }
    .sidebar-menu { list-style: none; padding: 0; margin: 0; }
    .sidebar-item a { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #ffffff; font-size: 17px; font-weight: 500; border-radius: 8px; margin-bottom: 8px; transition: all .2s; }
    .sidebar-item a:hover { background: rgba(255, 255, 255, 0.30); color: #ffffff; }
    .sidebar-item a.active { background-color: var(--accent-light); color: #000000; font-weight: 700; box-shadow: 0 4px 12px rgba(243, 208, 120, 0.35); }
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(3px); z-index: 1100; display: none; }
    .sidebar-overlay.active { display: block; }

    /* ── 共用個人資訊彈窗 (Modal) ── */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
        z-index: 2000; display: none; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .profile-card {
        background: #fff; width: 400px; padding: 35px; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,.15); position: relative;
        display: flex; flex-direction: column; align-items: center;
        border: 2px solid #000000;
    }
    .modal-close-btn {
        position: absolute; top: 15px; right: 15px; background: none; border: none;
        font-size: 22px; cursor: pointer; color: #4a4a4a;
    }
    .profile-avatar-wrapper {
        width: 110px; height: 110px; background: #4a4a4a; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; margin-bottom: 30px;
    }
    .profile-avatar-wrapper i { font-size: 65px; color: #ffffff; }
    .profile-form-group { width: 100%; margin-bottom: 25px; display: flex; flex-direction: column; gap: 8px; }
    .profile-form-group label { font-size: 18px; font-weight: bold; color: #000000; }
    .profile-form-group input { width: 100%; padding: 10px 5px; font-size: 16px; border: none; border-bottom: 1px solid #ccc; outline: none; }
    .profile-form-group input:focus { border-bottom: 1px solid var(--green-mid); }
    .modal-btn-group { display: flex; width: 100%; justify-content: space-between; gap: 20px; margin-top: 15px; }
    .btn-join-member { background: #f3d078; color: #000000; border: none; padding: 12px 24px; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; flex: 1; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.2s; }
    .btn-join-member:hover { background: #e2be67; }
    .btn-logout-action { background: #eeeeee; color: #000000; border: none; padding: 12px 24px; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; flex: 1; text-align: center; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.2s; }
    .btn-logout-action:hover { background: #dddddd; }
</style>

<header>
    <div class="nav-left">
        <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
        <div class="logo-title">EcoBox剩食平台</div>
    </div>
    <div class="nav-right">
        <a href="map.php" class="nav-icon" title="查看附近剩食"><i class="bi bi-geo-alt-fill"></i></a>
        
        <?php if($currentPage !== 'userhome.php'): ?>
            <a href="userhome.php" class="nav-icon" title="回首頁"><i class="fa-solid fa-house"></i></a>
        <?php endif; ?>

        <button class="nav-icon" id="profileBtn" title="消費者個人資訊" style="background: none; border: none; padding: 0;">
            <i class="bi bi-person-circle"></i>
        </button>
    </div>
</header>

<div class="sidebar" id="sidebar">
        <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--border); padding-bottom: 15px;">
           <div style="display: flex; flex-direction: column; gap: 2px; max-width: 190px;">
                <div style="font-size: 20px; font-weight: 800; color: #ffffff;">嗨，</div>
                <div style="font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.85); word-break: break-all; line-height: 1.4;">
                    <?php echo htmlspecialchars($user_display_name); ?>！
                </div>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" style="margin-bottom: 0;"><i class="fa-solid fa-xmark"></i></button>
        </div>    
        <ul class="sidebar-menu">
        <li class="sidebar-item"><a href="favorites.php" <?php if($currentPage == 'favorites.php') echo 'class="active"'; ?>><i class="fa-regular fa-heart"></i> 收藏清單</a></li>
        <li class="sidebar-item"><a href="notifications.php" <?php if($currentPage == 'notifications.php') echo 'class="active"'; ?>><i class="fa-regular fa-bell"></i> 通知管理</a></li>
        <li class="sidebar-item"><a href="cart.php" <?php if($currentPage == 'cart.php') echo 'class="active"'; ?>><i class="fa-solid fa-cart-shopping"></i> 購物車</a></li>
        <li class="sidebar-item"><a href="orders.php" <?php if($currentPage == 'orders.php') echo 'class="active"'; ?>><i class="fa-regular fa-clipboard"></i> 歷史訂單</a></li>
        <li class="sidebar-item"><a href="help.php" <?php if($currentPage == 'help.php') echo 'class="active"'; ?>><i class="fa-regular fa-circle-question"></i> 買家幫助中心</a></li>
    </ul>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="modal-overlay" id="profileModal">
    <div class="profile-card">
        <button class="modal-close-btn" id="modalCloseBtn"><i class="fa-solid fa-xmark"></i></button>
        <div class="profile-avatar-wrapper"><i class="fa-solid fa-user"></i></div>
        <form action="update_profile.php" method="POST" style="width: 100%;">
            <div class="profile-form-group">
                <label>使用者姓名：</label>
                <input type="text" name="update_name" value="<?php echo htmlspecialchars($user_display_name); ?>" required>
            </div>
            <div class="profile-form-group">
                <label>電子信箱：</label>
                <input type="email" name="update_email" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="請輸入電子信箱" required>
            </div>
            <div class="modal-btn-group">
                <button type="submit" class="btn-join-member">修改儲存</button>
                <a href="logout.php" class="btn-logout-action">登出</a>
            </div>
        </form>
    </div>
</div>

<script>
    // 側邊欄控制
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function openSidebar()  { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
    if(hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
    if(sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
    if(sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // 彈窗控制
    const profileBtn = document.getElementById('profileBtn');
    const profileModal = document.getElementById('profileModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');

    if(profileBtn) profileBtn.addEventListener('click', () => profileModal.classList.add('active'));
    if(modalCloseBtn) modalCloseBtn.addEventListener('click', () => profileModal.classList.remove('active'));
    if(profileModal) {
        profileModal.addEventListener('click', (e) => {
            if (e.target === profileModal) profileModal.classList.remove('active');
        });
    }
</script>