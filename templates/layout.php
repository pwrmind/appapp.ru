<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle ?? 'АпАп — Каталог PWA') ?></title>
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="container">
    <?= $content ?>
</div>
<footer class="footer">
    <div>
        <a href="/about">О проекте</a>
        <a href="/privacy">Политика конфиденциальности</a>
    </div>
    <p style="margin: 10px 0 0;"> <!-- оставляем небольшой инлайн, т.к. это специфичный отступ -->
        Все товарные знаки, логотипы и графические материалы принадлежат их законным владельцам.<br>
        АпАп является независимым каталогом общедоступных веб-ссылок.
    </p>
</footer>
</body>
</html>