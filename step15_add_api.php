<?php
$file = 'app/modules/corrective.php';
$content = file_get_contents($file);

$apiRoute = <<<EOT

function handle_route_api_corrective_data(PDO \$pdo): void
{
    require_login();
    header('Content-Type: application/json');
    \$action = \$_GET['action'] ?? '';

    if (\$action === 'get_types') {
        \$groupId = (int)(\$_GET['group_id'] ?? 0);
        \$stmt = \$pdo->prepare("SELECT id, type_code, type_name FROM asset_types WHERE asset_group_id = ? AND is_active=1 ORDER BY type_name");
        \$stmt->execute([\$groupId]);
        echo json_encode(\$stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (\$action === 'get_assets') {
        \$groupId = (int)(\$_GET['group_id'] ?? 0);
        \$typeId = (int)(\$_GET['type_id'] ?? 0);
        \$stmt = \$pdo->prepare("SELECT id, asset_code, asset_name, location_label, custodian_name, asset_mode FROM asset_items WHERE asset_group_id = ? AND asset_type_id = ? AND status <> 'inactive' ORDER BY (asset_mode = 'group') DESC, asset_code ASC");
        \$stmt->execute([\$groupId, \$typeId]);
        echo json_encode(\$stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (\$action === 'get_actions') {
        \$groupId = (int)(\$_GET['group_id'] ?? 0);
        \$typeId = (int)(\$_GET['type_id'] ?? 0);
        
        // Find Job Desk
        \$jdStmt = \$pdo->prepare("SELECT id, job_desk_name FROM corrective_job_desks WHERE asset_group_id = ? AND (asset_type_id = ? OR asset_type_id IS NULL OR asset_type_id = 0) ORDER BY CASE WHEN asset_type_id = ? THEN 0 ELSE 1 END, id ASC LIMIT 1");
        \$jdStmt->execute([\$groupId, \$typeId, \$typeId]);
        \$jdRow = \$jdStmt->fetch(PDO::FETCH_ASSOC);

        if (!\$jdRow) {
            echo json_encode(['desk_name' => null, 'actions' => []]);
            exit;
        }

        \$actStmt = \$pdo->prepare("SELECT id, action_title FROM corrective_action_types WHERE job_desk_id = ? ORDER BY action_title");
        \$actStmt->execute([\$jdRow['id']]);
        echo json_encode(['desk_name' => \$jdRow['job_desk_name'], 'actions' => \$actStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    echo json_encode([]);
    exit;
}
EOT;

$content .= $apiRoute;
file_put_contents($file, $content);
echo "Appended API Route.";
