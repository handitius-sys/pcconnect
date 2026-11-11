# Patch bootstrap.php, asset.php, asset_loan.php, maintenance.php

def patch_bootstrap():
    with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # In render_report_print_standalone_page:
    # 1. Clean title: remove "Inventaris" and remove "Per Bulan ..."
    # 2. In HTML: remove doc-brand, doc-company, doc-address
    target_header_render = """    echo '<div class="doc-header-box">' . "\\r\\n";
    echo '  <div class="doc-brand">' . htmlspecialchars($appName) . '</div>' . "\\r\\n";
    echo '  <div class="doc-company">' . htmlspecialchars($companyName) . '</div>' . "\\r\\n";
    echo '  <div class="doc-address">' . htmlspecialchars($companyAddress) . '</div>' . "\\r\\n";
    echo '  <div class="doc-title">' . htmlspecialchars($docTitle) . '</div>' . "\\r\\n";
    echo '  <div class="doc-period">' . htmlspecialchars($periodSubtitle) . '</div>' . "\\r\\n";
    echo '</div>' . "\\r\\n";"""

    rep_header_render = """    $docTitle = trim(preg_replace('/\\bInventaris\\s*/i', '', $docTitle));
    if ($periodSubtitle !== '') {
        $periodSubtitle = preg_replace('/^Per\\s+Bulan\\s+/i', 'Per ', $periodSubtitle);
    }

    echo '<div class="doc-header-box">' . "\\r\\n";
    echo '  <div class="doc-title">' . htmlspecialchars($docTitle) . '</div>' . "\\r\\n";
    if ($periodSubtitle !== '') {
        echo '  <div class="doc-period">' . htmlspecialchars($periodSubtitle) . '</div>' . "\\r\\n";
    }
    echo '</div>' . "\\r\\n";"""

    assert target_header_render in c, 'target_header_render not found in bootstrap.php'
    c = c.replace(target_header_render, rep_header_render, 1)

    # In render_report_document_header (the screen/print header on normal layout):
    target_screen_header = """    echo '<div class="print-doc-header">';
    echo '  <div class="doc-brand">' . e($appName) . '</div>';
    echo '  <div class="doc-company">' . e($companyName) . '</div>';
    echo '  <div class="doc-address">' . e($companyAddress) . '</div>';
    echo '  <div class="doc-title">' . e($reportTitle) . '</div>';
    echo '  <div class="doc-period">' . e($periodSubtitle) . '</div>';
    echo '</div>';"""

    rep_screen_header = """    $reportTitle = trim(preg_replace('/\\bInventaris\\s*/i', '', $reportTitle));
    if ($periodSubtitle !== '') {
        $periodSubtitle = preg_replace('/^Per\\s+Bulan\\s+/i', 'Per ', $periodSubtitle);
    }

    echo '<div class="print-doc-header">';
    echo '  <div class="doc-title">' . e($reportTitle) . '</div>';
    if ($periodSubtitle !== '') {
        echo '  <div class="doc-period">' . e($periodSubtitle) . '</div>';
    }
    echo '</div>';"""

    assert target_screen_header in c, 'target_screen_header not found in bootstrap.php'
    c = c.replace(target_screen_header, rep_screen_header, 1)

    # Add helper function format_report_period_label
    helper_code = """function format_report_period_label(string $startDate = '', string $endDate = ''): string
{
    $startDate = trim($startDate);
    $endDate = trim($endDate);
    if ($startDate !== '' && $endDate !== '') {
        $d1 = date('d/m/Y', strtotime($startDate));
        $d2 = date('d/m/Y', strtotime($endDate));
        return 'Per ' . $d1 . ' s/d ' . $d2;
    } elseif ($startDate !== '') {
        return 'Per Mulai ' . date('d/m/Y', strtotime($startDate));
    } elseif ($endDate !== '') {
        return 'Per Sampai ' . date('d/m/Y', strtotime($endDate));
    }
    return 'Per ' . date('d/m/Y');
}

"""
    target_func = "function render_report_print_standalone_page("
    assert target_func in c, 'target_func not found in bootstrap.php'
    c = c.replace(target_func, helper_code + target_func, 1)

    with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("bootstrap.php patched successfully.")


patch_bootstrap()
