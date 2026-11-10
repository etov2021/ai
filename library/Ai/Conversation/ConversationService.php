<?php

declare(strict_types=1);

namespace Ai\Conversation;

use Ai\Agent\AgentService;
use Ai\Exception\AiException;
use Ai\Message\Message;
use Ai\Storage\AgentRepository;
use Ai\Storage\ConversationRepository;

/** Разговор с агент: зарежда история, изпълнява агента и записва новите съобщения. */
final class ConversationService
{
    public function __construct(
        private AgentService $agents,
        private AgentRepository $agentRepo,
        private ConversationRepository $conversations,
        // Историята се реже, за да не расте цената на всяка следваща заявка.
        private int $maxHistoryMessages = 40,
    ) {
    }

    public function ask(string $agentCode, string $text, ?int $conversationId = null, ?int $userId = null): ConversationReply
    {
        $agent = $this->agentRepo->findByCode($agentCode);
        if ($agent === null || !$agent->runnable) {
            throw new AiException('Агентът не е наличен.');
        }

        if ($conversationId === null) {
            $conversation = $this->conversations->create($agent->id, $userId, $text);
        } else {
            $conversation = $this->conversations->find($conversationId);
            // Едно и също съобщение за всички случаи: чужд, несъществуващ или на друг агент разговор.
            if ($conversation === null || $conversation->agentId !== $agent->id || $conversation->userId !== $userId) {
                throw new AiException('Разговорът не е наличен.');
            }
        }

        $history = $this->conversations->history($conversation->id, $this->maxHistoryMessages);
        try {
            $result = $this->agents->run($agentCode, $text, $history, $conversation->id, $userId);
        } catch (\Throwable $e) {
            // Отхвърлени и неуспешни заявки не бива да оставят празни разговори.
            if ($conversationId === null) {
                $this->conversations->delete($conversation->id);
            }
            throw $e;
        }

        // Записва се само успешно изпълнение, така че историята остава последователна.
        $this->conversations->append($conversation->id, [Message::user($text), ...$result->messages]);

        return new ConversationReply($conversation->id, $result);
    }
}
