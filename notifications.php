<?php
session_start();

if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

$userEmail = $_SESSION['uName']; 

$link = mysqli_connect('localhost', 'root', '', 'food_waste');
$userEmail_escaped = mysqli_real_escape_string($link, $userEmail);

$sql_notify = "SELECT * FROM notifications WHERE user_name = '$userEmail_escaped' ORDER BY created_at DESC";
$result_notify = mysqli_query($link, $sql_notify);

$type_map = [
    'admin_reply'  => ['label' => '管理員回覆', 'icon' => 'fa-solid fa-headset',   'class' => 'badge-admin'],
    'order_ready'  => ['label' => '訂單通知',   'icon' => 'fa-solid fa-box-open',  'class' => 'badge-order'],
    'review_reply' => ['label' => '商家回覆',   'icon' => 'fa-solid fa-store',     'class' => 'badge-review'],
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>通知管理 — EcoBox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { height: 100%; font-family: "Noto Sans TC", sans-serif; background: #f7f6f2; color: #1a1a1a; overflow: hidden; }

        .main-wrapper { height: calc(100vh - 75px); width: 100%; overflow-y: auto; padding: 40px; }
        .content-container { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
        .page-block-title { font-size: 22px; font-weight: 900; color: #132a13; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

        .notify-list-wrapper { display: flex; flex-direction: column; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.04); overflow: hidden; }
        .notify-item-row { padding: 22px 32px; display: flex; align-items: flex-start; justify-content: space-between; gap: 30px; border-bottom: 1px solid #eef0f2; transition: background 0.2s; }
        .notify-item-row:last-child { border-bottom: none; }
        .notify-item-row:hover { background-color: #fafafa; }

        .notify-left-body { display: flex; align-items: flex-start; gap: 20px; flex: 1; }
        .notify-index { font-size: 18px; font-weight: 900; color: #3a5a40; min-width: 25px; padding-top: 2px; }
        .notify-text { font-size: 16px; font-weight: 500; color: #111111; }
        .notify-time { font-size: 14px; color: #888888; white-space: nowrap; font-weight: 500; padding-top: 4px; }
        .notify-main { display: flex; flex-direction: column; gap: 6px; flex: 1; }

        .notify-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 14px;
            font-size: 12px; font-weight: 700; width: fit-content;
        }
        .badge-admin  { background: #fff0e6; color: #b8975a; }
        .badge-order  { background: #e6f0ff; color: #2563eb; }
        .badge-review { background: #e6f4ea; color: #3a5a40; }

        .notify-original-box {
            background: #fffbf3;
            border-left: 4px solid #b8975a;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 4px;
        }
        .notify-original-title {
            font-size: 12px; font-weight: 700; color: #b8975a; margin-bottom: 5px;
        }
        .notify-original-text {
            font-size: 15px; color: #333;
        }
        .notify-reply-box {
            background: rgba(101, 146, 135, 0.08);
            border-left: 4px solid #659287;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 8px;
        }
        .notify-reply-title {
            font-size: 12px; font-weight: 700; color: #659287; margin-bottom: 5px;
        }

        .no-data { text-align: center; padding: 60px 20px; color: #888888; font-size: 16px; font-weight: 500; border: 2px dashed #cccccc; border-radius: 12px; background: #fff; }
        .no-data i { font-size: 44px; margin-bottom: 12px; display: block; color: #b8975a; }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <main class="main-wrapper">
        <div class="content-container">

            <div class="page-block-title">
                <i class="fa-regular fa-bell" style="color: #b8975a;"></i> 實時系統通知
            </div>

            <div class="notify-list-wrapper">
            <?php
            if ($result_notify && mysqli_num_rows($result_notify) > 0) {
                $idx = 1;
                while ($row_notify = mysqli_fetch_assoc($result_notify)) {
                    $timestamp    = strtotime($row_notify['created_at']);
                    $hour         = date('H', $timestamp);
                    $ampm         = ($hour >= 12) ? '下午' : '上午';
                    $time_str     = $ampm . date('g:i', $timestamp);
                    $type_key     = $row_notify['type'] ?? 'admin_reply';
                    $type_info    = $type_map[$type_key] ?? ['label' => '系統通知', 'icon' => 'fa-regular fa-bell', 'class' => 'badge-admin'];
                    $has_original = !empty($row_notify['original_message']);
            ?>
                <div class="notify-item-row">
                    <div class="notify-left-body">
                        <div class="notify-index"><?php echo $idx; ?>.</div>
                        <div class="notify-main">
                            <span class="notify-badge <?php echo $type_info['class']; ?>">
                                <i class="<?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['label']; ?>
                            </span>

                            <?php if ($has_original): ?>
                                <div class="notify-original-box">
                                    <div class="notify-original-title">
                                        <i class="fa-solid fa-circle-question"></i> 您的提問
                                    </div>
                                    <div class="notify-original-text">
                                        <?php echo nl2br(htmlspecialchars($row_notify['original_message'])); ?>
                                    </div>
                                </div>
                                <div class="notify-reply-box">
                                    <div class="notify-reply-title">
                                        <i class="fa-solid fa-reply-all"></i> 管理員回覆
                                    </div>
                                    <div class="notify-text">
                                        <?php echo nl2br(htmlspecialchars($row_notify['content'])); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="notify-text">
                                    <?php echo htmlspecialchars($row_notify['content']); ?>
                                </div>
                            <?php endif; ?>

                        </div><!-- /.notify-main -->
                    </div><!-- /.notify-left-body -->
                    <div class="notify-time"><?php echo $time_str; ?></div>
                </div><!-- /.notify-item-row -->
            <?php
                    $idx++;
                }
            } else {
                $samples = [
                    ['type' => 'order_ready',  'content' => '商品已備好，可取餐',         'time' => '下午2:56'],
                    ['type' => 'review_reply', 'content' => '收藏中的商家已有商品可選購', 'time' => '上午10:11'],
                ];
                foreach ($samples as $index => $sample) {
                    $type_info = $type_map[$sample['type']];
            ?>
                <div class="notify-item-row">
                    <div class="notify-left-body">
                        <div class="notify-index"><?php echo ($index + 1); ?>.</div>
                        <div class="notify-main">
                            <span class="notify-badge <?php echo $type_info['class']; ?>">
                                <i class="<?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['label']; ?>
                            </span>
                            <div class="notify-text"><?php echo $sample['content']; ?></div>
                        </div>
                    </div>
                    <div class="notify-time"><?php echo $sample['time']; ?></div>
                </div>
            <?php
                }
            }
            ?>
            </div><!-- /.notify-list-wrapper -->

        </div><!-- /.content-container -->
    </main>

</body>
</html>
<?php mysqli_close($link); ?>