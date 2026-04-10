<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Unit;

use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Services\ExpirationService;
use PHPUnit\Framework\TestCase;

class ExpirationServiceTest extends TestCase
{
    private ExpirationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExpirationService();
    }

    public function test_it_calculates_hourly_expiration(): void
    {
        $start = new DateTimeImmutable('2024-01-01 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Hourly, 2, $start);

        $this->assertEquals('2024-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_daily_expiration(): void
    {
        $start = new DateTimeImmutable('2024-01-01 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Daily, 5, $start);

        $this->assertEquals('2024-01-06 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_weekly_expiration(): void
    {
        $start = new DateTimeImmutable('2024-01-01 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Weekly, 2, $start);

        $this->assertEquals('2024-01-15 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_monthly_expiration(): void
    {
        $start = new DateTimeImmutable('2024-01-15 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Monthly, 1, $start);

        $this->assertEquals('2024-02-15 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_yearly_expiration(): void
    {
        $start = new DateTimeImmutable('2024-01-01 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Yearly, 1, $start);

        $this->assertEquals('2025-01-01 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_it_returns_immutable_date(): void
    {
        $start = new DateTimeImmutable('2024-01-01 10:00:00');
        $result = $this->service->getExpirationFor(PeriodicityType::Daily, 1, $start);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function test_it_calculates_next_cyclic_expiration_in_the_future(): void
    {
        $start = new DateTimeImmutable('2020-01-01 00:00:00');
        $result = $this->service->getNextCyclicExpirationFor(PeriodicityType::Yearly, 1, $start);

        $now = new DateTimeImmutable();
        $this->assertGreaterThanOrEqual($now, $result);
    }

    public function test_cyclic_expiration_iterates_from_start(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00');
        $result = $this->service->getNextCyclicExpirationFor(PeriodicityType::Monthly, 1, $start);

        $this->assertEquals('01', $result->format('d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }
}
