<?php

namespace App\Support;

final readonly class PushPayload
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url, // a path such as /h/1/transactions/9
        public string $tag, // the same tag replaces a notification instead of stacking
    ) {}
}
