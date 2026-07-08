<?php
class PwaParser {
    private PDO $pdo;
    private string $uploadDir;

    public function __construct(PDO $pdo, string $uploadDir = __DIR__ . '/../uploads/') {
        $this->pdo = $pdo;
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        if (!is_dir($this->uploadDir . 'icons/')) mkdir($this->uploadDir . 'icons/', 0755, true);
        if (!is_dir($this->uploadDir . 'screenshots/')) mkdir($this->uploadDir . 'screenshots/', 0755, true);
    }

    private function cleanExtension(string $string): string {
        return preg_replace('/[^a-zA-Z0-9]/', '', $string);
    }

    private function downloadFile(string $fileUrl, string $saveDir, ?string $cookies = null): ?string {
        if (empty($fileUrl)) return null;
        $pathInfo = pathinfo(parse_url($fileUrl, PHP_URL_PATH));
        $extension = !empty($pathInfo['extension']) ? $pathInfo['extension'] : 'png';
        $extension = substr($this->cleanExtension($extension), 0, 4);
        $localName = uniqid('pwa_', true) . '.' . $extension;
        $localPath = $this->uploadDir . $saveDir . $localName;

        $ch = curl_init($fileUrl);
        $fp = @fopen($localPath, 'wb');
        if (!$fp) return null;
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36');
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }
        $success = curl_exec($ch);
        fclose($fp);
        if ($success && filesize($localPath) > 0) {
            return $localPath;
        } else {
            @unlink($localPath);
            return null;
        }
    }

    private function curlGet(string $url, string $accept = 'application/json, text/plain, */*', ?string $referer = null, ?string $cookies = null): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        $headers = [
            'Accept: ' . $accept,
            'User-Agent: Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36'
        ];
        if ($referer) $headers[] = 'Referer: ' . $referer;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($cookies) curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$response, $httpCode];
    }

    private function looksLikeJson(string $str): bool {
        $trimmed = ltrim($str);
        return isset($trimmed[0]) && ($trimmed[0] === '{' || $trimmed[0] === '[');
    }

    private function fetchManifest(string $siteUrl, ?string $explicitManifestUrl = null, ?string $cookies = null): ?array {
        $siteUrl = rtrim($siteUrl, '/');
        if (!empty($explicitManifestUrl)) {
            if (parse_url($explicitManifestUrl, PHP_URL_SCHEME) === null) {
                $explicitManifestUrl = $siteUrl . '/' . ltrim($explicitManifestUrl, '/');
            }
            list($response, $httpCode) = $this->curlGet($explicitManifestUrl, 'application/manifest+json, application/json, text/plain, */*', $siteUrl . '/', $cookies);
            if ($httpCode === 200 && $response && $this->looksLikeJson($response)) {
                return json_decode($response, true);
            }
        }
        $url = $siteUrl . '/manifest.json';
        list($response, $httpCode) = $this->curlGet($url, 'application/manifest+json, application/json, text/plain, */*', $siteUrl . '/', $cookies);
        if ($httpCode === 200 && $response && $this->looksLikeJson($response)) {
            return json_decode($response, true);
        }
        list($html, $httpCode) = $this->curlGet($siteUrl, 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', null, $cookies);
        if ($httpCode !== 200 || !$html) return null;
        if (preg_match('/<link[^>]+rel=["\']manifest["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            $manifestPath = $matches[1];
            if (parse_url($manifestPath, PHP_URL_SCHEME) === null) {
                $manifestUrl = $siteUrl . '/' . ltrim($manifestPath, '/');
            } else {
                $manifestUrl = $manifestPath;
            }
            list($response, $httpCode) = $this->curlGet($manifestUrl, 'application/manifest+json, application/json, text/plain, */*', $siteUrl . '/', $cookies);
            if ($httpCode === 200 && $response && $this->looksLikeJson($response)) {
                return json_decode($response, true);
            }
        }
        return null;
    }

    private function getOrCreateDeveloper(array $manifest, string $siteUrl): int {
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        $developerName = $manifest['author'] ?? $manifest['developer'] ?? null;
        if (!$developerName) {
            $developerName = ucfirst(preg_replace('/^www\./', '', $domain));
            $developerName = preg_replace('/\.(com|ru|org|net|рф|su).*$/i', '', $developerName);
            $developerName = ucfirst($developerName);
        }
        $stmt = $this->pdo->prepare("SELECT id FROM developers WHERE name = :name");
        $stmt->execute([':name' => $developerName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['id'];
        }
        $stmt = $this->pdo->prepare("INSERT INTO developers (name, website) VALUES (:name, :website)");
        $stmt->execute([':name' => $developerName, ':website' => $siteUrl]);
        return $this->pdo->lastInsertId();
    }

    public function addPwaToCatalog(string $siteUrl, ?string $manifestUrl = null, ?string $cookies = null): string {
        $siteUrl = rtrim($siteUrl, '/');
        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) return "Неверный формат ссылки.";

        $manifest = $this->fetchManifest($siteUrl, $manifestUrl, $cookies);
        if (!$manifest) {
            return "Не удалось найти или загрузить манифест.";
        }

        $title = $manifest['name'] ?? $manifest['short_name'] ?? parse_url($siteUrl, PHP_URL_HOST);
        $description = $manifest['description'] ?? 'Прогрессивное веб-приложение (PWA) для мобильных устройств.';
        $finalCategory = 'Инструменты';
        $manifestCategories = !empty($manifest['categories']) ? array_map('strtolower', (array)$manifest['categories']) : [];
        foreach ($manifestCategories as $catTag) {
            if (strpos($catTag, 'game') !== false || strpos($catTag, 'arcade') !== false) { $finalCategory = 'Игры'; break; }
            if (strpos($catTag, 'social') !== false || strpos($catTag, 'chat') !== false || strpos($catTag, 'communication') !== false) { $finalCategory = 'Соцсети'; break; }
            if (strpos($catTag, 'shop') !== false || strpos($catTag, 'commerce') !== false) { $finalCategory = 'Покупки'; break; }
            if (strpos($catTag, 'news') !== false || strpos($catTag, 'book') !== false || strpos($catTag, 'productivity') !== false) { $finalCategory = 'Новости'; break; }
        }
        if ($finalCategory === 'Инструменты' && extension_loaded('mbstring')) {
            $descLower = mb_strtolower($description, 'UTF-8');
            if (mb_strpos($descLower, 'игра') !== false || mb_strpos($descLower, 'game') !== false) $finalCategory = 'Игры';
            elseif (mb_strpos($descLower, 'магазин') !== false || mb_strpos($descLower, 'купить') !== false) $finalCategory = 'Покупки';
        }

        $localIcon = '';
        if (!empty($manifest['icons']) && is_array($manifest['icons'])) {
            $lastIcon = end($manifest['icons']);
            $remoteIconSrc = $lastIcon['src'] ?? '';
            if ($remoteIconSrc) {
                if (parse_url($remoteIconSrc, PHP_URL_SCHEME) === null) {
                    $remoteIconUrl = $siteUrl . '/' . ltrim($remoteIconSrc, '/');
                } else {
                    $remoteIconUrl = $remoteIconSrc;
                }
                $localIcon = $this->downloadFile($remoteIconUrl, 'icons/', $cookies);
            }
        }

        $localScreenshots = [];
        if (!empty($manifest['screenshots']) && is_array($manifest['screenshots'])) {
            foreach ($manifest['screenshots'] as $screen) {
                $src = $screen['src'] ?? '';
                if ($src) {
                    if (parse_url($src, PHP_URL_SCHEME) === null) {
                        $remoteScreenUrl = $siteUrl . '/' . ltrim($src, '/');
                    } else {
                        $remoteScreenUrl = $src;
                    }
                    $saved = $this->downloadFile($remoteScreenUrl, 'screenshots/', $cookies);
                    if ($saved) $localScreenshots[] = $saved;
                }
            }
        }
        $screenshotsString = implode(',', $localScreenshots);

        $appHost = parse_url($siteUrl, PHP_URL_HOST);
        $isWhitelisted = 0;
        $ipAddress = gethostbyname($appHost);
        if ($ipAddress !== $appHost) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $rknCheck = @file_get_contents("https://isblocked.ru/" . urlencode($appHost), false, $ctx);
            if ($rknCheck) {
                $rknData = json_decode($rknCheck, true);
                if (isset($rknData['blocked']) && $rknData['blocked'] === false) {
                    if (preg_match('/\.(ru|рф|su)$/i', $appHost)) $isWhitelisted = 1;
                }
            } else {
                if (preg_match('/\.(ru|рф)$/i', $appHost) && strpos($siteUrl, 'https://') === 0) $isWhitelisted = 1;
            }
        }

        $developerId = $this->getOrCreateDeveloper($manifest, $siteUrl);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO apps 
                (title, url, icon, description, category, screenshots, downloads, is_verified, is_whitelisted, developer_id)
                VALUES (:title, :url, :icon, :description, :category, :screenshots, :downloads, :is_verified, :is_whitelisted, :developer_id)");
            $stmt->execute([
                ':title' => $title,
                ':url' => $siteUrl,
                ':icon' => $localIcon,
                ':description' => $description,
                ':category' => $finalCategory,
                ':screenshots' => $screenshotsString,
                ':downloads' => rand(250, 1800),
                ':is_verified' => 0,
                ':is_whitelisted' => $isWhitelisted,
                ':developer_id' => $developerId
            ]);
            return "success";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') return "Это PWA приложение уже добавлено в каталог.";
            return "Ошибка базы данных: " . $e->getMessage();
        }
    }
}