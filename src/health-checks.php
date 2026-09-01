<?php

declare(strict_types=1);

/**
 * Returns a map of check name => bool. Requires $config in scope. Shared
 * between the CLI's `health` command and the HTTP health endpoint.
 *
 * @var \Data2Md\Config\Config $config
 */

$dataDir = $config->string('APP_DATA_DIR');

$pdftotext = static function (): bool {
    $result = (new \Data2Md\Runtime\Process(10))->run(['pdftotext', '-v']);

    // pdftotext -v prints its banner to stderr and exits non-zero on some
    // builds; presence of the banner is the real signal.
    return str_contains($result->stderr . $result->stdout, 'pdftotext');
};

$writable = static function (string $dir): bool {
    return is_dir($dir) && is_writable($dir);
};

/**
 * A container serving requests against a schema it does not know about is
 * the state a failed or half-run migration leaves behind, and every other
 * check here passes while it happens.
 */
$schema = static function (string $path): bool {
    try {
        $version = (new \Data2Md\Storage\Database($path))->version();

        // Ahead is a rollback onto a newer volume, which DEPLOY.md documents
        // as supported: the older image serves what it understands.
        return $version >= \Data2Md\Storage\Database::latestVersion()
            || $version === 0;
    } catch (\Throwable) {
        return false;
    }
};

$database = static function (string $path): bool {
    try {
        return (new \Data2Md\Storage\Database($path))->pdo()->query('SELECT 1')->fetchColumn() !== false;
    } catch (\Throwable) {
        return false;
    }
};

return [
    'pdftotext' => $pdftotext(),
    'md_dir'    => $writable($dataDir . '/md'),
    'tmp_dir'   => $writable($dataDir . '/tmp'),
    'logs_dir'  => $writable($dataDir . '/logs'),
    'database'  => $database($dataDir . '/db/mappings.sqlite'),
    'schema'    => $schema($dataDir . '/db/mappings.sqlite'),
];
