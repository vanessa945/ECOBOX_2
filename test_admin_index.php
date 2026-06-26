<?php
// admin_index.php
session_start();
if (!file_exists("db.php")) { die("找不到 db.php"); }
if (!file_exists("admin-header.php")) { die("找不到 admin-header.php"); }
include("db.php");

$page_title = "使用者管理";
$active_page = "users";
include("admin-header.php");

// Fetch all users
$users_result = mysqli_query($conn, "SELECT * FROM consumer ORDER BY user_name ASC");
$total = mysqli_num_rows($users_result);
$rows = [];
while ($row = mysqli_fetch_assoc($users_result)) { $rows[] = $row; }
?>

<style>
    /* ── EcoBox 設計系統：共用與使用者卡片專屬樣式 ── */
    :root {
        --green-deep:  #659287; 
        --green-mid:   #3a5a40;
        --accent-gold: #b8975a;
        --bg-white:    #ffffff;
        --text-dark:   #23342b;
        --text-muted:  #5a6e63;
        --danger-red:  #e74c3c;
    }

    /* 頁面基礎設定 */
    body { padding-top: 75px; background-color: #fdfdfc; }

    .users-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* 頂部控制列 (標題與搜尋) */
    .page-header-ctrl {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    /* 搜尋框風格 */
    .search-wrap { 
        position: relative; 
    }
    .search-wrap input {
        padding: 10px 16px 10px 40px;
        border: 1px solid rgba(101, 146, 135, 0.3);
        border-radius: 20px;
        width: 280px;
        outline: none;
        font-size: 15px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(35, 52, 43, 0.05);
    }
    .search-wrap input:focus {
        border-color: var(--green-deep);
        box-shadow: 0 0 0 3px rgba(101, 146, 135, 0.1);
    }
    .search-wrap::before {
        content: '\f002'; /* FontAwesome 放大鏡 */
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute; left: 14px; top: 50%;
        transform: translateY(-50%); 
        font-size: 14px;
        color: var(--text-muted);
    }

    /* 統計晶片 */
    .stat-badge {
        background: rgba(101, 146, 135, 0.15);
        color: var(--green-mid);
        padding: 6px 16px; 
        border-radius: 20px; 
        font-size: 14px; 
        display: inline-block;
        margin-bottom: 20px;
    }
    .stat-badge strong { font-weight: 800; font-size: 15px; }

    /* 卡片主體 */
    .user-card {
        background: var(--bg-white);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
        border: 1px solid rgba(101, 146, 135, 0.15);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .user-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(35, 52, 43, 0.1);
    }

    /* 卡片標頭：名稱與按鈕 */
    .user-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }

    /* 圓形圖示區 */
    .user-title-group {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 18px;
        font-weight: bold;
        color: var(--text-dark);
    }
    .user-avatar {
        width: 42px; height: 42px;
        border-radius: 50%;
        background: rgba(101, 146, 135, 0.15);
        color: var(--green-mid);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    /* 內部資料區塊 */
    .user-content-box {
        background: #fdfdfc;
        padding: 18px;
        border-radius: 8px;
        border-left: 4px solid var(--green-deep);
    }

    /* 網格資訊欄位 */
    .user-info-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
        gap: 15px 20px; 
    }
    .info-item { display: flex; flex-direction: column; }
    .info-label { 
        font-size: 12px; 
        font-weight: 700; 
        color: var(--text-muted); 
        margin-bottom: 4px; 
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .info-value { 
        font-size: 15px; 
        color: var(--text-dark); 
        word-break: break-all; 
        font-weight: 500;
    }
    .info-value.masked { color: var(--text-muted); letter-spacing: 2px; }

    /* 右側按鈕群組 */
    .btn-group {
        display: flex;
        gap: 10px;
    }
    .btn { 
        padding: 6px 16px; 
        border-radius: 8px; 
        font-size: 13px; 
        font-weight: bold; 
        cursor: pointer; 
        border: none; 
        transition: 0.2s; 
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-edit { 
        background: #e8f4ec; 
        color: var(--green-mid); 
    }
    .btn-edit:hover { 
        background: #d0eadc; 
    }
    .btn-delete { 
        background: #fde8e8; 
        color: var(--danger-red); 
    }
    .btn-delete:hover { 
        background: #f9d5d5; 
    }

    /* 空狀態 */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
        background: var(--bg-white);
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
    }
</style>

<main class="admin-main" style="max-width: 1100px; margin: 0 auto; padding: 20px;">
    
    <div class="page-header-ctrl">
        <h2 style="font-size: 2.2rem; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-users"></i> 使用者管理
        </h2>
        <div class="search-wrap">
            <input type="text" id="search-input" placeholder="搜尋使用者名稱、信箱或電話..." oninput="filterUsers(this.value)">
        </div>
    </div>
    
    <div class="stat-badge">
        總使用者數 <strong><?php echo $total; ?></strong>
    </div>

    <div class="users-container" id="user-list-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open" style="font-size: 40px; margin-bottom: 15px; color: rgba(101,146,135,0.5);"></i>
                <p style="font-size: 16px;">目前尚無使用者資料</p>
            </div>
        <?php else: foreach ($rows as $row): 
            // 將多欄位加入搜尋範圍
            $search_text = htmlspecialchars(strtolower(($row['user_name'] ?? '') . ' ' . ($row['email'] ?? '') . ' ' . ($row['phone'] ?? '')));
        ?>
            <div class="user-card" data-searchtext="<?php echo $search_text; ?>">
                
                <div class="user-header">
                    <div class="user-title-group">
                        <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                        <span><?php echo htmlspecialchars($row['user_name'] ?? '—'); ?></span>
                    </div>
                    
                    <div class="btn-group">
                        <button class="btn btn-edit" onclick="location.href='admin_edit.php?id=<?php echo $row['No']; ?>'">
                            <i class="fa-solid fa-pen"></i> 修改
                        </button>
                        <button class="btn btn-delete" onclick="confirmDelete(<?php echo $row['No']; ?>, '<?php echo htmlspecialchars($row['user_name'] ?? '此使用者', ENT_QUOTES); ?>')">
                            <i class="fa-regular fa-trash-can"></i> 刪除
                        </button>
                    </div>
                </div>

                <div class="user-content-box">
                    <div class="user-info-grid">
                        <div class="info-item">
                            <span class="info-label"><i class="fa-solid fa-id-card"></i> 使用者帳號（暱稱）</span>
                            <span class="info-value"><?php echo htmlspecialchars($row['user_name'] ?? '—'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fa-solid fa-lock"></i> 登入密碼</span>
                            <span class="info-value masked">••••••••</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fa-solid fa-phone"></i> 聯絡電話</span>
                            <span class="info-value"><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fa-solid fa-envelope"></i> 電子郵件</span>
                            <span class="info-value"><?php echo htmlspecialchars($row['email'] ?? '—'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fa-solid fa-notes-medical"></i> 過敏原</span>
                            <span class="info-value"><?php echo htmlspecialchars($row['allergy'] ?? '無'); ?></span>
                        </div>
                    </div>
                </div>
                
            </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<script>
    // 搜尋過濾功能
    function filterUsers(keyword) {
        const cards = document.querySelectorAll('.user-card');
        const kw = keyword.toLowerCase().trim();
        cards.forEach(card => {
            const text = card.dataset.searchtext || '';
            card.style.display = (!kw || text.includes(kw)) ? '' : 'none';
        });
    }

    // 刪除確認功能
    function confirmDelete(userId, name) {
        if (confirm(`確定要刪除「${name}」嗎？此操作無法復原。`)) {
            window.location.href = `admin_delete.php?id=${userId}&type=user`;
        }
    }
</script>

</body>
</html>