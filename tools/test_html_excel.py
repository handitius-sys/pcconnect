# Create a test HTML excel file and check output format
html = """<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Sheet1</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  th {
    background-color: #ffffff;
    font-family: Arial, Calibri, sans-serif;
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
    border: 0.5pt solid #000000;
    padding: 6px 10px;
    text-align: left;
    vertical-align: middle;
  }
  td {
    font-family: Arial, Calibri, sans-serif;
    font-size: 10pt;
    border: 0.5pt solid #d4d4d4;
    padding: 5px 8px;
    vertical-align: middle;
    mso-number-format: "\\@";
  }
  .num {
    mso-number-format: "#,##0";
    text-align: right;
  }
</style>
</head>
<body>
<table>
<thead><tr><th>NO. PO</th><th>TANGGAL</th><th>SUPPLIER</th></tr></thead>
<tbody><tr><td>PO123</td><td>03-11-2026</td><td>PT. TEST</td></tr></tbody>
</table>
</body></html>
"""
with open('tools/test_excel.xls', 'w', encoding='utf-8') as f:
    f.write(html)
print("HTML Excel sample generated successfully")

