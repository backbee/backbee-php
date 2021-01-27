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

namespace BackBee\Stream\ClassWrapper;

use BackBee\AutoLoader\AutoLoader;
use BackBee\Stream\ClassWrapper\Exception\ClassWrapperException;
use BackBee\Stream\StreamWrapperInterface;
use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

/**
 * Abstract class for content wrapper in BackBee 4
 * Implements IClassWrapper.
 *
 * BackBee defines bb.class protocol to include its class definition
 * Several wrappers could be defined for several storing formats:
 *  - yaml files
 *  - xml files
 *  - yaml stream stored in DB
 *  - ...
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
abstract class AbstractClassWrapper implements StreamWrapperInterface
{
    /**
     * The registered BackBee autoloader.
     *
     * @var Autoloader
     */
    protected $_autoloader;

    /**
     * The data of the stream.
     *
     * @var string
     */
    protected $_data;

    /**
     * The seek position in the stream.
     *
     * @var int
     */
    protected $_pos = 0;

    /**
     * The protocol handled by the wrapper.
     *
     * @var string
     */
    protected $_protocol = "bb.class";

    /**
     * Information about the stream ressource.
     *
     * @var array
     */
    protected $_stat;

    /**
     * the class content name to load.
     *
     * @var string
     */
    protected $classname;

    /**
     * The class to be extended by the class content loaded.
     *
     * @var string
     */
    protected $extends = '\BackBee\ClassContent\AbstractClassContent';

    /**
     * Interface(s) used by the class content.
     *
     * @var string
     */
    protected $interfaces;

    /**
     * Trait(s) used by the class content.
     *
     * @var string
     */
    protected $traits;

    /**
     * The doctrine repository associated to the class content loaded.
     *
     * @var string
     */
    protected $repository = 'BackBee\ClassContent\Repository\ClassContentRepository';

    /**
     * The elements of the class content.
     *
     * @var array
     */
    protected $elements;

    /**
     * The namespace of the class content loaded.
     *
     * @var string
     */
    protected $namespace = "BackBee\ClassContent";

    /**
     * the user parameters of the class content.
     *
     * @var array
     */
    protected $parameters;

    /**
     * the properties of the class content.
     *
     * @var array
     */
    protected $properties;

    /**
     * Default php template to build the class file.
     *
     * @var string
     */
    protected $template =
        '<?php
namespace <namespace>;

/**
 * @\Doctrine\ORM\Mapping\Entity(repositoryClass="<repository>")
 * @\Doctrine\ORM\Mapping\Table(name="content")
 * @\Doctrine\ORM\Mapping\HasLifecycleCallbacks
 * <docblock>
 */
class <classname> extends <extends> <interface>
{
    <trait>
    public function __construct($uid = NULL, $options = NULL)
    {
        parent::__construct($uid, $options);
        $this->initData();
    }

    protected function initData()
    {
        <defineDatas>
        <defineParam>
        <defineProps>
        parent::initData();
    }
}
';
    protected $_cache;

    /**
     * Class constructor
     * Retrieve the registered BackBee autoloader.
     */
    public function __construct()
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (true === is_array($autoloader) && $autoloader[0] instanceof AutoLoader) {
                if ($autoloader[0] !== null && $autoloader[0]->getApplication()) {
                    $this->_autoloader = $autoloader[0];
                    break;
                }
            }
        }

        if (null !== $this->_autoloader && null !== $this->_autoloader->getApplication()) {
            $this->_cache = $this->_autoloader->getApplication()->getBootstrapCache();
        }

        $this->elements = array();
        $this->properties = array();
        $this->parameters = array();
    }

    /**
     * Build the php code corresponding to the loading class.
     *
     * @return string The generated php code
     */
    protected function _buildClass()
    {
        $defineData = $this->_extractData($this->elements);

        $defineParam = $this->parameters;
        $defineProps = $this->properties;

        $docBlock = '';
        foreach ($defineData as $key => $element) {
            $type = $element['type'];
            if ('scalar' === $type) {
                $type = 'string';
            }

            $docBlock .= "\n * @property " . $type . ' $' . $key . ' ' . ($element['options']['label'] ?? '');
        }

        array_walk(
            $defineData,
            function (&$value, $key) {
                $value = "->defineData('" . $key . "', '" . $value['type'] . "', " . var_export(
                    $value['options'],
                    true
                ) . ")";
            }
        );
        array_walk(
            $defineParam,
            function (&$value, $key) {
                $value = "->defineParam('" . $key . "', " . var_export($value, true) . ")";
            }
        );
        array_walk(
            $defineProps,
            function (&$value, $key) {
                $value = "->defineProperty('" . $key . "', " . var_export($value, true) . ")";
            }
        );

        $phpCode = str_replace(
            array(
                '<namespace>',
                '<classname>',
                '<repository>',
                '<extends>',
                '<interface>',
                '<trait>',
                '<defineDatas>',
                '<defineParam>',
                '<defineProps>',
                '<docblock>',
            ),
            array(
                $this->namespace,
                $this->classname,
                $this->repository,
                $this->extends,
                $this->interfaces,
                $this->traits,
                (0 < count($defineData)) ? '$this' . implode('', $defineData) . ';' : '',
                (0 < count($defineParam)) ? '$this' . implode('', $defineParam) . ';' : '',
                (0 < count($defineProps)) ? '$this' . implode('', $defineProps) . ';' : '',
                $docBlock,
            ),
            $this->template
        );

        return $phpCode;
    }

    /**
     * Checks for a normalize var name.
     *
     * @param string $var The var name to check
     *
     * @return string
     * @throws ClassWrapperException Occurs for a syntax error
     */
    protected function _normalizeVar($var, $includeSeparator = false)
    {
        if ($includeSeparator) {
            $var = explode(NAMESPACE_SEPARATOR, $var);
        }

        $vars = (array)$var;

        $pattern = "/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/";

        foreach ($vars as $var) {
            if ($var !== '' && !preg_match($pattern, $var)) {
                throw new ClassWrapperException(sprintf('Syntax error: \'%s\'', $var));
            }
        }

        return implode(($includeSeparator) ? NAMESPACE_SEPARATOR : '', $vars);
    }

    /**
     * @see ClassWrapperInterface::stream_set_option()
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    /**
     * @see ClassWrapperInterface::stream_close()
     */
    public function stream_close()
    {
    }

    /**
     * @see ClassWrapperInterface::stream_write()
     */
    public function stream_write($data)
    {
    }

    /**
     * @see ClassWrapperInterface::unlink()
     */
    public function unlink($path)
    {
    }

    /**
     * @see ClassWrapperInterface::stream_eof()
     */
    public function stream_eof()
    {
        return $this->_pos >= strlen($this->_data);
    }

    /**
     * @see ClassWrapperInterface::stream_read()
     */
    public function stream_read($count)
    {
        $ret = substr($this->_data, $this->_pos, $count);
        $this->_pos += strlen($ret);

        return $ret;
    }

    /**
     * @see ClassWrapperInterface::stream_seek()
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen($this->_data) && $offset >= 0) {
                    $this->_pos = $offset;

                    return true;
                }

                return false;

            case SEEK_CUR:
                if ($offset >= 0) {
                    $this->_pos += $offset;

                    return true;
                }

                return false;

            case SEEK_END:
                if (strlen($this->_data) + $offset >= 0) {
                    $this->_pos = strlen($this->_data) + $offset;

                    return true;
                }

                return false;

            default:
                return false;
        }
    }

    /**
     * @see ClassWrapperInterface::stream_stat()
     */
    public function stream_stat()
    {
        //return $this->_stat;
    }

    /**
     * @see ClassWrapperInterface::stream_tell()
     */
    public function stream_tell()
    {
        return $this->_pos;
    }

    /**
     * @see ClassWrapperInterface::url_stat()
     */
    public function url_stat($path, $flags)
    {
        return $this->_stat;
    }

    /**
     * Extract and format datas from parser.
     *
     * @param array $data
     */
    abstract protected function _extractData($data);

    /**
     * @see ClassWrapperInterface::glob()
     */
    abstract public function glob($pattern);
}
