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

namespace BackBee\Stream\ClassWrapper\Adapter;

use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\Stream\ClassWrapper\AbstractClassWrapper;
use BackBee\Stream\ClassWrapper\Exception\ClassWrapperException;
use BackBee\Utils\File\File;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as parserYaml;

/**
 * Stream wrapper to interprete yaml file as class content description
 * Extends AbstractClassWrapper
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Yaml extends AbstractClassWrapper
{
    /**
     * Current BackBee application.
     *
     * @var BackBee\BBApplication
     */
    private $_application;

    /**
     * Extensions to include searching file.
     *
     * @var array
     */
    private $_includeExtensions = array('.yml', '.yaml');

    /**
     * Path to the yaml file.
     *
     * @var string
     */
    private $_path;

    /**
     * Ordered directories file path to look for yaml file.
     *
     * @var array
     */
    private $_classcontentdir;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if (null === $this->_autoloader) {
            throw new ClassWrapperException('The BackBee autoloader can not be retreived.');
        }

        $this->_application = $this->_autoloader->getApplication();
        if (null !== $this->_application) {
            $this->_classcontentdir = $this->_application->getClassContentDir();
        }

        if (null === $this->_classcontentdir || 0 == count($this->_classcontentdir)) {
            throw new ClassWrapperException('None ClassContent repository defined.');
        }
    }

    /**
     * Extract and format data from parser.
     *
     * @param array $data
     *
     * @return array The extracted data
     */
    protected function _extractData($data)
    {
        $extractedData = array();

        foreach ($data as $key => $value) {
            $type = 'scalar';
            $options = array();

            if (is_array($value)) {
                if (isset($value['type'])) {
                    $type = $value['type'];
                    if (isset($value['default'])) {
                        $options['default'] = $value['default'];
                    }

                    if (isset($value['label'])) {
                        $options['label'] = $value['label'];
                    }

                    if (isset($value['accept'])) {
                        $options['accept'] = $value['accept'];
                    }

                    if (isset($value['maxentry'])) {
                        $options['maxentry'] = $value['maxentry'];
                    }

                    if (isset($value['minentry'])) {
                        $options['minentry'] = $value['minentry'];
                    }

                    if (isset($value['parameters'])) {
                        $options['parameters'] = $value['parameters'];
                    }

                    if (isset($value['extra'])) {
                        $options['extra'] = $value['extra'];
                    }
                } else {
                    $type = 'array';
                    $options['default'] = $value;
                }
            } else {
                $value = trim($value);

                if (strpos($value, '!!') === 0) {
                    $typedValue = explode(' ', $value, 2);
                    $type = str_replace('!!', '', $typedValue[0]);
                    if (isset($typedValue[1])) {
                        $options['default'] = $typedValue[1];
                    }
                }
            }

            $extractedData[$key] = array('type' => $type, 'options' => $options);
        }

        return $extractedData;
    }

    /**
     * Checks the validity of the extracted data from yaml file.
     *
     * @param array $yamlData The yaml data
     *
     * @return Boolean Returns TRUE if data are valid, FALSE if not
     *
     * @throws ClassWrapperException Occurs when data are not valid
     */
    private function checkDatas($yamlData)
    {
        try {
            if ($yamlData === false || !is_array($yamlData) || count($yamlData) > 1) {
                throw new ClassWrapperException('Malformed class content description');
            }

            foreach ($yamlData as $classname => $contentDesc) {
                if ($this->classname != $this->_normalizeVar($this->classname)) {
                    throw new ClassWrapperException("Class Name don't match with the filename");
                }

                if (!is_array($contentDesc)) {
                    throw new ClassWrapperException('None class content description found');
                }

                foreach ($contentDesc as $key => $data) {
                    switch ($key) {
                        case 'extends':
                            $this->extends = $this->_normalizeVar($data, true);
                            if (substr($this->extends, 0, 1) != NAMESPACE_SEPARATOR) {
                                $this->extends = NAMESPACE_SEPARATOR . $this->namespace .
                                    NAMESPACE_SEPARATOR . $this->extends;
                            }

                            break;
                        case 'interfaces':
                            $data = false === is_array($data) ? array($data) : $data;
                            $this->interfaces = array();

                            foreach ($data as $i) {
                                $interface = $i;
                                if (NAMESPACE_SEPARATOR !== substr($i, 0, 1)) {
                                    $interface = NAMESPACE_SEPARATOR . $i;
                                }

                                // add interface only if it exists
                                if (true === interface_exists($interface)) {
                                    $this->interfaces[] = $interface;
                                }
                            }

                            // build up interface use string
                            $str = implode(', ', $this->interfaces);
                            if (0 < count($this->interfaces)) {
                                $this->interfaces = 'implements ' . $str;
                            } else {
                                $this->interfaces = '';
                            }

                            break;
                        case 'repository':
                            if (class_exists($data)) {
                                $this->repository = $data;
                            }
                            break;
                        case 'traits':
                            $data = false === is_array($data) ? array($data) : $data;
                            $this->traits = array();

                            foreach ($data as $t) {
                                $trait = $t;
                                if (NAMESPACE_SEPARATOR !== substr($t, 0, 1)) {
                                    $trait = NAMESPACE_SEPARATOR . $t;
                                }

                                // add traits only if it exists
                                if (true === trait_exists($trait)) {
                                    $this->traits[] = $trait;
                                }
                            }

                            // build up trait use string
                            $str = implode(', ', $this->traits);
                            if (0 < count($this->traits)) {
                                $this->traits = 'use ' . $str . ';';
                            } else {
                                $this->traits = '';
                            }

                            break;
                        case 'elements':
                        case 'parameters':
                        case 'properties':
                            $values = array();
                            $data = (array)$data;
                            foreach ($data as $var => $value) {
                                $values[strtolower($this->_normalizeVar($var))] = $value;
                            }

                            $this->$key = $values;
                            break;
                    }
                }
            }
        } catch (ClassWrapperException $e) {
            throw new ClassWrapperException($e->getMessage(), 0, null, $this->_path);
        }

        return true;
    }

    /**
     * Return the real yaml file path of the loading class.
     *
     * @param string $path
     *
     * @return string The real path if found
     */
    private function resolveFilePath($path)
    {
        $path = str_replace(array($this->_protocol . '://', '/'), array('', DIRECTORY_SEPARATOR), $path);

        foreach ($this->_includeExtensions as $ext) {
            $filename = $path . $ext;
            File::resolveFilepath($filename, null, array('include_path' => $this->_classcontentdir));
            if (true === is_file($filename)) {
                return $filename;
            }
        }

        return $path;
    }

    /**
     * @see ClassWrapperInterface::glob()
     */
    public function glob($pattern)
    {
        $classnames = [];
        foreach ($this->_classcontentdir as $repository) {
            foreach ($this->_includeExtensions as $ext) {
                if (false !== $files = glob($repository . DIRECTORY_SEPARATOR . $pattern . $ext)) {
                    foreach ($files as $file) {
                        $classnames[] = $this->namespace . NAMESPACE_SEPARATOR . str_replace(
                            [$repository . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
                            ['', NAMESPACE_SEPARATOR],
                            $file
                        );
                    }
                }
            }
        }

        if (0 == count($classnames)) {
            return false;
        }

        foreach ($classnames as &$classname) {
            $classname = str_replace($this->_includeExtensions, '', $classname);
        }
        unset($classname);

        return array_unique($classnames);
    }

    /**
     * Opens a stream content for a yaml file.
     *
     * @throws BBException           Occurs when none yamel files were found
     * @throws ClassWrapperException Occurs when yaml file is not a valid class content description
     * @see BackBee\Stream\ClassWrapper.IClassWrapper::stream_open()
     *
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $path = str_replace([$this->_protocol.'://', '/', '.php'], ['', DIRECTORY_SEPARATOR, ''], $path);

        $this->classname = basename($path);
        if (dirname($path) && dirname($path) != DIRECTORY_SEPARATOR) {
            $this->namespace .= NAMESPACE_SEPARATOR . str_replace(
                DIRECTORY_SEPARATOR,
                NAMESPACE_SEPARATOR,
                dirname($path)
            );
        }

        $this->_path = $this->resolveFilePath($path);
        if (is_file($this->_path) && is_readable($this->_path)) {
            $this->_stat = stat($this->_path);

            if (null !== $this->_cache) {
                $expire = new \DateTime();
                $expire->setTimestamp($this->_stat['mtime']);
                $this->_data = $this->_cache->load(md5($this->_path), false, $expire);

                if (false !== $this->_data) {
                    return true;
                }
            }

            try {
                $yamlDatas = parserYaml::parse(file_get_contents($this->_path));
                if (null !== $this->_application) {
                    $event = new Event(
                        $this->namespace . NAMESPACE_SEPARATOR . $this->classname,
                        ['data' => $yamlDatas]
                    );

                    $this->_application
                        ->getEventDispatcher()
                        ->triggerEvent(
                            'streamparsing',
                            $this->namespace . NAMESPACE_SEPARATOR . $this->classname,
                            null,
                            $event
                        );

                    $this->_application
                        ->getEventDispatcher()
                        ->dispatch(
                            'classcontent.streamparsing',
                            $event
                        );

                    if ($event->hasArgument('data')) {
                        $yamlDatas = $event->getArgument('data');
                    }
                }
            } catch (ParseException $e) {
                throw new ClassWrapperException($e->getMessage());
            }

            if ($this->checkDatas($yamlDatas)) {
                $this->_data = $this->_buildClass();
                $opened_path = $this->_path;

                if (null !== $this->_cache) {
                    $this->_cache->save(md5($this->_path), $this->_data);
                }

                return true;
            }
        }

        throw new BBException(
            sprintf('Class \'%s\' not found', $this->namespace . NAMESPACE_SEPARATOR . $this->classname)
        );
    }

    /**
     * Retrieve information about a yaml file.
     *
     * @see BackBee\Stream\ClassWrapper.AbstractClassWrapper::url_stat()
     */
    public function url_stat($path, $flag)
    {
        $path = str_replace(array($this->_protocol . '://', '/'), array('', DIRECTORY_SEPARATOR), $path);

        $this->_path = $this->resolveFilePath($path);
        if (is_file($this->_path) && is_readable($this->_path)) {
            $fd = fopen($this->_path, 'rb');
            $this->_stat = fstat($fd);
            fclose($fd);

            return $this->_stat;
        }

        return;
    }
}
