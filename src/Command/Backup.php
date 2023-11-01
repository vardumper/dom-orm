<?php
declare(strict_types=1);

namespace DOM\ORM\Command;

class Backup
{
    protected static $defaultName = 'dom-orm:backup';

    public static function run(): void
    {
        $storage = getcwd() . '/storage/data.xml';

        if (!\is_dir(\dirname($storage) . '/backups')) {
            \mkdir(\dirname($storage) . '/backups', 0755, true);
        }

        $now = time();
        copy($storage, \dirname($storage) . '/backups/data-' . $now . '.xml');
    }
}
