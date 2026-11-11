<?php
$title = 'Laporan [Test] / * : ?';
$res = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title);
echo "Clean: $res\n";
