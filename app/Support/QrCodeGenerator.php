<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QrCodeGenerator — thin wrapper around bacon/bacon-qr-code (already a
 * composer dependency, used today only via the Google2FA TOTP wrapper in
 * TwoFactorController). This class generates a QR for any arbitrary string
 * (e.g. a /present/{token} microsite URL) rather than a TOTP URI, so it
 * doesn't go through the 2FA class.
 *
 * Renders to an inline SVG data URI — safe to drop straight into an <img
 * src="..."> in a Blade view (print templates run in a headless browser/PDF
 * context with no network access, so a data URI avoids a broken image).
 */
class QrCodeGenerator
{
    public static function dataUri(string $content, int $size = 160, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
