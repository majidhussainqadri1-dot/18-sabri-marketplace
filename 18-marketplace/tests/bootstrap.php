<?php
define('ABSPATH', __DIR__ . '/');
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $code = '', private string $message = '', private $data = null) {}
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data() { return $this->data; }
    }
}
function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
function sanitize_key($key): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key)); }
function __($text, $domain = null): string { return (string) $text; }
function apply_filters($hook, $value, ...$args) { return $value; }
function assert_true($condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    fwrite(STDOUT, "PASS: {$message}\n");
}
function assert_same($expected, $actual, string $message): void {
    assert_true($expected === $actual, $message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
}
