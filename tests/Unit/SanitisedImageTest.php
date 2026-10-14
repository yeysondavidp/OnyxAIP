<?php

use App\Ai\SanitisedImage;

/** A JPEG with an EXIF block carrying a description and an orientation, like a phone photo. */
function jpegWithExif(int $width, int $height, int $orientation, string $description): string
{
    $gd = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($gd);
    $jpeg = (string) ob_get_clean();

    // Little-endian TIFF: IFD0 with Orientation (SHORT) and ImageDescription (ASCII).
    $desc = $description."\0";
    $ifd  = pack('v', 2)
        .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
        .pack('vvVV', 0x010E, 2, strlen($desc), 8 + 2 + 24 + 4)
        .pack('V', 0);
    $tiff  = 'II'.pack('v', 42).pack('V', 8).$ifd.$desc;
    $app1  = "Exif\0\0".$tiff;
    $block = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    $path = tempnam(sys_get_temp_dir(), 'exif-');
    file_put_contents($path, substr($jpeg, 0, 2).$block.substr($jpeg, 2));

    return $path;
}

it('removes every metadata block from the image it sends', function () {
    $image = SanitisedImage::fromFile(jpegWithExif(64, 32, 1, 'SECRET-GPS-AND-OWNER'));

    expect([str_contains($image->bytes, 'SECRET-GPS-AND-OWNER'), str_contains($image->bytes, "Exif\0\0")])
        ->toBe([false, false]);
});

it('applies the camera rotation before dropping the EXIF that records it', function () {
    $image = SanitisedImage::fromFile(jpegWithExif(200, 100, 6, 'x'));
    $size  = getimagesizefromstring($image->bytes);

    expect([$size[0], $size[1]])->toBe([100, 200]);
});

it('downscales large photos to the label-reading size', function () {
    $gd   = imagecreatetruecolor(3000, 1000);
    $path = tempnam(sys_get_temp_dir(), 'big-');
    imagejpeg($gd, $path);

    $size = getimagesizefromstring(SanitisedImage::fromFile($path)->bytes);

    expect([$size[0], $size[1]])->toBe([SanitisedImage::MAX_EDGE, 523]);
});

it('refuses a file that is not an image', function () {
    $path = tempnam(sys_get_temp_dir(), 'txt-');
    file_put_contents($path, 'not an image');

    SanitisedImage::fromFile($path);
})->throws(InvalidArgumentException::class);
