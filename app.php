<?php
// app.php
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.1 404 Not Found");
    die("Приложение не найдено");
}

$app_id = (int)$_GET['id'];
$app = null;
$related_apps = [];

try {
    $db = new PDO(DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем приложение вместе с именем разработчика
    $stmt = $db->prepare("SELECT apps.*, developers.name AS developer_name 
                          FROM apps 
                          LEFT JOIN developers ON apps.developer_id = developers.id 
                          WHERE apps.id = :id");
    $stmt->execute([':id' => $app_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        header("HTTP/1.1 404 Not Found");
        die("Приложение не найдено");
    }

    $db->prepare("UPDATE apps SET downloads = downloads + 1 WHERE id = :id")->execute([':id' => $app_id]);

    // Похожие приложения
    $related_stmt = $db->prepare("SELECT id, title, icon, category, rating FROM apps WHERE category = :category AND id != :current_id ORDER BY RANDOM() LIMIT 4");
    $related_stmt->execute([':category' => $app['category'], ':current_id' => $app_id]);
    $related_apps = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($related_apps) < 4) {
        $needed = 4 - count($related_apps);
        $exclude_ids = array_merge([$app_id], array_column($related_apps, 'id'));
        $in_clause = implode(',', array_fill(0, count($exclude_ids), '?'));
        $fallback_stmt = $db->prepare("SELECT id, title, icon, category, rating FROM apps WHERE id NOT IN ($in_clause) ORDER BY RANDOM() LIMIT $needed");
        $fallback_stmt->execute($exclude_ids);
        $related_apps = array_merge($related_apps, $fallback_stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    die("Ошибка базы данных");
}

$screenshots = !empty($app['screenshots']) ? explode(',', $app['screenshots']) : [];
$app_domain = parse_url($app['url'], PHP_URL_HOST);
$store_domain = "https://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Скачать <?= htmlspecialchars($app['title']) ?> на телефон бесплатно — АпАп.рф</title>
    <meta name="description" content="Установите прогрессивное веб-приложение <?= htmlspecialchars($app['title']) ?> (категория: <?= htmlspecialchars($app['category']) ?>) на экран мобильного в один клик. Проверено: <?= ($app['is_verified'] == 1) ? 'Да' : 'В очереди' ?>.">
    <link rel="canonical" href="<?= $store_domain ?>/app.php?id=<?= $app['id'] ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $store_domain ?>/app.php?id=<?= $app['id'] ?>">
    <meta property="og:title" content="Скачать PWA <?= htmlspecialchars($app['title']) ?> — WebApp Store">
    <meta property="og:description" content="Полноэкранная мобильная версия сайта <?= $app_domain ?>. Категория: <?= htmlspecialchars($app['category']) ?>.">
    <meta property="og:image" content="<?= $store_domain ?>/<?= htmlspecialchars($app['icon']) ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="App Store">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($app['icon']) ?>">
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --bg-main: #f4f4f9; --bg-card: #ffffff; --bg-related: #f8f9fa;
            --text-primary: #111111; --text-secondary: #555555; --text-muted: #888888;
            --border-color: #e0e0e0; --btn-install-bg: #007ff6; --btn-install-text: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-main: #121214; --bg-card: #1e1e22; --bg-related: #26262b;
                --text-primary: #f5f5f7; --text-secondary: #a1a1aa; --text-muted: #71717a; --border-color: #2e2e33;
            }
        }
        html, body {
            margin: 0; padding: 0; background-color: var(--bg-main); color: var(--text-primary);
            font-family: system-ui, -apple-system, sans-serif; touch-action: manipulation;
            -webkit-user-select: none; user-select: none; -webkit-tap-highlight-color: transparent;
        }
        .app-container {
            max-width: 600px; margin: 0 auto;
            padding-top: calc(20px + env(safe-area-inset-top));
            padding-bottom: calc(45px + env(safe-area-inset-bottom));
            padding-left: calc(16px + env(safe-area-inset-left));
            padding-right: calc(16px + env(safe-area-inset-right));
        }
        .back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; color: var(--btn-install-bg); font-weight: 500; }
        .app-header { display: flex; gap: 18px; align-items: center; margin-bottom: 25px; }
        .app-main-icon { width: 90px; height: 90px; border-radius: 20px; object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,0.08); background: #eee; flex-shrink: 0; }
        .app-title-block { min-width: 0; flex-grow: 1; }
        .app-title-block h1 { margin: 0 0 4px 0; font-size: 1.4rem; font-weight: 700; }
        .developer { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 4px; }
        .developer a { color: var(--btn-install-bg); text-decoration: none; }
        .developer a:hover { text-decoration: underline; }
        .domain { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .action-buttons { display: flex; align-items: center; gap: 10px; }
        .btn-install { display: inline-block; background: var(--btn-install-bg); color: var(--btn-install-text); font-weight: bold; padding: 10px 28px; border-radius: 22px; text-decoration: none; font-size: 0.95rem; text-align: center; box-shadow: 0 4px 12px rgba(0,127,246,0.2); }
        .share-btn { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 1.1rem; padding: 0; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .badges-container { display: flex; justify-content: space-around; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 14px 0; margin-bottom: 25px; text-align: center; }
        .badge-item { display: flex; flex-direction: column; }
        .badge-value { font-weight: bold; font-size: 1.1rem; }
        .badge-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-top: 3px; }
        .screenshots-slider { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; margin-bottom: 25px; scroll-snap-type: x mandatory; }
        .screenshots-slider::-webkit-scrollbar { display: none; }
        .screenshots-slider img { height: 280px; border-radius: 12px; scroll-snap-align: start; box-shadow: 0 2px 6px rgba(0,0,0,0.06); flex-shrink: 0; }
        .description { line-height: 1.5; color: var(--text-secondary); font-size: 0.95rem; -webkit-user-select: text; user-select: text; }
        .related-card-link { text-decoration: none; color: inherit; }
        .related-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .related-card { background: var(--bg-related); border-radius: 14px; padding: 12px; display: flex; align-items: center; gap: 12px; border: 1px solid var(--border-color); }
        .related-card img { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
        .related-info h4 { margin: 0 0 2px 0; font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .related-cat { font-size: 0.75rem; color: var(--text-muted); }
        .btn-install:active { transform: scale(0.96); background-color: #0066c7; }
        .share-btn:active, .related-card:active { transform: scale(0.95); background: var(--border-color); }
        .modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); color: var(--text-primary); padding: 24px; border-radius: 20px; max-width: 85%; width: 320px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-content p { font-size: 0.95rem; line-height: 1.4; color: var(--text-secondary); }
    </style>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "<?= htmlspecialchars($app['title'], ENT_QUOTES) ?>",
      "operatingSystem": "iOS, Android, Windows, macOS",
      "applicationCategory": "MobileApplication",
      "downloadUrl": "<?= htmlspecialchars($app['url'], ENT_QUOTES) ?>",
      "image": "<?= $store_domain ?>/<?= htmlspecialchars($app['icon'], ENT_QUOTES) ?>",
      "description": "<?= htmlspecialchars(strip_tags($app['description']), ENT_QUOTES) ?>",
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<?= number_format($app['rating'], 1) ?>",
        "ratingCount": "<?= $app['downloads'] ?>"
      },
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      },
      "author": {
        "@type": "Organization",
        "name": "<?= htmlspecialchars($app['developer_name'], ENT_QUOTES) ?>"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [{
        "@type": "ListItem", "position": 1, "name": "Главная", "item": "<?= $store_domain ?>/index.php"
      },{
        "@type": "ListItem", "position": 2, "name": "<?= htmlspecialchars($app['category']) ?>", "item": "<?= $store_domain ?>/index.php?cat=<?= urlencode($app['category']) ?>"
      },{
        "@type": "ListItem", "position": 3, "name": "<?= htmlspecialchars($app['title']) ?>", "item": "<?= $store_domain ?>/app.php?id=<?= $app['id'] ?>"
      }]
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [{
        "@type": "Question",
        "name": "Как установить приложение <?= htmlspecialchars($app['title']) ?>?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Нажмите кнопку 'Открыть', затем в меню браузера выберите 'Добавить на экран Домой'."
        }
      }, {
        "@type": "Question",
        "name": "Безопасно ли использовать это PWA приложение?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Да, приложение работает по HTTPS и прошло ручную проверку модератором каталога."
        }
      }]
    }
    </script>
</head>
<body>
<div class="app-container">
    <a href="index.php" class="back-btn">← Назад в магазин</a>

    <div class="app-header">
        <img class="app-main-icon" src="<?= htmlspecialchars($app['icon']) ?>" alt="Icon">
        <div class="app-title-block">
            <h1><?= htmlspecialchars($app['title']) ?></h1>
            <?php if (!empty($app['developer_name'])): ?>
                <div class="developer">
                    <a href="developer.php?id=<?= $app['developer_id'] ?>"><?= htmlspecialchars($app['developer_name']) ?></a>
                </div>
            <?php endif; ?>
            <div class="domain"><?= $app_domain ?></div>
            <div class="action-buttons">
                <a href="#" id="openAppBtn" class="btn-install">ОТКРЫТЬ</a>
                <button id="shareBtn" class="share-btn">🔗</button>
            </div>
        </div>
    </div>

    <div class="badges-container">
        <div class="badge-item">
            <span class="badge-value"><?= number_format($app['rating'], 1) ?> ★</span>
            <span class="badge-label">Рейтинг</span>
        </div>
        <div class="badge-item">
            <?php if ($app['is_verified'] == 1): ?>
                <span class="badge-value" style="color:#28a745;">Да</span><span class="badge-label" style="color:#28a745; font-weight:bold;">Проверен</span>
            <?php else: ?>
                <span class="badge-value" style="color:var(--text-muted);">Ждет</span><span class="badge-label">Аудит</span>
            <?php endif; ?>
        </div>
        <div class="badge-item">
            <?php if (!empty($app['is_whitelisted']) && $app['is_whitelisted'] == 1): ?>
                <span class="badge-value" style="color:#007ff6;">РФ 🛡️</span>
                <span class="badge-label" style="color:#007ff6; font-weight:bold;">Реестр</span>
            <?php else: ?>
                <span class="badge-value" style="color:var(--text-muted);">Нет</span>
                <span class="badge-label">Реестр</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($screenshots)): ?>
        <h2>Скриншоты интерфейса</h2>
        <div class="screenshots-slider">
            <?php foreach ($screenshots as $screen): ?>
                <img src="<?= htmlspecialchars($screen) ?>" alt="Screenshot">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Описание</h2>
    <div class="description">
        <p><?= nl2br(htmlspecialchars($app['description'])) ?></p>
        <p style="color: var(--text-muted); font-size:0.85rem; margin-top:20px;">
            * Это прогрессивное веб-приложение (PWA). Чтобы установить его на рабочий стол, нажмите кнопку «Открыть», а затем выберите в меню браузера пункт «Добавить на экран Домой».
        </p>
    </div>

    <?php if (!empty($related_apps)): ?>
        <div class="related-section" style="margin-top:40px; border-top:1px solid var(--border-color); padding-top:25px;">
            <h2>👀 Вам также может понравиться</h2>
            <div class="related-grid">
                <?php foreach ($related_apps as $rel_app): ?>
                    <a href="app.php?id=<?= $rel_app['id'] ?>" class="related-card-link">
                        <div class="related-card">
                            <img src="<?= !empty($rel_app['icon']) ? htmlspecialchars($rel_app['icon']) : 'https://placeholder.com' ?>" alt="Icon">
                            <div class="related-info">
                                <h4><?= htmlspecialchars($rel_app['title']) ?></h4>
                                <span class="related-cat"><?= htmlspecialchars($rel_app['category']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
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

<div id="installModal" class="modal">
    <div class="modal-content">
        <div id="instruction-ios" style="display:none;">
            <h3>📐 Как установить на iPhone / iPad:</h3>
            <p>1. Сайт откроется в новой вкладке.</p>
            <p>2. Нажмите кнопку <b>«Поделиться»</b> (квадрат со стрелкой вверх) внизу экрана.</p>
            <p>3. Выберите <b>«На экран "Домой"»</b> (знак плюс ➕).</p>
        </div>
        <div id="instruction-android" style="display:none;">
            <h3>📐 Как установить на Android:</h3>
            <p>1. Сайт откроется в новой вкладке.</p>
            <p>2. Внизу экрана появится баннер <b>«Добавить на главный экран»</b>.</p>
            <p>3. Если баннера нет, нажмите <b>три точки (⋮)</b> вверху справа и выберите <b>«Установить приложение»</b>.</p>
        </div>
        <button id="confirmRedirectBtn" class="btn-install" style="width:100%; margin-top:15px;">Всё понятно, открыть</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const openBtn = document.getElementById("openAppBtn");
    const modal = document.getElementById("installModal");
    const confirmBtn = document.getElementById("confirmRedirectBtn");
    const shareBtn = document.getElementById("shareBtn");
    const appUrl = "<?= htmlspecialchars($app['url']) ?>";

    openBtn.addEventListener("click", function(e) {
        e.preventDefault();
        const userAgent = navigator.userAgent || navigator.vendor || window.opera;
        if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
            document.getElementById("instruction-ios").style.display = "block";
        } else {
            document.getElementById("instruction-android").style.display = "block";
        }
        modal.style.display = "flex";
    });

    confirmBtn.addEventListener("click", function() {
        modal.style.display = "none";
        window.open(appUrl, '_blank', 'noopener,noreferrer');
    });

    shareBtn.addEventListener("click", async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: 'PWA', url: window.location.href });
            } catch (err) {}
        } else {
            navigator.clipboard.writeText(window.location.href);
            alert('Ссылка на карточку скопирована в буфер обмена!');
        }
    });

    let touchStartX = 0;
    window.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].screenX; });
    window.addEventListener('touchend', e => {
        if (e.changedTouches[0].screenX - touchStartX > 130 && touchStartX < 40) {
            window.location.href = 'index.php';
        }
    });
});
</script>
</body>
</html>