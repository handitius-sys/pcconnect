with open('app/modules/mobile.php', 'r', encoding='utf-8') as f:
    content = f.read()

# In function smart_maintenance_card, return empty string so no panel is displayed
old_func = """function smart_maintenance_card(array $schedule): string
{
    $hardware = $schedule['general_specs'] ? json_decode((string)$schedule['general_specs'], true) : [];
    $benchmark = $schedule['benchmark'] ? json_decode((string)$schedule['benchmark'], true) : [];
    $score = 100;
    if ((float)($hardware['ram_gb'] ?? 8) < 8) {
        $score -= 15;
    }
    if (isset($benchmark['storage_write_mbps']) && (float)$benchmark['storage_write_mbps'] < 80) {
        $score -= 20;
    }
    if (isset($benchmark['cpu_quick_ms']) && (float)$benchmark['cpu_quick_ms'] > 2500) {
        $score -= 15;
    }
    $html = '<section class="panel"><h2>Smart Maintenance</h2><div class="grid two"><div><strong>Health Score</strong><br>' . e(max(0, $score)) . '%</div><div><strong>RAM</strong><br>' . e($hardware['ram_gb'] ?? '-') . ' GB</div><div><strong>Storage</strong><br>' . e(isset($benchmark['storage_write_mbps']) ? round((float)$benchmark['storage_write_mbps'], 1) . ' MB/s' : '-') . '</div><div><strong>Benchmark</strong><br>' . e(isset($benchmark['cpu_quick_ms']) ? round((float)$benchmark['cpu_quick_ms'], 1) . ' ms' : '-') . '</div></div>';
    $html .= '<p><strong>AI</strong><br>' . e($schedule['ai_recommendation'] ?: 'Belum ada rekomendasi PcNalisa.') . '</p></section>';
    return $html;
}"""

new_func = """function smart_maintenance_card(array $schedule): string
{
    // Panel Smart Maintenance dihapus sesuai permintaan
    return '';
}"""

old_call = "    echo smart_maintenance_card($schedule);\n"
new_call = "    // Panel Smart Maintenance dihapus sesuai permintaan\n"

print("Found old_func:", old_func in content)
print("Found old_call:", old_call in content)

if old_func in content and old_call in content:
    content = content.replace(old_func, new_func, 1)
    content = content.replace(old_call, new_call, 1)
    with open('app/modules/mobile.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: mobile.php updated!")
else:
    print("FAILED to update mobile.php")
