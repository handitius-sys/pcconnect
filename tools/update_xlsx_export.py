# Script to update output_tsv and export_excel in app/modules/maintenance.php
# to output real .xlsx files via ZipArchive

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

# 1. Update headers in export_excel
old_header = """    $filename = 'pcconnect-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '-' . date('Ymd-His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo "\\xEF\\xBB\\xBF";"""

new_header = """    $filename = 'pcconnect-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '-' . date('Ymd-His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');"""

assert old_header in c, "old_header not found in maintenance.php"
c = c.replace(old_header, new_header, 1)

# 2. Replace output_tsv with real XLSX writer
idx_out_start = c.find('function output_tsv')
idx_out_end = c.find('function handle_route_reports', idx_out_start)

new_output_func = """function output_tsv(array $headers, array $rows, string $title = 'Laporan'): void
{
    // Generate native OpenXML .xlsx file using PHP ZipArchive
    if (class_exists('ZipArchive')) {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\\r\\n"
                . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\\r\\n"
                . '  <Default Extension="xml" ContentType="application/xml"/>' . "\\r\\n"
                . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\\r\\n"
                . '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\\r\\n"
                . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\\r\\n"
                . '</Types>';

            $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\\r\\n"
                . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\\r\\n"
                . '</Relationships>';

            $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\\r\\n"
                . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\\r\\n"
                . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\\r\\n"
                . '</Relationships>';

            $cleanTitle = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title);
            $cleanTitle = mb_substr($cleanTitle ?: 'Sheet1', 0, 31);
            $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\\r\\n"
                . '  <sheets>' . "\\r\\n"
                . '    <sheet name="' . htmlspecialchars($cleanTitle, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>' . "\\r\\n"
                . '  </sheets>' . "\\r\\n"
                . '</workbook>';

            $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\\r\\n"
                . '  <fonts count="2">' . "\\r\\n"
                . '    <font><sz val="10"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\\r\\n"
                . '    <font><b/><sz val="10.5"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\\r\\n"
                . '  </fonts>' . "\\r\\n"
                . '  <fills count="3">' . "\\r\\n"
                . '    <fill><patternFill patternType="none"/></fill>' . "\\r\\n"
                . '    <fill><patternFill patternType="gray125"/></fill>' . "\\r\\n"
                . '    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>' . "\\r\\n"
                . '  </fills>' . "\\r\\n"
                . '  <borders count="2">' . "\\r\\n"
                . '    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\\r\\n"
                . '    <border>' . "\\r\\n"
                . '      <left style="thin"><color rgb="FF000000"/></left>' . "\\r\\n"
                . '      <right style="thin"><color rgb="FF000000"/></right>' . "\\r\\n"
                . '      <top style="thin"><color rgb="FF000000"/></top>' . "\\r\\n"
                . '      <bottom style="thin"><color rgb="FF000000"/></bottom>' . "\\r\\n"
                . '    </border>' . "\\r\\n"
                . '  </borders>' . "\\r\\n"
                . '  <cellStyleXfs count="1">' . "\\r\\n"
                . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\\r\\n"
                . '  </cellStyleXfs>' . "\\r\\n"
                . '  <cellXfs count="5">' . "\\r\\n"
                . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\\r\\n"
                . '    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . "\\r\\n"
                . '    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . "\\r\\n"
                . '    <xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\\r\\n"
                . '    <xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\\r\\n"
                . '  </cellXfs>' . "\\r\\n"
                . '</styleSheet>';

            $colLetter = function(int $n): string {
                $s = '';
                while ($n >= 0) {
                    $s = chr(($n % 26) + 65) . $s;
                    $n = intdiv($n, 26) - 1;
                }
                return $s;
            };

            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\\r\\n"
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\\r\\n"
                . '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane state="frozen" ySplit="1" topLeftCell="A2"/></sheetView></sheetViews>' . "\\r\\n"
                . '  <sheetData>' . "\\r\\n";

            // Row 1: Headers
            $sheetXml .= '    <row r="1">' . "\\r\\n";
            foreach ($headers as $cIdx => $h) {
                $ref = $colLetter($cIdx) . '1';
                $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars((string)$h, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\\r\\n";
            }
            $sheetXml .= '    </row>' . "\\r\\n";

            // Data rows
            $rNum = 2;
            foreach ($rows as $row) {
                $sheetXml .= '    <row r="' . $rNum . '">' . "\\r\\n";
                $cIdx = 0;
                foreach ($row as $val) {
                    $ref = $colLetter($cIdx) . $rNum;
                    $strVal = (string)$val;
                    if ($strVal !== '' && is_numeric($strVal) && strlen($strVal) < 11 && !str_starts_with($strVal, '0')) {
                        $sheetXml .= '      <c r="' . $ref . '" s="3"><v>' . $strVal . '</v></c>' . "\\r\\n";
                    } elseif (preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $strVal)) {
                        $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="4"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\\r\\n";
                    } else {
                        $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="2"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\\r\\n";
                    }
                    $cIdx++;
                }
                $sheetXml .= '    </row>' . "\\r\\n";
                $rNum++;
            }

            $sheetXml .= '  </sheetData>' . "\\r\\n";
            $sheetXml .= '</worksheet>';

            $zip->addFromString('[Content_Types].xml', $contentTypes);
            $zip->addFromString('_rels/.rels', $rels);
            $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
            $zip->addFromString('xl/workbook.xml', $workbook);
            $zip->addFromString('xl/styles.xml', $styles);
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->close();

            readfile($tempFile);
            @unlink($tempFile);
            return;
        }
    }

    // Fallback: TSV
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
}

"""

c = c[:idx_out_start] + new_output_func + c[idx_out_end:]

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Updated maintenance.php for XLSX export successfully")

