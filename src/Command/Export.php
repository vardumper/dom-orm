<?php
declare(strict_types=1);

namespace DOM\ORM\Command;

class Export
{
    protected static $defaultName = 'dom-orm:export';

    public static function run(string $file, $xml, $yaml, $json, $php): void
    {

    }
}
