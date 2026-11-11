import re

# 1. Update corrective.php
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    corr = f.read()

# Add format_clean_ticket_subject function if not exists
if 'function format_clean_ticket_subject(' not in corr:
    helper = """/**
 * Membersihkan tampilan Judul / Subjek Kendala agar murni judul kendala tanpa kode aset/spesifikasi
 */
function format_clean_ticket_subject(?string $subject, ?string $fallbackAction = null): string
{
    $s = trim((string)$subject);
    if ($s === '') {
        return $fallbackAction ?: '-';
    }

    // Jika subject format lama/otomatis: "Perbaikan [Job Desk ...] KODE - NAMA"
    if (preg_match('/^Perbaikan\\s*\\[([^\\]]+)\\]\\s*(.*)$/i', $s, $m)) {
        if (!empty($fallbackAction) && $fallbackAction !== 'Perbaikan Tiket' && $fallbackAction !== 'Reparasi') {
            return $fallbackAction;
        }
        $jobDesk = trim($m[1]);
        return $jobDesk ?: ($fallbackAction ?: 'Perbaikan Unit');
    }

    // Jika format "Perbaikan KODE - NAMA" tanpa keluhan nyata
    if (preg_match('/^Perbaikan\\s+([A-Z0-9_-]+)\\s*-\\s*(.*)$/i', $s, $m)) {
        if (!empty($fallbackAction) && $fallbackAction !== 'Perbaikan Tiket' && $fallbackAction !== 'Reparasi') {
            return $fallbackAction;
        }
        return $fallbackAction ?: 'Perbaikan Unit';
    }

    return $s;
}

"""
    # Place right before handle_route_tickets
    idx_t = corr.find('function handle_route_tickets(')
    corr = corr[:idx_t] + helper + corr[idx_t:]

# 2. Update $renderCmCard in corrective.php
# Replace card styling to match asset_loans
old_cm_card = """$renderCmCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#eff6ff', '#1e3a8a', '#3b82f6'], // 0: blue
            ['#fef2f2', '#7f1d1d', '#ef4444'], // 1: red
            ['#fffbeb', '#78350f', '#f59e0b'], // 2: yellow/amber
            ['#f3e8ff', '#581c87', '#a855f7'], // 3: purple
            ['#f0fdf4', '#14532d', '#22c55e'], // 4: green
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

if old_cm_card in corr:
    corr = corr.replace(old_cm_card, new_cm_card)
    print("Replaced $renderCmCard successfully!")
else:
    print("Warning: old_cm_card not found!")

# Also update the container grid for CM cards
old_cm_grid = """<div style="margin-bottom: 18px;">
        <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px;">"""
new_cm_grid = """<div style="margin-bottom: 20px;">
        <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">"""
if old_cm_grid in corr:
    corr = corr.replace(old_cm_grid, new_cm_grid)

# 3. Update daftar tiket corrective: tambahkan subquery first_action_title dan gunakan format_clean_ticket_subject
old_tkt_query = """(SELECT COUNT(*) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS repair_count,"""
new_tkt_query = """(SELECT cta.action_title FROM corrective_ticket_actions cta WHERE cta.ticket_id = t.id LIMIT 1) AS first_action_title,
                   (SELECT COUNT(*) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS repair_count,"""
if old_tkt_query in corr:
    corr = corr.replace(old_tkt_query, new_tkt_query)

# Update the display of $t['subject'] in handle_route_tickets
old_tkt_subject_td = """<td>
                                <strong><?= e($t['subject']) ?></strong>
                            </td>"""
new_tkt_subject_td = """<td>
                                <strong><?= e(format_clean_ticket_subject($t['subject'], $t['first_action_title'] ?? null)) ?></strong>
                            </td>"""
if old_tkt_subject_td in corr:
    corr = corr.replace(old_tkt_subject_td, new_tkt_subject_td)
    print("Updated ticket list subject column successfully!")
else:
    print("Warning: old_tkt_subject_td not found!")

# 4. Update Report Corrective Maintenance (tabel web & print)
old_rep_print_subject = "$subjectTxt = (string)($r['subject'] ?? '-');"
new_rep_print_subject = "$subjectTxt = format_clean_ticket_subject($r['subject'] ?? '', $r['action_taken'] ?? null);"
if old_rep_print_subject in corr:
    corr = corr.replace(old_rep_print_subject, new_rep_print_subject)
    print("Updated report print subject successfully!")

old_rep_web_subject = """<td>
                                <strong><?= e($r['subject'] ?: '-') ?></strong>
                            </td>"""
new_rep_web_subject = """<td>
                                <strong><?= e(format_clean_ticket_subject($r['subject'] ?? '', $r['action_taken'] ?? null)) ?></strong>
                            </td>"""
if old_rep_web_subject in corr:
    corr = corr.replace(old_rep_web_subject, new_rep_web_subject)
    print("Updated report web subject column successfully!")

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(corr)

# 5. Update maintenance.php: $renderPmCard styling
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    pm = f2.read()

old_pm_card = """$renderPmCard = function($number, $label, $iconPath, $themeIdx) {
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

if old_pm_card in pm:
    pm = pm.replace(old_pm_card, new_pm_card)
    print("Replaced $renderPmCard successfully!")
else:
    print("Warning: old_pm_card not found!")

old_pm_grid = """echo '<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">';"""
new_pm_grid = """echo '<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px;">';"""
if old_pm_grid in pm:
    pm = pm.replace(old_pm_grid, new_pm_grid)

# Also update maintenance.php export subject clean
old_maint_exp_sub = "$r['subject'] ?: '-',"
new_maint_exp_sub = "format_clean_ticket_subject($r['subject'] ?? '', $r['action_taken'] ?? null),"
if old_maint_exp_sub in pm:
    pm = pm.replace(old_maint_exp_sub, new_maint_exp_sub)
    # Also add format_clean_ticket_subject to maintenance.php if not exists
    if 'function format_clean_ticket_subject(' not in pm:
        pm = helper + pm
    print("Updated maintenance export subject successfully!")

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f2:
    f2.write(pm)

print("All modifications applied successfully.")
