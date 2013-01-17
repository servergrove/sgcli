<?php

/*
 * This file is part of sgcli.
 *
 * (c) ServerGrove
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ServerGrove\Tests\Cli\Console;

use ServerGrove\Cli\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    private $app;

    public function setUp()
    {
        $this->app = new Application();
        $this->app->setAutoExit(false);
    }

    public function testCommandList()
    {
        $tester = new ApplicationTester($this->app);
        $tester->run(array(), array('decorated' => false));

        $this->assertStringEqualsFile(
            __DIR__.'/fixtures/application_output.txt',
            $tester->getDisplay(),
            'Executing the application without arguments shows the commands list'
        );
    }

}