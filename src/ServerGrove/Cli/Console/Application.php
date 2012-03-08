<?php
/*
 * This file is part of sgcli.
 *
 * (c) ServerGrove
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ServerGrove\Cli\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Finder\Finder;
use ServerGrove\Cli\Command;
use ServerGrove\APIClient;
use ServerGrove\Cli\Command\Helper\DialogHelper;

/**
 * The console application that handles the commands
 *
 * @author Pablo Godel <pablo@servergrove.com>
 * @author Ryan Weaver <ryan@knplabs.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author François Pluchino <francois.pluchino@opendisplay.com>
 */
class Application extends BaseApplication
{
    const URL = 'https://control.servergrove.com';
    const DEMO_API_KEY = '38d25347692ce2bebd5035678e46cf1e';
    const DEMO_API_SECRET = '175d6c3a657e10bb7b5b21fc2b6b1a28';

    /**
     * @var APIClient
     */
    private $client;

    public function __construct()
    {
        parent::__construct('SGCli', '0.1');
    }

    /**
     * {@inheritDoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $styles['highlight'] = new OutputFormatterStyle('red');
            $styles['warning'] = new OutputFormatterStyle('black', 'yellow');
            $formatter = new OutputFormatter(null, $styles);
            $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        }

        return parent::run($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    /**
     * @return APIClient
     */
    public function getClient()
    {
        if (null === $this->client) {
            $this->client = new APIClient(self::URL);
            $this->client->setApiKey(isset($_SERVER['SG_API_KEY']) ? $_SERVER['SG_API_KEY'] : self::DEMO_API_KEY);
            $this->client->setApiSecret(isset($_SERVER['SG_API_SECRET']) ? $_SERVER['SG_API_SECRET'] :self::DEMO_API_SECRET);
        }
        return $this->client;
    }

    /**
     * Initializes all commands
     */
    protected function registerCommands()
    {
        $this->add(new Command\ClientCommand());
        $this->add(new Command\ShellCommand());
    }

    /**
       * {@inheritDoc}
       */
      protected function getDefaultHelperSet()
      {
          $helperSet = parent::getDefaultHelperSet();

          $helperSet->set(new DialogHelper());

          return $helperSet;
      }
}