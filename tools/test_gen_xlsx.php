<?php
$title = 'Report Unit Aset';
$cleanTitle = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $title);
$cleanTitle = mb_substr(trim($cleanTitle) ?: 'Sheet1', 0, 31);

$tempFile = __DIR__ . '/test_gen.xlsx';
if (file_exists($tempFile)) unlink($tempFile);

$zip = new ZipArchive();
if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\r\n"
        . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\r\n"
        . '  <Default Extension="xml" ContentType="application/xml"/>' . "\r\n"
        . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\r\n"
        . '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\r\n"
        . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\r\n"
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\r\n"
        . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\r\n"
        . '</Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\r\n"
        . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\r\n"
        . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\r\n"
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\r\n"
        . '  <sheets>' . "\r\n"
        . '    <sheet name="' . htmlspecialchars($cleanTitle, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>' . "\r\n"
        . '  </sheets>' . "\r\n"
        . '</workbook>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\r\n"
        . '  <fonts count="2">' . "\r\n"
        . '    <font><sz val="10"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\r\n"
        . '    <font><b/><sz val="10.5"/><color rgb="FF000000"/><name val="Calibri"/></font>' . "\r\n"
        . '  </fonts>' . "\r\n"
        . '  <fills count="3">' . "\r\n"
        . '    <fill><patternFill patternType="none"/></fill>' . "\r\n"
        . '    <fill><patternFill patternType="gray125"/></fill>' . "\r\n"
        . '    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>' . "\r\n"
        . '  </fills>' . "\r\n"
        . '  <borders count="2">' . "\r\n"
        . '    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\r\n"
        . '    <border>' . "\r\n"
        . '      <left style="thin"><color rgb="FF000000"/></left>' . "\r\n"
        . '      <right style="thin"><color rgb="FF000000"/></right>' . "\r\n"
        . '      <top style="thin"><color rgb="FF000000"/></top>' . "\r\n"
        . '      <bottom style="thin"><color rgb="FF000000"/></bottom>' . "\r\n"
        . '    </border>' . "\r\n"
        . '  </borders>' . "\r\n"
        . '  <cellStyleXfs count="1">' . "\r\n"
        . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\r\n"
        . '  </cellStyleXfs>' . "\r\n"
        . '  <cellXfs count="5">' . "\r\n"
        . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\r\n"
        . '    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . "\r\n"
        . '    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . "\r\n"
        . '    <xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\r\n"
        . '    <xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\r\n"
        . '  </cellXfs>' . "\r\n"
        . '</styleSheet>';

    $colLetter = function(int $n): string {
        $s = '';
        while ($n >= 0) {
            $s = chr(($n % 26) + 65) . $s;
            $n = intdiv($n, 26) - 1;
        }
        return $s;
    };

    $headers = ['Kode Unit', 'Kode Lama', 'Nama Barang', 'Harga'];
    $rows = [
        ['FC-LD-000001', '25/21-2-2023', 'FILLING KABINET LION', '1500000'],
        ['FC-LD-000002', '61/21-2-2023', 'FILLING KABINET LION', '2500000'],
    ];

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\r\n"
        . '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane state="frozen" ySplit="1" topLeftCell="A2"/></sheetView></sheetViews>' . "\r\n"
        . '  <sheetData>' . "\r\n";

    // Row 1: Headers
    $sheetXml .= '    <row r="1">' . "\r\n";
    foreach ($headers as $cIdx => $h) {
        $ref = $colLetter($cIdx) . '1';
        $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars((string)$h, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\r\n";
    }
    $sheetXml .= '    </row>' . "\r\n";

    $rNum = 2;
    foreach ($rows as $row) {
        $sheetXml .= '    <row r="' . $rNum . '">' . "\r\n";
        $cIdx = 0;
        foreach ($row as $val) {
            $ref = $colLetter($cIdx) . $rNum;
            $strVal = (string)$val;
            if ($strVal !== '' && is_numeric($strVal) && strlen($strVal) < 11 && !str_starts_with($strVal, '0')) {
                $sheetXml .= '      <c r="' . $ref . '" s="3"><v>' . $strVal . '</v></c>' . "\r\n";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $strVal)) {
                $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="4"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\r\n";
            } else {
                $sheetXml .= '      <c r="' . $ref . '" t="inlineStr" s="2"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>' . "\r\n";
            }
            $cIdx++;
        }
        $sheetXml .= '    </row>' . "\r\n";
        $rNum++;
    }

    $sheetXml .= '  </sheetData>' . "\r\n";
    $sheetXml .= '</worksheet>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
    echo "FILE WRITTEN: $tempFile, size: " . filesize($tempFile) . "\n";
}
