<?php
session_start();

// Cookie 存在但 Session 不見時，重建 Session
if (!isset($_SESSION['login']) && isset($_COOKIE['uName'])) {
    $_SESSION['uName'] = $_COOKIE['uName'];
    $_SESSION['login'] = $_COOKIE['role'] ?? 'user';
}

// 兩個都沒有才導回登入頁
if (!isset($_SESSION['login']) || !isset($_SESSION['uName'])) {
    header("Location: index.php");
    exit();
}

// 取得當前登入使用者的帳號與角色資訊
$uName = $_SESSION['uName'];
$role = $_SESSION['login'];

$link = @mysqli_connect('localhost', 'root', '', 'food_waste');
if (!$link) die("資料庫連線失敗: " . mysqli_connect_error());
mysqli_set_charset($link, "utf8mb4");

$uName_escaped = mysqli_real_escape_string($link, $uName);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoBox 剩食平台 - 首頁</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
    
    <style>
        /* 這裡只保留 userhome.php (首頁) 專屬的 CSS，如輪播圖與卡片樣式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Arial, "Noto Sans TC", "Microsoft JhengHei", sans-serif;
        }

        body {
            background-color: #fbfbfa; /* 配合你首頁的設定 */
            color: #2f3e46;
            overflow-x: hidden; 
            line-height: 1.6;
        }

        /* --- 滿版高級輪播區 (與頂部無縫銜接) --- */
        .carousel-container {
            width: 100%;
            margin: 0 0 60px 0;
            overflow: hidden;
            position: relative;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .carousel-track-wrapper {
            width: 100%;
            height: 520px; 
            position: relative;
        }

        .carousel-track {
            display: flex;
            width: 300%;
            height: 100%;
            transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .carousel-slide {
            width: 33.333%;
            height: 100%;
            position: relative;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(65%);
        }

        .slide-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(to top, rgba(19, 42, 19, 0.85) 0%, rgba(19, 42, 19, 0.3) 60%, transparent 100%);
            color: #fff;
            padding: 80px 60px 50px 60px;
            text-align: left;
        }

        .slide-caption h3 {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: 1.5px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .slide-caption p {
            font-size: 16px;
            color: #f4f3ef;
            max-width: 600px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: #fff;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
        }
        .arrow:hover { 
            background: var(--green-mid); 
            border-color: var(--green-mid);
            transform: translateY(-50%) scale(1.1);
        }
        .arrow-left { left: 40px; }
        .arrow-right { right: 40px; }

        .dots {
            position: absolute;
            bottom: 50px; 
            right: 60px; 
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .dot {
            width: 12px;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .dot.active { 
            background-color: var(--accent-gold); 
            transform: scale(1.2);
        }

        /* --- 最新消息 --- */
        main { max-width: 1300px; margin: 0 auto 80px auto; padding: 0 40px; position: relative; }
        .news-header { text-align: center; margin-bottom: 40px; }
        .news-header h2 { font-size: 28px; font-weight: 600; letter-spacing: 3px; color: #222222; text-transform: uppercase; }
        .news-header .news-sub { font-size: 16px; color: #b8975a; margin-top: 5px; letter-spacing: 1px; }
        .news-slider-wrapper { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; }
        .news-ctrl { background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #b8975a; font-weight: bold; font-size: 12px; letter-spacing: 1px; transition: opacity 0.2s; position: absolute; top: 50%; transform: translateY(-50%); z-index: 5; }
        .news-ctrl:hover { opacity: 0.7; }
        .news-ctrl-prev { left: -40px; }
        .news-ctrl-next { right: -40px; }
        .news-ctrl i { font-size: 14px; margin-top: 2px; }
        .news-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 35px; width: 100%; }
        .news-card-premium { background-color: #ffffff; border: 1px solid #eaeaea; display: flex; position: relative; padding: 25px; min-height: 260px; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .news-card-premium:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .card-date-box { position: absolute; top: -15px; left: 20px; background-color: #ffffff; border: 1px solid #eaeaea; width: 50px; height: 55px; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 2; }
        .card-date-box .day { font-size: 20px; font-weight: 700; color: #b8975a; line-height: 1; }
        .card-date-box .month { font-size: 10px; color: #8e9aaf; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-img-area { width: 45%; overflow: hidden; background-color: #f5f5f5; border: 1px solid #eeeeee; }
        .card-img-area img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .news-card-premium:hover .card-img-area img { transform: scale(1.05); }
        .card-info-area { width: 55%; padding: 10px 0 10px 25px; display: flex; flex-direction: column; justify-content: space-between; }
        .card-tag-pills { font-size: 13px; color: #b8975a; letter-spacing: 1px; margin-bottom: 8px; }
        .card-main-title { font-size: 18px; font-weight: 700; color: #111111; line-height: 1.4; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-desc { font-size: 14px; color: #666666; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 15px; }
        .card-more-btn { display: inline-flex; align-items: center; gap: 15px; text-decoration: none; font-size: 13px; color: #222222; font-weight: 500; transition: color 0.2s; margin-top: auto; }
        .card-more-btn::after { content: ''; width: 30px; height: 1px; background-color: #cccccc; transition: width 0.3s, background-color 0.3s; }
        .card-more-btn:hover { color: #b8975a; }
        .card-more-btn:hover::after { width: 45px; background-color: #b8975a; }
        .news-bottom-dots { display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 40px; }
        .news-b-dot { width: 7px; height: 7px; border: 1px solid #b8975a; transform: rotate(45deg); cursor: pointer; transition: all 0.3s ease; }
        .news-b-dot.active { background-color: #b8975a; transform: rotate(45deg) scale(1.3); }
    </style>
</head>
<body>

    <?php include 'shared_header.php'; ?>

    <div class="carousel-container">
        <button class="arrow arrow-left" id="prevBtn">&lt;</button>
        <button class="arrow arrow-right" id="nextBtn">&gt;</button>
        
        <div class="dots">
            <span class="dot active" data-index="0"></span>
            <span class="dot" data-index="1"></span>
            <span class="dot" data-index="2"></span>
        </div>

        <div class="carousel-track-wrapper">
            <div class="carousel-track" id="carouselTrack">
                <div class="carousel-slide">
                    <img src="image/廣告1.png" alt="珍惜美味">
                    <div class="slide-caption">
                        <h3>珍惜美味，不留遺憾</h3>
                        <p>今日限時救援餐點上架，晚了就沒囉！立即預訂。</p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="image/廣告2.png" alt="剩食救援計畫">
                    <div class="slide-caption">
                        <h3>EcoBox 剩食救援計畫</h3>
                        <p>串聯在地優質店家與您的橋樑，減少不必要的食材浪費。</p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="image/廣告3.png" alt="用行動挺環保">
                    <div class="slide-caption">
                        <h3>用行動環保，用美味永續</h3>
                        <p>全平台本月已累計成功救援 1,200 份餐點，減碳成果亮眼！</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main>
        <div class="news-header">
            <h2>Latest News</h2>
            <div class="news-sub">[ 最新優惠活動 ]</div>
        </div>

        <div class="news-slider-wrapper">
            <button class="news-ctrl news-ctrl-prev">PREV <i class="fa-solid fa-arrow-left-long"></i></button>
            <button class="news-ctrl news-ctrl-next">NEXT <i class="fa-solid fa-arrow-right-long"></i></button>

            <div class="news-container">
                <div class="news-card-premium">
                    <div class="card-date-box">
                        <span class="day">27</span>
                        <span class="month">May</span>
                    </div>
                    <div class="card-img-area">
                        <img src="image/廣告1.png" alt="新門市開幕">
                    </div>
                    <div class="card-info-area">
                        <div class="card-tag-pills">[ 門市開幕 ]</div>
                        <div class="card-main-title">EcoBox 實體概念合作站點即將進駐楠梓區！</div>
                        <div class="card-desc">全新線下環保示範基地即將揭幕，現場將提供剩食環保餐盒租借、惜食講座與專屬首發大禮包...</div>
                        <a href="news_detail.php?id=1" class="card-more-btn">查看更多</a>
                    </div>
                </div>

                <div class="news-card-premium">
                    <div class="card-date-box">
                        <span class="day">05</span>
                        <span class="month">May</span>
                    </div>
                    <div class="card-img-area">
                        <img src="image/廣告2.png" alt="新品上市或新活動">
                    </div>
                    <div class="card-info-area">
                        <div class="card-tag-pills">[ 好康優惠 ]</div>
                        <div class="card-main-title">歡慶 EcoBox 上線！新註冊會員首單現折 20%</div>
                        <div class="card-desc">手手控準備暴動！首度攜手在地指標性永續餐飲店家推出惜食企劃，將經典美味以不可思議的驚喜超值價留給最懂得珍惜的你...</div>
                        <a href="news_detail.php?id=2" class="card-more-btn">查看更多</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="news-bottom-dots">
            <span class="news-b-dot active"></span>
            <span class="news-b-dot"></span>
            <span class="news-b-dot"></span>
            <span class="news-b-dot"></span>
            <span class="news-b-dot"></span>
        </div>
    </main>

    <script>
        // --- 這裡只保留 userhome.php 的輪播圖 JS，側邊欄與彈窗控制已在 shared_header.php 處理 ---
        // --- 圖片輪播控制 ---
        const track = document.getElementById('carouselTrack');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const carouselDots = document.querySelectorAll('.dot');
        
        let currentIndex = 0;
        const totalSlides = 3;

        function updateCarousel(index) {
            currentIndex = index;
            track.style.transform = `translateX(-${currentIndex * 33.333}%)`;
            
            carouselDots.forEach(d => d.classList.remove('active'));
            carouselDots[currentIndex].classList.add('active');
        }

        nextBtn.addEventListener('click', () => {
            let nextIndex = currentIndex + 1;
            if (nextIndex >= totalSlides) nextIndex = 0;
            updateCarousel(nextIndex);
        });

        prevBtn.addEventListener('click', () => {
            let prevIndex = currentIndex - 1;
            if (prevIndex < 0) prevIndex = totalSlides - 1;
            updateCarousel(prevIndex);
        });

        carouselDots.forEach(dot => {
            dot.addEventListener('click', (e) => {
                const targetIndex = parseInt(e.target.getAttribute('data-index'));
                updateCarousel(targetIndex);
            });
        });

        // 自動輪播 (4秒)
        setInterval(() => {
            let nextIndex = currentIndex + 1;
            if (nextIndex >= totalSlides) nextIndex = 0;
            updateCarousel(nextIndex);
        }, 4000);
    </script>

</body>
</html>
<?php mysqli_close($link); ?>