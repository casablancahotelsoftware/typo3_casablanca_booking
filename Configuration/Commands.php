<?php

declare(strict_types=1);

use Casablanca\CasablancaBooking\Command\SyncAvailabilityCommand;

return [
    'casablanca-booking:sync' => SyncAvailabilityCommand::class,
];
