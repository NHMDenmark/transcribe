<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Profile photos: re-encoded as a square JPEG (so nothing but pixels survives the upload),
 * kept in public/avatars like the project covers and the branding files.
 *
 * Needs PHP's GD extension, which the app already depends on (the Excel reader requires it), but
 * which image formats a given GD build can read varies from server to server, so the formats on
 * offer come from what this server's GD reports instead of being assumed.
 */
class AvatarStore
{
    public const SIZE = 256;

    /** The exception codes: the image is the problem (try another), or the server is (nothing to do with the image). */
    public const IMAGE_PROBLEM = 1;
    public const SERVER_PROBLEM = 2;

    /** File extensions this server's GD can read (empty if there is no usable GD). */
    public static function formats(): array
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            return [];
        }

        $info = gd_info();
        $formats = [];
        if (!empty($info['JPEG Support'])) {
            $formats[] = 'jpg';
            $formats[] = 'jpeg';
        }
        if (!empty($info['PNG Support'])) {
            $formats[] = 'png';
        }
        if (!empty($info['WebP Support']) && function_exists('imagecreatefromwebp')) {
            $formats[] = 'webp';
        }

        return $formats;
    }

    /** Photos are written as JPEG, so uploads are only possible when GD can do that. */
    public static function available(): bool
    {
        return in_array('jpg', self::formats(), true) && function_exists('imagejpeg');
    }

    public function directory(): string
    {
        return public_path('avatars');
    }

    /**
     * Stores the upload as the user's photo, replacing the previous one. Returns the file name.
     *
     * @throws \RuntimeException when the image cannot be read or saved
     */
    public function store(UploadedFile $file, User $user): string
    {
        if (!self::available()) {
            throw new \RuntimeException('Photos are not available on this server', self::SERVER_PROBLEM);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new \RuntimeException('Not a readable image', self::IMAGE_PROBLEM);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));   // transparent PNG/WebP end up on white
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        if (!is_dir($this->directory()) && !@mkdir($this->directory(), 0755, true) && !is_dir($this->directory())) {
            throw new \RuntimeException('The photo folder cannot be created', self::SERVER_PROBLEM);
        }

        $name = 'u' . $user->id . '_' . time() . '.jpg';
        $saved = @imagejpeg($square, $this->directory() . DIRECTORY_SEPARATOR . $name, 85);
        imagedestroy($source);
        imagedestroy($square);

        if (!$saved) {
            throw new \RuntimeException('The photo could not be saved', self::SERVER_PROBLEM);
        }

        $this->delete($user);
        $user->avatar = $name;
        $user->save();

        return $name;
    }

    /** Removes the user's photo (file and reference). */
    public function remove(User $user): void
    {
        $this->delete($user);
        $user->avatar = null;
        $user->save();
    }

    private function delete(User $user): void
    {
        $old = (string) $user->avatar;
        // only ever touch a file this class could have written
        if ($old !== '' && preg_match('/^u\d+_\d+\.jpg$/', $old)) {
            $path = $this->directory() . DIRECTORY_SEPARATOR . $old;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    /**
     * Writes what a failed upload needs to be understood by whoever looks after the server: where it tried to
     * write, whether that exists and is writable, and as which user PHP runs.
     */
    public function logFailure(\RuntimeException $e): void
    {
        $dir = $this->directory();
        \Illuminate\Support\Facades\Log::error('Profile photo failed: ' . $e->getMessage(), [
            'directory' => $dir,
            'exists' => is_dir($dir),
            'writable' => is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir)),
            'php_user' => function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : get_current_user(),
        ]);
    }
}
