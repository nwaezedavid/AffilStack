<?php

namespace App\Support\Seo;

use InvalidArgumentException;

/**
 * Writes a real Windows ICO container (not a renamed PNG) holding one or
 * more PNG-compressed images — the "PNG in ICO" layout every current
 * browser and crawler (including Googlebot-Image) reads. Written by hand
 * because GD has no ICO encoder and Imagick isn't available on Hostinger.
 *
 * Layout: ICONDIR (6 bytes) + one ICONDIRENTRY (16 bytes) per image + the
 * PNG payloads back to back.
 */
final class IcoEncoder
{
    /**
     * @param  array<int, string>  $pngImages  raw PNG bytes, each no larger than 256×256
     */
    public static function encode(array $pngImages): string
    {
        if ($pngImages === []) {
            throw new InvalidArgumentException('An ICO needs at least one image.');
        }

        $count = count($pngImages);
        $header = pack('vvv', 0, 1, $count);
        $directory = '';
        $payload = '';
        $offset = 6 + (16 * $count);

        foreach (array_values($pngImages) as $png) {
            $size = self::pngSize($png);

            if ($size === null || $size[0] > 256 || $size[1] > 256) {
                throw new InvalidArgumentException('Every ICO image must be a PNG of at most 256×256.');
            }

            $directory .= pack(
                'CCCCvvVV',
                $size[0] === 256 ? 0 : $size[0],
                $size[1] === 256 ? 0 : $size[1],
                0,
                0,
                1,
                32,
                strlen($png),
                $offset,
            );

            $payload .= $png;
            $offset += strlen($png);
        }

        return $header.$directory.$payload;
    }

    /**
     * Reads the directory entries back — used by the admin health check
     * and the tests.
     *
     * @return array<int, array{width: int, height: int, bytes: int, offset: int}>
     */
    public static function entries(string $ico): array
    {
        if (strlen($ico) < 6) {
            return [];
        }

        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));

        if ($header['reserved'] !== 0 || $header['type'] !== 1) {
            return [];
        }

        $entries = [];

        for ($i = 0; $i < $header['count']; $i++) {
            $raw = substr($ico, 6 + ($i * 16), 16);

            if (strlen($raw) < 16) {
                return [];
            }

            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbpp/Vbytes/Voffset', $raw);
            $entries[] = [
                'width' => $entry['width'] ?: 256,
                'height' => $entry['height'] ?: 256,
                'bytes' => $entry['bytes'],
                'offset' => $entry['offset'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public static function pngSize(string $png): ?array
    {
        if (! str_starts_with($png, "\x89PNG\r\n\x1a\n") || strlen($png) < 24) {
            return null;
        }

        $dimensions = unpack('Nwidth/Nheight', substr($png, 16, 8));

        return [$dimensions['width'], $dimensions['height']];
    }
}
