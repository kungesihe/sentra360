<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Restricted image uploads: size-limited, MIME sniffed from content (not the
 * client-supplied type), random file names, and the uploads folder never
 * executes scripts (see public/uploads/.htaccess). SVG is refused (can carry script).
 */
final class Upload
{
    private const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * Validate an uploaded image and return its bytes, for storing in the database.
     * @return array{data?:string, mime?:string, error?:string}
     */
    public static function imageData(array $file, int $maxBytes = 1048576): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'The upload failed. Please try again.'];
        }
        if ($file['size'] > $maxBytes) {
            return ['error' => 'The image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return ['error' => 'Only PNG, JPG or WEBP images are allowed.'];
        }
        return ['data' => (string) file_get_contents($file['tmp_name']), 'mime' => $mime];
    }

    /** @return array{path?:string, error?:string} */
    public static function image(array $file, string $subdir, int $maxBytes = 1048576): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'The upload failed. Please try again.'];
        }
        if ($file['size'] > $maxBytes) {
            return ['error' => 'The image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return ['error' => 'Only PNG, JPG or WEBP images are allowed.'];
        }
        if (!preg_match('#^[a-z0-9/_-]+$#', $subdir)) {
            throw new \InvalidArgumentException('Bad upload directory');
        }
        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'Could not store the file.'];
        }
        $name = bin2hex(random_bytes(12)) . '.' . self::IMAGE_TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            return ['error' => 'Could not store the file.'];
        }
        return ['path' => '/uploads/' . $subdir . '/' . $name];
    }
}
