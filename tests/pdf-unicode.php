<?php
declare(strict_types=1);

// Run the production renderer with a temporary, in-memory notice. No live DB or email.
$output = $argv[1] ?? null;
if (!$output) {
    fwrite(STDERR, "Usage: php tests/pdf-unicode.php output.pdf\n");
    exit(2);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE cleanup_notices (
    id INTEGER PRIMARY KEY, site_name TEXT, location TEXT, issued_to TEXT,
    issued_by TEXT, issued_at TEXT, status TEXT, closed_at TEXT,
    reason TEXT, description TEXT, urgency TEXT, deadline_at TEXT,
    completed_ok TEXT, mcgoff_clear TEXT, signature_path TEXT
)');
$pdo->exec('CREATE TABLE cleanup_photos (id INTEGER PRIMARY KEY, notice_id INTEGER, path TEXT)');

$image = realpath(__DIR__ . '/../assets/icons/icon-96.png');
if (!$image) throw new RuntimeException('Fixture image is missing');
$pdo->prepare('INSERT INTO cleanup_notices VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([
        'TEST — École Straße Καλημέρα Привет',
        'Málaga – Zürich № 7',
        'Zoë Łódź',
        'TEST Automation',
        '2026-10-01 18:06:00',
        'open',
        null,
        'Unicode punctuation: — – “quotes”',
        'Synthetic photo and signature; no real site defect.',
        'This week',
        '2026-10-02 18:07:00',
        'yes',
        'no',
        $image,
    ]);
$pdo->prepare('INSERT INTO cleanup_photos (notice_id, path) VALUES (1, ?)')->execute([$image]);

define('PDF_CAPTURE_MODE', true);
$NOTICE_ID = 1;
ob_start();
require __DIR__ . '/../forms/clean-up/pdf.php';
$bytes = ob_get_clean();
if (!str_starts_with($bytes, '%PDF-')) throw new RuntimeException('Renderer did not return a PDF');
file_put_contents($output, $bytes);
echo "Wrote $output (" . strlen($bytes) . " bytes)\n";
