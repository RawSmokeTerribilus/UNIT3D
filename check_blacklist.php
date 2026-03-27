<?php

require __DIR__ . '/bootstrap/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$blacklist = cache()->get(config('email-blacklist.cache-key'), []);
$suspiciousDomains = ['dralias.com', 'passinbox.com', 'simplelogin.com', 'catmx.eu'];

echo "=== Checking Suspicious Domains ===\n";
foreach($suspiciousDomains as $domain) {
    $found = in_array($domain, $blacklist, true) ? 'FOUND ✓' : 'NOT FOUND ✗';
    echo "{$domain}: {$found}\n";
}

echo "\n=== Blacklist Stats ===\n";
echo "Total domains in blacklist: " . count($blacklist) . "\n";

echo "\n=== Sample domains (first 30) ===\n";
$sample = array_slice($blacklist, 0, 30);
foreach($sample as $d) {
    echo "  - {$d}\n";
}

// Check if any of our suspicious domains are substring matches
echo "\n=== Substring Search ===\n";
foreach($suspiciousDomains as $domain) {
    $matches = array_filter($blacklist, function($d) use ($domain) {
        return stripos($d, $domain) !== false || stripos($domain, $d) !== false;
    });
    if($matches) {
        echo "{$domain}: Found " . count($matches) . " matches\n";
        foreach(array_slice($matches, 0, 3) as $m) echo "    - {$m}\n";
    }
}
