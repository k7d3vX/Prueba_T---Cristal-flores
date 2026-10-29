<?php

declare(strict_types=1);

namespace App\Support;

/** Una alerta para la dueña (RN-30). level: 'high' | 'warn'. */
final readonly class Alert
{
    public function __construct(
        public string $level,
        public string $message,
        public ?int $sessionId = null,
    ) {}
}
