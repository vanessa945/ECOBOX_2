<?php
session_start();
include("db.php");

$redirect = "admin_index.php";

// 1. 取得類型與 ID
$type = $_GET['type'] ?? 'user'; 
$id = intval($_GET['id'] ?? 0);

if (!$id) { die("<div style='padding:20px; text-align:center;'>缺少 ID 參數！</div>"); }

// 2. 設定正確的資料表與欄位變數
if ($type === 'store') {
    $table = "store";
    $id_col = "No";             
    $name_col = "store_name";   
} else {
    $table = "consumer";
    $id_col = "No";             
    $name_col = "user_name";    
}

// 3. 更新邏輯
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    
    if ($type === 'user') {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $allergy = mysqli_real_escape_string($conn, $_POST['allergy']);
        $sql = "UPDATE consumer SET user_name='$name', email='$email', phone='$phone', allergy='$allergy' WHERE No=$id";
    } else {
        // 商家一般欄位處理
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $intro = mysqli_real_escape_string($conn, $_POST['intro']);
        
        // 💡 處理菜單圖片上傳
        $menu_update_sql = ""; 
        if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] == 0) {
            $upload_dir = 'uploads/'; // 指定上傳資料夾
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // 如果資料夾不存在就自動建立
            }

            // 取得副檔名並產生唯一檔名，避免檔名重複覆蓋
            $file_ext = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'menu_store_' . $id . '_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $new_filename;

            // 將檔案從暫存區移動到指定資料夾
            if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $target_path)) {
                // 如果上傳成功，就把更新菜單檔名的 SQL 加進去 (假設資料庫欄位名為 menu)
                $menu_update_sql = ", menu='$new_filename'"; 
            }
        }

        // 組合商家的更新 SQL 語法
        $sql = "UPDATE store SET store_name='$name', phone='$phone', email='$email', address='$address', intro='$intro' $menu_update_sql WHERE No=$id";
    }
    
    if (mysqli_query($conn, $sql)) {
        $redirect = ($type === 'store') ? 'admin_stores.php' : 'admin_index.php';
        echo "<script>alert('更新成功！'); window.location.href='./$redirect';</script>";
    } else {
        echo "<script>alert('更新失敗: " . mysqli_error($conn) . "');</script>";
    }
}

// 4. 撈取舊資料
$query = "SELECT * FROM $table WHERE $id_col = $id";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("<div style='padding:20px; text-align:center;'>找不到此資料，可能已被刪除。</div>");
}

include("admin-header.php");
?>

<style>
    :root {
        --green-deep:  #659287; 
        --green-mid:   #3a5a40;
        --bg-white:    #ffffff;
        --text-dark:   #23342b;
        --text-muted:  #5a6e63;
    }
    body { padding-top: 75px; background-color: #fdfdfc; }
    
    .edit-card {
        background: var(--bg-white);
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 16px rgba(35, 52, 43, 0.05);
        border: 1px solid rgba(101, 146, 135, 0.15);
    }
    
    .form-group { margin-bottom: 20px; }
    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    .form-group input[type="text"], 
    .form-group input[type="email"], 
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid rgba(101, 146, 135, 0.3);
        border-radius: 8px;
        font-size: 15px;
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }
    .form-group input:focus, .form-group textarea:focus {
        border-color: var(--green-deep);
        box-shadow: 0 0 0 3px rgba(101, 146, 135, 0.1);
    }

    /* 檔案上傳樣式 */
    .file-input-wrap {
        border: 1px dashed rgba(101, 146, 135, 0.5);
        padding: 15px;
        border-radius: 8px;
        background: #f9fbf9;
    }
    
    .btn-submit {
        background: var(--green-mid);
        color: white;
        border: none;
        padding: 14px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        transition: 0.2s;
    }
    .btn-submit:hover { background: var(--green-deep); }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 20px;
        color: var(--green-deep);
        text-decoration: none;
        font-weight: bold;
        font-size: 15px;
    }
    .back-link:hover { text-decoration: underline; }
</style>

<main style="max-width: 600px; margin: 40px auto; padding: 20px;">
    
    <a href="<?php echo ($type === 'store') ? 'admin_stores.php' : 'admin_index.php'; ?>" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> 返回列表
    </a>

    <div class="edit-card">
        <h2 style="margin-top: 0; margin-bottom: 25px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-pen-to-square"></i> 編輯 <?php echo ($type === 'store' ? '商家' : '使用者'); ?> 資訊
        </h2>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>名稱</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($data[$name_col] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>電子郵件</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>聯絡電話</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
            </div>
            
            <?php if ($type === 'user'): ?>
                <div class="form-group">
                    <label>過敏原</label>
                    <input type="text" name="allergy" value="<?php echo htmlspecialchars($data['allergy'] ?? ''); ?>">
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label>商家地址</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($data['address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>商家介紹</label>
                    <textarea name="intro" rows="4"><?php echo htmlspecialchars($data['intro'] ?? $data['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-image"></i> 菜單圖片</label>
                    <div class="file-input-wrap">
                        <?php if (!empty($data['menu'])): ?>
                            <div style="margin-bottom: 15px;">
                                <span style="font-size:13px; color:var(--text-muted);">目前的菜單：</span><br>
                                <img src="uploads/<?php echo htmlspecialchars($data['menu']); ?>" alt="目前菜單" style="max-width: 100%; max-height: 200px; border-radius: 6px; margin-top: 8px; border: 1px solid #ddd;">
                            </div>
                        <?php endif; ?>
                        
                        <span style="font-size:13px; color:var(--text-muted); display:block; margin-bottom: 5px;">若不更改菜單，請留空即可：</span>
                        <input type="file" name="menu_image" accept="image/*" style="font-size: 14px;">
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-submit">確認修改</button>
        </form>
    </div>
</main>