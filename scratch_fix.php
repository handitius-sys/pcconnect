<?php
$f = __DIR__ . '/app/lib/bootstrap.php';
$c = file_get_contents($f);

// Fix syntax error and remove the rest of the Maintenance block
$search = <<<'EOD'
        echo '  </div>';
        echo '</details>';

        ';

        // Sub menu Mobile
        if ($isAdmin || has_regulation('mobile_service', 'view', $user)) {
            echo '<details class="nav-subaccordion"' . ($curRoute === 'mobile_service' ? ' open' : '') . '>';
            echo '  <summary title="Mobile Field Service"><span>📱 Mobile</span><span class="nav-chevron">▶</span></summary>';
            echo '  <div style="display:flex;flex-direction:column;gap:1px;padding-top:2px;">';
            echo '<a class="nav-item' . ($curRoute === 'mobile_service' ? ' active' : '') . '" href="' . route_url('mobile_service') . '" target="_blank"><span class="nav-icon">' . $svgMobile . '</span><span class="nav-label">📱 Mobile Service</span><span class="nav-badge">PWA</span></a>';
            echo '  </div>';
            echo '</details>';
        }

        echo '  </div>';
        echo '</details>';
EOD;

$c = str_replace("        ';\r\n\r\n        // Sub menu Mobile", "        // Sub menu Mobile", $c);
$c = str_replace("        ';\n\n        // Sub menu Mobile", "        // Sub menu Mobile", $c);

// Also we should just remove the Sub menu Mobile completely since it's part of the maintenance accordion.
$pattern = '/\s*\';\s*\/\/ Sub menu Mobile.*?echo \'  <\/div>\';\s*echo \'<\/details>\';/is';
$c = preg_replace($pattern, '', $c);

file_put_contents($f, $c);
echo "Fixed bootstrap\n";

