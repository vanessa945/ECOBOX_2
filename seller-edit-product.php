<?php
session_start();
include("db.php");

if (!isset($_SESSION['store_name'])) {
    $_SESSION['store_name'] = 1; 
}
$current_id = $_SESSION['store_name'];

// 1. 抓取商家名稱
$sql_store = "SELECT store_name FROM store WHERE store_name = '$current_id'";
$res_store = mysqli_query($conn, $sql_store);
$data_store = mysqli_fetch_assoc($res_store);
$store_name = $data_store ? $data_store['store_name'] : "未知商家";

// 2. 檢查是否有帶入要修改的商品 ID
if (!isset($_GET['id'])) {
    echo "<script>alert('未指定商品！'); window.location.href='seller-products.php';</script>";
    exit;
}
$product_id = intval($_GET['id']);


/* ==========================================
   動作 A：撈取該商品原本的舊資料，放進表單預填
   ========================================== */
// 🟢 修正：將 WHERE product_name = '$product_id' 改成 WHERE No = $product_id
$sql_get_p = "SELECT * FROM seller_product WHERE No = $product_id AND store_name = '$current_id'";
$res_get_p = mysqli_query($conn, $sql_get_p);
$product = mysqli_fetch_assoc($res_get_p);

if (!$product) {
    echo "<script>alert('找不到該商品或您無權限修改！'); window.location.href='seller-products.php';</script>";
    exit;
}


/* ==========================================
   動作 B：處理「確認修改」表單提交 (含圖片更新)
   ========================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit'])) {
    $p_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $p_desc = mysqli_real_escape_string($conn, $_POST['product_desc']);
    $price  = intval($_POST['price']);
    $qty    = intval($_POST['quantity']);
    
    // 預設沿用舊圖片
    $img_name = $product['product_img']; 
    
    // 如果商家有上傳「新圖片」
    if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext = pathinfo($_FILES["product_img"]["name"], PATHINFO_EXTENSION);
        $img_name = time() . "_" . uniqid() . "." . $ext;
        $target_file = $target_dir . $img_name;
        
        if (move_uploaded_file($_FILES["product_img"]["tmp_name"], $target_file)) {
            // 可選：成功上傳新圖後，可以把資料庫舊的實體檔案刪除 (這裡先略過保持程式碼乾淨)
        }
    }

    // 更新資料庫
    // 🟢 修正：同樣將 WHERE product_name = '$product_id' 改成 WHERE No = $product_id
    $sql_update = "UPDATE seller_product 
                   SET product_name = '$p_name', product_desc = '$p_desc', price = $price, quantity = $qty, product_img = '$img_name' 
                   WHERE No = $product_id AND store_name = '$current_id'";
    
    if (mysqli_query($conn, $sql_update)) {
        echo "<script>alert('商品修改成功！'); window.location.href='seller-products.php';</script>";
        exit;
    } else {
        echo "<script>alert('修改失敗，請檢查資料庫設定');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>修改商品 - EcoBox 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="seller.css">
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <style>
    /* 內容排版調整，避開 Fixed 導覽列 */
    body {
        background-color: #f7f6f2;
    }
    .edit-container { 
      padding: 30px; 
      max-width: 800px; 
      margin: 20px auto 0 auto; /* 調整邊距以適應新的 Header */
      box-sizing: border-box; 
    }
    
    .edit-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      border: 1px solid #333;
    }

    .page-title { font-size: 2.2rem; font-weight: 700; margin-bottom: 10px; color: #333; }
    .section-subtitle { font-size: 1.4rem; background: #e0e0e0; display: inline-block; padding: 4px 12px; border-radius: 6px; margin-bottom: 25px; font-weight: bold;}

    /* 表單格線設計 */
    .form-group {
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .form-group label {
      font-size: 1.5rem;
      font-weight: 700;
      color: #333;
    }
    .form-input {
      width: 100%;
      padding: 12px;
      border: 1px solid #999;
      box-sizing: border-box;
      font-size: 1.5rem;
      border-radius: 4px;
    }

    /* 圖片預覽區 */
    .image-preview-section {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 20px;
      padding: 15px;
      border: 1px dashed #ccc;
      background: #fafafa;
    }
    .current-img {
      width: 120px;
      height: 90px;
      object-fit: cover;
      border: 1px solid #999;
      border-radius: 4px;
    }

    /* 按鈕區 */
    .btn-group {
      display: flex;
      justify-content: flex-end;
      gap: 15px;
      margin-top: 30px;
    }
    .btn-cancel {
      background: #fff;
      border: 1px solid #333;
      padding: 10px 24px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 1.5rem;
      text-decoration: none;
      color: #333;
      text-align: center;
    }
    .btn-cancel:hover { background: #eee; }
    
    .btn-submit {
      background: #1e4620;
      color: #fff;
      border: 1px solid #1e4620;
      padding: 10px 30px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 1.5rem;
      font-weight: bold;
    }
    .btn-submit:hover { background: #132e15; }
  </style>
</head>
<body>

  <?php 
    $link = $conn; 
    include("seller_header.php"); 
  ?>

  <main class="edit-container">
    <div class="edit-card">
      <div class="page-title">修改商品資訊</div>
      <div class="section-subtitle">商品編號：#<?php echo $product['No']; ?></div>
      
      <form action="seller-edit-product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
          <label>商品圖片</label>
          <div class="image-preview-section">
            <?php if(!empty($product['product_img'])): ?>
              <img src="uploads/<?php echo htmlspecialchars($product['product_img']); ?>" class="current-img" title="目前圖片">
            <?php else: ?>
              <div style="width:120px;height:90px;background:#eee;text-align:center;line-height:90px;color:#999;font-size:1.3rem;border:1px solid #999;">暫無圖片</div>
            <?php endif; ?>
            
            <div>
              <p style="margin:0 0 8px 0; font-size:1.3rem; color:#666;">如需更換，請選擇新檔案：</p>
              <input type="file" name="product_img" accept="image/*">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>商品名稱</label>
          <input type="text" name="product_name" class="form-input" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
        </div>

        <div class="form-group">
          <label>商品描述</label>
          <input type="text" name="product_desc" class="form-input" value="<?php echo htmlspecialchars($product['product_desc']); ?>">
        </div>

        <div class="form-group">
          <label>價錢 ($)</label>
          <input type="number" name="price" class="form-input" value="<?php echo intval($product['price']); ?>" required min="0">
        </div>

        <div class="form-group">
          <label>剩餘數量</label>
          <input type="number" name="quantity" class="form-input" value="<?php echo intval($product['quantity']); ?>" required min="0">
        </div>

        <div class="btn-group">
          <a href="seller-products.php" class="btn-cancel">取消返回</a>
          <button type="submit" name="submit_edit" class="btn-submit">儲存修改</button>
        </div>

      </form>
    </div>
  </main>

  <?php mysqli_close($conn); ?>
</body>
</html>