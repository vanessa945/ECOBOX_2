<?php
session_start();
include("db.php");
// 引入你原本的寄信小幫手
include("notify_helper.php");

header('Content-Type: application/json');

// 接收商家點擊完成時傳來的訂單編號 (對應 orders 表的 No)
$order_no = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($order_no <= 0) {
    echo json_encode(['status' => 'error', 'message' => '無效的訂單編號']);
    exit;
}

try {
    // 1. 取得這筆訂單的資訊，並關聯 consumer 表抓取消費者的 Email
    // (假設你的訂單表叫 orders，消費者表叫 consumer)
    $sql = "SELECT o.user_name, o.store_name, c.email 
            FROM orders o 
            LEFT JOIN consumer c ON o.user_name = c.user_name 
            WHERE o.No = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => '找不到訂單或消費者資料']);
        exit;
    }
    
    $order_data = $result->fetch_assoc();
    $user_name = $order_data['user_name'];
    $store_name = $order_data['store_name'];
    $user_email = $order_data['email'];

    // 2. 更新訂單狀態為 2 (代表已準備完成)
    $update_order = "UPDATE orders SET status = 2 WHERE No = ?";
    $stmt_order = $conn->prepare($update_order);
    $stmt_order->bind_param("i", $order_no);
    $stmt_order->execute();

    // 3. 寫入通知到消費者的 notifications 資料表 (供你的消費者通知頁面讀取)
    $notify_content = "【系統通知】您在「{$store_name}」訂購的餐點已經準備好囉！請盡速前往門市取餐。";
    $insert_notify = "INSERT INTO notifications (user_name, content) VALUES (?, ?)";
    $stmt_notify = $conn->prepare($insert_notify);
    $stmt_notify->bind_param("ss", $user_name, $notify_content);
    $stmt_notify->execute();

    // 4. 更新商家通知 (seller_notifications) 為已完成 (避免商家重複點擊)
    // 假設 seller_notifications 有 order_id 跟 is_completed 欄位
    $update_seller_notif = "UPDATE seller_notifications SET is_completed = 1 WHERE order_id = ?";
    $stmt_seller = $conn->prepare($update_seller_notif);
    $stmt_seller->bind_param("i", $order_no);
    $stmt_seller->execute();

    // 5. 寄送 Email 給消費者 (直接呼叫 notify_helper 裡的 send_email_via_phpmailer)
    $mail_subject = "【EcoBox 訂單通知】您的餐點已準備完成！";
    $mail_body = "<h3>親愛的 {$user_name} 您好：</h3>
                  <p>您在「{$store_name}」訂購的餐點已經準備好囉！</p>
                  <p>請盡速前往門市取餐，感謝您為地球減少剩食！</p>";
    
    // 如果 email 存在就寄信
    if (!empty($user_email)) {
        send_email_via_phpmailer($user_email, $mail_subject, $mail_body);
    }

    // 成功回傳
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '系統發生異常：' . $e->getMessage()]);
}
?>