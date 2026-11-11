# 1. Update maintenance.php cleanly
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Replace $renderPmCard
old_render_pm = """    $renderPmCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#eff6ff', '#1e3a8a', '#3b82f6'], // 0: blue
            ['#fffbeb', '#78350f', '#f59e0b'], // 1: yellow/amber
            ['#f0fdf4', '#14532d', '#22c55e'], // 2: green
            ['#fef2f2', '#7f1d1d', '#ef4444'], // 3: red
            ['#f3e8ff', '#581c87', '#a855f7'], // 4: purple
        ];
        $t = $themes[$themeIdx % count($themes)];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="' . $t[2] . '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $iconPath . '"/></svg>';
        $bgImage = "data:image/svg+xml;base64," . base64_encode($svg);

        return '<div class="stat" style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 5px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 16px 18px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; min-width: 0; flex: 1 1 0;">'
             . '<div style="position: absolute; right: -15px; top: -15px; width: 110px; height: 110px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 30px; display: block; margin-bottom: 2px; line-height: 1; color: ' . $t[1] . ';">' . e($number) . '</strong>'
             . '<span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; opacity: 0.85; color: ' . $t[1] . ';">' . e($label) . '</span>'
             . '</div></div>';
    };"""

new_render_pm = """    $renderPmCard = function($number, $label, $iconPath, $themeIdx) {
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

idx_p = text.find('$renderPmCard = function(')
idx_p_end = text.find('};', idx_p) + 2
text = text[:idx_p] + new_render_pm.strip() + text[idx_p_end:]

# Replace grid styling
old_grid = "echo '<section style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;\">';"
new_grid = "echo '<section style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px;\">';"
if old_grid in text:
    text = text.replace(old_grid, new_grid)

# Update export subject to clean subject
old_sub_exp = "$r['subject'] ?: '-',"
new_sub_exp = "function_exists('format_clean_ticket_subject') ? format_clean_ticket_subject($r['subject'] ?? '', $r['action_taken'] ?? null) : ($r['subject'] ?: '-'),"
if old_sub_exp in text:
    text = text.replace(old_sub_exp, new_sub_exp)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Updated maintenance.php cleanly!")
