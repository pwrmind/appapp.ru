<?php
// index.php
require_once 'config.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_category = isset($_GET['cat']) ? trim($_GET['cat']) : '';

// Определяем, включён ли режим фильтрации
$is_filtered = !empty($search) || !empty($current_category);

$apps = [];
$carousel_main = $carousel_featured = $carousel_tools = [];

try {
    $db = new PDO(DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS developers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        website TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS apps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        url TEXT UNIQUE,
        icon TEXT,
        description TEXT,
        category TEXT DEFAULT 'Инструменты',
        screenshots TEXT,
        rating REAL DEFAULT 4.5,
        downloads INTEGER DEFAULT 0,
        is_verified INTEGER DEFAULT 0,
        is_whitelisted INTEGER DEFAULT 0,
        developer_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (developer_id) REFERENCES developers(id)
    )");

    if ($is_filtered) {
        // Режим поиска/категорий – старый добрый запрос с фильтрацией
        $sql = "SELECT * FROM apps WHERE 1=1";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (title LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if (!empty($current_category)) {
            $sql .= " AND category = :category";
            $params[':category'] = $current_category;
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Главная страница: подборки для каруселей
        $stmt_main = $db->query("SELECT * FROM apps ORDER BY downloads DESC LIMIT 18");
        $carousel_main = $stmt_main->fetchAll(PDO::FETCH_ASSOC);

        $stmt_featured = $db->query("SELECT * FROM apps WHERE is_verified = 1 ORDER BY id DESC LIMIT 18");
        $carousel_featured = $stmt_featured->fetchAll(PDO::FETCH_ASSOC);
        if (count($carousel_featured) < 3) {
            $stmt_fallback = $db->query("SELECT * FROM apps ORDER BY id DESC LIMIT 18");
            $carousel_featured = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt_tools = $db->query("SELECT * FROM apps WHERE category = 'Инструменты' ORDER BY downloads DESC LIMIT 18");
        $carousel_tools = $stmt_tools->fetchAll(PDO::FETCH_ASSOC);
        if (empty($carousel_tools)) {
            $stmt_tools = $db->query("SELECT * FROM apps ORDER BY RANDOM() LIMIT 18");
            $carousel_tools = $stmt_tools->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $apps = [];
    $carousel_main = $carousel_featured = $carousel_tools = [];
}

$categories = ['Игры', 'Инструменты', 'Соцсети', 'Новости', 'Покупки'];
$store_domain = "https://" . $_SERVER['HTTP_HOST'];

// Функция рендеринга трёхрядной карусели (только на главной)
function render_app_carousel_3row($title, $apps_list) {
    if (empty($apps_list)) return;
    $chunks = array_chunk($apps_list, 3);
?>
    <div class="carousel-section-3row">
        <div class="carousel-header">
            <h2><?= htmlspecialchars($title) ?></h2>
            <span class="carousel-arrow">›</span>
        </div>
        <div class="horizontal-carousel-wrapper-3row">
            <?php foreach ($chunks as $column_apps): ?>
                <div class="carousel-column">
                    <?php foreach ($column_apps as $app): ?>
                        <a href="app.php?id=<?= $app['id'] ?>" class="carousel-item-link-3row"
                           onclick="saveToRecent(<?= $app['id'] ?>, '<?= htmlspecialchars($app['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['icon'], ENT_QUOTES) ?>')">
                            <div class="carousel-app-card-3row">
                                <img src="<?= !empty($app['icon']) ? htmlspecialchars($app['icon']) : 'https://placeholder.com' ?>" alt="Icon" loading="lazy">
                                <div class="carousel-app-info">
                                    <h4>
                                        <?= htmlspecialchars($app['title']) ?>
                                        <?php if (!empty($app['is_verified']) && $app['is_verified'] == 1): ?>
                                            <span class="verified-badge">✓</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="carousel-app-desc"><?= htmlspecialchars($app['description']) ?></span>
                                </div>
                                <span class="btn-watch">Смотреть</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>АпАп.рф – каталог российских приложений</title>
    <meta name="description" content="АпАп.рф — независимый каталог российских прогрессивных веб-приложений (PWA). Устанавливайте на главный экран без магазинов. Поиск, категории, подборки.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $store_domain ?>/index.php">
    <meta property="og:title" content="АпАп.рф – каталог российских приложений">
    <meta property="og:description" content="Независимый каталог российских прогрессивных веб-приложений (PWA). Устанавливайте на главный экран без магазинов.">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="App Store">
    <link rel="apple-touch-icon" href="https://flaticon.com">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f4f4f9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#121214" media="(prefers-color-scheme: dark)">
    <meta name="google-site-verification" content="DfAhLnYCVoIBx-Bwjgar4TEySCWPUG6cO6X2ks2uETM" />
    <style>
        :root {
            --bg-main: #f4f4f9;
            --bg-card: #ffffff;
            --text-primary: #111111;
            --text-secondary: #555555;
            --text-muted: #888888;
            --border-color: #e0e0e0;
            --btn-install-bg: #007ff6;
            --btn-install-text: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-main: #121214;
                --bg-card: #1e1e22;
                --text-primary: #f5f5f7;
                --text-secondary: #a1a1aa;
                --text-muted: #71717a;
                --border-color: #2e2e33;
            }
        }
        html, body {
            margin: 0; padding: 0;
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            touch-action: manipulation;
            -webkit-user-select: none; user-select: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-overflow-scrolling: touch;
        }
        .container {
            max-width: 800px; margin: 0 auto;
            padding-top: calc(20px + env(safe-area-inset-top));
            padding-bottom: calc(40px + env(safe-area-inset-bottom));
            padding-left: calc(16px + env(safe-area-inset-left));
            padding-right: calc(16px + env(safe-area-inset-right));
        }
        h1 { font-size: 1.8rem; font-weight: 700; margin: 10px 0 20px 0; }
        h2 { font-size: 1.3rem; font-weight: 600; margin: 0 0 15px 0; }
        .search-container { margin-bottom: 20px; position: relative; }
        .search-container input[type="text"] {
            width: 100%; box-sizing: border-box; padding: 14px 45px 14px 16px;
            border: 1px solid var(--border-color); border-radius: 25px;
            font-size: 1rem; background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: border-color 0.2s;
        }
        .search-container input[type="text"]:focus { border-color: var(--btn-install-bg); }
        .clear-search {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            text-decoration: none; color: var(--text-muted); font-size: 1.1rem;
        }
        .categories-scroll {
            display: flex; gap: 8px; overflow-x: auto; padding-bottom: 5px; margin-bottom: 25px;
            scroll-snap-type: x mandatory;
        }
        .categories-scroll::-webkit-scrollbar { display: none; }
        .cat-chip { scroll-snap-align: start; flex-shrink: 0; }
        .cat-chip {
            padding: 8px 18px; background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 20px; color: var(--text-secondary); text-decoration: none;
            font-size: 0.9rem; font-weight: 500;
        }
        .cat-chip.active {
            background: var(--btn-install-bg); color: var(--btn-install-text);
            border-color: var(--btn-install-bg);
        }

        /* --- СТИЛИ ДЛЯ РЕЗУЛЬТАТОВ ПОИСКА/КАТЕГОРИЙ (сетка) --- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }
        .app-card-link { text-decoration: none; color: inherit; }
        .app-card {
            background: var(--bg-card); border-radius: 16px; padding: 14px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid var(--border-color);
        }
        .app-card img { width: 60px; height: 60px; border-radius: 14px; object-fit: cover; background: #eee; flex-shrink: 0; }
        .app-info { display: flex; flex-direction: column; min-width: 0; }
        .app-info h3 {
            margin: 0 0 4px 0; font-size: 1rem; font-weight: 600; color: var(--text-primary);
            display: flex; align-items: center; gap: 5px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;
        }
        .category { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }
        .meta { font-size: 0.8rem; color: var(--text-secondary); display: flex; gap: 6px; align-items: center; }

        /* --- ГОРИЗОНТАЛЬНАЯ КАРУСЕЛЬ "НЕДАВНИЕ" --- */
        .horizontal-scroll {
            display: flex !important;
            overflow-x: auto;
            gap: 20px;
            padding-bottom: 10px;
            scroll-snap-type: x mandatory;
        }
        .horizontal-scroll::-webkit-scrollbar { display: none; }

        .launcher-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: inherit;
            min-width: 76px;
            scroll-snap-align: start;
            flex-shrink: 0;
        }
        .launcher-card img {
            width: 66px;
            height: 66px;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 8px;
            object-fit: cover;
        }
        .launcher-card span {
            font-size: 0.8rem;
            font-weight: 500;
            max-width: 76px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- ТРЁХРЯДНЫЕ КАРУСЕЛИ --- */
        .carousel-section-3row {
            margin-bottom: 30px;
        }
        .carousel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-right: 4px;
        }
        .carousel-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }
        .carousel-arrow {
            font-size: 1.5rem;
            color: var(--text-muted);
            font-weight: 300;
        }
        .horizontal-carousel-wrapper-3row {
            display: flex;
            flex-flow: row nowrap;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }
        .horizontal-carousel-wrapper-3row::-webkit-scrollbar {
            display: none;
        }
        .carousel-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex-shrink: 0;
            width: 88%;
            max-width: 340px;
            scroll-snap-align: start;
        }
        .carousel-item-link-3row {
            text-decoration: none;
            color: inherit;
            width: 100%;
        }
        .carousel-app-card-3row {
            background: var(--bg-card);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 8px 4px 2px;
            border-radius: 8px;
        }
        .carousel-app-card-3row img {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            object-fit: cover;
            background: #eee;
            flex-shrink: 0;
        }
        .carousel-app-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex-grow: 1;
        }
        .carousel-app-info h4 {
            margin: 0 0 2px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .carousel-app-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
        }
        .btn-watch {
            font-size: 0.8rem;
            font-weight: 700;
            color: #007ff6;
            background: rgba(0, 127, 246, 0.07);
            padding: 6px 14px;
            border-radius: 20px;
            flex-shrink: 0;
            margin-left: 8px;
        }
        @media (prefers-color-scheme: dark) {
            .btn-watch {
                background: rgba(0, 127, 246, 0.15);
                color: #38a1ff;
            }
        }

        /* Бейджи проверки */
        .verified-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #28a745;
            color: #fff;
            font-size: 0.6rem;
            font-weight: bold;
            width: 13px; height: 13px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .whitelist-badge {
            font-size: 0.75rem; font-weight: 600; color: #007ff6;
            background: rgba(0,127,246,0.08); padding: 1px 6px; border-radius: 4px; white-space: nowrap;
        }
        @media (prefers-color-scheme: dark) {
            .whitelist-badge { background: rgba(0,127,246,0.2); color: #38a1ff; }
        }
        .carousel-item-link-3row:active .carousel-app-card-3row {
            opacity: 0.7;
            transform: scale(0.99);
            transition: all 0.05s ease;
        }

        /* Установочный баннер */
        #own-pwa-banner {
            background: var(--btn-install-bg); color: var(--btn-install-text);
            padding: 14px; border-radius: 14px; margin-bottom: 20px;
            align-items: center; justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,127,246,0.2);
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📱 АпАп</h1>

    <!-- Баннер установки самого магазина -->
    <div id="own-pwa-banner" style="display: none;">
        <span style="font-size:0.9rem; font-weight:500; padding-right:10px;">Добавь маркет на экран «Домой» для быстрого запуска!</span>
        <button id="btn-install-store" style="background:#fff; color:var(--btn-install-bg); border:none; padding:8px 14px; border-radius:20px; font-weight:bold; font-size:0.85rem; cursor:pointer; flex-shrink:0;">Установить</button>
    </div>

    <!-- Поиск -->
    <div class="search-container">
        <form method="GET" action="index.php">
            <?php if(!empty($current_category)): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($current_category) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Поиск приложений..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            <?php if(!empty($search) || !empty($current_category)): ?>
                <a href="index.php" class="clear-search">✖</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Категории -->
    <div class="categories-scroll">
        <a href="index.php<?= !empty($search) ? '?search='.urlencode($search) : '' ?>" class="cat-chip <?= empty($current_category) ? 'active' : '' ?>">Все</a>
        <?php foreach ($categories as $cat): ?>
            <?php 
                $url = "?cat=" . urlencode($cat);
                if (!empty($search)) $url .= "&search=" . urlencode($search);
            ?>
            <a href="<?= $url ?>" class="cat-chip <?= $current_category === $cat ? 'active' : '' ?>"><?= $cat ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Недавние приложения (localStorage) - показываются всегда, если есть -->
    <div id="recent-apps-section" class="store-layout" style="display: none; margin-bottom: 40px;">
        <h2>🚀 Недавние</h2>
        <div id="recent-apps-grid" class="horizontal-scroll"></div>
        <hr style="border:0; border-top:1px solid var(--border-color); margin-top:30px;">
    </div>

    <?php if ($is_filtered): ?>
        <!-- РЕЗУЛЬТАТЫ ПОИСКА / КАТЕГОРИИ (сетка) -->
        <div class="store-layout">
            <h2>Результаты</h2>
            <?php if (empty($apps)): ?>
                <p>Ничего не найдено.</p>
            <?php else: ?>
            <div class="grid">
                <?php foreach ($apps as $app): ?>
                    <a href="app.php?id=<?= $app['id'] ?>" class="app-card-link"
                       onclick="saveToRecent(<?= $app['id'] ?>, '<?= htmlspecialchars($app['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['icon'], ENT_QUOTES) ?>')">
                        <div class="app-card">
                            <img src="<?= !empty($app['icon']) ? htmlspecialchars($app['icon']) : 'https://placeholder.com' ?>" alt="Icon">
                            <div class="app-info">
                                <h3>
                                    <?= htmlspecialchars($app['title']) ?>
                                    <?php if (!empty($app['is_verified']) && $app['is_verified'] == 1): ?>
                                        <span class="verified-badge">✓</span>
                                    <?php endif; ?>
                                </h3>
                                <span class="category"><?= htmlspecialchars($app['category'] ?? 'Приложение') ?></span>
                                <div class="meta">
                                    <span style="color:#ffb400;">★</span>
                                    <span><?= number_format($app['rating'] ?? 4.5, 1) ?></span>
                                    <span style="color: var(--border-color);">|</span>
                                    <span>📥 <?= number_format($app['downloads']) ?>+</span>
                                    <?php if (!empty($app['is_whitelisted']) && $app['is_whitelisted'] == 1): ?>
                                        <span style="color: var(--border-color);">|</span>
                                        <span class="whitelist-badge">РФ 🛡️</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Главная страница: карусели с подборками -->
        <div class="store-content-blocks">
            <?php render_app_carousel_3row("Самое основное", $carousel_main); ?>
            <?php render_app_carousel_3row("Избранное за неделю", $carousel_featured); ?>
            <?php render_app_carousel_3row("Топ бесплатных приложений", $carousel_tools); ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== ФУТЕР ===== -->
<footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center; font-size: 0.8rem; color: var(--text-muted);">
    <div style="margin-bottom: 10px;">
        <a href="about.php" style="color: var(--btn-install-bg); text-decoration: none; margin: 0 10px;">О проекте</a>
        <a href="privacy.php" style="color: var(--btn-install-bg); text-decoration: none; margin: 0 10px;">Политика конфиденциальности</a>
    </div>
    <p style="margin: 0; line-height: 1.4;">
        Все товарные знаки, логотипы и графические материалы принадлежат их законным владельцам.<br>
        АпАп является независимым каталогом общедоступных веб-ссылок.
    </p>
</footer>
<!-- ===== /ФУТЕР ===== -->

<script>
function saveToRecent(id, title, icon) {
    let recentApps = JSON.parse(localStorage.getItem('recent_pwa_apps')) || [];
    recentApps = recentApps.filter(app => app.id !== id);
    recentApps.unshift({ id: id, title: title, icon: icon });
    if (recentApps.length > 8) recentApps.pop();
    localStorage.setItem('recent_pwa_apps', JSON.stringify(recentApps));
}

document.addEventListener("DOMContentLoaded", function() {
    const recentApps = JSON.parse(localStorage.getItem('recent_pwa_apps')) || [];
    const section = document.getElementById('recent-apps-section');
    const grid = document.getElementById('recent-apps-grid');
    if (recentApps.length > 0) {
        section.style.display = 'block';
        recentApps.forEach(app => {
            const cardHtml = `
                <a href="app.php?id=${app.id}" class="launcher-card">
                    <img src="${app.icon ? app.icon : 'https://placeholder.com'}" alt="${app.title}">
                    <span>${app.title}</span>
                </a>`;
            grid.insertAdjacentHTML('beforeend', cardHtml);
        });
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Error', err));
    }

    let deferredPrompt;
    const pwaBanner = document.getElementById('own-pwa-banner');
    const installBtnStore = document.getElementById('btn-install-store');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!window.matchMedia('(display-mode: standalone)').matches) {
            pwaBanner.style.display = 'flex';
        }
    });

    installBtnStore.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            pwaBanner.style.display = 'none';
        }
    });

    if (window.matchMedia('(display-mode: standalone)').matches) {
        pwaBanner.style.display = 'none';
    }
});
</script>

<!-- Schema.org для поисковой строки -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "url": "<?= $store_domain ?>/index.php",
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "<?= $store_domain ?>/index.php?search={search_term_string}"
    },
    "query-input": "required name=search_term_string"
  }
}
</script>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=110482686', 'ym');

    ym(110482686, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/110482686" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</body>
</html>