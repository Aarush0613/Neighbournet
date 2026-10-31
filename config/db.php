<?php
/**
 * NeighbourNet - Database Configuration
 * Reusable PDO connection with secure prepared statements
 */

// Database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'if0_43319061');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_43319061_neighbournet_db');
define('DB_USER', getenv('DB_USER') ?: 'sql302.infinityfree.com');
define('DB_PASS', getenv('DB_PASS') ?: 'wearepro234');

function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In API context or web page, handle connection failure gracefully
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Please ensure MySQL is running in XAMPP.',
                ]);
                exit;
            } else {
                die("<h3>Database Connection Error</h3><p>Could not connect to the database. Please make sure MySQL is started in XAMPP and <code>neighbournet_db</code> is imported.</p>");
            }
        }
    }

    return $pdo;
}
