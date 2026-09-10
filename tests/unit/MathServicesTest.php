<?php

declare(strict_types=1);

namespace tests\unit;

use app\services\CoinConverter;
use app\services\PriceDeltaCalculator;
use app\services\VolumeAnomalyDetector;
use PHPUnit\Framework\TestCase;

final class MathServicesTest extends TestCase
{
    public function testPriceDeltaPercent(): void
    {
        $c = new PriceDeltaCalculator();
        $r = $c->calc(110.0, 100.0);
        self::assertSame(10.0, $r['delta_abs']);
        self::assertEqualsWithDelta(10.0, $r['delta_percent'], 0.0001);
    }

    public function testPriceDeltaWithoutPrevious(): void
    {
        $r = (new PriceDeltaCalculator())->calc(50.0, null);
        self::assertNull($r['delta_percent']);
    }

    public function testVolumeAnomaly(): void
    {
        $d = new VolumeAnomalyDetector();
        self::assertTrue($d->isAnomalous(200.0, [50.0, 60.0, 40.0], 2.0));
        self::assertFalse($d->isAnomalous(80.0, [50.0, 60.0, 40.0], 2.0));
    }

    public function testConvertBtcToEth(): void
    {
        // 1 BTC at $80k → ETH at $2k = 40 ETH
        $r = (new CoinConverter())->convert(1.0, 80000.0, 2000.0);
        self::assertEqualsWithDelta(40.0, $r['amount_to'], 0.0001);
        self::assertEqualsWithDelta(40.0, $r['rate'], 0.0001);
    }
}
