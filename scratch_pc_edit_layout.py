import os

f = 'app/modules/pc.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

search = """    $html .= '<section class="panel"><h2>Data Analisa PcNalisa</h2><p class="muted">Bagian ini otomatis diisi dari upload/agent PcNalisa. Edit manual hanya jika perlu koreksi.</p><div class="analysis-grid-edit">';
    $analysisFields = [
        'general_specs' => 'Spesifikasi Umum',
        'software' => 'Software',
        'device_management' => 'Device Management',
        'benchmark' => 'Benchmark',
        'startup_analysis' => 'Startup Analysis',
    ];
    foreach ($analysisFields as $name => $label) {
        $open = $name === 'device_management' || $name === 'startup_analysis' ? ' open' : '';
        $wide = in_array($name, ['device_management', 'startup_analysis'], true) ? ' class="wide"' : '';
        $html .= '<details' . $wide . $open . '><summary>' . e($label) . '</summary><label>' . e($label) . '<textarea name="' . e($name) . '" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name]) . '</textarea></label></details>';
    }
    $html .= '</div></section><section class="footer-actions"><div class="actions"><button class="btn primary">' . ($editing ? 'Simpan Perubahan' : 'Tambah PC') . '</button><a class="btn" href="' . route_url('pcs') . '">Batal</a>';
"""

replace = """    $html .= '</form><section class="analysis-grid">';
    $analysisFields = [
        'general_specs' => 'Spesifikasi Umum',
        'software' => 'Software',
        'device_management' => 'Device Management',
        'benchmark' => 'Benchmark',
        'startup_analysis' => 'Startup Analisa',
    ];
    foreach ($analysisFields as $name => $label) {
        $wide = in_array($name, ['device_management', 'startup_analysis'], true) ? ' wide-panel' : '';
        $data = null;
        if (!empty($pc[$name])) {
            $data = json_decode($pc[$name], true);
        }
        $neatHtml = $data && function_exists('render_analysis_section') 
            ? render_analysis_section($label, $data) 
            : '<p class="muted">Belum ada data JSON atau format tidak valid.</p>';
            
        $html .= '<div class="panel' . $wide . '"><h2>' . e($label) . '</h2>';
        $html .= '<div style="margin-bottom:12px;">' . $neatHtml . '</div>';
        $html .= '<label style="font-size:12px;color:#64748b;margin-bottom:4px;display:block;">Edit Manual Raw JSON (Hati-hati)</label>';
        $html .= '<textarea form="pcForm" name="' . e($name) . '" style="width:100%; min-height:80px; font-family:Consolas,monospace; font-size:12px; margin-bottom:8px;" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name] ?? '') . '</textarea>';
        $html .= '</div>';
    }
    $html .= '</section><form id="pcForm" class="pc-form" method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '">';
    $html .= '<section class="footer-actions"><div class="actions"><button type="submit" form="pcForm" class="btn primary">' . ($editing ? 'Simpan Perubahan' : 'Tambah PC') . '</button><a class="btn" href="' . route_url('pcs') . '">Batal</a>';
"""

if search in c:
    c = c.replace(search, replace)
    
    # Also I need to change `<form class="pc-form" method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '">` at the top to `<form id="pcForm" class="pc-form" method="post">`
    c = c.replace('<form class="pc-form" method="post"><input type="hidden" name="csrf" value="\' . csrf_token() . \'">', '<form id="pcForm" class="pc-form" method="post"><input type="hidden" name="csrf" value="\' . csrf_token() . \'">')

    with open(f, 'w', encoding='utf-8') as file:
        file.write(c)
    print("Replaced successfully")
else:
    print("Search string not found")

