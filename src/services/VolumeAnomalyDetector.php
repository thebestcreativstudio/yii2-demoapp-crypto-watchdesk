<?php

declare(strict_types=1);

namespace app\services;

/**
 * Unusual volume = current volume >= average(previous volumes) * ratio
 *
 * Example: average of last 6 snaps = 1M, ratio 2.0 → flag if volume >= 2M
 */
final class VolumeAnomalyDetector
{
    /**
     * @param list<float> $previousVolumes oldest→newest or any order (we average all)
     */
    public function isAnomalous(float $currentVolume, array $previousVolumes, float $ratio = 2.0): bool
    {
        if ($previousVolumes === [] || $currentVolume <= 0) {
            return false;
        }
        $avg = array_sum($previousVolumes) / count($previousVolumes);
        if ($avg <= 0) {
            return false;
        }
        return $currentVolume >= ($avg * $ratio);
    }
}
