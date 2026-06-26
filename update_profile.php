<?php
session_start();

// 1. 安全檢查（修正：改用 uName）
if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentEmail = $_SESSION['uName'];   // ← 修正
    $newName = trim($_POST['update_name']);
    $newEmail = trim($_POST['update_email']);

    $link = @mysqli_connect('localhost', 'root', '', 'food_waste');
    if (!$link) die("資料庫連線失敗");
    mysqli_set_charset($link, "utf8mb4");

    $currentEmail_escaped = mysqli_real_escape_string($link, $currentEmail);
    $newName_escaped = mysqli_real_escape_string($link, $newName);
    $newEmail_escaped = mysqli_real_escape_string($link, $newEmail);

    $sql_update = "UPDATE consumer SET user_name = '$newName_escaped', email = '$newEmail_escaped' WHERE id = '$currentEmail_escaped'";
    
    if (mysqli_query($link, $sql_update)) {
        if (!empty($newEmail)) {
            $_SESSION['uName'] = $newEmail;   // ← 修正：同步更新的也是 uName
        }
        
        $back_url = $_SERVER['HTTP_REFERER'] ?? 'userhome.php';  // 加個防呆，避免 referer 不存在時報錯
        echo "<script>alert('個人資訊修改成功！'); window.location.href='$back_url';</script>";
    } else {
        echo "<script>alert('修改失敗，請稍後再試。'); window.location.href='userhome.php';</script>";
    }
    mysqli_close($link);
}
?>