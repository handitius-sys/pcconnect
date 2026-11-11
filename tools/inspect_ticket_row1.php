<?php
require_once __DIR__ . '/../app/lib/bootstrap.php';
$pdo = Database::pdo();
$row = $pdo->query("SELECT * FROM corrective_tickets WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
print_r($row);
