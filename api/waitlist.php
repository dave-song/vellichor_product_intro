<?php
/**
 * Waitlist signup API – MySQL with PDO prepared statements (SQL injection safe).
 * Expects POST: name (optional), email (required), phone (optional).
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Load config (use sample if config.php not present, for local testing)
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/config.sample.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration missing. Copy api/config.sample.php to api/config.php and set your MySQL credentials.']);
    exit;
}

$config = require $configFile;
$table = $config['waitlist_table'] ?? 'waitlist';
// Whitelist table name (alphanumeric + underscore only) to avoid injection via config
$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: 'waitlist';

// Input: normalize and validate (no raw input in SQL – prepared statements only)
$name  = isset($_POST['name'])  ? trim((string) $_POST['name'])  : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim((string) $_POST['phone']) : '';

// Length limits (enforce even before DB to avoid truncation issues)
$name  = mb_substr($name, 0, 120);
$email = mb_substr($email, 0, 255);
$phone = mb_substr($phone, 0, 30);

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['dbname'],
        $config['db']['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Use prepared statement – all user input is bound, never concatenated (SQL injection safe)
    $sql = "INSERT INTO `{$table}` (name, email, phone, created_at) VALUES (:name, :email, :phone, :created_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'       => $name,
        ':email'      => $email,
        ':phone'      => $phone === '' ? null : $phone,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    echo json_encode([
        'success' => true,
        'message' => "You're on the list! We'll be in touch.",
    ]);
} catch (PDOException $e) {
    // Duplicate email or other DB constraint – user-friendly message; log $e for debugging
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate') !== false || (int) $e->getCode() === 23000) {
        echo json_encode(['success' => false, 'message' => 'This email is already on the waitlist.']);
    } else {
    http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
