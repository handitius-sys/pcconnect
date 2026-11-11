<?php
require_once __DIR__ . '/../app/lib/bootstrap.php';
$pdo = Database::pdo();
$rows = $pdo->query("SELECT id, ticket_code, subject FROM corrective_tickets")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " | CODE: " . $r['ticket_code'] . "\n";
    echo "SUBJECT: " . $r['subject'] . "\n\n";
}
