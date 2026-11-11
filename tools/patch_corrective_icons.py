with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_corr_btn = """            <div class="actions no-print">
                <a class="btn primary" href="<?= route_url('tickets') ?>">Daftar Tiket Aktif</a>
                <?php
    $exportParams = ['type' => 'corrective_repairs'];
    if ($startDate !== '') $exportParams['start_date'] = $startDate;
    if ($endDate !== '') $exportParams['end_date'] = $endDate;
    ?>
                <a class="btn" href="<?= route_url('export_excel', $exportParams) ?>">Export Excel</a>
                <a class="btn primary" href="<?= e(route_url('corrective_repairs', array_merge($_GET, ['print' => 1]))) ?>" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>
            </div>"""

new_corr_btn = """            <div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">
                <a class="btn primary" href="<?= route_url('tickets') ?>">Daftar Tiket Aktif</a>
                <?php
    $exportParams = ['type' => 'corrective_repairs'];
    if ($startDate !== '') $exportParams['start_date'] = $startDate;
    if ($endDate !== '') $exportParams['end_date'] = $endDate;
    ?>
                <?= btn_icon_excel(route_url('export_excel', $exportParams), 'Export Excel Reparasi & Sparepart') ?>
                <?= btn_icon_print(route_url('corrective_repairs', array_merge($_GET, ['print' => 1])), 'Print Report Reparasi & Sparepart') ?>
            </div>"""

print("Found old_corr_btn:", old_corr_btn in content)

if old_corr_btn in content:
    content = content.replace(old_corr_btn, new_corr_btn, 1)
    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: corrective.php updated!")
else:
    print("FAILED to update corrective.php")
