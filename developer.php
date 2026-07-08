<?php
// developer.php
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.1 404 Not Found");
    die("Разработчик не найден");
}

$developer_id = (int)$_GET['id'];
$developer = null;
$apps = [];

try {
    $db = new PDO(DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("SELECT * FROM developers WHERE id = :id");
    $stmt->execute([':id' => $developer_id]);
    $developer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$developer) {
        header("HTTP/1.1 404 Not Found");
        die("Разработчик не найден");
    }

    $stmt = $db->prepare("SELECT * FROM apps WHERE developer_id = :developer_id ORDER BY id DESC");
    $stmt->execute([':developer_id' => $developer_id]);
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка базы данных");
}

$store_domain = "https://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($developer['name']) ?> — разработчик приложений в АпАп</title>
    <meta name="description" content="Все прогрессивные веб-приложения (PWA) от разработчика <?= htmlspecialchars($developer['name']) ?> в каталоге АпАп. Устанавливайте на главный экран без магазинов.">
    <link rel="canonical" href="<?= $store_domain ?>/developer.php?id=<?= $developer_id ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $store_domain ?>/developer.php?id=<?= $developer_id ?>">
    <meta property="og:title" content="<?= htmlspecialchars($developer['name']) ?> — разработчик приложений в АпАп">
    <meta property="og:description" content="Все прогрессивные веб-приложения (PWA) от разработчика <?= htmlspecialchars($developer['name']) ?> в каталоге АпАп.">
    <meta name="color-scheme" content="light dark">
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
        .back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; color: var(--btn-install-bg); font-weight: 500; }
        h1 { font-size: 1.8rem; font-weight: 700; margin: 10px 0 20px 0; }
        .developer-info { margin-bottom: 30px; }
        .developer-info p { color: var(--text-secondary); line-height: 1.5; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
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
            white-space: nowrap; text-overflow: ellipsis; overflow: hidden;
        }
        .category { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }
        .meta { font-size: 0.8rem; color: var(--text-secondary); display: flex; gap: 6px; align-items: center; }
        .app-card:active { transform: scale(0.97); }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-btn">← Назад в каталог</a>
    <h1><?= htmlspecialchars($developer['name']) ?></h1>
    <div class="developer-info">
        <?php if (!empty($developer['website'])): ?>
            <p>Сайт: <a href="<?= htmlspecialchars($developer['website']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(parse_url($developer['website'], PHP_URL_HOST)) ?></a></p>
        <?php endif; ?>
        <p>Всего приложений: <?= count($apps) ?></p>
    </div>

    <?php if (empty($apps)): ?>
        <p>У этого разработчика пока нет приложений в каталоге.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($apps as $app): ?>
                <a href="app.php?id=<?= $app['id'] ?>" class="app-card-link">
                    <div class="app-card">
                        <img src="<?= !empty($app['icon']) ? htmlspecialchars($app['icon']) : 'https://placeholder.com' ?>" alt="Icon">
                        <div class="app-info">
                            <h3><?= htmlspecialchars($app['title']) ?></h3>
                            <span class="category"><?= htmlspecialchars($app['category'] ?? 'Приложение') ?></span>
                            <div class="meta">
                                <span style="color:#ffb400;">★</span>
                                <span><?= number_format($app['rating'] ?? 4.5, 1) ?></span>
                                <span style="color: var(--border-color);">|</span>
                                <span>📥 <?= number_format($app['downloads']) ?>+</span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>