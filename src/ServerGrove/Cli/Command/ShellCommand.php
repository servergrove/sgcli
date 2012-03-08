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
use ServerGrove\APIClient;
use ServerGrove\Cli\Console\Application;

class ShellCommand extends Command
{
    /**
     * @var APIClient
     */
    private $client;
    private $input;
    private $output;

    private $server;
    private $servers = array();

    private $domain;
    private $domains = array();

    private $app;
    private $apps = array();

    private $args;

    private $lastCommand = null;

    private $commands = array(
        '.' => 'Repeat last command.',
        'x' => 'Reset internal buffers.',
        'help' => 'Print this help.',
        'quit' => 'Quit shell.',
        'servers' => 'List servers',
        'server' => 'Select a server. You can specify the server name, part of a name to search for, or a numeric option from the list of servers.',
        'domains' => 'List domains under selected server. You can pass the server name to get the domains under a server.',
        'domain' => 'Select a domain. You can specify the domain name, part of a name to search for, or a numeric option from the list of domains.',
        'apps' => 'List applications under selected server. You can pass the server name to get the apps under a server.',
        'app' => 'Select an app. You can specify the app name, part of a name to search for, or a numeric option from the list of apps.',
        'reboot' => 'Reboot a server. If no server name is given, it will reboot the selected server. It will ask for confirmation.',
        'shutdown'=> 'Shutdown a server. If no server name is given, it will shutdown the selected server. It will ask for confirmation.',
        'bootup' => 'Boot up a server. If no server name is given, it will boot the selected server. It will ask for confirmation.',
        'restart' => 'Restart an application. It will ask for confirmation.',
        'stop' => 'Stop an application. It will ask for confirmation.',
        'start' => 'Start an application.',
        'exec' => 'Execute a command in the server',
        'login' => 'Login with a different set of credentials',
    );

    protected function configure()
    {
        parent::configure();

        $this
                ->setName('shell')
                ->setDescription("Provides a shell for the ServerGrove Control Panel API. For more information visit https://control.servergrove.com/docs/api")
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
        $this->input = $input;
        $this->output = $output;

        // check command is valid
        $argStr = $input->getArgument('args');

        $this->options = $input->getOptions();

        parse_str($argStr, $this->args);

        $this->client = $this->getClient();
        /* @var $apiclient \ServerGrove\APIClient */
        if ($this->options['url']) {
            $this->client->setUrl($this->options['url']);
        }

        $this->runShell();
    }

    protected function getLoginFromUser()
    {
        $dialog = $this->getHelperSet()->get('dialog');

        if ($dialog->askConfirmation($this->output, $dialog->getQuestion('Would you like login with your credentials', 'yes', '?'), true)) {

            $this->client->setApiKey(null);
            $this->client->setApiSecret(null);

            $email = $dialog->askAndValidate($this->output, "Enter your Control Panel Email: ", function($value) {
                if (null === $value) {
                    throw new \Exception("Please enter a valid email address");
                }
                return $value;
            });

            $passwd = $dialog->askAndValidate($this->output, "Enter your Control Panel Password: ", function($value) {
                if (null === $value) {
                    throw new \Exception("Please enter your password");
                }
                return $value;
            });

            $this->output->write("<info>Authenticating...</info>");
            $res = $this->call('authentication/getLoggedUser', array(
                'apiUsername' => $email,
                'apiPassword' => $passwd,
            ));

            if (!$res) {
                $this->output->writeln("Authentication failed. Please check your credentials and try again.");
                die(1);
            }

            $this->output->writeln(" <info>OK!</info>");


            $this->client->setArg('apiUsername', $email);
            $this->client->setArg('apiPassword', $passwd);
        }

    }
    protected function runShell()
    {
        $this->output->writeln("ServerGrove Command Line Interface Shell\n");

        if ($this->client->getApiKey() == Application::DEMO_API_KEY) {
            $this->output->writeln("<comment>Warning: You are connecting with the demo account.</comment>\n");

            $this->output->writeln("You can store your API Key and Secret in the environment variables SG_API_KEY and SG_API_SECRET to skip the login questions.
            Example:
            $ export SG_API_KEY=yourkey
            $ export SG_API_SECRET=yoursecret

            For addtional help visit https://github.com/servergrove/sgcli
            ");

            $this->getLoginFromUser();
        }

        $this->executeServers(array());
        if (count($this->servers) == 1) {
            $this->executeServer(array(1));
        }
        while (true) {
            $command = $this->readline($this->getPrompt());

            switch($command) {
                case 'help':
                case 'h':
                case '?':
                    $this->executeHelp();
                    break;
                case 'exit':
                case 'quit':
                    $this->output->writeln("Exiting, goodbye!");
                    break 2;
                case 'login':
                    $this->getLoginFromUser();
                    break;
                default:
                    $this->processCommand($command);
            }
        }
    }

    protected function executeHelp($args=null)
    {
        $this->output->writeln('Help:');
        foreach($this->commands as $cmd => $help) {
            $this->output->writeln(' <info>'.str_pad($cmd, 8).'</info> '.$help );
        }
    }

    protected function processCommand($command)
    {
        $command = trim($command);

        if (empty($command)) {
            return;
        }

        if ($command == '.' && $this->lastCommand) {
            $command = $this->lastCommand;
        }


        foreach($this->commands as $cmd => $help) {
            if (strpos($command, $cmd) === 0) {
                $method = 'execute'.lcfirst(str_replace(' ', '', $cmd));
                if (false === $argStr = substr($command, strlen($cmd))) {
                    $args = array();
                } else {
                    $args = explode(' ', trim($argStr));
                }

                if (method_exists($this, $method)) {
                    $this->lastCommand = $command;
                    $this->$method($args);
                    return;
                }
            }
        }

        $this->error('Unrecognized command.');
        $this->executeHelp();
    }

    protected function getPrompt($msg = '$ ')
    {
        $prompt = '';

        if ($this->server) {
            $prompt .= $this->server['hostname'].' ';
        }

        if ($this->domain) {
            $prompt .= '> '.$this->domain['name'].' ';
        }

        if ($this->app) {
            $prompt .= '> '.$this->app['name'].' ';
        }

        return $prompt.$msg;
    }

    protected function executeServer($args)
    {
        $this->selectServer($args[0]);
    }

    protected function executeDomain($args)
    {
        $this->selectDomain($args[0]);
    }

    protected function executeApp($args)
    {
        $this->selectApp($args[0]);
    }

    protected function selectServer($s = null)
    {
        if (!count($this->servers)) {
            if (!$this->loadServers()) {
                return false;
            }
        }

        if (count($this->servers) == 1) {
            return $this->setServer(1);
        }

        if (empty($s)) {
            return false;
        }
        if (is_numeric($s)) {
            if (isset($this->servers[$s])) {
                return $this->setServer($s);
            }
        } else {
            foreach($this->servers as $idx => $server) {
                if ($server['hostname'] == $s) {
                    return $this->setServer($idx);
                }
            }
            foreach($this->servers as $idx => $server) {
                if (stripos($server['hostname'], $s) !== false) {
                    return $this->setServer($idx);
                }
            }
        }

        $this->server = null;
        $this->error("Server not found. Try listing all servers with the command 'servers'.");
        return false;
    }

    protected function setServer($server)
    {
        $this->server = $this->servers[$server];
        $this->info("Selected server ".$this->server['hostname']);
        $this->reset(false);
        return true;
    }

    protected function selectDomain($s = null)
    {
        if (!count($this->domains)) {
            if (!$this->loadDomains()) {
                return false;
            }
        }

        if (count($this->domains) == 1) {
            return $this->setDomain(1);
        }

        if (empty($s)) {
            return false;
        }
        if (is_numeric($s)) {
            if (isset($this->domains[$s])) {
                return $this->setDomain($s);
            }
        } else {
            foreach($this->domains as $idx => $dom) {
                if ($dom['name'] == $s) {
                    return $this->setDomain($idx);
                }
            }
            foreach($this->domains as $idx => $dom) {
                if (stripos($dom['name'], $s) !== false) {
                    return $this->setDomain($idx);
                }
            }
        }

        $this->domain = null;
        $this->error("Domain not found. Try listing all domains with the command 'domains'.");
        return false;
    }

    protected function setDomain($domain)
    {
        $this->domain = $this->domains[$domain];
        $this->info("Selected domain ".$this->domain['name']);
        return true;
    }

    protected function selectApp($s = null)
    {
           if (!count($this->apps)) {
               if (!$this->loadApps()) {
                   return false;
               }
           }

           if (count($this->apps) == 1) {
               return $this->setApp(1);
           }

           if (empty($s)) {
               return false;
           }
           if (is_numeric($s)) {
               if (isset($this->apps[$s])) {
                   return $this->setApp($s);
               }
           } else {
               foreach($this->apps as $idx => $app) {
                   if ($app['name'] == $s) {
                       return $this->setApp($idx);
                   }
               }
               foreach($this->apps as $idx => $app) {
                   if (stripos($app['name'], $s) !== false) {
                       return $this->setApp($idx);
                   }
               }
           }

           $this->app = null;
           $this->error("Application not found. Try listing all applications with the command 'apps'.");
           return false;
       }

    protected function setApp($app)
    {
        $this->app = $this->apps[$app];
        $this->info("Selected app ".$this->app['name']);
        return true;
    }

    protected function loadServers()
    {
        $this->servers = array();

        $this->info("Fetching list of servers...");

        if (!$res = $this->call('server/list', array())) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->result = array();
        $i = 1;
        foreach($rsp['rsp'] as $server) {
            $this->servers[$i] = $server;
            $i++;
        }
        return true;
    }

    protected function loadDomains($serverId = null)
    {
        if (!$serverId) {
            $serverId = $this->server['id'];
        }

        $this->domains = array();

        $this->info("Fetching list of domains...");
        if (!$res = $this->call('domain/list', array('serverId' => $serverId))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->result = array();
        $i = 1;
        foreach($rsp['rsp'] as $domain) {
            $this->domains[$i] = $domain;
            $i++;
        }
        return true;
    }

    protected function loadApps($serverId = null)
    {
        if (!$serverId) {
            $serverId = $this->server['id'];
        }

        $this->apps = array();

        $this->info("Ftching list of apps...");
        if (!$res = $this->call('app/list', array('serverId' => $serverId))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->result = array();
        $i = 1;

        foreach($rsp['rsp'] as $app) {
            $this->apps[$i] = $app;
            $i++;
        }
        return true;
    }

    protected function serverExec($cmd, $serverId = null)
    {
        if (!$serverId) {
            $serverId = $this->server['id'];
        }

        $this->info("Sending request...");
        if (!$res = $this->call('server/exec', array(
            'serverId' => $serverId,
            'cmd' => $cmd,
            'async' => 0,
            ))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return;
        }

        $this->output->writeln($rsp['msg']);

        return true;
    }

    protected function appCall($method, $args = null, $appId = null)
    {
        if (!$appId) {
            $appId = $this->app['id'];
        }

        $this->info("Sending request...");
        if (!$res = $this->call('app/call', array(
            'serverId' => $this->server['id'],
            'appId' => $appId,
            'method' => $method,
            'async' => 0,
            ))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return;
        }

        $this->output->writeln($rsp['msg']);

        return true;
    }

    protected function executeX($args)
    {
        return $this->reset();
    }

    protected function executeQ($args)
    {
        return $this->reset();
    }

    protected function reset($server = true, $domain = true, $app = true)
    {
        if ($server) {
            $this->server = null;
            $this->servers = array();
        }
        if ($domain) {
            $this->domain = null;
            $this->domains = array();
        }
        if ($app) {
            $this->app = null;
            $this->apps = array();
        }

        return true;
    }

    protected function executeServers($args)
    {
        if (!count($this->servers)) {
            if (!$this->loadServers()) {
                return false;
            }
        }
        foreach($this->servers as $i => $server) {
            $this->output->writeln(sprintf("$i. <info>%s</info> IP: <info>%s</info> Plan: <info>%s</info> %s",
                str_pad($server['hostname'], 40),
                str_pad($server['mainIpAddress'], 15),
                str_pad(isset($server['plan']) ? $server['plan'] : 'n/a', 8),
                $server['isActive'] ? '<info>Active</info>' : '<error>Offline</error>'
            ));
        }

        return true;
    }

    protected function executeDomains($args)
    {
        if (isset($args[0])) {
            if (!$this->selectServer($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        if (!count($this->domains)) {
            if (!$this->loadDomains()) {
                return false;
            }
        }
        foreach($this->domains as $i => $domain) {
            $this->output->writeln(sprintf("$i. <info>%s</info>",
                str_pad($domain['name'], 40)
            ));
        }

        return true;
    }

    protected function executeApps($args)
    {
        if (isset($args[0])) {
            if (!$this->selectServer($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        if (!count($this->apps)) {
            if (!$this->loadApps()) {
                return false;
            }
        }
        foreach($this->apps as $i => $app) {
            $this->output->writeln(sprintf("$i. <info>%s</info> <info>%s</info> %s",
                str_pad($app['name'], 15),
                str_pad($app['version'], 10),
                $app['isActive'] ? '<info>Active</info>' : '<error>Inactive</error>'
            ));
        }

        return true;
    }

    protected function executeExec($args)
    {
        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        $this->serverExec(implode(' ', $args));

        return true;
    }

    protected function executeReboot($args)
    {
        if (count($args) == 1) {
            if (!$this->selectServer($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        $serverId = $this->server['id'];

        if ('y' !== $this->readline('Are you sure you want to <error>reboot</error> <info>'.$this->server['hostname'].'</info>? [y/N] ')) {
            return false;
        }

        $this->info("Sending request...");
        if (!$res = $this->call('server/restart', array(
            'serverId' => $serverId,
            'async' => 0,
            ))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->output->writeln($rsp['msg']);

        return true;
    }

    protected function executeShutdown($args)
    {
        if (count($args) == 1) {
            if (!$this->selectServer($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        $serverId = $this->server['id'];

        if ('y' !== $this->readline('Are you sure you want to <error>shutdown</error> <info>'.$this->server['hostname'].'</info>? [y/N] ')) {
            return false;
        }

        $this->info("Sending request...");
        if (!$res = $this->call('server/stop', array(
            'serverId' => $serverId,
            'async' => 0,
            ))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->output->writeln($rsp['msg']);

        return true;
    }

    protected function executeBootup($args)
    {
        if (count($args) == 1) {
            if (!$this->selectServer($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        $serverId = $this->server['id'];


        $this->info("Sending request...");
        if (!$res = $this->call('server/start', array(
            'serverId' => $serverId,
            'async' => 0,
            ))) {
            return false;
        }

        if (false === $rsp = $this->client->getResponse(APIClient::FORMAT_ARRAY)) {
            return false;
        }

        $this->output->writeln($rsp['msg']);

        return true;
    }

    protected function executeRestart($args)
    {
        if (count($args) == 1) {
            if (!$this->selectApp($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        if ('y' !== $this->readline('Are you sure you want to restart <info>'.$this->app['name'].'</info> on <info>'.$this->server['hostname'].'</info>? [y/N] ')) {
            return false;
        }

        return $this->appCall('svcRestart');
    }

    protected function executeStop($args)
    {
        if (count($args) == 1) {
            if (!$this->selectApp($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        if ('y' !== $this->readline('Are you sure you want to stop <info>'.$this->app['name'].'</info> on <info>'.$this->server['hostname'].'</info>? [y/N] ')) {
            return false;
        }

        return $this->appCall('svcStop');
    }

    protected function executeStart($args)
    {
        if (count($args) == 1) {
            if (!$this->selectApp($args[0])) {
                return false;
            }
        }

        if (!$this->server) {
            if (!$this->selectServer()) {
                return $this->error("No server selected. Please select a server with 'server [name]'.");
            }
        }

        return $this->appCall('svcStart');
    }

    function readline($prompt="")
    {
       $this->output->write($prompt);
       $out = "";
       $key = "";
       $key = fgetc(STDIN);        //read from standard input (keyboard)
       while ($key!="\n")        //if the newline character has not yet arrived read another
       {
           $out.= $key;
           $key = fread(STDIN, 1);
       }
       return $out;
    }

    protected function error($msg)
    {
        $this->output->writeln("<error>".$msg."</error>\n");
        return false;
    }

    protected function info($msg)
    {
        $this->output->writeln("<info>".$msg."</info>");
        return true;
    }

    protected function call($call, $args)
    {
        $args = array_merge($this->args, $args);

        if ($this->options['verbose']) {
            $this->output->writeln("Calling: <info>".$this->client->getFullUrl($call, $args)."</info>");
        }

        $res =  $this->client->call($call, $args);

        if (!$res) {
            $this->error($this->client->getError());
        }

        return $res;
    }
}