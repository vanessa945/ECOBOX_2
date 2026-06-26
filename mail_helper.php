<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

// 寄給商家的通知（存進 seller_notifications + 寄信）
function add_notification($conn, $store_id, $type, $content, $to_email) {
    $stmt = $conn->prepare("INSERT INTO seller_notifications (store_id, type, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $store_id, $type, $content);
    $stmt->execute();
    
    return send_email_via_phpmailer($to_email, '【EcoBox 系統通知】您有一則新訊息', "<h3>親愛的商家您好：</h3><p>" . nl2br(htmlspecialchars($content)) . "</p>");
}

// 寄給消費者的通知（存進現有的 notifications 表 + 寄信）
function notify_consumer($conn, $user_name, $type, $title, $content, $to_email, $original_message = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_name, type, content, original_message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $user_name, $type, $content, $original_message);
    $stmt->execute();
    
    return send_email_via_phpmailer($to_email, '【EcoBox 訂單通知】' . $title, "<h3>親愛的顧客您好：</h3><p>" . nl2br(htmlspecialchars($content)) . "</p><p>感謝您使用 EcoBox 剩食平台！</p>");
}

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