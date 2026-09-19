<?php
declare(strict_types=1);

$host = '127.0.0.1';
$user = 'root';
$pass = ''; // Set your MySQL password here if you have one

try {
    // 1. Connect to MySQL server
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `MediCareMini` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✔ Database 'MediCareMini' created successfully.\n";

    $pdo->exec("USE `MediCareMini`");

    // 3. Import Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $pdo->exec(file_get_contents($schemaFile));
        echo "✔ Schema imported successfully.\n";
    }

    // 4. Import Seed Data
    $seedFile = __DIR__ . '/seed.sql';
    if (file_exists($seedFile)) {
        $pdo->exec(file_get_contents($seedFile));
        echo "✔ Seed data imported successfully.\n";
    }

    echo "\nDatabase setup complete! Next run: php bin/install.php\n";

} catch (PDOException $e) {
    die("❌ Setup Failed: " . $e->getMessage() . "\n");
}