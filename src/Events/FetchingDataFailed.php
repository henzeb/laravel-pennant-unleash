<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Events;

use Exception;

class FetchingDataFailed
{
    public function __construct(
        public readonly Exception $exception,
    ) {
    }
}
