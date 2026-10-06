<?php
declare(strict_types=1);

function offline_reply(int $status, array $body): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function offline_schema(PDO $pdo): void {
    $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('cleanup_notices', 'cleanup_photos')")->fetchAll(PDO::FETCH_COLUMN);
    if (count($engines) !== 2 || count(array_filter($engines, static fn($engine) => strtoupper((string)$engine) !== 'INNODB')) !== 0)
        throw new RuntimeException('Notice tables must use InnoDB for safe offline sync.');
    $pdo->exec("CREATE TABLE IF NOT EXISTS notice_submission_receipts (
        user_name VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        client_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        notice_id BIGINT UNSIGNED NULL,
        mail_state VARCHAR(20) NOT NULL DEFAULT 'pending',
        recipient_count INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_name, client_id), INDEX (notice_id)
    ) ENGINE=InnoDB");
}
function offline_receipt(PDO $pdo, string $owner, string $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM notice_submission_receipts WHERE user_name = ? AND client_id = ?');
    $stmt->execute([$owner, $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
function offline_receipt_body(array $receipt): array {
    return ['success' => true, 'id' => (int)$receipt['notice_id'],
        'pdfUrl' => '/api/offline_asset.php?id='.(int)$receipt['notice_id'].'&kind=pdf',
        'successUrl' => '/forms/clean-up/success.php?id='.(int)$receipt['notice_id'],
        'emailStatus' => $receipt['mail_state'] === 'sending' ? 'uncertain' : $receipt['mail_state'],
        'recipientCount' => (int)$receipt['recipient_count']];
}
function offline_payload_hash(array $fields, array $files): string {
    unset($fields['csrf_token'], $fields['client_submission_id'], $fields['offline_owner']);
    ksort($fields);
    $hash = hash_init('sha256');
    hash_update($hash, json_encode($fields, JSON_INVALID_UTF8_SUBSTITUTE));
    ksort($files);
    foreach ($files as $name => $group) {
        $temps = is_array($group['tmp_name']) ? $group['tmp_name'] : [$group['tmp_name']];
        $errors = is_array($group['error']) ? $group['error'] : [$group['error']];
        foreach ($temps as $i => $tmp) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                throw new InvalidArgumentException('A photo could not be uploaded. Reduce its size and retry.');
            }
            hash_update($hash, $name.':'.hash_file('sha256', $tmp));
        }
    }
    return hash_final($hash);
}
function offline_cleanup_directory(string $directory): void {
    if (!is_dir($directory)) return;
    foreach (new DirectoryIterator($directory) as $file) {
        if ($file->isFile()) @unlink($file->getPathname());
    }
    @rmdir($directory);
}
