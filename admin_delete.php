<?php
include("db.php");

// 預設跳轉頁面
$redirect = "admin_index.php";

// 透過 URL 傳入 type 與 id
if (isset($_GET['id']) && isset($_GET['type'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'];

    if ($type === 'user') {
        mysqli_query($conn, "DELETE FROM consumer WHERE No = $id");
        $redirect = "admin_index.php"; // 使用者列表頁

    } elseif ($type === 'store') {
        mysqli_query($conn, "DELETE FROM store WHERE No = $id");
        $redirect = "admin_stores.php"; // 商家管理頁

    } 
    // 💡 修正 1：為了保險起見，同時支援 'store_review' 跟 'review' 兩種參數
    elseif ($type === 'store_review' || $type === 'review') {
        // 💡 修正 2：將資料表改為 store_reviews，主鍵欄位改為 No
        mysqli_query($conn, "DELETE FROM store_reviews WHERE No = $id");
        $redirect = "admin_reviews.php"; // 刪除後正確跳回評論管理頁
    }
}

// 刪除後跳轉回對應頁面
header("Location: $redirect");
exit();
?>