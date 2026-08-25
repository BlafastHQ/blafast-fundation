<?php

declare(strict_types=1);

/**
 * Task 4 (audit C8) subset gate: fail when the PHPUnit run's failing-test set is NOT a subset
 * of tests/BASELINE.md. A new red test breaks the build even while the baseline
 * is non-empty; shrinking the manifest is a reviewed edit.
 *
 * Usage: php tests/check-baseline.php <junit.xml> <BASELINE.md>
 */
[$_, $junitPath, $baselinePath] = $argv + [null, null, null];

if (! is_string($junitPath) || ! is_file($junitPath)) {
    fwrite(STDERR, "check-baseline: junit report not found: {$junitPath}\n");
    exit(1);
}
if (! is_string($baselinePath) || ! is_file($baselinePath)) {
    fwrite(STDERR, "check-baseline: baseline manifest not found: {$baselinePath}\n");
    exit(1);
}

$doc = new DOMDocument;
if (! @$doc->load($junitPath)) {
    fwrite(STDERR, "check-baseline: unparseable junit xml\n");
    exit(1);
}

$total = 0;
$failing = [];
foreach ($doc->getElementsByTagName('testcase') as $tc) {
    $total++;
    foreach ($tc->childNodes as $child) {
        if (in_array($child->nodeName, ['failure', 'error'], true)) {
            $failing[] = $tc->getAttribute('class').'::'.$tc->getAttribute('name');
            break;
        }
    }
}

if ($total === 0) {
    fwrite(STDERR, "check-baseline: junit report contains ZERO tests — refusing to pass vacuously\n");
    exit(1);
}

$allowed = [];
foreach (file($baselinePath) as $line) {
    // Pest test names contain spaces ("login requires email") — capture the whole
    // entry after "- ", not just a \S+::\S+ token (which fits PHPUnit method names).
    if (preg_match('/^- (\S+::.+)$/', trim($line), $m)) {
        $allowed[$m[1]] = true;
    }
}

$new = array_values(array_filter(array_unique($failing), fn (string $n): bool => ! isset($allowed[$n])));

$summary = sprintf(
    'check-baseline: %d tests, %d failing, %d allowed by manifest',
    $total,
    count(array_unique($failing)),
    count($allowed)
);

if ($new !== []) {
    fwrite(STDERR, $summary."\ncheck-baseline: FAIL — failing tests NOT in tests/BASELINE.md:\n");
    foreach ($new as $n) {
        fwrite(STDERR, "  NEW: {$n}\n");
    }
    exit(1);
}

$healed = array_diff(array_keys($allowed), array_unique($failing));
echo $summary."\n";
if ($healed !== [] && count($healed) <= 20) {
    echo 'check-baseline: '.count($healed)." manifest entries did not fail this run (candidates for removal)\n";
}
echo "check-baseline: OK\n";
exit(0);
