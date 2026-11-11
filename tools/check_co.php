require 'app/lib/bootstrap.php';
$pdo = Database::pdo();
$cos = $pdo->query('SELECT * FROM asset_companies LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($cos);

