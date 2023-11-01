<?php
declare(strict_types=1);

namespace DOM\ORM\Command;

class Init
{
    protected static $defaultName = 'dom-orm:init';

    public static function run(): void
    {
        $storage = getcwd() . '/storage/data.xml';

        if (!\is_dir(\dirname($storage))) {
            \mkdir(\dirname($storage), 0755, true);
        }

        if (!\is_writable(\dirname($storage))) {
            \chmod(\dirname($storage), 0755);
        }

        if (!\file_exists($storage)) {
            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->preserveWhiteSpace = false;
            $dom->validateOnParse = false;
            $dom->strictErrorChecking = false;
            $dom->formatOutput = true;
            $dom->loadXML('<data />');
            $dom->save($storage);
        }
    }
}
