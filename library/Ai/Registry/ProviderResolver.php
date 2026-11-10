<?php

declare(strict_types=1);

namespace Ai\Registry;

use Ai\Contract\ProviderInterface;
use Ai\Exception\AiException;
use Ai\Storage\ProviderRepository;

final class ProviderResolver
{
    public function __construct(private ProviderRepository $providers, private ProviderRegistry $registry)
    {
    }

    public function get(string $code): ProviderInterface
    {
        $config = $this->providers->findByCode($code);
        if ($config === null || !$config->active) {
            throw new AiException('Доставчикът не е наличен.');
        }

        return $this->registry->create($config, $this->providers->apiKey($code) ?? '');
    }
}
