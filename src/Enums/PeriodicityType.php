<?php

namespace OpenSaas\SubscriptionsForLaravel\Enums;

enum PeriodicityType: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
