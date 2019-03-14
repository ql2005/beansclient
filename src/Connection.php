<?php
/**
 * @Author : a.zinovyev
 * @Package: beansclient
 * @License: http://www.opensource.org/licenses/mit-license.php
 */

namespace xobotyi\beansclient;

use swoole\Client;
use xobotyi\beansclient\Command\Stats;
use xobotyi\beansclient\Command\IgnoreTube;

/**
 * Class Connection
 *
 * @package xobotyi\beansclient
 */
class Connection extends SocketFunctions implements Interfaces\Connection
{
    const SOCK_CONNECTION_TIMEOUT = 60;
    const SOCK_READ_TIMEOUT       = 3;
    const SOCK_WRITE_RETRIES      = 8;
    const CRLF = "\r\n";

    private $host;
    private $persistent;
    private $port;
    private $socket;
    private $timeout;
    private $isReConnection = false;

    /**
     * Connection constructor.
     *
     * @param string $host
     * @param int    $port
     * @param int    $connectionTimeout
     * @param bool   $persistent
     *
     * @throws Exception\Connection
     */
    public function __construct(
        string $host = 'localhost',
        int $port = 11300,
        int $connectionTimeout = null,
        bool $persistent = true
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->timeout    = $connectionTimeout === null ? self::SOCK_CONNECTION_TIMEOUT : $connectionTimeout;
        $this->persistent = $persistent;

        $this->socket = new Client(SWOOLE_SOCK_TCP);

        $this->socket->set([
            'open_eof_check' => true,
            'package_eof' => self::CRLF,
            'package_max_length' => 1024 * 1024 * 2,
        ]);

        if (! $this->socket->connect($this->host, $this->port, $this->timeout)) {
            $this->socket = null;
            throw new Exception\Connection(0, 'beanstalk connection error');
        }
    }

    public function __destruct()
    {
        $this->socket->close();
        $this->socket = null;
    }

    /**
     *  Disconnect the socket
     */
    public function disconnect() :bool
    {
        if (!$this->socket) {
            return false;
        }

        $this->socket->close();

        return !$this->isActive();
    }

    /**
     * @return string
     */
    public function getHost() :string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort() :int
    {
        return $this->port;
    }

    /**
     * @return int
     */
    public function getTimeout() :int
    {
        return $this->timeout;
    }

    /**
     * @return bool
     */
    public function isActive() :bool
    {
        return !!$this->socket;
    }

    /**
     * @return bool
     */
    public function isPersistent() :bool
    {
        return $this->persistent;
    }

    /**
     * Reads up to $length bytes from socket
     *
     * @return string
     * @throws \xobotyi\beansclient\Exception\Connection
     * @throws \xobotyi\beansclient\Exception\Socket
     */
    public function read() :string
    {
        if (!$this->socket) {
            throw new Exception\Connection(0, "Unable to read from closed connection");
        }

        $str = $this->socket->recv();
        return $str;
    }

    /**
     * Reads up to newline or $length-1 bytes from socket
     *
     * @throws \xobotyi\beansclient\Exception\Connection
     * @throws \xobotyi\beansclient\Exception\Socket
     */
    public function readln() :string
    {
        return $this->read();
    }

    /**
     * @param string $str
     *
     * Writes data to the socket
     *
     * @throws \xobotyi\beansclient\Exception\Connection
     * @throws \xobotyi\beansclient\Exception\Socket
     */
    public function write(string $str) :void
    {
        if (!$this->socket) {
            throw new Exception\Connection(0, "Unable to write into closed connection");
        }

        try {
            $send = $this->socket->send($str);
        } catch (\Exception $e) {
            $this->reConnection();
            $send = $this->socket->send($str);
        }

        if (! $send) {
            $this->reConnection();
            $send = $this->socket->send($str);
            if (! $send) {
                throw new Exception\Socket('beanstalk send fail: ' . $this->socket->errCode);
            }
        }
    }

    /**
     * reConnection beanstalk
     */
    public function reConnection()
    {
        $this->socket->close();
        $reconn = $this->socket->connect($this->host, $this->port, $this->timeout);
        if (! $reconn) {
            throw new Exception\Socket('beanstalk connect fail: ' . $this->socket->errCode);
        }
        $this->isReConnection = true;
        return $reconn;
    }

    /**
     * when socket reconnection return true
     *
     * @return boolean
     * @date 2019-03-12
     */
    public function isReconn(): bool
    {
        return $this->isReConnection;
    }

    /**
     * reset isReConnection false
     *
     * @return self
     * @date 2019-03-12
     */
    public function clearReconn(): self
    {
        $this->isReConnection = false;
        return $this;
    }
}
