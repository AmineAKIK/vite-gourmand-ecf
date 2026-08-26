<?php

declare(strict_types=1);

namespace App\Domain;

use App\Config\Configuration;
use App\Config\ConfigurationInvalidException;
use App\Geo\Exception\DeliveryOutOfRangeException;

final class DeliveryPolicy
{
    /** @param list<string> $freePostalCodes */
    public function __construct(
        private readonly float $originLatitude,
        private readonly float $originLongitude,
        private readonly int $radiusKm,
        private readonly array $freePostalCodes,
        private readonly int $baseFeeCents,
        private readonly int $perKmFeeCents,
    ) {
        if (!is_finite($this->originLatitude) || $this->originLatitude < -90 || $this->originLatitude > 90) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery.origin.latitude');
        }
        if (!is_finite($this->originLongitude) || $this->originLongitude < -180 || $this->originLongitude > 180) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery.origin.longitude');
        }
        if ($this->radiusKm <= 0) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery.radius_km');
        }
        if ($this->baseFeeCents < 0 || $this->perKmFeeCents < 0) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery pricing');
        }
        foreach ($this->freePostalCodes as $postalCode) {
            if (preg_match('/^\d{5}$/', $postalCode) !== 1) {
                throw new ConfigurationInvalidException('Configuration invalid: delivery.free_postal_codes');
            }
        }
    }

    public static function fromConfiguration(): self
    {
        $latitude = Configuration::get('delivery.origin.latitude');
        $longitude = Configuration::get('delivery.origin.longitude');
        $radiusKm = Configuration::get('delivery.radius_km');
        $freePostalCodes = Configuration::get('delivery.free_postal_codes');
        $baseFee = Configuration::get('delivery.base_fee');
        $perKmFee = Configuration::get('delivery.per_km_fee');

        if ((!is_float($latitude) && !is_int($latitude)) || (!is_float($longitude) && !is_int($longitude))) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery origin');
        }
        if (!is_int($radiusKm) || !is_string($baseFee) || !is_string($perKmFee)) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery policy');
        }
        if ($freePostalCodes !== null && !is_array($freePostalCodes)) {
            throw new ConfigurationInvalidException('Configuration invalid: delivery.free_postal_codes');
        }

        return new self(
            (float) $latitude,
            (float) $longitude,
            $radiusKm,
            array_values(array_unique(array_map('strval', $freePostalCodes ?? []))),
            Money::fromDecimal($baseFee),
            Money::fromDecimal($perKmFee),
        );
    }

    public function originLatitude(): float
    {
        return $this->originLatitude;
    }

    public function originLongitude(): float
    {
        return $this->originLongitude;
    }

    public function radiusKm(): int
    {
        return $this->radiusKm;
    }

    public function priceCents(float $distanceKm, string $postalCode): int
    {
        if (!is_finite($distanceKm) || $distanceKm < 0) {
            throw new \InvalidArgumentException('Distance de livraison invalide.');
        }

        if ($distanceKm > $this->radiusKm) {
            throw new DeliveryOutOfRangeException(
                sprintf(
                    'Cette adresse se trouve à %.1f km, au-delà du rayon de livraison de %d km.',
                    $distanceKm,
                    $this->radiusKm,
                ),
                $distanceKm,
                $this->radiusKm,
            );
        }

        if (in_array($postalCode, $this->freePostalCodes, true)) {
            return 0;
        }

        $distanceHundredthsKm = (int) round($distanceKm * 100);
        $variableCents = intdiv(($this->perKmFeeCents * $distanceHundredthsKm) + 50, 100);

        return $this->baseFeeCents + $variableCents;
    }

    public function pricingLabel(): string
    {
        $label = sprintf(
            'Livraison : %s € + %s €/km, dans un rayon maximal de %d km.',
            Money::toDecimalString($this->baseFeeCents),
            Money::toDecimalString($this->perKmFeeCents),
            $this->radiusKm,
        );

        if ($this->freePostalCodes !== []) {
            $label .= ' Certains codes postaux configurés sont gratuits.';
        }

        return $label;
    }
}
