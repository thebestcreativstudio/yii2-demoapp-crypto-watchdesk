<?php

declare(strict_types=1);

/**
 * App knobs (not Docker/infra).
 *
 * Unusual volume: current 24h volume >= average(last N snapshots) * ratio.
 */
return [
    'anomalyLookback' => 7,
    'anomalyVolumeRatio' => 2.0,
];
