<?php

namespace Files\Helper;

class DocxConversion
{
    protected $path;

    public function __construct($path = null)
    {
        if ($path !== null) {
            $this->setPath($path);
        }
    }

    public function setPath($path)
    {
        if (!is_file($path)) {
            throw new \Exception('File not found');
        }

        $this->path = $path;
    }

    public function convert()
    {
        $info = pathinfo($this->path);

        switch ($info['extension']) {
            case 'doc' :
                return $this->doc();
                break;
            case 'docx' :
                return $this->docx();
                break;
            case 'xlsx' :
                return $this->xlsx();
                break;
            case 'xml' :
                return $this->xml();
                break;
            case 'htm' :
            case 'html' :
                return $this->html();
                break;
            case 'txt' :
                return $this->txt();
                break;
        }

        throw new \Exception('Invalid file type');
    }

    protected function doc()
    {
        $fp = fopen($this->path, 'r');

        $line = @fread($fp, filesize($this->path));

        fclose($fp);

        $content = '';

        foreach (explode(chr(0x0D), $line) as $l) {
            $pos = strpos($l, chr(0x00));
            if (($pos === false) && (strlen($l) !== 0)) {
                $content .= $l . ' ';
            }
        }

        $content = preg_replace('~[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]~', '', $content);

        return $content;
    }

    protected function docx()
    {
        $content = '';

        $zip = zip_open($this->path);

        if (!$zip || is_numeric($zip)) {
            return $content;
        }

        while (($entry = zip_read($zip)) !== false) {

            if (zip_entry_open($zip, $entry) == false) {
                continue;
            }

            if (zip_entry_name($entry) != 'word/document.xml') {
                continue;
            }

            $content .= zip_entry_read($entry, zip_entry_filesize($entry));

            zip_entry_close($entry);
        }

        zip_close($zip);

        $content = str_replace('</w:r></w:p></w:tc><w:tc>', ' ', $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $content = trim(strip_tags($content));

        return $content;
    }

    protected function xlsx()
    {
        $content = '';

        $zip = new \ZipArchive();

        if (true === $zip->open($this->path)) {
            if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
                $entry = $zip->getFromIndex($index);
                $xml = new \DOMDocument();
                $xml->loadXML($entry, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $content = strip_tags($xml->documentElement->textContent);
            }
            $zip->close();
        }

        return $content;
    }

    protected function xml()
    {
        $xml = new \DOMDocument();
        $xml->load($this->path, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        return strip_tags($xml->documentElement->textContent);
    }

    protected function html()
    {
        return strip_tags(file_get_contents($this->path));
    }

    protected function txt()
    {
        return $this->html();
    }
}