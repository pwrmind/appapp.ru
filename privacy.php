<?php
// privacy.php
$store_domain = "https://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Политика конфиденциальности — АпАп</title>
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
    <h1>Политика конфиденциальности</h1>
    <p>Наш каталог (АпАп) не собирает и не хранит персональные данные пользователей. Мы не используем файлы cookie для отслеживания. Все переходы на сторонние сайты осуществляются по прямым ссылкам – вы взаимодействуете с ними напрямую, согласно их собственным политикам конфиденциальности.</p>
    <p>Если у вас есть вопросы, свяжитесь с нами через форму обратной связи (будет добавлена позже).</p>
    <p><a href="index.php">← Вернуться в каталог</a></p>
</body>
</html>