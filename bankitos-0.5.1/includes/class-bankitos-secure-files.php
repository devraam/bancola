<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Secure_Files {
    private const META_PATH = '_bankitos_secure_path';
    private const META_NAME = '_bankitos_secure_name';

    public static function init(): void {
        add_action('delete_attachment', [__CLASS__, 'delete_protected_file']);
    }

    public static function protect_attachment(int $attachment_id): bool {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return false;
        }
        $storage = self::ensure_storage_dir();
        if (!$storage) {
            return false;
        }
        $target_dir = trailingslashit($storage['dir']) . $attachment_id;
        if (!wp_mkdir_p($target_dir)) {
            return false;
        }
        $filename = wp_unique_filename($target_dir, wp_basename($file));
        $target = trailingslashit($target_dir) . $filename;
        if (!self::move_file($file, $target)) {
            return false;
        }
        $relative = self::relative_to_uploads($target);
        if (!$relative) {
            return false;
        }
        update_post_meta($attachment_id, self::META_PATH, $relative);
        update_post_meta($attachment_id, self::META_NAME, wp_basename($file));
        update_attached_file($attachment_id, $relative);
        return true;
    }

    public static function get_protected_path(int $attachment_id): string {
        $relative = get_post_meta($attachment_id, self::META_PATH, true);
        if (!$relative) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        $path = trailingslashit($uploads['basedir']) . ltrim($relative, '/');
        return file_exists($path) ? $path : '';
    }

    public static function get_download_filename(int $attachment_id): string {
        $name = get_post_meta($attachment_id, self::META_NAME, true);
        if ($name) {
            return $name;
        }
        $path = self::get_protected_path($attachment_id);
        return $path ? wp_basename($path) : ('archivo-' . $attachment_id);
    }

    public static function delete_protected_file(int $attachment_id): void {
        $path = self::get_protected_path($attachment_id);
        if ($path && file_exists($path)) {
            wp_delete_file($path);
        }
        delete_post_meta($attachment_id, self::META_PATH);
        delete_post_meta($attachment_id, self::META_NAME);
    }

    private static function ensure_storage_dir(): array {
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['error'])) {
            return [];
        }
        $dir = trailingslashit($uploads['basedir']) . 'bankitos-private';
        if (!wp_mkdir_p($dir)) {
            return [];
        }
        self::ensure_access_controls($dir);
        return ['dir' => $dir];
    }

    private static function ensure_access_controls(string $dir): void {
        $htaccess = trailingslashit($dir) . '.htaccess';
        $htaccess_contents = "Options -Indexes\n";
        file_put_contents($htaccess, $htaccess_contents);
        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\nhttp_response_code(403); exit;\n");
        }
    }

    public static function get_protected_url(int $attachment_id): string {
        $relative = get_post_meta($attachment_id, self::META_PATH, true);
        if (!$relative) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['baseurl'])) {
            return '';
        }
        $url = trailingslashit($uploads['baseurl']) . ltrim($relative, '/');
        return $url;
    }
    
    private static function relative_to_uploads(string $path): string {
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['error'])) {
            return '';
        }
        $base = trailingslashit($uploads['basedir']);
        if (strpos($path, $base) !== 0) {
            return '';
        }
        return ltrim(str_replace($base, '', $path), '/');
    }

    private static function move_file(string $source, string $target): bool {
        if (@rename($source, $target)) {
            return true;
        }
        if (@copy($source, $target)) {
            @unlink($source);
            return true;
        }
        return false;
    }
}