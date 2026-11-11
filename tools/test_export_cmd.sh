curl -s "http://127.0.0.1/PcConnect/public/index.php?route=export_excel&type=asset_items" -o /tmp/test_export.xlsx
ls -lh /tmp/test_export.xlsx
file /tmp/test_export.xlsx 2>/dev/null || true
head -c 50 /tmp/test_export.xlsx
echo ""
