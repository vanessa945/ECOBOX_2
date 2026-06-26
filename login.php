

<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>會員登入 - SaveBite</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700&family=Playfair+Display:wght=700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .header { background: var(--green-deep); position: relative; padding: 15px 0; }
    .error-alert { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
  </style>
</head>
<body>

  <header class="header">
    <div class="container">
      <a href="index.php" class="logo"><div class="logo-icon">🎃</div>EcoBox</a>    </div>
  </header>

  <main class="container">
    <div class="form-container">
      <h2 class="section-title text-center" style="margin-bottom: 24px;">會員登入</h2>
      
      <?php if(isset($error_msg)): ?>
          <div class="error-alert"><?php echo $error_msg; ?></div>
      <?php endif; ?>

      <form action="logincheck.php" method="POST">
        <div class="form-group">
          <label for="email">電子信箱 / Email</label>
          <input type="email" id="email" name="uName" class="form-control" placeholder="請輸入您的電子信箱" required>
        </div>
        <div class="form-group">
          <label for="password">密碼 / Password</label>
          <input type="password" id="password" name="uPwd" class="form-control" placeholder="請輸入密碼" required>
        </div>
        <button type="submit" class="cta-primary" style="width: 100%; margin-top: 10px;">立即登入</button>
      </form>
      
      <p style="text-align: center; margin-top: 20px; font-size: 1.4rem; color: var(--text-muted);">
        還沒有帳號嗎？ <a href="register.php" style="display:inline; color: var(--green-mid); font-weight: 700;">點此免費註冊</a>
      </p>
    </div>
  </main>

</body>
</html>