<?php
// 啟動 Session
session_start();


?>


<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoBox - 剩食平台</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght=400;500;700&family=Playfair+Display:wght=700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container">
        <a href="index.php" class="logo">EcoBox</a>
      <nav class="navbar">
        <a href="about.php" class="navbar-link">關於我們</a>
        <a href="partners.php" class="navbar-link">合作商家</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content">
          <div class="hero-tag"><span class="dot"></span>台灣首選剩食平台</div>
          <h1 class="hero-title">讓<span class="accent">剩食</span>找到<br>新的歸屬</h1>
          <p class="hero-sub">連結餐廳與消費者，用優惠價格搶救即將浪費的美食。一起守護地球，也守護你的荷包。</p>
          
          <div class="hero-cta">
              <a href="login.php" class="cta-primary">立即登入</a>
              <a href="register.php" class="cta-secondary">免費註冊</a>
          </div>
          
        </div>
      </div>
    </section>
  </main>

</body>
</html>