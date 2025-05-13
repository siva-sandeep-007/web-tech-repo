<?php
session_start();

// Define file paths
define('USERS_FILE', 'data/users.json');
define('CRIMES_FILE', 'data/crimes.json');

// Create data directory if it doesn't exist
if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

// Initialize JSON files if they don't exist
function initializeJsonFiles() {
    if (!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, json_encode([
            'users' => [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'role' => 'admin'
                ],
                [
                    'id' => 2,
                    'username' => 'police',
                    'password' => password_hash('police123', PASSWORD_DEFAULT),
                    'role' => 'police'
                ],
                [
                    'id' => 3,
                    'username' => 'citizen',
                    'password' => password_hash('citizen123', PASSWORD_DEFAULT),
                    'role' => 'user'
                ]
            ]
        ]));
    }

    if (!file_exists(CRIMES_FILE)) {
        file_put_contents(CRIMES_FILE, json_encode(['crimes' => []]));
    }
}

// Read JSON file
function readJsonFile($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true);
}

// Write to JSON file
function writeJsonFile($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function checkRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $requiredRole;
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Initialize JSON files
initializeJsonFiles();
?> 