# Function to add to app/lib/bootstrap.php and update print CSS

with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

# 1. Update print CSS:
# - Header table styling with lavender/purple background (#b4a7d6 or #998ec3 or #a49fc4)
# - Report document header styling: .print-doc-header (only visible in print, or clean on screen)
# - Force exact background colors in print

target_css = """.report-table-plain{width:100%;border-collapse:collapse;background:#fff!important;border:1px solid #cbd5e1;}
.report-table-plain th{background:#f8fafc!important;color:#0f172a!important;font-weight:700!important;border:1px solid #cbd5e1!important;padding:7px 9px!important;white-space:nowrap;}
.report-table-plain td{background:#fff!important;color:#1e293b!important;border:1px solid #cbd5e1!important;padding:6px 9px!important;font-weight:normal;}
.report-table-plain tr:hover td{background:#f8fafc!important;}
.report-table-plain .badge,.report-table-plain span,.report-table-plain code,.report-table-plain strong,.report-table-plain a{background:transparent!important;border:none!important;box-shadow:none!important;color:inherit!important;padding:0!important;font-size:inherit!important;font-weight:inherit!important;text-decoration:none!important;}
@media print{
  html,body{width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important;color:#000!important;font-size:8pt!important;overflow:visible!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .sidebar,.topbar,.no-print,.actions,.btn,.flash,form,.font-resizer,.collapse-btn,.print-settings-modal,.sidebar-overlay,.sidebar-resizer{display:none!important;}
  .app-layout{display:block!important;width:100%!important;min-height:0!important;}
  .main-wrapper{margin:0!important;padding:0!important;width:100%!important;min-width:0!important;max-width:100%!important;overflow:visible!important;}
  main{display:block!important;max-width:100%!important;padding:0!important;margin:0!important;overflow:visible!important;}
  .panel{border:none!important;box-shadow:none!important;padding:0!important;margin:0 0 14px 0!important;background:transparent!important;overflow:visible!important;}
  div[style*="overflow"]{overflow:visible!important;max-height:none!important;height:auto!important;}
  .report-table-plain{width:100%!important;font-size:7.5pt!important;table-layout:auto!important;border-collapse:collapse!important;margin-bottom:12px!important;}
  .report-table-plain th,.report-table-plain td{padding:3px 5px!important;border:0.5pt solid #333!important;color:#000!important;background:#fff!important;word-break:break-word!important;}
  .report-table-plain th{background:#e2e8f0!important;font-weight:bold!important;}
  h1,h2,h3,p{color:#000!important;margin:0 0 6px 0!important;page-break-after:avoid;}
  tr{page-break-inside:avoid;}
  table{page-break-inside:auto;}
  thead{display:table-header-group;}
}"""

replacement_css = """.report-table-plain{width:100%;border-collapse:collapse;background:#fff!important;border:1px solid #1e293b;}
.report-table-plain th{background:#a49fc4!important;color:#0f172a!important;font-weight:700!important;border:1px solid #334155!important;padding:7px 9px!important;white-space:nowrap;font-size:12px;text-align:center;}
.report-table-plain td{background:#fff!important;color:#1e293b!important;border:1px solid #334155!important;padding:6px 9px!important;font-weight:normal;font-size:11.5px;}
.report-table-plain tr:hover td{background:#f8fafc!important;}
.report-table-plain .badge,.report-table-plain span,.report-table-plain code,.report-table-plain strong,.report-table-plain a{background:transparent!important;border:none!important;box-shadow:none!important;color:inherit!important;padding:0!important;font-size:inherit!important;font-weight:inherit!important;text-decoration:none!important;}

.print-doc-header{display:none;}

@media print{
  @page{size:A4 landscape;margin:8mm 8mm 8mm 8mm;}
  html,body{width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important;color:#000!important;font-size:8.5pt!important;overflow:visible!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
  .sidebar,.topbar,.no-print,.actions,.btn,.flash,form,.font-resizer,.collapse-btn,.print-settings-modal,.sidebar-overlay,.sidebar-resizer{display:none!important;}
  .app-layout{display:block!important;width:100%!important;min-height:0!important;padding:0!important;margin:0!important;}
  .main-wrapper{margin:0!important;padding:0!important;width:100%!important;min-width:0!important;max-width:100%!important;overflow:visible!important;}
  main{display:block!important;max-width:100%!important;padding:0!important;margin:0!important;overflow:visible!important;}
  .panel{border:none!important;box-shadow:none!important;padding:0!important;margin:0 0 10px 0!important;background:transparent!important;overflow:visible!important;}
  div[style*="overflow"]{overflow:visible!important;max-height:none!important;height:auto!important;}

  .print-doc-header{display:block!important;text-align:center!important;margin-bottom:14px!important;padding-bottom:8px!important;border-bottom:1.5pt solid #0f172a!important;}
  .print-doc-header .doc-brand{font-size:24pt!important;font-weight:900!important;letter-spacing:-0.5px;color:#0f172a!important;line-height:1.1;margin-bottom:3px!important;}
  .print-doc-header .doc-company{font-size:12pt!important;font-weight:800!important;color:#0f172a!important;margin-bottom:2px!important;}
  .print-doc-header .doc-address{font-size:8.5pt!important;color:#475569!important;margin-bottom:8px!important;}
  .print-doc-header .doc-title{font-size:13pt!important;font-weight:800!important;text-transform:uppercase!important;color:#0f172a!important;margin-top:6px!important;}
  .print-doc-header .doc-period{font-size:9.5pt!important;font-weight:600!important;color:#334155!important;}

  .report-table-plain{width:100%!important;font-size:7.5pt!important;table-layout:auto!important;border-collapse:collapse!important;margin-bottom:14px!important;border:1pt solid #000!important;}
  .report-table-plain th{background:#a49fc4!important;color:#000!important;font-weight:bold!important;border:0.75pt solid #000!important;padding:4px 6px!important;text-align:center!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
  .report-table-plain td{border:0.5pt solid #000!important;padding:3.5px 5px!important;color:#000!important;background:#fff!important;word-break:break-word!important;}
  
  h1,h2,h3,p{color:#000!important;margin:0 0 4px 0!important;page-break-after:avoid;}
  tr{page-break-inside:avoid;}
  table{page-break-inside:auto;}
  thead{display:table-header-group;}
}"""

assert target_css in c, "target_css not found in bootstrap.php"
c = c.replace(target_css, replacement_css, 1)

# 2. Add helper function render_report_document_header in bootstrap.php
helper_func = """function render_report_document_header(string $reportTitle, ?string $periodSubtitle = null, ?PDO $pdo = null): void
{
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

    if (!$periodSubtitle) {
        $periodSubtitle = 'Per ' . date('d F Y');
    }

    echo '<div class="print-doc-header">';
    echo '  <div class="doc-brand">' . e($appName) . '</div>';
    echo '  <div class="doc-company">' . e($companyName) . '</div>';
    echo '  <div class="doc-address">' . e($companyAddress) . '</div>';
    echo '  <div class="doc-title">' . e($reportTitle) . '</div>';
    echo '  <div class="doc-period">' . e($periodSubtitle) . '</div>';
    echo '</div>';
}
"""

idx_ins = c.find('function render_header(')
assert idx_ins != -1, "render_header not found"
c = c[:idx_ins] + helper_func + "\n" + c[idx_ins:]

with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Updated bootstrap.php with render_report_document_header and print layout CSS")

