import zipfile
import io

def generate_xlsx(headers, rows, sheet_name="Laporan"):
    # Convert column index to Excel column name (0 -> A, 1 -> B, ..., 26 -> AA)
    def col_name(n):
        s = ""
        while n >= 0:
            s = chr(n % 26 + 65) + s
            n = n // 26 - 1
        return s

    content_types = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>"""

    rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>"""

    wb_rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""

    workbook = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{sheet_name}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>"""

    # Styles:
    # Font 0: regular, Font 1: bold header
    # Fill 0: none, Fill 1: gray125, Fill 2: light gray header (#F1F5F9)
    # Border 0: none, Border 1: thin border all sides
    # XF 0: normal, XF 1: header, XF 2: data text with border, XF 3: data number with border, XF 4: data date with border
    styles = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="10"/><color rgb="FF000000"/><name val="Calibri"/></font>
    <font><b/><sz val="10.5"/><color rgb="FF000000"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FF000000"/></left>
      <right style="thin"><color rgb="FF000000"/></right>
      <top style="thin"><color rgb="FF000000"/></top>
      <bottom style="thin"><color rgb="FF000000"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="5">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
    <xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>"""

    sheet_xml_parts = [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">',
        '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane state="frozen" ySplit="1" topLeftCell="A2"/></sheetView></sheetViews>',
        '  <sheetData>'
    ]

    # Row 1: Header
    r1 = ['    <row r="1">']
    for c_idx, h in enumerate(headers):
        ref = f"{col_name(c_idx)}1"
        escaped_h = (str(h) or "").replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")
        r1.append(f'<c r="{ref}" t="inlineStr" s="1"><is><t>{escaped_h}</t></is></c>')
    r1.append('    </row>')
    sheet_xml_parts.append("".join(r1))

    # Data rows
    for r_idx, row in enumerate(rows, start=2):
        row_str = [f'    <row r="{r_idx}">']
        for c_idx, val in enumerate(row):
            ref = f"{col_name(c_idx)}{r_idx}"
            s_val = str(val) if val is not None else ""
            escaped_val = s_val.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")
            
            # Numeric check (integers or decimals)
            if s_val.isdigit() and len(s_val) < 11 and not s_val.startswith('0'):
                row_str.append(f'<c r="{ref}" s="3"><v>{s_val}</v></c>')
            else:
                row_str.append(f'<c r="{ref}" t="inlineStr" s="2"><is><t>{escaped_val}</t></is></c>')
        row_str.append('    </row>')
        sheet_xml_parts.append("".join(row_str))

    sheet_xml_parts.append('  </sheetData>')
    sheet_xml_parts.append('</worksheet>')
    sheet_xml = "\n".join(sheet_xml_parts)

    out = io.BytesIO()
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', content_types)
        z.writestr('_rels/.rels', rels)
        z.writestr('xl/_rels/workbook.xml.rels', wb_rels)
        z.writestr('xl/workbook.xml', workbook)
        z.writestr('xl/styles.xml', styles)
        z.writestr('xl/worksheets/sheet1.xml', sheet_xml)

    return out.getvalue()

data = generate_xlsx(["NO. PO", "TANGGAL", "SUPPLIER", "QTY"], [["PO26110001", "03-11-2026", "PT. INTI SENTOSA", 1], ["PO26110002", "03-11-2026", "CV. SURYA", 4]])
with open("tools/sample.xlsx", "wb") as f:
    f.write(data)
print("sample.xlsx generated successfully, size:", len(data))

