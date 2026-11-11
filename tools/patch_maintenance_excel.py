with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Fix preg_replace in output_tsv:
old_preg = "$cleanTitle = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title);"
new_preg = "$cleanTitle = str_replace(['\\\\', '/', '?', '*', '[', ']', ':'], '', $title);"

# 2. Fix exit in output_tsv:
old_exit = """            readfile($tempFile);
            @unlink($tempFile);
            return;
        }
    }

    // Fallback: TSV
    echo implode("\\t", array_map('excel_cell', $headers)) . "\\r\\n";
    foreach ($rows as $row) {
        echo implode("\\t", array_map('excel_cell', array_values($row))) . "\\r\\n";
    }
}"""

new_exit = """            readfile($tempFile);
            @unlink($tempFile);
            exit;
        }
    }

    // Fallback: TSV
    echo implode("\\t", array_map('excel_cell', $headers)) . "\\r\\n";
    foreach ($rows as $row) {
        echo implode("\\t", array_map('excel_cell', array_values($row))) . "\\r\\n";
    }
    exit;
}"""

# 3. Update report buttons in handle_route_reports
old_maint_btn = '<a class="btn" href="\' . route_url(\'export_excel\', $exportParams) . \'">Export Excel</a> <a class="btn primary" href="\' . e(route_url(\'reports\', array_merge($_GET, [\'print\' => 1]))) . \'" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>'
new_maint_btn = '<div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">\' . btn_icon_excel(route_url(\'export_excel\', $exportParams), \'Export Excel Maintenance\') . btn_icon_print(route_url(\'reports\', array_merge($_GET, [\'print\' => 1])), \'Print Report Maintenance\') . \'</div>'

# 4. Update report buttons in handle_route_maintenance_status_report
old_maint_status_btn = '<div class="actions"><a class="btn" href="\' . route_url(\'reports\') . \'">Report Detail</a><a class="btn" href="\' . route_url(\'export_excel\', $exportParams) . \'">Export Excel</a> <button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button></div>'
new_maint_status_btn = '<div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;"><a class="btn" href="\' . route_url(\'reports\') . \'">Report Detail</a>\' . btn_icon_excel(route_url(\'export_excel\', $exportParams), \'Export Excel Status Maintenance\') . \'<button type="button" class="btn-icon print" onclick="window.print()" title="Print Status Maintenance" aria-label="Print Status Maintenance"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg></button></div>'

print("Found old_preg:", old_preg in content)
print("Found old_exit:", old_exit in content)
print("Found old_maint_btn:", old_maint_btn in content)
print("Found old_maint_status_btn:", old_maint_status_btn in content)

if old_preg in content and old_exit in content and old_maint_btn in content and old_maint_status_btn in content:
    content = content.replace(old_preg, new_preg, 1)
    content = content.replace(old_exit, new_exit, 1)
    content = content.replace(old_maint_btn, new_maint_btn, 1)
    content = content.replace(old_maint_status_btn, new_maint_status_btn, 1)
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: maintenance.php updated!")
else:
    print("FAILED to update maintenance.php")
