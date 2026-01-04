<?php

declare(strict_types=1);

namespace Blafast\Foundation\Enums;

enum DeferredRequestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
