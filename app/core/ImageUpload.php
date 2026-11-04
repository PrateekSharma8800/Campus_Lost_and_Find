<?php
/**
 * Secure photo uploads.
 *
 * Every photo goes through these checks:
 *  1. PHP reported no upload error and the file really was uploaded.
 *  2. Size is at most 2 MB.
 *  3. The REAL file type (read from the file's content, not its name) is
 *     JPEG, PNG or WebP.
 *  4. GD can open it as an image with sensible dimensions.
 * The image is then RE-DRAWN with GD and saved under a random name. Re-drawing
 * removes anything hidden inside the file (scripts, EXIF/GPS location data).
 * Files are stored in storage/ (outside public/) and served by PHP.
 */

declare(strict_types=1);

final class ImageUpload
{
    public const MAX_FILES     = 3;
    public const MAX_BYTES     = 2 * 1024 * 1024;   // 2 MB (matches PHP's default upload limit)
    public const MAX_WIDTH     = 1600;               // bigger photos are scaled down
    public const MAX_DIMENSION = 8000;               // refuse absurd sizes (memory protection)

    /** Allowed MIME type => file extension */
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * Turn $_FILES['photos'] (multiple input) into a simple list of files,
     * skipping empty slots where no file was chosen.
     */
    public static function filesFromRequest(string $field): array
    {
        $input = $_FILES[$field] ?? null;
        if (!is_array($input) || !is_array($input['name'] ?? null)) {
            return [];
        }
        $files = [];
        foreach ($input['name'] as $i => $name) {
            if (($input['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => (string) $name,
                'tmp_name' => (string) $input['tmp_name'][$i],
                'error'    => (int) $input['error'][$i],
                'size'     => (int) $input['size'][$i],
            ];
        }
        return $files;
    }

    /** Returns an error message, or null if the file is a valid photo. */
    public static function validate(array $file): ?string
    {
        $name = mb_substr($file['name'], 0, 60);

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return "\"$name\" is larger than 2 MB.";
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return "\"$name\" could not be uploaded. Please try again.";
        }
        if ($file['size'] > self::MAX_BYTES) {
            return "\"$name\" is larger than 2 MB.";
        }

        // Check the real content type (a .jpg name proves nothing).
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            return "\"$name\" is not a JPG, PNG or WebP image.";
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || $info['mime'] !== $mime
            || $info[0] < 1 || $info[1] < 1
            || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            return "\"$name\" is not a valid image.";
        }
        return null;
    }

    /**
     * Re-draw and save a validated upload.
     * @return array{file_name: string, mime_type: string, file_size: int}
     */
    public static function save(array $file): array
    {
        $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if ($image === false) {
            throw new RuntimeException('Could not read image');
        }

        if ($mime === 'image/jpeg') {
            $image = self::fixPhoneRotation($image, $file['tmp_name']);
        }

        // Scale down very large photos to save space and load faster.
        if (imagesx($image) > self::MAX_WIDTH) {
            $image = imagescale($image, self::MAX_WIDTH);
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];   // e.g. 3f9a...c1.jpg
        $path = UPLOAD_PATH . '/' . $fileName;

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 85),
            'image/png'  => imagesavealpha($image, true) && imagepng($image, $path, 6),
            'image/webp' => imagewebp($image, $path, 85),
        };
        if (!$ok) {
            throw new RuntimeException('Could not save image');
        }

        return ['file_name' => $fileName, 'mime_type' => $mime, 'file_size' => (int) filesize($path)];
    }

    public static function deleteFile(string $fileName): void
    {
        // basename() makes sure we can only ever delete inside the uploads folder.
        $path = UPLOAD_PATH . '/' . basename($fileName);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Phone photos store rotation in EXIF; apply it before EXIF is discarded. */
    private static function fixPhoneRotation(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        return $angle !== 0 ? imagerotate($image, $angle, 0) : $image;
    }
}
