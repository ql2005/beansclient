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

        if (PHP_SAPI == "cli") {
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
        } else {
            $this->socket = $persistent
            ? $this->pfsockopen($this->host, $this->port, $errNo, $errStr, $this->timeout)
            : $this->fsockopen($this->host, $this->port, $errNo, $errStr, $this->timeout);

            if (!$this->socket) {
                throw new Exception\Connection($errNo, $errStr . " (while connecting to {$this->host}:{$this->port})");
            }

            $this->setReadTimeout($this->socket, self::SOCK_READ_TIMEOUT);
        }
    }

    public function __destruct()
    {
        if (PHP_SAPI == "cli") {
            $this->socket->close();
            $this->socket = null;
        } else {
            if (!$this->persistent) {
                if (!$this->fclose($this->socket)) {
                    throw new Exception\Connection(0, "Unable to close connection");
                }

                $this->socket = null;
            }
        }
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
        if (PHP_SAPI == "cli") {
            if (!$this->socket) {
                throw new Exception\Connection(0, "Unable to read from closed connection");
            }

            $str = $this->socket->recv();
            return $str;
        } else {
            return $this->readln();
        }
    }

    /**
     * Reads up to newline or $length-1 bytes from socket
     *
     * @throws \xobotyi\beansclient\Exception\Connection
     * @throws \xobotyi\beansclient\Exception\Socket
     */
    public function readln($length = null) :string
    {
        if (PHP_SAPI == "cli") {
            return $this->read();
        } else {
            if (!$this->socket) {
                throw new Exception\Connection(0, "Unable to read from closed connection");
            }

            $str = false;

            while ($str === false) {
                $str = isset($length)
                    ? $this->fgets($this->socket, $length)
                    : $this->fgets($this->socket);

                if ($this->feof($this->socket)) {
                    throw new Exception\Socket(sprintf("Socket closed by remote ({$this->host}:{$this->port})"));
                }
            }

            return rtrim($str);
        }
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
        if (PHP_SAPI == "cli") {
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
        } else {
            if (!$this->socket) {
                throw new Exception\Connection(0, "Unable to write into closed connection");
            }

            for ($attempt = $written = $iterWritten = 0; $written < strlen($str); $written += $iterWritten) {
                $iterWritten = $this->fwrite($this->socket, substr($str, $written));

                if (++$attempt === self::SOCK_WRITE_RETRIES) {
                    throw new Exception\Socket(sprintf("Failed to write data to socket after %u retries (%u:%u)", self::SOCK_WRITE_RETRIES, $this->host, $this->port));
                }
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
        if ($this->isReConnection === true) {
            // reset
            $this->isReConnection = false;
            return true;
        }

        return false;
    }
}
