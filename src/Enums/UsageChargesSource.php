<?php

namespace OpenSaas\SubscriptionsForLaravel\Enums;

enum UsageChargesSource: string
{
    case Plan = 'plan';
    case Ticket = 'ticket';
}
