<?php
function septenture_config(string $key, string $default = ''): string {
    static $local;
    if ($local === null) {
        $path = __DIR__.'/private.php';
        $local = is_file($path) ? require $path : [];
    }
    $value = getenv($key);
    return $value !== false ? $value : (string)($local[$key] ?? $default);
}
function septenture_db(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('mysql:host='.septenture_config('DB_HOST', 'database').';dbname='.septenture_config('DB_NAME', 'nova').';charset=utf8mb4',
            septenture_config('DB_USER', 'nova'), septenture_config('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $db;
}
