<?php

/*
 * Copyright (c) 2011-2013 Lp digital system
 *
 * This file is part of BackBuilder5.
 *
 * BackBuilder5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBuilder5 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBuilder5. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBuilder\Command\Tests;

use BackBuilder\Tests\TestCase;

use Symfony\Component\Console\Tester\CommandTester;
use BackBuilder\Console\Console;

use BackBuilder\Command\AclLoadCommand;

use org\bovigo\vfs\vfsStream;

use BackBuilder\Security\Group;

/**
 * AclDbInitCommand Test
 *
 * @category    BackBuilder
 * @package     BackBuilder\Command
 * @copyright   Lp digital system
 * @author      k.golovin
 * 
 * @coversDefaultClass \BackBuilder\Command\AclLoadCommand
 */
class AclLoadCommandTest extends TestCase
{
    protected function setUp()
    {
        $this->bbapp = $this->getBBApp();
        $this->initDb($this->bbapp);
        $this->initAcl();
        
        $superAdminGroup = new Group();
        $superAdminGroup
            ->setIdentifier('super_admin')
            ->setName('Super Admin')
        ;
        $this->bbapp->getEntityManager()->persist($superAdminGroup);
        
        $this->getBBApp()->getEntityManager()->flush();
        
    }
    
    /**
     * @covers ::execute
     */
    public function testExecute()
    {
        // mock the Kernel or create one depending on your needs
        $application = new Console($this->getBBApp());
        $application->add(new AclLoadCommand());

        $command = $application->find('acl:load');
        $commandTester = new CommandTester($command);
        
        
        $this->_mock_basedir = vfsStream::setup('test_dir', 0777, array(
            'file.xml' => '
groups:
  super_admin:
    sites:
      resources: all
      actions: all
            '
        ));
        
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                'file' => 'vfs://test_dir/file.xml'
            )
        );
        
        $this->assertContains('Processing file: vfs://test_dir/file.xml', $commandTester->getDisplay());
    }
    
    
    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     * @covers ::execute
     */
    public function testExecute_invalidFile()
    {
        // mock the Kernel or create one depending on your needs
        $application = new Console($this->getBBApp());
        $application->add(new AclLoadCommand());

        $command = $application->find('acl:load');
        $commandTester = new CommandTester($command);
        
        
        $this->_mock_basedir = vfsStream::setup('test_dir', 0777, array(
            'fileInvalid.xml' => '
incorrectly formatted
yml file'
        ));
        
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                'file' => 'vfs://test_dir/fileInvalid.xml'
            )
        );
    }
    
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage File not found: file_doesnt_exist.ext
     * @covers ::execute
     */
    public function testExecute_fileNotFound()
    {
        // mock the Kernel or create one depending on your needs
        $application = new Console($this->getBBApp());
        $application->add(new AclLoadCommand());

        $command = $application->find('acl:load');
        $commandTester = new CommandTester($command);
        
        $file = 'file_doesnt_exist.ext';
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                'file' => $file
            )
        );
    }
}