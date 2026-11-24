<?php

namespace AiAdmin\Controller\Admin;

use AiAdmin\Service\BaseController;
use Micro\Http\Response;

/** Журнал на действията: кой, какво и с какъв резултат (само за четене). */
class Audit extends BaseController
{
    private const PAGE_SIZE = 50;
    private const EXPORT_LIMIT = 5000;

    public function indexAction()
    {
        $filters = $this->filters();
        $page = max(1, (int) $this->request->getParam('page', 1));
        $total = (int) $this->safely(fn () => $this->ai()->audit()->count($filters), 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $query = array_filter($filters, static fn (string $v): bool => $v !== '');

        return [
            'rows' => $this->safely(fn () => $this->ai()->audit()->search($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)),
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'prevUrl' => $page > 1 ? $this->url('audit', 'index', null, $query + ['page' => $page - 1]) : null,
            'nextUrl' => $page < $pages ? $this->url('audit', 'index', null, $query + ['page' => $page + 1]) : null,
            'filterUrl' => $this->url('audit'),
            'exportUrl' => $this->url('audit', 'export', null, $query),
            'retentionDays' => (int) config('ai.audit.retention_days', 365),
            'homeUrl' => $this->url('index'),
        ];
    }

    /** CSV с до 5000 най-нови записа по същите филтри. Самото изтегляне също се записва. */
    public function exportAction()
    {
        $filters = $this->filters();
        $rows = $this->ai()->audit()->search($filters, self::EXPORT_LIMIT);
        $this->audit('audit.export', 'audit', null, ['rows' => count($rows), 'filters' => array_filter($filters, static fn (string $v): bool => $v !== '')]);

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'time', 'actorType', 'actor', 'ip', 'action', 'entityType', 'entity', 'result', 'details'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(self::csvCell(...), [
                $row['id'], $row['created'], $row['actorType'], $row['actorName'] ?? '', $row['ip'] ?? '',
                $row['action'], $row['entityType'] ?? '', $row['entityId'] ?? '', $row['result'], $row['details'] ?? '',
            ]), ',', '"', '');
        }
        rewind($out);

        $response = (new Response(null, 200))
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="ai-audit-' . date('Ymd-His') . '.csv"');
        $response->getBody()->write((string) stream_get_contents($out));
        fclose($out);

        return $response;
    }

    /** Клетки, започващи с =, +, -, @ или табулация, се изпълняват като формули в Excel; префиксът ' ги прави текст. */
    private static function csvCell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && strpbrk($text[0], "=+-@\t\r") !== false ? "'" . $text : $text;
    }

    /** @return array{action: string, result: string, actor: string, from: string, to: string} */
    private function filters(): array
    {
        $get = fn (string $key): string => trim((string) $this->request->getParam($key, ''));

        return [
            'action' => mb_substr($get('action'), 0, 60),
            'result' => $get('result'),
            'actor' => mb_substr($get('actor'), 0, 100),
            'from' => $get('from'),
            'to' => $get('to'),
        ];
    }
}
