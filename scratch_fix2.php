<?php
$f = __DIR__ . '/app/lib/bootstrap.php';
$c = file_get_contents($f);

$search = "        echo '  </div>';\r\n        echo '</details>\r\n        }\r\n\r\n        echo '  </div>';\r\n        echo '</details>';";
$search2 = "        echo '  </div>';\n        echo '</details>\n        }\n\n        echo '  </div>';\n        echo '</details>';";

$replace = "        echo '  </div>';\n        echo '</details>';";

$c = str_replace($search, $replace, $c);
$c = str_replace($search2, $replace, $c);

file_put_contents($f, $c);
echo "Fixed syntax error\n";

