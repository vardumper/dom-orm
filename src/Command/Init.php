<?php
declare(strict_types=1);

namespace DOM\ORM\Command;

class Init
{
    protected static $defaultName = 'dom-orm:init';

    public static function run(): ?string
    {
        $storage = getcwd() . '/storage/data.xml';

        try {
            if (!\is_dir(\dirname($storage))) {
                \mkdir(\dirname($storage), 0755, true);
            }

            if (!\is_writable(\dirname($storage))) {
                \chmod(\dirname($storage), 0755);
            }
        } catch (\Throwable) {
            return sprintf('Unable to create storage directory %s or directory isn`t writable. Check your configuration and permissions. Exiting.', \dirname($storage));
        }

        if (\file_exists($storage)) {
            return 'Database is already initialized. Exiting.';
        }
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = false;
        $dom->strictErrorChecking = false;
        $dom->formatOutput = true;
        $dom->loadXML('<data />');
        $dom->save($storage);

        return null;
    }
}
