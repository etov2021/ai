<?php

namespace Micro\Http\Response;

use Micro\Http\Response;
use Micro\Exception\Exception as CoreException;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;


/**
 * Produce a redirect response.
 */
class DownloadResponse extends Response
{

    public function __construct($uri, $status = 200, $headers = [])
    {
        if (!is_string($uri) && !$uri instanceof UriInterface) {
            throw new InvalidArgumentException(sprintf(
                'Uri provided to %s MUST be a string or Psr\Http\Message\UriInterface instance; received "%s"',
                __CLASS__,
                (is_object($uri) ? get_class($uri) : gettype($uri))
            ));
        }

        $fileHeaders = get_headers($uri,1);
        if($fileHeaders[0] == 'HTTP/1.1 200 OK') {
            header('Content-Description: File Transfer');
            header('Content-Type: '. $fileHeaders['Content-Type']);
            header('Content-Disposition: attachment; filename="'.basename($uri).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $fileHeaders['Content-Length']);
            ob_clean();
            flush();
            readfile($uri);
            exit;
        }

        throw new CoreException('Invalid url');

    }

}
