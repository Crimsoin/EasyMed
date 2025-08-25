<?php
// Migration: create lab_offers and lab_offer_doctors tables for SQLite
try {
    $dbPath = __DIR__ . '/../../database/easymed.sqlite';
    if (!file_exists(dirname($dbPath))) {
        mkdir(dirname($dbPath), 0777, true);
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $sql1 = "CREATE TABLE IF NOT EXISTS lab_offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT
    );";

    $sql2 = "CREATE TABLE IF NOT EXISTS lab_offer_doctors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lab_offer_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(lab_offer_id) REFERENCES lab_offers(id) ON DELETE CASCADE,
        FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    );";

    $pdo->exec($sql1);
    $pdo->exec($sql2);

    echo "Migration completed: lab_offers and lab_offer_doctors created or already exist.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
