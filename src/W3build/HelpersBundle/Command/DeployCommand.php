<?php
/**
 * Created by PhpStorm.
 * User: Jahodal
 * Date: 29.7.14
 * Time: 22:21
 */

namespace W3build\HelpersBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

class DeployCommand extends ContainerAwareCommand {

    protected function getDialogHelper(){
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || get_class($dialog) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper') {
            $this->getHelperSet()->set($dialog = new DialogHelper());
        }

        return $dialog;
    }

    protected function configure()
    {
        $this->setName('w3build:deploy')
            ->setDescription('Set all necessary to enable environment')
            ->addArgument('u', InputArgument::OPTIONAL, 'Web server user', 'www-data')
            ->addArgument('g', InputArgument::OPTIONAL, 'Web server group', 'www-data')
            ->addOption('load-fixtures', 'f', InputOption::VALUE_NONE, 'Load fixtures')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = realpath($this->getContainer()->get('kernel')->getRootDir() . '/../');

        $realCacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $cacheDir = realpath($realCacheDir . '/../');
        $realLogsDir = $this->getContainer()->getParameter('kernel.logs_dir');
        $uploadsDir = realpath($root . '/web/uploads');

        $filesystem = $this->getContainer()->get('filesystem');

        $input->setOption('env', 'test');

        // Update DB schema
        $output->writeln('Updating database schema');
        $command = $this->getApplication()->find('doctrine:schema:update');
        $arguments = array(
            'command' => 'doctrine:schema:update',
            '--force' => true,
            '--env' => 'test'
        );
        $commandInput = new ArrayInput($arguments);
        $command->run($commandInput, $output);

        // Fixtures
        if($input->getOption('load-fixtures')){
            $output->writeln('Loading fixtures');
            $command = $this->getApplication()->find('doctrine:fixtures:load');
            $arguments = array(
                'command' => 'doctrine:fixtures:load',
            );
            $commandInput = new ArrayInput($arguments);
            $command->run($commandInput, $output);
        }

        // Assets
        $output->writeln('Deploing assets');
        $command = $this->getApplication()->find('assets:install');
        $arguments = array(
            'command' => 'assets:install',
            '--symlink' => true,
        );
        $commandInput = new ArrayInput($arguments);
        $command->run($commandInput, $output);
        $command = $this->getApplication()->find('assetic:dump');
        $arguments = array(
            'command' => 'assetic:dump',
        );
        $commandInput = new ArrayInput($arguments);
        $command->run($commandInput, $output);

        // Clear cache
        $output->writeln('Clearing cache');
        $command = $this->getApplication()->find('cache:clear');
        $arguments = array(
            'command' => 'cache:clear',
        );
        $commandInput = new ArrayInput($arguments);
        $command->run($commandInput, $output);

        // Cache
        $output->writeln('Setting permissions on cache dir');
        $filesystem->chown($cacheDir, $input->getArgument('u'), true);
        $filesystem->chgrp($cacheDir, $input->getArgument('g'), true);

        // Logs
        $output->writeln('Setting permissions on cache dir');
        $filesystem->chown($realLogsDir, $input->getArgument('u'), true);
        $filesystem->chgrp($realLogsDir, $input->getArgument('g'), true);

        // Logs
        $output->writeln('Setting permissions on uploads dir');
        $filesystem->chown($uploadsDir, $input->getArgument('u'), true);
        $filesystem->chgrp($uploadsDir, $input->getArgument('g'), true);
    }

}