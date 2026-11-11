<?php
// Test PHP ZipArchive
function build_xlsx_test(array $headers, array $rows, string $sheetTitle = 'Laporan'): string {
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Cannot create zip");
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n"
        . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n"
        . '  <Default Extension="xml" ContentType="application/xml"/>' . "\n"
        . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n"
        . '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n"
        . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n"
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
        . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n"
        . '</Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
        . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n"
        . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n"
        . '</Relationships>';

    $cleanSheetTitle = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $sheetTitle);
    $cleanSheetTitle = mb_substr($cleanSheetTitle ?: 'Sheet1', 0, 31);
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n"
        . '  <sheets>' . "\n"
        . '    <sheet name="' . htmlspecialchars($cleanSheetTitle, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>' . "\n"
        . '  </sheets>' . "\n"
        . '</workbook>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
        . '  <fonts count="2">' . "\n"
        . '    <font><sz val="10"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\n"
        . '    <font><b/><sz val="10.5"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\n"
        . '  </fonts>' . "\n"
        . '  <fills count="3">' . "\n"
        . '    <fill><patternFill patternType="none"/></fill>' . "\n"
        . '    <fill><patternFill patternType="gray125"/></fill>' . "\n"
        . '    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>' . "\n"
        . '  </fills>' . "\n"
        . '  <borders count="2">' . "\n"
        . '    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\n"
        . '    <border>' . "\n"
        . '      <left style="thin"><color rgb="FF000000"/></left>' . "\n"
        . '      <right style="thin"><color rgb="FF000000"/></right>' . "\n"
        . '      <top style="thin"><color rgb="FF000000"/></top>' . "\n"
        . '      <bottom style="thin"><color rgb="FF000000"/></bottom>' . "\n"
        . '    </border>' . "\n"
        . '  </borders>' . "\n"
        . '  <cellStyleXfs count="1">' . "\n"
        . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\n"
        . '  </cellStyleXfs>' . "\n"
        . '  <cellXfs count="5">' . "\n"
        . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n"
        . '    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . "\n"
        . '    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . "\n"
        . '    <xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n"
        . '    <xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n"
        . '  </cellXfs>' . "\n"
        . '</styleSheet>';

    $colLetter = function(int $n): string {
        $s = '';
        while ($n >= 0) {
            $s = chr(($n % 26) + 65) . $s;
            $n = intdiv($n, 26) - 1;
        }
        return $s;
    };

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
        . '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane state="frozen" ySplit="1" topLeftCell="A2"/></sheetView></sheetViews>' . "\n"
        . '  <sheetData>' . "\n";

    // Row 1: Headers
    $sheetXml .= '    <row r="1">' . "\n";
    foreach ($headers as $cIdx => $h) {
        $ref = $colLetter($cIdx) . '1';
        $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars((string)$h, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\n";
    }
    $sheetXml .= '    </row>' . "\n";

    // Data rows
    $rNum = 2;
    foreach ($rows as $row) {
        $sheetXml .= '    <row r="' . $rNum . '">' . "\n";
        $cIdx = 0;
        foreach ($row as $val) {
            $ref = $colLetter($cIdx) . $rNum;
            $strVal = (string)$val;
            if ($strVal !== '' && is_numeric($strVal) && strlen($strVal) < 11 && !str_starts_with($strVal, '0')) {
                $sheetXml .= '      <c r="' . $ref . '" s="3"><v>' . $strVal . '</v></c>' . "\n";
            } else {
                $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="2"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\n";
            }
            $cIdx++;
        }
        $sheetXml .= '    </row>' . "\n";
        $rNum++;
    }

    $sheetXml .= '  </sheetData>' . "\n";
    $sheetXml .= '</worksheet>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $content = file_get_contents($tempFile);
    unlink($tempFile);
    return $content;
}

$res = build_xlsx_test(['NO', 'NAMA'], [[1, 'A'], [2, 'B']]);
echo "Success! Length: " . strlen($res) . "\n";

