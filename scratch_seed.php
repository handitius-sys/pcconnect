<?php
require_once __DIR__ . '/app/lib/bootstrap.php';
require_once __DIR__ . '/app/lib/schema.php';

try {
    $pdo = Database::pdo();
    echo "Connected.\n";
    ensure_corrective_maintenance_schema($pdo);
    echo "Seeding completed.\n";

    $stmt = $pdo->query("SELECT job_desk_name, COUNT(*) as cnt FROM corrective_action_types GROUP BY job_desk_name");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

