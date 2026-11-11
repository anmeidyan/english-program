<?php

// Generates PWA icons/screenshots with GD: php docker/php/make-icons.php
function icon(int $size, bool $maskable, string $file): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $blue = imagecolorallocate($im, 0x2F, 0x6F, 0xE4);
    $white = imagecolorallocate($im, 255, 255, 255);
    $gold = imagecolorallocate($im, 0xFF, 0xD1, 0x66);
    $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $maskable ? $blue : $clear);
    if (! $maskable) {
        $r = (int) ($size * 0.22);
        imagefilledrectangle($im, $r, 0, $size - $r, $size, $blue);
        imagefilledrectangle($im, 0, $r, $size, $size - $r, $blue);
        foreach ([[$r, $r], [$size - $r, $r], [$r, $size - $r], [$size - $r, $size - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, 2 * $r, 2 * $r, $blue);
        }
    }
    $s = $maskable ? 0.62 : 0.8;           // safe zone for maskable icons
    $o = $size * (1 - $s) / 2;
    $p = fn ($x, $y) => [(int) ($o + $x / 64 * $size * $s), (int) ($o + $y / 64 * $size * $s)];
    imagesetthickness($im, max(2, (int) ($size * $s * 5 / 64)));
    $pts = [$p(14, 44), $p(26, 32), $p(34, 38), $p(50, 20)];
    for ($i = 0; $i < 3; $i++) {
        imageline($im, $pts[$i][0], $pts[$i][1], $pts[$i + 1][0], $pts[$i + 1][1], $white);
    }
    [$cx, $cy] = $p(50, 20);
    $d = (int) ($size * $s * 10 / 64);
    imagefilledellipse($im, $cx, $cy, $d, $d, $gold);
    imagepng($im, $file);
}
function shot(int $w, int $h, string $file, string $title): void
{
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, 0xF8, 0xFA, 0xFC);
    $blue = imagecolorallocate($im, 0x2F, 0x6F, 0xE4);
    $card = imagecolorallocate($im, 255, 255, 255);
    $grey = imagecolorallocate($im, 0xCB, 0xD5, 0xE1);
    imagefill($im, 0, 0, $bg);
    imagefilledrectangle($im, 0, 0, $w, 64, $blue);
    imagestring($im, 5, 24, 24, 'EnglishPath - '.$title, $card);
    for ($i = 0; $i < 4; $i++) {
        $y = 100 + $i * (int) (($h - 140) / 4);
        imagefilledrectangle($im, 24, $y, $w - 24, $y + (int) (($h - 140) / 4) - 20, $card);
        imagefilledrectangle($im, 48, $y + 24, (int) ($w * 0.6), $y + 40, $grey);
        imagefilledrectangle($im, 48, $y + 56, (int) ($w * (0.3 + 0.1 * $i)), $y + 68, $blue);
    }
    imagepng($im, $file);
}
$out = $argv[1] ?? 'public';
icon(192, false, "$out/icons/icon-192.png");
icon(512, false, "$out/icons/icon-512.png");
icon(512, true, "$out/icons/icon-maskable-512.png");
icon(180, true, "$out/icons/apple-touch-icon.png");
shot(1280, 720, "$out/screenshots/desktop.png", 'Dashboard');
shot(540, 1170, "$out/screenshots/mobile.png", 'Lesson');
echo "ok\n";
