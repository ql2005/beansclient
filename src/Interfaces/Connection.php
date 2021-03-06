<?php
/**
 * @Author : a.zinovyev
 * @Package: beansclient
 * @License: http://www.opensource.org/licenses/mit-license.php
 */

namespace xobotyi\beansclient\Interfaces;

/**
 * Interface Connection
 *
 * @package xobotyi\beansclient\Interfaces
 */
interface Connection
{
    /**
     * Connection constructor.
     *
     * @param string   $host
     * @param int      $port
     * @param int|null $connectionTimeout
     * @param bool     $persistent
     */
    public function __construct(string $host = 'localhost', int $port = -1, int $connectionTimeout = null, bool $persistent = false);

    /**
     * @return bool
     */
    public function disconnect() :bool;

    /**
     * @return string
     */
    public function getHost() :string;

    /**
     * @return int
     */
    public function getPort() :int;

    /**
     * @return bool
     */
    public function isActive() :bool;

    /**
     * @return bool
     */
    public function isPersistent() :bool;

    /**
     * @param int $length
     *
     * @return string
     */
    public function read() :string;

    /**
     * @param int|null $length
     *
     * @return string
     */
    public function readln() :string;

    /**
     * @param string $str
     */
    public function write(string $str) :void;
}
