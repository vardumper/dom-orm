<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    protected static $defaultName = 'dom-orm:init';

    protected function configure()
    {
        $this->setDescription('Initializes DOM ORM');
        $this->addArgument('project-dir', null, 'Project directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Initializing DOM ORM...');
        $projectDir = $input->getArgument('project-dir');
        $this->init($projectDir);
        $output->writeln('Done.');

        return Command::SUCCESS;
    }

    private function init(string $projectDir): void
    {
        $storage = $projectDir . '/storage/data.xml';

        if (!is_dir(dirname($storage))) {
            mkdir(dirname($storage), 0755, true);
        }

        if (!is_writable(dirname($storage))) {
            chmod(dirname($storage), 0755);
        }

        if (!file_exists($storage)) {
            $xml = $this->getEmptyDom();
            $xml->loadXML('<data />');
            $xml->save($storage);
        }
    }

    private function getEmptyDom(): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = false;
        $dom->strictErrorChecking = false;
        $dom->formatOutput = true;

        return $dom;
    }
}
