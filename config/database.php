<?php
try {
    $pdo = new PDO(DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Создаём таблицы, если их нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS developers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        website TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS apps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        url TEXT UNIQUE NOT NULL,
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
    // Индексы для ускорения запросов
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_apps_category ON apps(category)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_apps_developer_id ON apps(developer_id)");
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}