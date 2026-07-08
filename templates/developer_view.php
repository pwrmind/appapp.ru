<a href="/" class="back-btn">← Назад в каталог</a>

<div class="developer-profile">
    <div class="dev-avatar">
        <?= e(mb_substr($developer['name'], 0, 1)) ?>
    </div>
    <div>
        <h1 class="dev-profile-name"><?= e($developer['name']) ?></h1>
        <?php if (!empty($developer['website'])): ?>
            <a href="<?= e($developer['website']) ?>" target="_blank" class="dev-profile-link">🌐 Официальный сайт</a>
        <?php endif; ?>
        <p class="dev-meta">В каталоге с: <?= date('d.m.Y', strtotime($developer['created_at'])) ?></p>
    </div>
</div>

<h2 class="section-title">Приложения автора (<?= count($developerApps) ?>)</h2>

<?php if (empty($developerApps)): ?>
    <p class="empty-message">У данного разработчика пока нет опубликованных PWA-приложений.</p>
<?php else: ?>
    <div class="apps-grid">
        <?php foreach ($developerApps as $app): ?>
            <div class="grid-card">
                <a href="/app/<?= $app['id'] ?>" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; align-items:center;">
                    <img src="/<?= e($app['icon'] ?? 'https://placeholder.com') ?>" alt="" class="grid-card-icon">
                    <div class="grid-card-title"><?= e($app['title']) ?></div>
                    <div class="grid-card-category"><?= e($app['category']) ?></div>
                </a>
                <a href="/app/<?= $app['id'] ?>" class="btn-grid-view">Смотреть</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>