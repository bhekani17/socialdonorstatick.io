<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables from a `.env` file in the project root (optional)
// Copy `.env.example` to `.env` and update values for MongoDB Atlas.
load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

function load_env_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function app_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dbPath = $dataDir . DIRECTORY_SEPARATOR . 'socialdonor.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    init_schema($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS donors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            first_name TEXT NOT NULL,
            surname TEXT NOT NULL,
            id_number TEXT NOT NULL,
            cell_number TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            blood_type TEXT NOT NULL,
            address TEXT NOT NULL,
            race TEXT NOT NULL,
            gender TEXT NOT NULL,
            emergency_contact TEXT,
            emergency_number TEXT,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            full_name TEXT NOT NULL,
            surname TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            cell_number TEXT,
            role TEXT NOT NULL DEFAULT "Admin",
            permissions TEXT,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS urgent_places (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            area TEXT NOT NULL,
            blood TEXT NOT NULL,
            urgency TEXT NOT NULL,
            lat REAL NOT NULL,
            lng REAL NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    ensure_column($pdo, 'donors', 'username', 'TEXT');
    ensure_column($pdo, 'donors', 'emergency_contact', 'TEXT');
    ensure_column($pdo, 'donors', 'emergency_number', 'TEXT');
    ensure_column($pdo, 'admins', 'username', 'TEXT');
    ensure_column($pdo, 'admins', 'permissions', 'TEXT');

    seed_urgent_places($pdo);
    seed_default_admin($pdo);
}

function seed_urgent_places(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM urgent_places')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $rows = [
        ['Charlotte Maxeke Hospital', 'Johannesburg CBD', 'O-', 'Critical', -26.1884, 28.0398],
        ['Chris Hani Baragwanath', 'Soweto', 'A+', 'High', -26.2564, 27.9427],
        ['Helen Joseph Hospital', 'Auckland Park', 'B-', 'High', -26.1812, 28.0104],
        ['Tembisa Hospital', 'Tembisa', 'AB-', 'Critical', -25.9943, 28.2228],
        ['Rahima Moosa Hospital', 'Coronationville', 'O+', 'Moderate', -26.1699, 27.9964],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO urgent_places (name, area, blood, urgency, lat, lng) VALUES (:name, :area, :blood, :urgency, :lat, :lng)'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            ':name' => $row[0],
            ':area' => $row[1],
            ':blood' => $row[2],
            ':urgency' => $row[3],
            ':lat' => $row[4],
            ':lng' => $row[5],
        ]);
    }
}

function seed_default_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admins (username, full_name, surname, email, cell_number, role, permissions, password_hash)
         VALUES (:username, :full_name, :surname, :email, :cell_number, :role, :permissions, :password_hash)'
    );

    $stmt->execute([
        ':username' => 'sysadmin',
        ':full_name' => 'System',
        ':surname' => 'Administrator',
        ':email' => 'admin@socialdonor.org',
        ':cell_number' => '+27 11 000 0000',
        ':role' => 'Super Admin',
        ':permissions' => 'Can approve donor records, issue urgent alerts, and view request analytics.',
        ':password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
    ]);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $query = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $query ? $query->fetchAll() : [];

    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function post_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}


// MongoDB helpers — optional. If the MongoDB PHP library (mongodb/mongodb) is
// installed via Composer and the extension is available this will return a
// MongoDB\Client instance. If not available the functions return null so the
// rest of the application can continue using the existing SQLite `app_pdo()`.
function app_mongo(): ?MongoDB\Client
{
    static $client = null;
    if ($client instanceof MongoDB\Client) {
        return $client;
    }

    $composerAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    }

    if (!class_exists('MongoDB\\Client')) {
        return null;
    }

    $dsn = getenv('MONGO_DSN') ?: 'mongodb://127.0.0.1:27017';
    $client = new MongoDB\Client($dsn);

    return $client;
}

function mongo_db(): ?MongoDB\Database
{
    $client = app_mongo();
    if ($client === null) {
        return null;
    }

    $dbName = getenv('MONGO_DB') ?: 'socialdonor';
    return $client->selectDatabase($dbName);
}

function mongo_collection(string $name): ?MongoDB\Collection
{
    $db = mongo_db();
    if ($db === null) {
        return null;
    }
    return $db->selectCollection($name);
}
