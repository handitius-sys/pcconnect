<?php
require_once __DIR__ . '/../app/lib/bootstrap.php';

try {
    $pdo = Database::pdo();

    echo "--- TIKET CORRECTIVE ---\n";
    $tickets = $pdo->query("SELECT id, ticket_code, subject, status, created_at, asset_item_id, maintenance_asset_id FROM corrective_tickets")->fetchAll(PDO::FETCH_ASSOC);
    print_r($tickets);

    echo "\n--- CORRECTIVE REPAIRS ---\n";
    $repairs = $pdo->query("SELECT * FROM corrective_repairs")->fetchAll(PDO::FETCH_ASSOC);
    print_r($repairs);

    echo "\n--- CORRECTIVE TICKET ACTIONS ---\n";
    $actions = $pdo->query("SELECT * FROM corrective_ticket_actions")->fetchAll(PDO::FETCH_ASSOC);
    print_r($actions);

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
