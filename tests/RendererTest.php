<?php

declare(strict_types=1);

namespace VietQr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VietQr\Payment;
use VietQr\Rendering\BaconQrRenderer;
use VietQr\Rendering\QrRenderer;

final class RendererTest extends TestCase
{
    public function testSvgAndPngAreStandaloneImagesAtRequestedDimensions(): void
    {
        $renderer = new BaconQrRenderer();
        self::assertInstanceOf(QrRenderer::class, $renderer);
        $payment = new Payment('970403', '0011012345678', 180000, 'ORDER001');
        $svg = $renderer->svg($payment);
        $xml = new \DOMDocument();
        self::assertTrue($xml->loadXML($svg, LIBXML_NONET));
        self::assertSame('svg', $xml->documentElement->localName);
        self::assertSame('320', $xml->documentElement->getAttribute('width'));
        self::assertSame(0, $xml->getElementsByTagName('script')->length);
        self::assertSame(0, $xml->getElementsByTagName('image')->length);
        $png = $renderer->png($payment);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $size = getimagesizefromstring($png);
        self::assertSame(320, $size[0]);
        self::assertSame(320, $size[1]);
        self::assertSame('image/png', $size['mime']);
    }

    public function testRendererCanBeReusedWithoutLeakingPreviousPayment(): void
    {
        $renderer = new BaconQrRenderer();
        $first = new Payment('970403', '001', 1);
        $second = new Payment('970403', '002', 2);
        $before = $renderer->png($first, 256);
        self::assertNotSame($before, $renderer->png($second, 256));
        self::assertSame($before, $renderer->png($first, 256));
    }

    #[DataProvider('invalidSizes')]
    public function testRejectsExcessiveAllocationsAndUndersizedQrCodes(int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BaconQrRenderer())->png(new Payment('970403', '001'), $size);
    }

    public static function invalidSizes(): iterable
    {
        foreach ([-1, 0, 1, 255, 2049, PHP_INT_MAX] as $value) {
            yield (string) $value => [$value];
        }
    }
}
