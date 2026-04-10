<?php

namespace OpenSaas\SubscriptionsForLaravel\Enums;

enum FeatureType: string
{
    case Consumable = 'consumable';
    case Flag = 'flag';
    case Limit = 'limit';
}
