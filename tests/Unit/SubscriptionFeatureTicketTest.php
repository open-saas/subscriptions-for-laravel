<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Unit;

use Carbon\Carbon;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureTicket;
use OpenSaas\SubscriptionsForLaravel\Tests\TestCase;

class SubscriptionFeatureTicketTest extends TestCase
{
    public function test_ticket_is_not_expired_when_expires_at_is_null(): void
    {
        $ticket = new SubscriptionFeatureTicket();
        $ticket->expires_at = null;

        $this->assertTrue($ticket->isNotExpired());
    }

    public function test_ticket_is_not_expired_when_expires_at_is_in_the_future(): void
    {
        $ticket = new SubscriptionFeatureTicket();
        $ticket->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($ticket->isNotExpired());
    }

    public function test_ticket_is_expired_when_expires_at_is_in_the_past(): void
    {
        $ticket = new SubscriptionFeatureTicket();
        $ticket->expires_at = Carbon::now()->subDay();

        $this->assertFalse($ticket->isNotExpired());
    }

    public function test_ticket_is_active_when_not_expired(): void
    {
        $ticket = new SubscriptionFeatureTicket();
        $ticket->expires_at = null;

        $this->assertTrue($ticket->isActive());
    }

    public function test_ticket_is_not_active_when_expired(): void
    {
        $ticket = new SubscriptionFeatureTicket();
        $ticket->expires_at = Carbon::now()->subDay();

        $this->assertFalse($ticket->isActive());
    }
}
