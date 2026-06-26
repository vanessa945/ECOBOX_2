<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

// 原本的：寄給商家的通知
function add_notification($conn, $store_id, $type, $content, $to_email) {
    $stmt = $conn->prepare("INSERT INTO seller_notifications (store_id, type, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $store_id, $type, $content);
    $stmt->execute();
    
    return send_email_via_phpmailer($to_email, '【EcoBox 系統通知】您有一則新訊息', "<h3>親愛的商家您好：</h3><p>" . nl2br(htmlspecialchars($content)) . "</p>");
}

// 💡 新增的：寄給消費者的通知 (當餐點完成時呼叫)
function notify_consumer($conn, $user_id, $title, $content, $to_email) {
    // 1. 存入消費者的通知資料庫 (請確保你有 consumer_notifications 這個資料表)
    $stmt = $conn->prepare("INSERT INTO consumer_notifications (user_id, title, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    
    // 2. 寄發 Email 給消費者
    return send_email_via_phpmailer($to_email, '【EcoBox 訂單通知】' . $title, "<h3>親愛的顧客您好：</h3><p>" . nl2br(htmlspecialchars($content)) . "</p><p>感謝您使用 EcoBox 剩食平台！</p>");
}

// 將發信邏輯獨立出來，讓程式碼更乾淨
function send_email_via_phpmailer($to_email, $subject, $html_body) {
    $smtp_user = 'lppei03056319@gmail.com';
    $smtp_pass = 'koeu ktlt izmj keyb'; // Gmail App 密碼

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp_user, 'EcoBox 平台通知');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("郵件發送失敗: " . $mail->ErrorInfo);
        return false;
    }
}
?>