<?php
$f = __DIR__ . '/app/modules/pc.php';
$c = file_get_contents($f);

$search = <<<'EOD'
    foreach ($analysisFields as $name => $label) {
        $open = $name === 'device_management' || $name === 'startup_analysis' ? ' open' : '';
        $wide = in_array($name, ['device_management', 'startup_analysis'], true) ? ' class="wide"' : '';
        $html .= '<details' . $wide . $open . '><summary>' . e($label) . '</summary><label>' . e($label) . '<textarea name="' . e($name) . '" placeholder="Boleh isi manual atau nanti diisi otomatis dari PcNalisa JSON">' . e($pc[$name]) . '</textarea></label></details>';
    }
EOD;

$replace = <<<'EOD'
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
EOD;

$c = str_replace($search, $replace, $c);

// CR/LF fix if needed
$search_cr = str_replace("\r\n", "\n", $search);
$replace_cr = str_replace("\r\n", "\n", $replace);
$c = str_replace($search_cr, $replace_cr, $c);

file_put_contents($f, $c);
echo "Replaced details block in pc.php\n";

