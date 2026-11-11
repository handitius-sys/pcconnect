# -*- coding: utf-8 -*-
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

target = """    // Bersihkan seluruh buffer output agar file excel dapat di-stream & di-download langsung
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'pcconnect-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=\\'\\'UTF-8' . rawurlencode($filename));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo "\\xEF\\xBB\\xBF";"""

replacement = """    // Bersihkan seluruh buffer output agar file excel dapat di-stream & di-download langsung
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'pcconnect-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '-' . date('Ymd-His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo "\\xEF\\xBB\\xBF";"""

if target in text:
    text = text.replace(target, replacement, 1)
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print('Updated export headers to xls successfully')
else:
    print('Target not found in maintenance.php')

