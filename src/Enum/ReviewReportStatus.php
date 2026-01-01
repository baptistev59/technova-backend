<?php

declare(strict_types=1);

namespace App\Enum;

enum ReviewReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
