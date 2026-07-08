<?php
class AppRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findAppById(int $id): ?array {
        $sql = "SELECT a.*, d.name AS developer_name, d.website AS developer_website
                FROM apps a
                LEFT JOIN developers d ON a.developer_id = d.id
                WHERE a.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getApps(array $filters = [], int $limit = 30): array {
        $sql = "SELECT a.*, d.name AS developer_name
                FROM apps a
                LEFT JOIN developers d ON a.developer_id = d.id";
        $where = [];
        $params = [];
        
        if (!empty($filters['category']) && $filters['category'] !== 'Все') {
            $where[] = "a.category = :category";
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $where[] = "a.title LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY a.created_at DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        
        // ИСПРАВЛЕНИЕ ОШИБКИ №1: Привязываем LIMIT строго как INT, а остальные параметры передаем через execute
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDeveloperById(int $id): ?array {
        $sql = "SELECT * FROM developers WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAppsByDeveloperId(int $developerId): array {
        $sql = "SELECT a.*, d.name AS developer_name
                FROM apps a
                LEFT JOIN developers d ON a.developer_id = d.id
                WHERE a.developer_id = :developer_id
                ORDER BY a.title ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['developer_id' => $developerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementDownloads(int $appId): void {
        $sql = "UPDATE apps SET downloads = downloads + 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $appId]);
    }

    public function getRelatedApps(string $category, int $currentId, int $limit = 4): array {
        $sql = "SELECT id, title, icon, category, rating FROM apps
                WHERE category = :category AND id != :current_id
                ORDER BY RANDOM() LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('category', $category, PDO::PARAM_STR);
        $stmt->bindValue('current_id', $currentId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Если похожих приложений в текущей категории не хватило до лимита
        if (count($result) < $limit) {
            $needed = $limit - count($result);
            $excludeIds = array_merge([$currentId], array_column($result, 'id'));
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            
            $sql2 = "SELECT id, title, icon, category, rating FROM apps
                     WHERE id NOT IN ($placeholders)
                     ORDER BY RANDOM() LIMIT :needed";
            
            $stmt2 = $this->pdo->prepare($sql2);
            
            // ИСПРАВЛЕНИЕ ОШИБКИ №2: Явно биндим IDшники как числа через цикл, чтобы NOT IN работал корректно
            foreach ($excludeIds as $index => $id) {
                $stmt2->bindValue($index + 1, (int)$id, PDO::PARAM_INT); // Индексы плейсхолдеров '?' в PDO начинаются с 1
            }
            $stmt2->bindValue('needed', $needed, PDO::PARAM_INT);
            
            $stmt2->execute();
            $result = array_merge($result, $stmt2->fetchAll(PDO::FETCH_ASSOC));
        }
        
        return $result;
    }
}
