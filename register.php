<?php
// 如果表單被提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: 建立資料庫連線
    $link = @mysqli_connect('localhost', 'root', '', 'food_waste');
    
    if (!$link) {
        die("資料庫連線失敗: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($link, "utf8mb4");

    // 取得註冊角色類型 (user 或 store)
    $role_type = $_POST['role_type'];

    // 統一取得使用者輸入的「電子信箱」作為登入帳號 (id)
    $input_id = ($role_type === 'user') ? $_POST['uEmail'] : $_POST['sEmail'];
    $input_id = mysqli_real_escape_string($link, $input_id);

    // ================= 核心安全機制：跨三個資料表檢查帳號是否重複 =================
    // 1. admin 表使用 'id' 欄位作為帳號
    $check_admin = mysqli_query($link, "SELECT * FROM admin WHERE id='$input_id'");

// 2. 請檢查 consumer 與 store 的結構，如果它們也是用 'id' 欄位存帳號，就維持這樣：
    $check_consumer = mysqli_query($link, "SELECT * FROM consumer WHERE id='$input_id'");
    $check_store    = mysqli_query($link, "SELECT * FROM store WHERE email='$input_id'");

    if (mysqli_num_rows($check_admin) > 0 || mysqli_num_rows($check_consumer) > 0 || mysqli_num_rows($check_store) > 0) {
        echo "<script>alert('註冊失敗：此信箱已存在於系統中，請更換其他電子信箱！'); history.back();</script>";
        mysqli_close($link);
        exit();
    }
    // =========================================================================

    if ($role_type === 'user') {
        // 消費者註冊資料封裝
        $id       = $input_id; // id 就是 Email
        $pwd      = mysqli_real_escape_string($link, $_POST['uPassword']);
        $name     = mysqli_real_escape_string($link, $_POST['uName']);
        $phone    = mysqli_real_escape_string($link, $_POST['uPhone']);
        $email    = $input_id;
        $allergy  = mysqli_real_escape_string($link, $_POST['uAllergy']);
        $card     = mysqli_real_escape_string($link, $_POST['uCard']);

        // 【已修正】補上原本遺漏的 address 欄位與值
        // 將 INSERT 改為這樣 (移除 id)
        // 請將您的 INSERT 語句修改為：
        $sql = "INSERT INTO consumer (id, pwd, user_name, phone, email, allergy, card) 
                VALUES ('$id', '$pwd', '$name', '$phone', '$email', '$allergy', '$card')";

    } else if ($role_type === 'store') {
        // 商家註冊資料封裝
        $id       = $input_id; // id 就是 郵件
        $pwd      = mysqli_real_escape_string($link, $_POST['sPassword']);
        $name     = mysqli_real_escape_string($link, $_POST['sName']);
        $phone    = mysqli_real_escape_string($link, $_POST['sPhone']);
        $email    = $input_id;
        $address  = mysqli_real_escape_string($link, $_POST['sAddress']);
        $intro    = mysqli_real_escape_string($link, $_POST['sIntro']);
        
        // --- 菜單檔案上傳處理 ---
        $menuTargetName = ""; 
        if (isset($_FILES['sMenuFile']) && $_FILES['sMenuFile']['error'] === UPLOAD_ERR_OK) {
            $fileTmpName = $_FILES['sMenuFile']['tmp_name'];
            $fileName = $_FILES['sMenuFile']['name'];
            
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpName, $targetPath)) {
                $menuTargetName = mysqli_real_escape_string($link, $targetPath);
            }
        }

       $sql = "INSERT INTO store (pwd, store_name, phone, email, address, intro, menu) 
        VALUES ('$pwd', '$name', '$phone', '$email', '$address', '$intro', '$menuTargetName')";
    }

    if (mysqli_query($link, $sql)) {
        echo "<script>alert('註冊成功！將為您導向登入頁面。'); location.href='login.php';</script>";
    } else {
        echo "<script>alert('註冊失敗，寫入資料庫時發生錯誤！');</script>";
    }

    mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>建立新帳號 - EcoBox</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
        --green-deep: #132a13;
        --green-mid: #3a5a40;
        --bg-warm: #f4f3ef;
        --accent-active: #ecf39e;
        --text-dark: #2f3e46;
        --border-light: #cccccc;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: "Noto Sans TC", sans-serif;
    }

    body {
        background-color: var(--bg-warm);
        color: var(--text-dark);
        line-height: 1.6;
    }

    .header { 
        background: var(--green-deep); 
        padding: 18px 40px; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .logo {
        color: #fff;
        text-decoration: none;
        font-size: 24px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .main-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 0 20px;
    }

    .form-box {
        background: #ffffff;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border-top: 5px solid var(--green-mid);
    }

    .form-title {
        text-align: center;
        font-size: 28px;
        color: var(--green-deep);
        margin-bottom: 30px;
        font-weight: 700;
    }

    .role-selector {
        display: flex;
        gap: 15px;
        margin-bottom: 35px;
    }
    .role-btn {
        flex: 1;
        padding: 15px;
        border: 2px solid #eaeaea;
        background: #fff;
        border-radius: 12px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        transition: all 0.25s ease;
    }
    .role-btn i { font-size: 28px; color: #6b705c; }
    .role-btn:hover { border-color: var(--green-mid); }
    .role-btn.active {
        border: 2px solid #3a5a40;
        background-color: var(--accent-active);
        color: #132a13;
    }
    .role-btn.active i { color: #132a13; }

    .avatar-placeholder {
        width: 100px;
        height: 100px;
        background-color: #e9ecef;
        border-radius: 50%;
        margin: 0 auto 30px auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .avatar-placeholder i { font-size: 60px; color: #adb5bd; }

    .form-group { margin-bottom: 22px; }
    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-size: 16px;
        font-weight: 500;
        color: var(--green-deep);
    }
    .form-control {
        width: 100%;
        padding: 14px 18px;
        font-size: 15px;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        outline: none;
    }
    .form-control:focus {
        border-color: var(--green-mid);
        box-shadow: 0 0 0 3px rgba(58, 90, 64, 0.1);
    }
    
    .file-input-wrapper {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }

    .dynamic-form { display: none; }
    .dynamic-form.active { display: block; }

    .submit-btn {
        width: 100%;
        padding: 14px;
        background-color: #f3d078;
        border: none;
        border-radius: 25px;
        color: var(--green-deep);
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        margin-top: 15px;
        transition: all 0.2s ease;
    }
    .submit-btn:hover { background-color: #e9c46a; }

    .footer-link { text-align: center; margin-top: 25px; font-size: 14px; }
    .footer-link a { color: var(--green-mid); text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>

  <header class="header">
    <div class="container">
      <a href="index.php" class="logo">🌿 EcoBox</a>
    </div>
  </header>

  <main class="main-container">
    <div class="form-box">
      <h2 class="form-title">建立新帳號</h2>

      <div class="role-selector">
        <button type="button" class="role-btn active" onclick="switchRole('user', event)">
          <i class="fa-solid fa-user"></i>
          我是消費者
        </button>
        <button type="button" class="role-btn" onclick="switchRole('store', event)">
          <i class="fa-solid fa-store"></i>
          我是合作商家
        </button>
      </div>

      <div class="avatar-placeholder">
        <i class="fa-solid fa-user"></i>
      </div>

      <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="role_type" id="roleType" value="user">

        <div id="userForm" class="dynamic-form active">
            <div class="form-group">
              <label>使用者暱稱：</label>
              <input type="text" name="uName" class="form-control" placeholder="請輸入使用者暱稱">
            </div>
            <div class="form-group">
              <label>登入密碼：</label>
              <input type="password" name="uPassword" class="form-control" placeholder="請輸入密碼">
            </div>
            <div class="form-group">
              <label>電話：</label>
              <input type="tel" name="uPhone" class="form-control" placeholder="請輸入連絡電話">
            </div>
            <div class="form-group">
              <label>電子信箱 (將作為登入帳號)：</label>
              <input type="email" name="uEmail" class="form-control" placeholder="請輸入電子信箱">
            </div>
            <div class="form-group">
              <label>過敏原：</label>
              <input type="text" name="uAllergy" class="form-control" placeholder="例：海鮮、蛋奶、堅果">
            </div>
            <div class="form-group">
              <label>信用卡卡號：</label>
              <input type="text" name="uCard" class="form-control" placeholder="請輸入信用卡卡號">
            </div>
        </div>

        <div id="storeForm" class="dynamic-form">
            <div class="form-group">
              <label>店名：</label>
              <input type="text" name="sName" class="form-control" placeholder="請輸入您的餐廳或店家名稱">
            </div>
            <div class="form-group">
              <label>登入密碼：</label>
              <input type="password" name="sPassword" class="form-control" placeholder="請設定商家登入密碼">
            </div>
            <div class="form-group">
              <label>電話：</label>
              <input type="tel" name="sPhone" class="form-control" placeholder="請輸入店家聯絡電話">
            </div>
            <div class="form-group">
              <label>郵件 (將作為登入帳號)：</label>
              <input type="email" name="sEmail" class="form-control" placeholder="請輸入商家主要的電子郵件">
            </div>
            <div class="form-group">
              <label>商家位址：</label>
              <input type="text" name="sAddress" class="form-control" placeholder="請輸入店面實體地址">
            </div>
            <div class="form-group">
              <label>商家介紹：</label>
              <textarea name="sIntro" class="form-control" rows="3" placeholder="請簡單介紹您的店鋪特色"></textarea>
            </div>
            <div class="form-group">
              <label>上傳菜單品項 (支援圖片或 PDF)：</label>
              <div class="file-input-wrapper">
                <input type="file" name="sMenuFile" accept="image/*,.pdf" style="font-size: 14px;">
              </div>
            </div>
        </div>

        <button type="submit" class="submit-btn">註冊</button>
      </form>

      <div class="footer-link">
        已經有帳號了？ <a href="login.php">點此登入</a>
      </div>
    </div>
  </main>

  <script>
    function switchRole(role, event) { 
    document.getElementById('roleType').value = role;
    const buttons = document.querySelectorAll('.role-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    const userForm = document.getElementById('userForm');
    const storeForm = document.getElementById('storeForm');

    if (role === 'user') {
        event.currentTarget.classList.add('active');
        userForm.classList.add('active');
        storeForm.classList.remove('active');
    } else {
        event.currentTarget.classList.add('active');
        storeForm.classList.add('active');
        userForm.classList.remove('active');
    }
}
  </script>
</body>
</html>