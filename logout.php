<?php

session_start();
session_destroy(); //銷毀伺服器 Session

//順便把你自訂的登入Cookie刪掉
if (isset($_COOKIE['uName'])) {
    setcookie('uName', '', time() - 3600, '/');
    setcookie('role', '', time() - 3600, '/');
}

//導回首頁
header("Location: index.php");
exit();

?>