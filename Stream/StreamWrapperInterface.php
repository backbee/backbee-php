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

namespace BackBee\Stream;

/**
 * Interface for the construction of new class wrappers.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
interface StreamWrapperInterface
{
    /**
     * Renames a content.
     *
     * @see php.net/manual/en/book.stream.php
     */
    /*public function rename($path_from, $path_to);*/

    /**
     * Close an resource.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_close();

    /**
     * Tests for end-of-file on a resource.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_eof();

    /**
     * Opens a stream content.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_open($path, $mode, $options, &$opened_path);

    /**
     * Read from stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_read($count);

    /**
     * Seeks to specific location in a stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_seek($offset, $whence = \SEEK_SET);

    /**
     * Retrieve information about a stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_stat();

    /**
     * Retrieve the current position of a stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_tell();

    /**
     * Write to stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_write($data);

    /**
     * Option setter.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool;

    /**
     * Delete a file.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function unlink($path);

    /**
     * Retrieve information about a stream.
     *
     * @see php.net/manual/en/book.stream.php
     */
    public function url_stat($path, $flags);
}
