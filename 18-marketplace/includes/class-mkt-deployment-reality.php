<?php
/**
 * File18 read-only deployed reality snapshot.
 * A verified installed file manifest is NOT proof of exact GitHub parity or
 * archive SHA equivalence. Never reveal salts, credentials or private rows.
 */
defined('ABSPATH') || exit;
final class MKT_Deployment_Reality {
    private const MAX_FILES = 500;
    private const MAX_MANIFEST_BYTES = 131072;
    private const MAX_FILE_BYTES = 8388608;

    public static function snapshot(): array {
        global $wpdb;
        $root = rtrim(MKT_DIR, '/\\') . DIRECTORY_SEPARATOR;
        $markers = [
            'mkt_plugin_version',
            'mkt_schema_version',
            'mkt_governance_schema_version',
            'mkt_plan_completion_schema_version',
            'mkt_legacy_migration_version',
            'mkt_schema_error',
            'mkt_schema_verified_at',
        ];
        $safe_options = [];
        foreach ($markers as $key) {
            $value = get_option($key, '');
            $safe_options[$key] = is_scalar($value) ? substr((string) $value, 0, 80) : 'unknown';
        }
        return [
            'source_manifest' => self::verify_manifest($root),
            'runtime' => [
                'plugin' => defined('MKT_VERSION') ? MKT_VERSION : 'unknown',
                'schema_expected' => defined('MKT_SCHEMA_VERSION') ? MKT_SCHEMA_VERSION : 'unknown',
                'php' => PHP_VERSION,
                'wordpress' => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : 'unknown',
                'mysql' => is_object($wpdb) && method_exists($wpdb, 'db_version') ? (string) $wpdb->db_version() : 'unknown',
            ],
            'migration_markers' => $safe_options,
            'archive_sha256' => null,
            'repository_head' => 'unverified',
            'deployment_parity' => 'unverified',
            'live_acceptance' => 'unverified',
        ];
    }

    public static function verify_manifest(string $root): array {
        $base = [
            'status' => 'unverified',
            'files_checked' => 0,
            'mismatch_count' => 0,
            'extra_file_count' => 0,
            'manifest_sha256' => '',
        ];
        $root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $path = $root . 'MANIFEST.sha256';
        if (!is_file($path) || is_link($path)) {
            $base['status'] = 'manifest_missing';
            return $base;
        }
        $bytes = @filesize($path);
        if ($bytes === false || $bytes < 70 || $bytes > self::MAX_MANIFEST_BYTES) {
            $base['status'] = 'manifest_unreadable';
            return $base;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || !$lines || count($lines) > self::MAX_FILES) {
            $base['status'] = 'manifest_unreadable';
            return $base;
        }
        $base['manifest_sha256'] = (string) (@hash_file('sha256', $path) ?: '');
        $seen = [];
        foreach ($lines as $line) {
            if (!is_string($line) || !preg_match('~^([a-f0-9]{64})  \./([A-Za-z0-9][A-Za-z0-9._/-]*)$~D', $line, $m)) {
                $base['status'] = 'manifest_invalid';
                return $base;
            }
            $relative = $m[2];
            if (isset($seen[$relative]) || preg_match('~(^|/)\.\.?(/|$)|//~', $relative)) {
                $base['status'] = 'manifest_invalid';
                return $base;
            }
            $seen[$relative] = true;
            $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $fileBytes = @filesize($file);
            if (!is_file($file) || is_link($file) || $fileBytes === false || $fileBytes > self::MAX_FILE_BYTES) {
                $base['mismatch_count']++;
            } else {
                $actual = @hash_file('sha256', $file);
                if (!is_string($actual) || !hash_equals($m[1], $actual)) {
                    $base['mismatch_count']++;
                }
            }
            $base['files_checked']++;
        }
        if (!isset($seen['marketplace.php'])) {
            $base['status'] = 'manifest_invalid';
            return $base;
        }
        // A checksum manifest cannot certify unknown files omitted from it.
        // Inventory only the plugin directory, never the WordPress installation.
        try {
            $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
            $walk = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);
            $total = 0;
            foreach ($walk as $file) {
                if (++$total > self::MAX_FILES + 20) {
                    $base['status'] = 'inventory_limit';
                    return $base;
                }
                $relative = substr($file->getPathname(), strlen($root));
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                if ($relative === 'MANIFEST.sha256') continue;
                if (!isset($seen[$relative])) $base['extra_file_count']++;
            }
        } catch (Throwable $e) {
            $base['status'] = 'inventory_unavailable';
            return $base;
        }
        $base['status'] = $base['mismatch_count'] === 0 && $base['extra_file_count'] === 0
            ? 'verified' : 'mismatch';
        return $base;
    }
}
