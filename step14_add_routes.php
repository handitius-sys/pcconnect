<?php
$file = 'app/modules/corrective.php';
$content = file_get_contents($file);

$newRoutes = <<<EOT

/**
 * Modul: Bengkel / Workshop Master
 * Proses: Menampilkan daftar bengkel/workshop.
 */
function handle_route_corrective_workshops(PDO \$pdo): void
{
    \$user = require_regulation('corrective_admin', 'view');
    ensure_corrective_maintenance_schema(\$pdo);

    \$stmt = \$pdo->query("SELECT * FROM corrective_workshops ORDER BY workshop_name ASC");
    \$rows = \$stmt->fetchAll();

    render_header('Master Bengkel / Workshop', \$user);
    echo '<section class="panel">';
    echo '<div class="split"><h1>Master Bengkel / Workshop</h1>';
    if (has_regulation('corrective_admin', 'create', \$user)) {
        echo '<a class="btn primary" href="' . route_url('corrective_workshop_form') . '">dY"  Tambah Bengkel Baru</a>';
    }
    echo '</div>';
    echo '<table><tr><th>Kode</th><th>Nama Bengkel</th><th>Aksi</th></tr>';
    foreach (\$rows as \$r) {
        echo '<tr><td>' . e(\$r['workshop_code']) . '</td><td>' . e(\$r['workshop_name']) . '</td><td>';
        if (has_regulation('corrective_admin', 'update', \$user)) {
            echo '<a class="btn" href="' . route_url('corrective_workshop_form', ['id' => \$r['id']]) . '">dY"  Edit</a>';
        }
        echo '</td></tr>';
    }
    echo '</table></section>';
    render_footer();
}

/**
 * Modul: Bengkel / Workshop Form
 * Proses: Tambah / Edit bengkel.
 */
function handle_route_corrective_workshop_form(PDO \$pdo): void
{
    ensure_corrective_maintenance_schema(\$pdo);
    \$id = (int)(\$_GET['id'] ?? 0);
    if (\$id > 0) {
        \$user = require_regulation('corrective_admin', 'update');
    } else {
        \$user = require_regulation('corrective_admin', 'create');
    }

    \$row = ['workshop_name' => ''];
    if (\$id > 0) {
        \$stmt = \$pdo->prepare("SELECT * FROM corrective_workshops WHERE id = ?");
        \$stmt->execute([\$id]);
        \$row = \$stmt->fetch() ?: \$row;
    }

    if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        \$name = trim((string)(\$_POST['workshop_name'] ?? ''));
        if (\$name === '') {
            flash('Nama bengkel harus diisi.', 'err');
        } else {
            if (\$id > 0) {
                \$pdo->prepare("UPDATE corrective_workshops SET workshop_name = ? WHERE id = ?")->execute([\$name, \$id]);
                flash("Bengkel berhasil diupdate.");
                redirect_to('corrective_workshops');
            } else {
                // Auto generate code WKS-001
                \$cStmt = \$pdo->query("SELECT MAX(CAST(SUBSTRING(workshop_code, 5) AS UNSIGNED)) FROM corrective_workshops");
                \$max = (int)\$cStmt->fetchColumn();
                \$code = 'WKS-' . str_pad((string)(\$max + 1), 3, '0', STR_PAD_LEFT);
                \$pdo->prepare("INSERT INTO corrective_workshops (workshop_code, workshop_name) VALUES (?, ?)")->execute([\$code, \$name]);
                flash("Bengkel {\$code} berhasil ditambahkan.");
                redirect_to('corrective_workshops');
            }
        }
    }

    render_header(\$id > 0 ? 'Edit Bengkel' : 'Tambah Bengkel', \$user);
    echo '<section class="panel"><h1>' . (\$id > 0 ? 'Edit' : 'Tambah') . ' Bengkel</h1>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<label>Nama Bengkel<input name="workshop_name" required value="' . e(\$row['workshop_name']) . '"></label>';
    echo '<div style="margin-top:16px;"><button class="btn primary">Simpan</button> ';
    echo '<a class="btn" href="' . route_url('corrective_workshops') . '">Batal</a></div>';
    echo '</form></section>';
    render_footer();
}
EOT;

$content .= $newRoutes;

// And let's add the subnav to the correct place. I'll find where `<a href="' . route_url('corrective_job_desks') . '">` is printed.
$find = "<a href=\"' . route_url('corrective_job_desks') . '\">Master Job Desk</a>";
$replace = "<a href=\"' . route_url('corrective_job_desks') . '\">Master Job Desk</a><a href=\"' . route_url('corrective_workshops') . '\">Master Bengkel</a>";
$content = str_replace($find, $replace, $content);

file_put_contents($file, $content);
echo "Added workshop routes at bottom.";
