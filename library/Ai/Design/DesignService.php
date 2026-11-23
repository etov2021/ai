<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Exception\AiException;
use Ai\Message\Message;

/** Генерира и доразвива HTML/CSS макети чрез агент; резултатът се чисти и се пази като версия. */
final class DesignService
{
    private const MAX_HTML_CHARS = 300_000;

    public function __construct(private AgentService $agents, private DesignRepository $designs, private ?ReferenceFetcher $reference = null, private ?TemplateRepository $templates = null)
    {
    }

    /** @return array{designId: int, version: int, warning: ?string} */
    public function create(DesignBrief $brief, string $agentCode, ?int $userId): array
    {
        // Образецът се взима преди записа: недостъпна страница или изтрит шаблон не оставя празен макет.
        if ($brief->referenceTemplateId > 0) {
            $template = ($this->templates ?? throw new AiException('Библиотеката с шаблони не е включена.'))->find($brief->referenceTemplateId)
                ?? throw new AiException('Шаблонът не е намерен.');
            $brief = $brief->withReference($template['summary']);
        } elseif ($brief->referenceUrl !== '') {
            $brief = $brief->withReference(($this->reference ?? throw new AiException('Образци от страници не са включени.'))->summarize($brief->referenceUrl));
        }

        // Генерирането е най-скъпата част: параметрите вече са валидирани, а записът се трие, ако то не успее.
        $designId = $this->designs->create($brief, $userId);

        try {
            $generated = $this->generate($agentCode, $brief->prompt(), [], $userId);
            $version = $this->designs->addVersion($designId, 'Първоначален макет', $generated['html'], $agentCode);
        } catch (\Throwable $e) {
            $this->designs->delete($designId);
            throw $e;
        }

        return ['designId' => $designId, 'version' => $version, 'warning' => $generated['warning']];
    }

    /** @return array{designId: int, version: int, warning: ?string} */
    public function refine(int $designId, string $instruction, string $agentCode, ?int $userId): array
    {
        $instruction = trim($instruction);
        if ($instruction === '' || mb_strlen($instruction) > 2000) {
            throw new AiException('Опишете промяната (до 2000 знака).');
        }

        $design = $this->designs->find($designId) ?? throw new AiException('Макетът не е намерен.');
        $latest = $this->designs->latestVersion($designId);
        $current = $latest === null ? null : $this->designs->version($designId, $latest);
        if ($current === null) {
            throw new AiException('Макетът няма версии.');
        }

        // Предишният макет е в историята, не в съобщението: ограничението за дължина важи само за новото съобщение.
        $history = [Message::user($design['brief']->prompt()), Message::assistant($current['html'])];
        $generated = $this->generate(
            $agentCode,
            "Промени макета: {$instruction}\nВърни пълния обновен HTML документ със същите правила, без обяснения.",
            $history,
            $userId,
        );

        return ['designId' => $designId, 'version' => $this->designs->addVersion($designId, $instruction, $generated['html'], $agentCode), 'warning' => $generated['warning']];
    }

    /**
     * @param list<Message> $history
     * @return array{html: string, warning: ?string}
     */
    private function generate(string $agentCode, string $input, array $history, ?int $userId): array
    {
        $result = $this->agents->run($agentCode, $input, $history, null, $userId);

        if ($result->content === null || trim($result->content) === '' || !in_array($result->status, [RunStatus::Completed, RunStatus::Truncated], true)) {
            throw new AiException(sprintf('Агентът не върна макет (%s).', $result->status->value));
        }

        $raw = self::extractHtml($result->content);
        $truncated = $result->status === RunStatus::Truncated || stripos($raw, '</html>') === false;

        $html = HtmlSanitizer::sanitize($raw);
        if (mb_strlen($html) > self::MAX_HTML_CHARS) {
            throw new AiException('Макетът е прекалено голям.');
        }

        return [
            'html' => $html,
            'warning' => $truncated ? 'Отговорът изглежда отрязан (няма край на документа). Увеличете „Макс. токени“ на агента.' : null,
        ];
    }

    /** Взема документа от отговора на модела: маха оградите ``` и текста преди и след него. */
    public static function extractHtml(string $text): string
    {
        $text = preg_replace('/^\s*```(?:html)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        if (preg_match('/<!doctype\s+html|<html[\s>]/i', $text, $m, PREG_OFFSET_CAPTURE) !== 1) {
            throw new AiException('Агентът не върна HTML документ.');
        }
        $text = substr($text, $m[0][1]);

        $end = strripos($text, '</html>');

        return trim($end === false ? $text : substr($text, 0, $end + 7));
    }
}
