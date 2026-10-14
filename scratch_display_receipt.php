<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$lines = file($file);

for ($i = 1000; $i < 1300; $i++) {
    if (strpos($lines[$i], '<?php if ($r[\'photo_after\']): ?>') !== false) {
        $inject = <<<'EOD'
                    <?php if ($r['receipt_image_path'] ?? null): ?>
                        <div style="margin-top:8px;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:4px;">BUKTI TAGIHAN/KWITANSI:</div>
                            <a href="<?= e($r['receipt_image_path']) ?>" target="_blank">
                                <img src="<?= e($r['receipt_image_path']) ?>" style="max-height:100px;border-radius:4px;border:1px solid #cbd5e1;">
                            </a>
                        </div>
                    <?php endif; ?>

EOD;
        array_splice($lines, $i, 0, [$inject]);
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Successfully added receipt display to ticket detail!\n";
?>
