# Update output_tsv to generate professional HTML-based Excel tables matching Microsoft Excel native view

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

target = """function output_tsv(array $headers, array $rows): void
{
    echo implode("\\t", array_map('excel_cell', $headers)) . "\\r\\n";
    foreach ($rows as $row) {
        echo implode("\\t", array_map('excel_cell', array_values($row))) . "\\r\\n";
    }
}

function excel_cell(mixed $value): string
{
    $text = (string)$value;
    $text = str_replace(["\\r\\n", "\\n", "\\r", "\\t"], ' ', $text);
    return $text;
}"""

replacement = """function output_tsv(array $headers, array $rows, string $title = 'Laporan'): void
{
    // Render Excel XML/HTML Workbook yang kompatibel penuh dengan MS Excel,
    // menampilkan header tebal dengan border jelas, gridlines, dan format data rapi
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\\r\\n";
    echo '<head>' . "\\r\\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . "\\r\\n";
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($title) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\\r\\n";
    echo '<style>' . "\\r\\n";
    echo '  table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }' . "\\r\\n";
    echo '  th { background-color: #f1f5f9; color: #000000; font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; text-transform: uppercase; border: 0.5pt solid #000000; padding: 6px 10px; text-align: left; vertical-align: middle; }' . "\\r\\n";
    echo '  td { color: #000000; font-family: Calibri, Arial, sans-serif; font-size: 10pt; border: 0.5pt solid #000000; padding: 5px 8px; vertical-align: middle; mso-number-format: "\\\\@"; }' . "\\r\\n";
    echo '  .num { text-align: right; mso-number-format: "#,##0"; }' . "\\r\\n";
    echo '  .date { text-align: center; mso-number-format: "yyyy-mm-dd"; }' . "\\r\\n";
    echo '  .center { text-align: center; }' . "\\r\\n";
    echo '</style>' . "\\r\\n";
    echo '</head>' . "\\r\\n";
    echo '<body>' . "\\r\\n";
    echo '<table border="1">' . "\\r\\n";
    echo '<thead><tr>' . "\\r\\n";
    foreach ($headers as $h) {
        echo '  <th>' . htmlspecialchars((string)$h) . '</th>' . "\\r\\n";
    }
    echo '</tr></thead>' . "\\r\\n";
    echo '<tbody>' . "\\r\\n";
    foreach ($rows as $row) {
        echo '<tr>' . "\\r\\n";
        foreach ($row as $val) {
            $strVal = (string)$val;
            $class = '';
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $strVal)) {
                $class = ' class="date"';
            } elseif (is_numeric($strVal) && strlen($strVal) > 0 && !str_starts_with($strVal, '0')) {
                $class = ' class="num"';
            }
            echo '  <td' . $class . '>' . htmlspecialchars($strVal) . '</td>' . "\\r\\n";
        }
        echo '</tr>' . "\\r\\n";
    }
    echo '</tbody>' . "\\r\\n";
    echo '</table>' . "\\r\\n";
    echo '</body></html>' . "\\r\\n";
}

function excel_cell(mixed $value): string
{
    $text = (string)$value;
    $text = str_replace(["\\r\\n", "\\n", "\\r", "\\t"], ' ', $text);
    return $text;
}"""

assert target in c, "Target output_tsv not found in maintenance.php"
c = c.replace(target, replacement, 1)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Updated output_tsv in maintenance.php successfully")

