<?php
// Функция рендеринга трёхрядной карусели
function render_app_carousel_3row($title, $apps_list) {
    if (empty($apps_list)) return;
    $chunks = array_chunk($apps_list, 3);
?>
    <div class="carousel-section-3row">
        <div class="carousel-header">
            <h2><?= e($title) ?></h2>
            <span class="carousel-arrow">›</span>
        </div>
        <div class="horizontal-carousel-wrapper-3row">
            <?php foreach ($chunks as $column_apps): ?>
                <div class="carousel-column">
                    <?php foreach ($column_apps as $app): ?>
                        <a href="/app/<?= $app['id'] ?>" class="carousel-item-link-3row">
                            <div class="carousel-app-card-3row">
                                <img src="/<?= e($app['icon'] ?? 'https://placeholder.com') ?>" alt="Icon" loading="lazy">
                                <div class="carousel-app-info">
                                    <h4>
                                        <?= e($app['title']) ?>
                                        <?php if (!empty($app['is_verified']) && $app['is_verified'] == 1): ?>
                                            <span class="verified-badge">✓</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="carousel-app-desc"><?= e($app['description'] ?? '') ?></span>
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

<div class="store-header">
    <h1>📱 АпАп</h1>
</div>

<!-- Поиск -->
<form action="/" method="GET" class="search-form">
    <?php if (!empty($category)): ?>
        <input type="hidden" name="category" value="<?= e($category) ?>">
    <?php endif; ?>
    <input type="text" name="q" placeholder="Поиск приложений..." value="<?= e($searchQuery ?? '') ?>" class="search-input">
</form>

<!-- Категории -->
<div class="categories-scroll">
    <?php foreach ($categories as $cat): ?>
        <?php
            $active = ($cat === 'Все' && empty($category)) || ($cat === $category);
            $link = ($cat === 'Все') ? '/' : '/?category=' . urlencode($cat);
            if (!empty($searchQuery)) $link .= '&q=' . urlencode($searchQuery);
        ?>
        <a href="<?= $link ?>" class="cat-chip <?= $active ? 'active' : '' ?>">
            <?= e($cat) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($isFiltered): ?>
    <h2 class="section-title">Результаты (<?= count($appsList) ?>)</h2>
    <?php if (empty($appsList)): ?>
        <p class="empty-message">Ничего не найдено.</p>
    <?php else: ?>
        <div class="apps-list">
            <?php foreach ($appsList as $app): ?>
                <a href="/app/<?= $app['id'] ?>" class="app-card-link">
                    <div class="app-card">
                        <img src="/<?= e($app['icon'] ?? 'https://placeholder.com') ?>" alt="" class="app-icon">
                        <div class="app-info">
                            <h3 class="app-title"><?= e($app['title']) ?></h3>
                            <span class="app-category"><?= e($app['category'] ?? 'Приложение') ?></span>
                            <div class="app-meta">
                                ★ <?= number_format($app['rating'] ?? 4.5, 1) ?> |
                                📥 <?= number_format($app['downloads'] ?? 0) ?>+
                            </div>
                        </div>
                        <span class="btn-view">Смотреть</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Главная с каруселями -->
    <?php render_app_carousel_3row("Самое основное", $mainApps); ?>
    <?php render_app_carousel_3row("Избранное за неделю", $weeklyTop); ?>
    <?php render_app_carousel_3row("Топ бесплатных приложений", $recentApps); ?>
<?php endif; ?>