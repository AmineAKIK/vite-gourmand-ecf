<?php

declare(strict_types=1);

namespace App\Geo;

interface GeocodingProvider
{
    /** @return array{0:float,1:float}|null */
    public function geocodeCity(string $city): ?array;

    /** @return array{label:string,city:string,postcode:string,lat:float,lng:float,score:float,fallback:bool}|null */
    public function resolveAddress(string $address, string $city, string $postalCode): ?array;
}
