# Библиотека Ai

Независима от Micro библиотека за LLM агенти (PHP 8.3, без външни зависимости). Админ интерфейсът е в `application/modules/AiAdmin`.

## Структура

| Папка | Съдържание |
|---|---|
| `Contract/` | Интерфейси: `ProviderInterface`, `ToolInterface`, `HttpClientInterface`, `RetrieverInterface`, `ToolProviderInterface`, `WorkflowInterface`, `ExtensionInterface` |
| `Provider/` | `OpenAi`, `Anthropic`, `Gemini`, `OpenAiCompatible` |
| `Message/` | Унифициран формат на заявка и отговор |
| `Agent/` | `AgentRunner` (цикъл модел - инструменти), `AgentService` (входна точка), `RunLogger` |
| `Tool/` | `HttpTool`, `SchemaValidator`, `ToolRegistry`, `RetrieverTool`, `AgentTool` |
| `Security/` | `KeyVault` (AES-256-GCM), `Policy` (валидация, SSRF), `Redactor` |
| `Limits/` | `RateLimiter`, `UsageGuard` (лимити на заявките и бюджети) |
| `Storage/` | Репозиторийте и `migrations/` |
| `Conversation/` | `ConversationService` |
| `Extension/` | `ExtensionRegistry` |

## Внедряване

1. **Миграции** - по ред, веднъж, в базата на приложението:
   `Storage/migrations/001_providers_models.sql`, `002_agents_tools.sql`, `003_conversations_logs.sql`, `004_budgets_limits.sql`, `005_agent_escalation.sql`, `006_workflows.sql`, `007_gmail_accounts.sql`, `008_mail_messages.sql`.
2. **Главен ключ** - генерирайте и го задайте в средата на уеб сървъра (PHP-FPM: `env[AI_MASTER_KEY]` и `clear_env = no`; Apache: `SetEnv`; CLI: системна променлива).
   ```
   php -r "require 'library/Ai/Exception/AiException.php'; require 'library/Ai/Security/KeyVault.php'; echo Ai\Security\KeyVault::generateMasterKey();"
   ```
   Ключът не се пази в репото. При загубата му записаните API ключове се въвеждат наново.
3. **Автозареждане** - в `application/config/app.php` е добавено `'Ai' => 'library/Ai'` в `microloader.paths`.
4. **Модул** - `AiAdmin` е в `application/config/modules.php`.
5. **Права** - в Потребители - Групи дайте права за ресурсите `AiAdmin_Controller_Admin_*`.
6. **Меню** - в Навигация добавете елемент към `admin.main` с адрес `/admin/ai-admin`.
7. **Настройки** - `application/config/ai.php`: `allow_private_hosts` (локални сървъри като Ollama, по подразбиране изключено), `extensions`.

## Ред на настройка в админа

Доставчик (ключ) - Модел (цени в EUR за 1 млн. токена) - Инструмент - Агент - Лимити - тест през Чат.

## Gmail (само четене)

1. В Google Cloud: включете Gmail API, направете OAuth клиент от тип Web application и добавете като redirect URI адреса, показан на страницата AiAdmin - Gmail.
2. В `.env`: `GOOGLE_CLIENT_ID` и `GOOGLE_CLIENT_SECRET`.
3. AiAdmin - Gmail - „Свържи Gmail акаунт“ (refresh токенът се пази криптиран).
4. В Инструменти добавете два реда с тип `builtin` и код `mail_search` и `mail_read`, после ги разрешете на агента.

Писмата са недоверен вход: агентът получава само права за четене, а текстът им се предава с предупреждение срещу prompt injection. Инструментите ползват първия свързан акаунт.

### Известия за нови писма

`cronjobs/gmail-poll.php` (напр. на всеки 10 минути) записва новите писма в `AiMailMessages` и ги показва в камбанката в хедъра и в AiAdmin - Получена поща. Ако в `config/ai.php` е зададен `gmail.agent`, агентът анализира всяко писмо (до `analyze_per_run` на пускане, до 3 опита). Пълният текст на писмата не се записва в базата.

## Използване от код

```php
$service = new Ai\Agent\AgentService($agents, $tools, $toolRegistry, $resolver, $log, $guard);
$result  = $service->run('helper', 'Въпрос', [], $conversationId, $userId);

$chat  = new Ai\Conversation\ConversationService($service, $agents, $conversations);
$reply = $chat->ask('helper', 'Въпрос', $conversationId, $userId);
```

`LimitException` (`RateLimitException`, `BudgetExceededException`) носи съобщение, безопасно за потребителя; подробностите са в `reason` и в `AiRequestLog`.

## Разширяване

- **Нов доставчик** - клас, имплементиращ `ProviderInterface`, и `ProviderRegistry::register('тип', фабрика)`.
- **Нов инструмент** - `ToolRegistry::registerBuiltin()` или `ExtensionRegistry::addToolProvider()`, после ред в `AiTools` с `type = builtin` и разрешение на агента.
- **RAG, MCP, workflows, agent-to-agent** - разширение в `config/ai.php` - `extensions`; виж `RetrieverTool` и `AgentTool`.

## Ескалация към по-мощен модел

В Агенти -> „Модел за ескалация" се задава втори, по-скъп модел. Основният (евтин) отговаря първи и получава указание да върне `[[ESCALATE]]`, ако въпросът е твърде сложен. Скъпият модел се вика само при маркер, празен отговор или изчерпани стъпки (не при блокиране, съкращаване или таймаут) и само ако бюджетът позволява. Токените и разходът на двата опита се сумират, а в `AiRequestLog` са под един `runId` с ред `run` / `escalated`.

## Чеклист за приемане

- [ ] Миграциите 001-005 са изпълнени без грешка.
- [ ] `AI_MASTER_KEY` е зададен; таблото `/admin/ai-admin` не показва предупреждение.
- [ ] Записан API ключ не се вижда в базата (`AiProviders.apiKeyEnc`), в страниците и в логовете.
- [ ] Чат с агент без инструменти връща отговор; `AiRequestLog` съдържа редове `llm` и `run` с токени и цена.
- [ ] Агент с HTTP инструмент вика само хостовете от `allowedHosts`.
- [ ] Правило `user` / `*` със `rateLimitPerMinute: 2` отхвърля третата заявка за минута.
- [ ] Дневен бюджет `0` на доставчик блокира заявките; редът `rejected` е в лога.
- [ ] Потребител без права получава 403 за страниците на `AiAdmin`.
- [ ] Разговор на друг потребител не се отваря (404).

## Известни ограничения

- Бюджетите се проверяват преди изпълнението и между стъпките, но не са атомарни при паралелни заявки.
- Фиксираният прозорец на лимита на заявките допуска до 2x заявки на границата между минути.
- `HttpTool` няма секретни заглавия: работи само с публични API-та.
- Кешираните токени на доставчиците не се отчитат отделно.
- Липсват MCP клиент, векторно хранилище и workflow двигател - има само контракти и регистър.
- Фоновото изпълнение през `Micro\Job` не е реализирано.
