<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "food_waste";

// 直接建立一個 mysqli 物件
$conn = new mysqli($host, $user, $pass, $dbname);

// 檢查連線
if ($conn->connect_error) {
    die("資料庫連線失敗: " . $conn->connect_error);
}

// 設定編碼
$conn->set_charset("utf8mb4");
?>
 