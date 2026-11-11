with open('app/lib/bootstrap.php', 'rb') as f:
    b = f.read()

start_sig = b'.report-table-plain{width:100%;border-collapse:collapse;background:#fff!important;border:1px solid #cbd5e1;}'
end_sig = b"echo '</style></head><body>';"

pos1 = b.find(start_sig)
pos2 = b.find(end_sig, pos1)

if pos1 != -1 and pos2 != -1:
    css_text = """.report-table-plain{width:100%;border-collapse:collapse;background:#fff!important;border:1px solid #1e293b;}
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
}';\n    """
    new_b = b[:pos1] + css_text.encode('utf-8') + b[pos2:]
    with open('app/lib/bootstrap.php', 'wb') as f:
        f.write(new_b)
    print("SUCCESS")
else:
    print("Signatures not found:", pos1, pos2)

