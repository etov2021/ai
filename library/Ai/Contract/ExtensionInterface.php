<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Extension\ExtensionRegistry;

/** Входна точка на разширение. register() само добавя в регистъра: без заявки към базата и без обръщения към услугите на AI. */
interface ExtensionInterface
{
    public function name(): string;

    public function register(ExtensionRegistry $registry): void;
}
