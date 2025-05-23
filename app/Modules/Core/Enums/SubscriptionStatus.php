<?php

namespace App\Modules\Core\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
    case TRIAL = 'trial';
    case EXPIRED = 'expired';
    case FREE = 'free';
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::CANCELED => 'Canceled',
            self::TRIAL => 'Trial',
            self::EXPIRED => 'Expired',
            self::FREE => 'Free',
        };
    }
}
