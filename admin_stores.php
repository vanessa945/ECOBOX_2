<?php
session_start();
include("db.php");
$page_title = "商家管理";
$active_page = "stores";
include("admin-header.php");

$stores_result = mysqli_query($conn, "SELECT * FROM store ORDER BY store_name ASC");
$rows = mysqli_fetch_all($stores_result, MYSQLI_ASSOC);
$total = count($rows);
?>

<style>
    /* ── EcoBox 設計系統：共用與商家卡片專屬樣式 ── */
    :root {
        --green-deep:  #659287; 
        --green-mid:   #3a5a40;
        --accent-gold: #b8975a;
        --bg-white:    #ffffff;
        --text-dark:   #23342b;
        --text-muted:  #5a6e63;
        --danger-red:  #e74c3c;
    }

    .stores-container {
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
    .store-card {
        background: var(--bg-white);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
        border: 1px solid rgba(101, 146, 135, 0.15);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .store-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(35, 52, 43, 0.1);
    }

    /* 卡片標頭：商家名稱與按鈕 */
    .store-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }

    /* 圓形圖示區 */
    .store-title-group {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 18px;
        font-weight: bold;
        color: var(--text-dark);
    }
    .store-avatar {
        width: 42px; height: 42px;
        border-radius: 50%;
        background: rgba(101, 146, 135, 0.15);
        color: var(--green-mid);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    /* 💡 內部資料區塊改成橫向 Flex 排版 */
    .store-content-box {
        background: #fdfdfc;
        padding: 18px;
        border-radius: 8px;
        border-left: 4px solid var(--green-deep);
        display: flex; 
        gap: 30px; /* 左邊文字與右邊圖片的間距 */
        align-items: flex-start;
    }

    /* 左側文字區塊 */
    .store-info-main {
        flex: 1; /* 佔據剩餘的所有空間 */
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* 網格資訊欄位 (聯絡資訊) */
    .store-info-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
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
        line-height: 1.5;
    }

    /* 右側菜單圖片區塊 */
    .store-menu-aside {
        width: 250px; /* 固定右側圖片區塊寬度 */
        flex-shrink: 0; /* 防止圖片被壓縮 */
        border-left: 1px dashed rgba(101, 146, 135, 0.3);
        padding-left: 30px;
    }
    .store-menu-aside img {
        width: 100%;
        border-radius: 8px;
        border: 1px solid rgba(101, 146, 135, 0.2);
        margin-top: 8px;
    }

    /* 響應式：手機版時自動切回直向排版 */
    @media (max-width: 768px) {
        .store-content-box { flex-direction: column; gap: 20px; }
        .store-menu-aside { 
            width: 100%; 
            border-left: none; 
            padding-left: 0; 
            border-top: 1px dashed rgba(101, 146, 135, 0.3); 
            padding-top: 20px; 
        }
    }

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
    .btn-edit { background: #e8f4ec; color: var(--green-mid); }
    .btn-edit:hover { background: #d0eadc; }
    .btn-delete { background: #fde8e8; color: var(--danger-red); }
    .btn-delete:hover { background: #f9d5d5; }

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
            <i class="fa-solid fa-store"></i> 商家管理
        </h2>
        <div class="search-wrap">
            <input type="text" id="search-input" placeholder="搜尋商家名稱、電話或信箱..." oninput="filterStores(this.value)">
        </div>
    </div>
    
    <div class="stat-badge">
        總商家數 <strong><?php echo $total; ?></strong>
    </div>

    <div class="stores-container" id="store-list-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open" style="font-size: 40px; margin-bottom: 15px; color: rgba(101,146,135,0.5);"></i>
                <p style="font-size: 16px;">目前尚無商家資料</p>
            </div>
        <?php else: foreach ($rows as $row): 
            $search_text = htmlspecialchars(strtolower(($row['store_name'] ?? '') . ' ' . ($row['phone'] ?? '') . ' ' . ($row['email'] ?? '')));
        ?>
            <div class="store-card" data-searchtext="<?php echo $search_text; ?>">
                
                <div class="store-header">
                    <div class="store-title-group">
                        <div class="store-avatar"><i class="fa-solid fa-shop"></i></div>
                        <span><?php echo htmlspecialchars($row['store_name'] ?? '—'); ?></span>
                    </div>
                    
                    <div class="btn-group">
                        <button class="btn btn-edit" onclick="location.href='admin_edit.php?id=<?php echo $row['No']; ?>&type=store'">
                            <i class="fa-solid fa-pen"></i> 修改
                        </button>
                        <button class="btn btn-delete" onclick="confirmDelete(<?php echo $row['No']; ?>, '<?php echo htmlspecialchars($row['store_name'], ENT_QUOTES); ?>')">
                            <i class="fa-regular fa-trash-can"></i> 刪除
                        </button>
                    </div>
                </div>

                <div class="store-content-box">
                    
                    <div class="store-info-main">
                        <div class="store-info-grid">
                            <div class="info-item">
                                <span class="info-label"><i class="fa-solid fa-phone"></i> 聯絡電話</span>
                                <span class="info-value"><?php echo htmlspecialchars($row['phone'] ?? '無'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fa-solid fa-envelope"></i> 電子郵件</span>
                                <span class="info-value"><?php echo htmlspecialchars($row['email'] ?? '無'); ?></span>
                            </div>
                            <div class="info-item"> 
                                <span class="info-label"><i class="fa-solid fa-location-dot"></i> 商家地址</span>
                                <span class="info-value"><?php echo htmlspecialchars($row['address'] ?? '無'); ?></span>
                            </div>
                        </div>

                        <div class="info-item"> 
                            <span class="info-label"><i class="fa-solid fa-circle-info"></i> 商家介紹</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($row['intro'] ?? $row['description'] ?? '無介紹')); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($row['menu'])): ?>
                    <div class="store-menu-aside">
                        <span class="info-label"><i class="fa-solid fa-image"></i> 菜單</span>
                        <img src="uploads/<?php echo htmlspecialchars($row['menu']); ?>" alt="菜單預覽">
                    </div>
                    <?php endif; ?>
                    
                </div>
                
            </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<script>
    // 搜尋過濾功能
    function filterStores(keyword) {
        const cards = document.querySelectorAll('.store-card');
        const kw = keyword.toLowerCase().trim();
        cards.forEach(card => {
            const text = card.dataset.searchtext || '';
            card.style.display = (!kw || text.includes(kw)) ? '' : 'none';
        });
    }

    // 刪除確認功能
    function confirmDelete(id, name) {
        if (confirm(`確定要刪除商家「${name}」嗎？此操作無法復原。`)) {
            window.location.href = `admin_delete.php?id=${id}&type=store`;
        }
    }
</script>