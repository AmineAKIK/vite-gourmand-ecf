<?php

namespace App\Domain;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationMissingException;
use DateTimeImmutable;
use InvalidArgumentException;

final class BusinessPolicy
{
    /** @param callable(string):mixed $resolve */
    public function __construct(private readonly mixed $resolve)
    {
        if (!is_callable($this->resolve)) {
            throw new InvalidArgumentException('BusinessPolicy resolver must be callable.');
        }
    }

    public function minimumOrderLeadHours(): int
    {
        return $this->requiredInt('order.minimum_lead_hours', 1);
    }

    public function maximumOrderAdvanceDays(): int
    {
        return $this->requiredInt('order.maximum_advance_days', 1);
    }

    public function customerCancellationCutoffHours(): int
    {
        return $this->requiredInt('order.cancellation_cutoff_hours', 0);
    }

    /** @return list<string> */
    public function blackoutDates(): array
    {
        $value = ($this->resolve)('order.blackout_dates');
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new ConfigurationInvalidException('Configuration invalid: order.blackout_dates');
        }

        $dates = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new ConfigurationInvalidException('Configuration invalid: order.blackout_dates');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $item);
            if (!$date || $date->format('Y-m-d') !== $item) {
                throw new ConfigurationInvalidException('Configuration invalid: order.blackout_dates');
            }
            $dates[$item] = $item;
        }

        ksort($dates, SORT_STRING);
        return array_values($dates);
    }

    public function isBlackoutDate(string $date): bool
    {
        return in_array($date, $this->blackoutDates(), true);
    }

    public function quoteValidityDays(): int
    {
        return $this->requiredInt('quote.validity_days', 1);
    }

    public function materialReturnDays(): int
    {
        return $this->requiredInt('material.return_days', 0);
    }

    public function materialLateFeeCents(): int
    {
        return $this->requiredInt('material.late_fee_cents', 0);
    }

    /** @return list<int> */
    public function reminderDaysBefore(): array
    {
        $value = ($this->resolve)('reminder.order_days_before');
        if (!is_array($value) || $value === []) {
            throw new ConfigurationMissingException('Configuration required: reminder.order_days_before');
        }

        $days = [];
        foreach ($value as $item) {
            if (!is_string($item) || preg_match('/^\d+$/', $item) !== 1) {
                throw new ConfigurationInvalidException('Configuration invalid: reminder.order_days_before');
            }
            $day = (int) $item;
            if ($day < 1 || $day > 365) {
                throw new ConfigurationInvalidException('Configuration invalid: reminder.order_days_before');
            }
            $days[] = $day;
        }

        $days = array_values(array_unique($days));
        rsort($days, SORT_NUMERIC);
        return $days;
    }

    public function assertOrderSchedule(DateTimeImmutable $serviceAt, DateTimeImmutable $now): void
    {
        if ($this->isBlackoutDate($serviceAt->format('Y-m-d'))) {
            throw new InvalidArgumentException('Le traiteur est indisponible à cette date. Choisissez une autre date.');
        }

        $earliest = $now->modify('+' . $this->minimumOrderLeadHours() . ' hours');
        $latest = $now->modify('+' . $this->maximumOrderAdvanceDays() . ' days');

        if ($serviceAt < $earliest) {
            throw new InvalidArgumentException('La date de prestation ne respecte pas le délai minimum de commande configuré.');
        }
        if ($serviceAt > $latest) {
            throw new InvalidArgumentException('La date de prestation dépasse l’horizon maximal de réservation configuré.');
        }
    }

    private function requiredInt(string $key, int $minimum): int
    {
        $value = ($this->resolve)($key);
        if ($value === null) {
            throw new ConfigurationMissingException('Configuration required: ' . $key);
        }
        if (!is_int($value) || $value < $minimum) {
            throw new ConfigurationInvalidException('Configuration invalid: ' . $key);
        }

        return $value;
    }
}
