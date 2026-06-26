<?php
session_start();
include("db.php");
require_once 'mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $msg_id = (int)$_POST['msg_id'];
    $reply  = mysqli_real_escape_string($conn, $_POST['reply']);

    // 1. 查出這筆問題的發送者與原始內容
    $res_select = mysqli_query($conn, "SELECT sender_id, sender_type, message_content FROM admin_messages WHERE message_id = $msg_id");
    $msg_row    = mysqli_fetch_assoc($res_select);

    if (!$msg_row) { die("找不到這筆問題資料。"); }

    $sender_id    = $msg_row['sender_id'];
    $sender_type  = $msg_row['sender_type'];
    $original_msg = $msg_row['message_content']; // ← 在這裡統一賦值，商家和消費者都能用

    // 2. 更新回覆內容
    $sql_update = "UPDATE admin_messages 
                   SET admin_reply = '$reply', status = 'replied' 
                   WHERE message_id = $msg_id";

    if (mysqli_query($conn, $sql_update)) {

        if ($sender_type === 'store') {
            // 3a. 商家：用 store_name 查出 No 和 email
            $sender_id_escaped = mysqli_real_escape_string($conn, $sender_id);
            $res_store = mysqli_query($conn, "SELECT No, email FROM store WHERE store_name = '$sender_id_escaped'");
            $store_row = mysqli_fetch_assoc($res_store);

            if ($store_row) {
                add_notification($conn, $store_row['No'], '客服回覆', $reply, $store_row['email']);
            }

        } else {
            // 3b. 消費者：sender_id 本身就是 email
            notify_consumer($conn, $sender_id, 'admin_reply', '客服回覆通知', $reply, $sender_id, $original_msg);
        }

        echo "<script>alert('回覆成功！'); window.location.href='admin_questions.php';</script>";
    } else {
        echo "錯誤: " . mysqli_error($conn);
    }
}
?>