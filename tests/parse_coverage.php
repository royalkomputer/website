<?php
$html = file_get_contents(__DIR__ . '/coverage/config.php.html');

// The HTML uses CSS classes: "h" for covered, "n" for not covered
preg_match_all('/<td class="h"><a id="(\d+)"/', $html, $hits);
preg_match_all('/<td class="n"><a id="(\d+)"/', $html, $nothits);

echo "Covered lines (" . count($hits[1]) . "): " . implode(', ', $hits[1]) . "\n";
echo "Uncovered lines (" . count($nothits[1]) . "): " . implode(', ', $nothits[1]) . "\n";
echo "\n";

// Show the source for uncovered lines
$src = file(__DIR__ . '/../backend/config.php');
echo "=== Uncovered source lines ===\n";
foreach ($nothits[1] as $lineNum) {
    $idx = $lineNum - 1;
    if (isset($src[$idx])) {
        printf("Line %3d: %s", $lineNum, rtrim($src[$idx]) . "\n");
    }
}
