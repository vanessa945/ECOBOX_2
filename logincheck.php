<?php
session_start();

//Step1:連線資料庫
$link = @mysqli_connect( 
            'localhost',  // MySQL主機名稱 
            'root',       // 使用者名稱 
            '',  // 密碼
            'food_waste');  // 預設使用的資料庫名稱

$dbname='food_waste';

if (!$link) {
    die("資料庫連線失敗: " . mysqli_connect_error());
}

if (!mysqli_select_db($link, $dbname)) {
    die("無法開啟 $dbname 資料庫!<br/>");
}

// 接收前端表單資料
$uID = $_POST['uName'];
$uPwd = $_POST['uPwd'];

// 為了避免 SQL 注入攻擊，進行基本字串過濾
$uID = mysqli_real_escape_string($link, $uID);
$uPwd = mysqli_real_escape_string($link, $uPwd);

// 初始化身份標籤與轉導路徑
$role = null;
$redirect_url = '';

// Step 2 & 3: 開始分層查詢（管理員 -> 商家 -> 消費者）

// 1. 先查管理員資料表 (假設表名叫 admin)
$sql_admin = "SELECT * FROM admin WHERE id='$uID' AND pwd='$uPwd'";
$res_admin = mysqli_query($link, $sql_admin);

if ($res_admin && mysqli_num_rows($res_admin) > 0) {
    $role = 'admin';
    $redirect_url = 'admin_index.php';
} else {
    // 2. 若不是管理員，查商家資料表 (假設表名叫 store)
    $sql_store = "SELECT * FROM store WHERE email='$uID' AND pwd='$uPwd'";
    $res_store = mysqli_query($link, $sql_store);
    
    if ($res_store && mysqli_num_rows($res_store) > 0) {
        $role = 'store';
        $redirect_url = 'seller_index.php';
        $row_store = mysqli_fetch_assoc($res_store);
        $_SESSION['store_name'] = $row_store['store_name'];
    } else {
        // 3. 若不是商家，查消費者資料表 (假設表名叫 consumer)
        $sql_consumer = "SELECT * FROM consumer WHERE id='$uID' AND pwd='$uPwd'";
        $res_consumer = mysqli_query($link, $sql_consumer);
        
        if ($res_consumer && mysqli_num_rows($res_consumer) > 0) {
            $role = 'user'; // 消費者
            $redirect_url = 'userhome.php';
        }
    }
}

// Step 4: 根據查詢結果進行處理
if ($role !== null) {
    // 登入成功，設定 Cookie & Session
    $date = strtotime("+1 days", time()); // 設定 Cookie 有效期限為 1 天
    
    $_SESSION['login'] = $role;      // 存入角色權限 ('admin', 'store', 'user')
    $_SESSION['uName'] = $uID;        // 存入帳號
    setcookie("uName", $uID, $date);  // 寫入瀏覽器 Cookie
    
    // 提示訊息並導向對應頁面
    switch ($role) {
        case 'admin':
            echo "管理者登入成功！正導向管理介面...";
            break;
        case 'store':
            echo "商家登入成功！正導向商家後台...";
            break;
        case 'user':
            //echo "登入成功！正導向申請頁面...";
            break;
    }
    header("Refresh:1; url=$redirect_url");

} else {
    // 登入失敗
    echo "<h2>登入失敗！帳號或密碼錯誤。系統將在 2 秒後返回首頁</h2>";
    header("Refresh:2; url=index.php");
}

// Step 5: 關閉資料庫連線
mysqli_close($link);
?>