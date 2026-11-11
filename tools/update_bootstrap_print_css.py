# Update bootstrap.php print styles to completely eliminate scrollbars, overflow wrappers, and screen layout constraints during printing

with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

target = """@media print{
  html,body{width:100%!important;margin:0!important;padding:0!important;background:#fff!important;color:#000!important;font-size:8pt!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .sidebar,.topbar,.no-print,.actions,.btn,.flash,form,.font-resizer,.collapse-btn{display:none!important;}
  .main-wrapper{margin:0!important;padding:0!important;width:100%!important;}
  main{max-width:100%!important;padding:0!important;margin:0!important;}
  .panel{border:none!important;box-shadow:none!important;padding:0!important;margin:0 0 10px 0!important;background:transparent!important;}
  .report-table-plain{width:100%!important;font-size:7.5pt!important;table-layout:auto!important;border-collapse:collapse!important;}
  .report-table-plain th,.report-table-plain td{padding:3px 5px!important;border:0.5pt solid #555!important;color:#000!important;background:#fff!important;word-break:break-word!important;}
  .report-table-plain th{background:#f0f0f0!important;font-weight:bold!important;}
  h1,h2,h3{color:#000!important;margin:0 0 6px 0!important;page-break-after:avoid;}
  tr{page-break-inside:avoid;}
}"""

replacement = """@media print{
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

assert target in c, "Target media print block not found in bootstrap.php"
c = c.replace(target, replacement, 1)

with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Updated print media queries in bootstrap.php successfully")

