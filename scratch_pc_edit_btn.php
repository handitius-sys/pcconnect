<?php
$f = __DIR__ . '/app/modules/pc.php';
$c = file_get_contents($f);

// Change Download Runner button in Edit to match View
$c = str_replace('<a class="btn primary" href="\' . e($runnerDownloadUrl) . \'" style="background:#0284c7;border-color:#0284c7;">📥 Download PcNalisa-Run-\' . e($effectivePcId) . \'.cmd</a>', '<a class="btn good" href="\' . e($runnerDownloadUrl) . \'">📥 Download PcNalisa-run-\' . e($effectivePcId) . \'</a>', $c);

// Change Script PS1 button in Edit to match View
$c = str_replace('<a class="btn" href="\' . e($agentDownloadUrl) . \'">📄 Script PcNalisa.ps1</a>', '<a class="btn" href="\' . e($agentDownloadUrl) . \'">📄 Script PS1</a>', $c);

file_put_contents($f, $c);
echo "Updated buttons in edit to match view\n";

