<?php

namespace AiAdmin\Controller;

use AiAdmin\Service\AiServices;
use Micro\Application\Controller;
use Micro\Http\Response;

/** Публичен MCP endpoint за външни клиенти. Няма сесия: достъпът е само с токен (виж Ai\McpServer\McpGateway). */
class McpEndpoint extends Controller
{
    public function indexAction()
    {
        // Изключване на цялата повърхност без изтриване на токените.
        if (!config('ai.mcp_server.enabled', false)) {
            return new Response(null, 404);
        }

        // ask_agent може да чака модела.
        @set_time_limit(300);

        // Заглавията в Micro са с малки букви.
        $result = AiServices::get()->mcpGateway()->handle(
            $this->request->getMethod(),
            $this->request->getHeaderLine('origin'),
            $this->request->getHeaderLine('authorization'),
            (string) ($this->request->getServerParams()['REMOTE_ADDR'] ?? ''),
            (string) $this->request->getBody(),
        );

        $response = new Response(null, $result['status']);
        foreach ($result['headers'] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        if ($result['body'] !== null) {
            $response->getBody()->write($result['body']);
        }

        return $response;
    }
}
