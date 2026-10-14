<?php

namespace App\Ai;

/**
 * An image that is safe to send to an AI provider (ADR-003 §6): decoded and re-encoded by GD, so
 * every EXIF/XMP/IPTC block (GPS, device, timestamps, owner) is gone, and downscaled to the
 * smallest size that keeps label text legible. The only way to attach an image to an AiRequest.
 */
final readonly class SanitisedImage
{
    /** Long edge in pixels — enough for label text, far below a phone photo. */
    public const MAX_EDGE = 1568;

    private function __construct(public string $bytes) {}

    /** @throws \InvalidArgumentException when the file is not a readable image */
    public static function fromFile(string $path, int $maxEdge = self::MAX_EDGE): self
    {
        $raw = is_file($path) ? file_get_contents($path) : false;

        // Check the format first: GD warns on unknown data instead of just returning false.
        $image = $raw === false || getimagesizefromstring($raw) === false ? false : imagecreatefromstring($raw);

        if ($image === false) {
            throw new \InvalidArgumentException('Not a readable image.');
        }

        // Apply the camera's rotation before the EXIF that records it is dropped,
        // otherwise portrait phone photos arrive sideways.
        $image = self::applyOrientation($image, $path);

        $width  = imagesx($image);
        $height = imagesy($image);
        $scale  = min(1, $maxEdge / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            $image   = $resized === false ? $image : $resized;
        }

        ob_start();
        imagejpeg($image, null, 85);

        return new self((string) ob_get_clean());
    }

    public function mediaType(): string
    {
        return 'image/jpeg';
    }

    public function base64(): string
    {
        return base64_encode($this->bytes);
    }

    private static function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        // Untrusted EXIF: a malformed block must not break the upload, only skip the rotation.
        $exif        = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
