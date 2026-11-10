<?php

namespace Seo\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Micro\Http\Response\RedirectResponse;
use Seo\Model\Table\Redirects as TableRedirects;

class Redirects
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $uri = (string) $request->getUri();

        foreach ((new TableRedirects())->getRedirects() as $row) {

            if (preg_match($row['pattern'], $uri)) {

                $end = substr($row['replacement'], -1);

                if (preg_match('~^https?\:\/\/~', $row['replacement'])) {
                    $uri = $row['replacement'];
                } else if ($end === '$') {
                    $uri = server_url(base_url(substr($row['replacement'], 1, -1)));
                } else {
                    $uri = @preg_replace($row['pattern'], $row['replacement'], $uri);
                }

                if ($uri) {
                    return new RedirectResponse($uri, (int) $row['code']);
                }
            }
        }

        return $next($request, $response);
    }
}