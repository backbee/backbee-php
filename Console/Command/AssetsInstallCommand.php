<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use BackBee\Console\AbstractCommand;
use BackBee\Utils\File\Dir;

/**
 * Install BBApp assets.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class AssetsInstallCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('assets:install')
            ->setDescription('Updated bbapp')
            ->setHelp(<<<EOF
The <info>%command.name%</info> install app assets:

   <info>php assets:install</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bbapp = $this->getContainer()->get('bbapp');

        $publicResourcesDir = $bbapp->getBaseDir().'/public/ressources';

        $bbappResourcesDir = $bbapp->getBBDir().'/Resources';
        $repoResourcesDir = $bbapp->getBaseRepository().'/Ressources';

        Dir::copy($repoResourcesDir, $publicResourcesDir, 0755);
        Dir::copy($bbappResourcesDir, $publicResourcesDir, 0755);

        foreach ($bbapp->getBundles() as $bundle) {
            Dir::copy($bundle->getResourcesDir(), $publicResourcesDir, 0755);
        }
    }
}
