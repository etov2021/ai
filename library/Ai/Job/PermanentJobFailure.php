<?php

declare(strict_types=1);

namespace Ai\Job;

use Ai\Exception\AiException;

/** Грешка, която повторен опит няма да оправи (липсващ агент в данните, забранен достъп). */
final class PermanentJobFailure extends AiException
{
}
