<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/easymed.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->exec('DROP TABLE IF EXISTS lab_offer_doctors');
    $db->exec('DROP TABLE IF EXISTS lab_offers');
    $db->exec('PRAGMA foreign_keys = ON');
    echo "Dropped lab_offer tables if existed.\n";
} catch (Exception $e) {
    echo "Error dropping tables: " . $e->getMessage() . "\n";
}
