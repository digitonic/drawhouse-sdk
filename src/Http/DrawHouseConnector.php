<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

final class DrawHouseConnector extends Connector
{
    use AcceptsJson;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => $this->timeout,
        ];
    }
}
