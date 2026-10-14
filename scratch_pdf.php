<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

// Replace button text
$oldBtn = '<button type="button" class="btn" onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;">&#128424; Cetak Bukti Pinjam</button>';
$newBtn = '<button type="button" class="btn" onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;">&#128424; Cetak / Export PDF</button>';
$content = str_replace($oldBtn, $newBtn, $content);

// In case the emoji isn't there or is different, let's just replace the text
$content = str_replace('>&#128424; Cetak Bukti Pinjam</button>', '>&#128424; Cetak / Export PDF</button>', $content);
// There's a dY-"?,? in the terminal output, meaning there's a weird byte sequence.
// Let's use preg_replace for the button
$content = preg_replace('/<button type="button" class="btn" onclick="window.print\(\)".*?<\/button>/s', '<button type="button" class="btn" onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;background:#3b82f6;border-color:#3b82f6;color:#fff;font-weight:bold;">&#128424; Cetak / Export PDF</button>', $content);

// Replace print-receipt inline style
$oldStyle = 'style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:50px;max-width:850px;margin:20px auto;box-shadow:0 10px 25px rgba(0,0,0,0.1);font-family: Arial, sans-serif;"';
$newStyle = 'style="background:#fff;border:1px solid #cbd5e1;padding:20mm;width:210mm;min-height:297mm;box-sizing:border-box;margin:20px auto;box-shadow:0 10px 25px rgba(0,0,0,0.15);font-family: Arial, sans-serif;position:relative;"';
$content = str_replace($oldStyle, $newStyle, $content);

file_put_contents($file, $content);
echo "Modifications done!\n";
?>
