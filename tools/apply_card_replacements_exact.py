with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Replace $renderCmCard definition
idx_cm = text.find('$renderCmCard = function(')
idx_cm_end = text.find('};', idx_cm) + 2

new_cm_card = """$renderCmCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#f0f9ff', '#e0f2fe', '#bae6fd', '#0284c7', 'rgba(2,132,199,0.06)'], // 0: sky/blue
            ['#fff1f2', '#ffe4e6', '#fecdd3', '#e11d48', 'rgba(225,29,72,0.06)'], // 1: rose/red
            ['#fffbeb', '#fef3c7', '#fde68a', '#d97706', 'rgba(217,119,6,0.06)'], // 2: amber
            ['#fdf4ff', '#fae8ff', '#f5d0fe', '#a21caf', 'rgba(162,28,175,0.06)'], // 3: purple
            ['#f0fdf4', '#dcfce7', '#bbf7d0', '#16a34a', 'rgba(22,163,74,0.06)'], // 4: green
        ];
        $t = $themes[$themeIdx % count($themes)];

        return '<div style="background:linear-gradient(135deg, ' . $t[0] . ' 0%, ' . $t[1] . ' 100%);border:1px solid ' . $t[2] . ';border-left:4px solid ' . $t[3] . ';border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px ' . $t[4] . ';min-width:0;">'
             . '<div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
             . '<svg viewBox="0 0 24 24" fill="none" stroke="' . $t[3] . '" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' . $iconPath . '"/></svg>'
             . '</div>'
             . '<div style="position:relative;z-index:1;">'
             . '<div style="font-size:24px;font-weight:800;color:' . $t[3] . ';margin-top:3px;line-height:1.2;">' . e($number) . '</div>'
             . '<div style="font-size:12px;font-weight:700;color:' . $t[3] . ';opacity:0.85;margin-top:2px;">' . e($label) . '</div>'
             . '</div></div>';
    };"""

text = text[:idx_cm] + new_cm_card + text[idx_cm_end:]

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Updated $renderCmCard in corrective.php successfully!")

# Now update $renderPmCard in maintenance.php
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    pm_text = f2.read()

idx_pm = pm_text.find('$renderPmCard = function(')
idx_pm_end = pm_text.find('};', idx_pm) + 2

new_pm_card = """$renderPmCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#f0f9ff', '#e0f2fe', '#bae6fd', '#0284c7', 'rgba(2,132,199,0.06)'], // 0: sky/blue
            ['#fffbeb', '#fef3c7', '#fde68a', '#d97706', 'rgba(217,119,6,0.06)'], // 1: amber
            ['#f0fdf4', '#dcfce7', '#bbf7d0', '#16a34a', 'rgba(22,163,74,0.06)'], // 2: green
            ['#fff1f2', '#ffe4e6', '#fecdd3', '#e11d48', 'rgba(225,29,72,0.06)'], // 3: rose/red
            ['#fdf4ff', '#fae8ff', '#f5d0fe', '#a21caf', 'rgba(162,28,175,0.06)'], // 4: purple
        ];
        $t = $themes[$themeIdx % count($themes)];

        return '<div style="background:linear-gradient(135deg, ' . $t[0] . ' 0%, ' . $t[1] . ' 100%);border:1px solid ' . $t[2] . ';border-left:4px solid ' . $t[3] . ';border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px ' . $t[4] . ';min-width:0;">'
             . '<div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
             . '<svg viewBox="0 0 24 24" fill="none" stroke="' . $t[3] . '" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' . $iconPath . '"/></svg>'
             . '</div>'
             . '<div style="position:relative;z-index:1;">'
             . '<div style="font-size:24px;font-weight:800;color:' . $t[3] . ';margin-top:3px;line-height:1.2;">' . e($number) . '</div>'
             . '<div style="font-size:12px;font-weight:700;color:' . $t[3] . ';opacity:0.85;margin-top:2px;">' . e($label) . '</div>'
             . '</div></div>';
    };"""

pm_text = pm_text[:idx_pm] + new_pm_card + pm_text[idx_pm_end:]

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f2:
    f2.write(pm_text)

print("Updated $renderPmCard in maintenance.php successfully!")
