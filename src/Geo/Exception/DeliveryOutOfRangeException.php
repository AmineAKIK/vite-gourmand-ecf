<?php

namespace App\Geo\Exception;

class DeliveryOutOfRangeException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly float $distanceKm,
        private readonly int   $rayonMaxKm,
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getDistanceKm(): float { return $this->distanceKm; }
    public function getRayonMaxKm(): int   { return $this->rayonMaxKm; }
}
