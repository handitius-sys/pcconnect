import sys

# Test building standalone print page HTML
print_html = """<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak Laporan</title>
<style>
  @page { size: A4 landscape; margin: 10mm 10mm 10mm 10mm; }
  body { margin: 0; padding: 15px; font-family: Segoe UI, Arial, sans-serif; background: #fff; color: #000; }
  .report-print-header { text-align: center; margin-bottom: 20px; }
  .report-print-header .brand-name { font-size: 26pt; font-weight: 900; line-height: 1.1; margin-bottom: 4px; }
  .report-print-header .company-name { font-size: 13pt; font-weight: 800; margin-bottom: 3px; }
  .report-print-header .company-address { font-size: 9pt; color: #444; margin-bottom: 12px; }
  .report-print-header .doc-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
  .report-print-header .doc-period { font-size: 10pt; font-weight: 600; color: #333; }
  
  table.doc-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; table-layout: auto; }
  table.doc-table th { background-color: #a49fc4 !important; color: #000 !important; font-weight: bold; border: 1pt solid #000; padding: 6px 8px; text-align: center; }
  table.doc-table td { border: 0.5pt solid #000; padding: 5px 7px; vertical-align: top; }
  tr { page-break-inside: avoid; }
  thead { display: table-header-group; }
  
  .print-controls { margin-bottom: 16px; display: flex; justify-content: flex-end; gap: 8px; }
  @media print { .print-controls { display: none !important; } }
</style>
</head>
<body>
  <div class="print-controls">
    <button onclick="window.print()" style="padding: 8px 16px; font-weight: bold; background: #1457d9; color: #fff; border: none; border-radius: 6px; cursor: pointer;">PRINT</button>
  </div>
</body>
</html>
"""
print("Standalone print template drafted successfully")

