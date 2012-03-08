<?php

/*
 * This file is part of sgcli.
 *
 * (c) ServerGrove
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ServerGrove\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ClientCommand extends Command
{

    protected function configure()
    {
        parent::configure();

        $this
                ->setName('client')
                ->setDescription("Executes a call to the ServerGrove Control Panel API. For more information visit https://control.servergrove.com/docs/api")
                ->addArgument('call', InputArgument::REQUIRED, 'API Call')
                ->addArgument('args', InputArgument::OPTIONAL, 'API Arguments')
                ->addOption('url', null, null, 'API URL')
        ;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract class is not implemented
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // check command is valid
        $call = strtolower($input->getArgument('call'));
        $argStr = $input->getArgument('args');

        $options = $input->getOptions();

        parse_str($argStr, $args);

        $apiclient = $this->getClient();
        /* @var $apiclient \ServerGrove\APIClient */
        if ($options['url']) {
            $apiclient->setUrl($options['url']);
        }

        if ($options['verbose']) {
            $output->writeln("Calling: <info>".$apiclient->getFullUrl($call, $args)."</info>");
        }

        if ($apiclient->call($call, $args)) {
            if ($options['verbose']) {
                $output->writeln("Response: <info>".print_r($apiclient->getResponse(), true)."</info>");
            } else {
                $output->writeln($apiclient->getRawResponse());
            }
            return 0;
        } else {
            if ($options['verbose']) {
                $output->writeln("<error>".$apiclient->getError()."</error>");
            } else {
                $output->writeln($apiclient->getRawResponse());
            }
            return 1;
        }
    }


}
