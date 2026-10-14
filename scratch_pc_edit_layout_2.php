<?php
$f = __DIR__ . '/app/modules/pc.php';
$c = file_get_contents($f);

$search = <<<'EOD'
    $html .= '<section class="panel"><h2>Data Analisa PcNalisa</h2><p class="muted">Bagian ini otomatis diisi dari upload/agent PcNalisa. Edit manual hanya jika perlu koreksi.</p><div class="analysis-grid-edit">';
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
        
        $data = null;
        if (!empty($pc[$name])) {
            $data = json_decode($pc[$name], true);
        }
        
        $neatHtml = $data && function_exists('render_analysis_section') 
            ? render_analysis_section($label, $data) 
            : '<p class="muted">Belum ada data JSON atau format tidak valid.</p>';
            
        $html .= '<details' . $wide . $open . '><summary>' . e($label) . '</summary>';
        $html .= '<div style="margin-bottom:12px;">' . $neatHtml . '</div>';
        $html .= '<label style="font-size:12px;color:#64748b;margin-bottom:4px;display:block;">Edit Manual Raw JSON (Hati-hati)</label>';
        $html .= '<textarea name="' . e($name) . '" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name] ?? '') . '</textarea>';
        $html .= '</details>';
    }
    $html .= '</div></section>
EOD;

$replace = <<<'EOD'
    $html .= '</form><section class="analysis-grid">';
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
        $html .= '<textarea form="pcForm" name="' . e($name) . '" style="width:100%; min-height:100px;" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name] ?? '') . '</textarea>';
        $html .= '</div>';
    }
    $html .= '</section><form id="pcForm" class="pc-form" method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '">';
EOD;

// Wait, the original form is `<form class="pc-form" method="post">`. I can't just close it and reopen it unless I bind everything with `form="pcForm"`.
// Actually, it's much simpler to just keep it inside the form and use `class="analysis-grid"` instead of `class="analysis-grid-edit"`.

$replace2 = <<<'EOD'
    $html .= '<section class="analysis-grid">';
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
            
        $html .= '<div class="panel' . $wide . '" style="background:#fff;"><h2>' . e($label) . '</h2>';
        $html .= '<div style="margin-bottom:12px;">' . $neatHtml . '</div>';
        $html .= '<label style="font-size:12px;color:#64748b;margin-bottom:4px;display:block;">Edit Manual Raw JSON (Hati-hati)</label>';
        $html .= '<textarea name="' . e($name) . '" style="width:100%; min-height:80px;" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name] ?? '') . '</textarea>';
        $html .= '</div>';
    }
    $html .= '</section>';
EOD;

$c = str_replace(str_replace("\r\n", "\n", $search), str_replace("\r\n", "\n", $replace2), $c);
file_put_contents($f, $c);
echo "Done\n";

