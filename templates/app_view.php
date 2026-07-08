<a href="/" class="back-btn">← Назад в каталог</a>

<div class="app-header">
    <img src="/<?= e($app['icon'] ?? 'https://placeholder.com') ?>" alt="" class="app-main-icon">
    <div class="app-title-block">
        <h1 class="app-title"><?= e($app['title']) ?></h1>
        <?php if (!empty($app['developer_name'])): ?>
            <div class="developer-link">
                Разработчик: <a href="/developer/<?= $app['developer_id'] ?>"><?= e($app['developer_name']) ?></a>
            </div>
        <?php endif; ?>
        <div class="app-domain"><?= e($appDomain) ?></div>
        <div class="action-buttons">
            <a href="<?= e($app['url']) ?>" target="_blank" class="btn-install">ОТКРЫТЬ</a>
            <button id="shareBtn" class="share-btn">🔗</button>
        </div>
    </div>
</div>

<div class="badges-container">
    <div class="badge-item">
        <span class="badge-value"><?= number_format($app['rating'] ?? 4.5, 1) ?> ★</span>
        <span class="badge-label">Рейтинг</span>
    </div>
    <div class="badge-item">
        <span class="badge-value <?= $app['is_verified'] ? 'badge-verified' : '' ?>">
            <?= $app['is_verified'] ? 'Да' : 'Ждет' ?>
        </span>
        <span class="badge-label">Проверен</span>
    </div>
    <div class="badge-item">
        <span class="badge-value <?= $app['is_whitelisted'] ? 'badge-whitelist' : '' ?>">
            <?= $app['is_whitelisted'] ? 'РФ 🛡️' : 'Нет' ?>
        </span>
        <span class="badge-label">Реестр</span>
    </div>
</div>

<?php if (!empty($screenshots)): ?>
    <h2 class="section-title">Скриншоты</h2>
    <div class="screenshots-slider">
        <?php foreach ($screenshots as $screen): ?>
            <img src="/<?= e($screen) ?>" alt="Скриншот">
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="section-title">Описание</h2>
<div class="description">
    <p><?= nl2br(e($app['description'])) ?></p>
    <p class="note">
        * Это прогрессивное веб-приложение (PWA). Чтобы установить его на рабочий стол, нажмите кнопку «Открыть», а затем выберите в меню браузера пункт «Добавить на экран Домой».
    </p>
</div>

<?php if (!empty($relatedApps)): ?>
    <div class="related-section">
        <h2 class="section-title">👀 Вам также может понравиться</h2>
        <div class="related-grid">
            <?php foreach ($relatedApps as $rel): ?>
                <a href="/app/<?= $rel['id'] ?>" class="related-card">
                    <img src="/<?= e($rel['icon'] ?? 'https://placeholder.com') ?>" alt="" class="related-icon">
                    <h4 class="related-title"><?= e($rel['title']) ?></h4>
                    <span class="related-category"><?= e($rel['category']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareBtn = document.getElementById('shareBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', async function() {
            if (navigator.share) {
                try { await navigator.share({ title: 'PWA', url: window.location.href }); } catch(e) {}
            } else {
                navigator.clipboard.writeText(window.location.href).then(() => alert('Ссылка скопирована'));
            }
        });
    }
});
</script>