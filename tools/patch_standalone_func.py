# Update app/lib/bootstrap.php with render_report_print_standalone_page

with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

target = "function render_report_document_header(string $reportTitle, ?string $periodSubtitle = null, ?PDO $pdo = null): void"

standalone_code = """function render_report_print_standalone_page(string $docTitle, string $periodSubtitle, array $headers, array $rows, ?PDO $pdo = null): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    $appName = app_name();
    $companyName = 'PT HASHMICRO SOLUSI INDONESIA';
    $companyAddress = 'Jalan Balikpapan Raya No.9A 9C, RT.10/RW.6, South Petojo, Gambir, Central Jakarta City, Jakarta 10158';

    if ($pdo) {
        try {
            if (function_exists('db_table_exists') && db_table_exists($pdo, 'asset_companies')) {
                $row = $pdo->query("SELECT company_name, legal_name, address FROM asset_companies WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (!empty($row['legal_name'])) {
                        $companyName = $row['legal_name'];
                    } elseif (!empty($row['company_name'])) {
                        $companyName = $row['company_name'];
                    }
                    if (!empty($row['address'])) {
                        $companyAddress = $row['address'];
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    echo '<!doctype html>' . "\\r\\n";
    echo '<html lang="id">' . "\\r\\n";
    echo '<head>' . "\\r\\n";
    echo '<meta charset="utf-8">' . "\\r\\n";
    echo '<title>' . htmlspecialchars($docTitle) . '</title>' . "\\r\\n";
    echo '<style>' . "\\r\\n";
    echo '@page { size: A4 landscape; margin: 8mm 8mm 8mm 8mm; }' . "\\r\\n";
    echo 'body { margin: 0; padding: 12px 18px; font-family: Segoe UI, -apple-system, BlinkMacSystemFont, Arial, sans-serif; background: #fff; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' . "\\r\\n";
    echo '.doc-header-box { text-align: center; margin-bottom: 18px; }' . "\\r\\n";
    echo '.doc-header-box .doc-brand { font-size: 26pt; font-weight: 900; line-height: 1.1; margin-bottom: 4px; color: #0f172a; letter-spacing: -0.5px; }' . "\\r\\n";
    echo '.doc-header-box .doc-company { font-size: 13pt; font-weight: 800; color: #0f172a; margin-bottom: 3px; }' . "\\r\\n";
    echo '.doc-header-box .doc-address { font-size: 9pt; color: #475569; margin-bottom: 14px; }' . "\\r\\n";
    echo '.doc-header-box .doc-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; color: #0f172a; margin-bottom: 4px; }' . "\\r\\n";
    echo '.doc-header-box .doc-period { font-size: 10pt; font-weight: 600; color: #334155; }' . "\\r\\n";
    echo 'table.doc-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; table-layout: auto; border: 1pt solid #000; margin-bottom: 14px; }' . "\\r\\n";
    echo 'table.doc-table th { background-color: #a49fc4 !important; color: #000 !important; font-weight: bold; border: 0.75pt solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' . "\\r\\n";
    echo 'table.doc-table td { border: 0.5pt solid #000; padding: 5px 7px; vertical-align: top; color: #000; background: #fff; word-break: break-word; }' . "\\r\\n";
    echo 'tr { page-break-inside: avoid; }' . "\\r\\n";
    echo 'thead { display: table-header-group; }' . "\\r\\n";
    echo '.print-bar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 16px; align-items: center; }' . "\\r\\n";
    echo '.print-bar button { background: #1457d9; color: #fff; border: 1px solid #1457d9; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; }' . "\\r\\n";
    echo '.print-bar button:hover { background: #0f46b3; }' . "\\r\\n";
    echo '@media print { .print-bar { display: none !important; } body { padding: 0 !important; } }' . "\\r\\n";
    echo '</style>' . "\\r\\n";
    echo '</head>' . "\\r\\n";
    echo '<body>' . "\\r\\n";
    echo '<div class="print-bar">' . "\\r\\n";
    echo '  <button onclick="window.print()">PRINT</button>' . "\\r\\n";
    echo '</div>' . "\\r\\n";
    echo '<div class="doc-header-box">' . "\\r\\n";
    echo '  <div class="doc-brand">' . htmlspecialchars($appName) . '</div>' . "\\r\\n";
    echo '  <div class="doc-company">' . htmlspecialchars($companyName) . '</div>' . "\\r\\n";
    echo '  <div class="doc-address">' . htmlspecialchars($companyAddress) . '</div>' . "\\r\\n";
    echo '  <div class="doc-title">' . htmlspecialchars($docTitle) . '</div>' . "\\r\\n";
    echo '  <div class="doc-period">' . htmlspecialchars($periodSubtitle) . '</div>' . "\\r\\n";
    echo '</div>' . "\\r\\n";
    echo '<table class="doc-table">' . "\\r\\n";
    echo '<thead><tr>' . "\\r\\n";
    foreach ($headers as $h) {
        echo '  <th>' . htmlspecialchars((string)$h) . '</th>' . "\\r\\n";
    }
    echo '</tr></thead>' . "\\r\\n";
    echo '<tbody>' . "\\r\\n";
    if (empty($rows)) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;padding:20px;color:#666;">Tidak ada data laporan.</td></tr>' . "\\r\\n";
    } else {
        foreach ($rows as $row) {
            echo '<tr>' . "\\r\\n";
            foreach ($row as $td) {
                echo '  <td>' . htmlspecialchars((string)$td) . '</td>' . "\\r\\n";
            }
            echo '</tr>' . "\\r\\n";
        }
    }
    echo '</tbody>' . "\\r\\n";
    echo '</table>' . "\\r\\n";
    echo '<script>' . "\\r\\n";
    echo 'window.onload = function() { setTimeout(function() { window.print(); }, 250); };' . "\\r\\n";
    echo '</script>' . "\\r\\n";
    echo '</body></html>';
    exit;
}

"""

assert target in c, "target function render_report_document_header not found in bootstrap.php"
c = c.replace(target, standalone_code + target, 1)

with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Added render_report_print_standalone_page to bootstrap.php successfully")

