<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;

class ExpirationService
{
    public function getExpirationFor(PeriodicityType $periodicityType, int $periodicityValue, DateTimeInterface $startDate): DateTimeImmutable
    {
        $interval = $this->periodicityTypeToInterval($periodicityType, $periodicityValue);

        return DateTimeImmutable::createFromInterface($startDate)->add($interval);
    }

    public function getNextCyclicExpirationFor(PeriodicityType $periodicityType, int $periodicityValue, DateTimeInterface $startDate): DateTimeImmutable
    {
        $now = new DateTimeImmutable();
        $expirationCandidate = DateTimeImmutable::createFromInterface($startDate);

        do {
            $expirationCandidate = $this->getExpirationFor($periodicityType, $periodicityValue, $expirationCandidate);
        } while ($expirationCandidate < $now);

        return $expirationCandidate;
    }

    private function periodicityTypeToInterval(PeriodicityType $periodicityType, int $periodicityValue): DateInterval
    {
        return match ($periodicityType) {
            PeriodicityType::Hourly => new DateInterval("PT{$periodicityValue}H"),
            PeriodicityType::Daily => new DateInterval("P{$periodicityValue}D"),
            PeriodicityType::Weekly => new DateInterval("P{$periodicityValue}W"),
            PeriodicityType::Monthly => new DateInterval("P{$periodicityValue}M"),
            PeriodicityType::Yearly => new DateInterval("P{$periodicityValue}Y"),
        };
    }
}
