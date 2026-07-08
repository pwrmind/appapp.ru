<?php
// about.php
$store_domain = "https://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>О проекте — АпАп</title>
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --bg-main: #f4f4f9;
            --text-primary: #111;
            --text-secondary: #555;
            --btn-install-bg: #007ff6;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-main: #121214;
                --text-primary: #f5f5f7;
                --text-secondary: #a1a1aa;
            }
        }
        body {
            margin: 0; padding: 20px;
            background: var(--bg-main);
            color: var(--text-primary);
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.6;
        }
        a { color: var(--btn-install-bg); }
    </style>
</head>
<body>
    <h1>О проекте АпАп</h1>
    <p>АпАп — это независимый каталог российских прогрессивных веб-приложений (PWA), которые можно установить на главный экран смартфона без скачивания из магазинов приложений.</p>
    <p>Мы собираем и структурируем общедоступные PWA, чтобы пользователи могли быстро находить и запускать нужные сервисы прямо из браузера. Все ссылки ведут на официальные сайты приложений.</p>
    <p><a href="index.php">← Вернуться в каталог</a></p>
</body>
</html>