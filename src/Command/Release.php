<?php
/**
 * @Author : a.zinovyev
 * @Package: beansclient
 * @License: http://www.opensource.org/licenses/mit-license.php
 */

namespace xobotyi\beansclient\Command;

use xobotyi\beansclient\Exception;
use xobotyi\beansclient\Interfaces;
use xobotyi\beansclient\Response;

/**
 * Class Release
 *
 * @package xobotyi\beansclient\Command
 */
class Release extends CommandAbstract
{
    /**
     * @var int
     */
    private $delay;
    /**
     * @var int
     */
    private $jobId;
    /**
     * @var int|float
     */
    private $priority;

    /**
     * Release constructor.
     *
     * @param int $jobId
     * @param     $priority
     * @param int $delay
     *
     * @throws \xobotyi\beansclient\Exception\Command
     */
    public function __construct(int $jobId, $priority, int $delay) {
        if ($jobId <= 0) {
            throw new Exception\Command('Job id must be a positive integer');
        }
        if (!is_numeric($priority)) {
            throw new Exception\Command('Argument 2 passed to xobotyi\beansclient\BeansClient::put() must be a number, got ' . gettype($priority));
        }
        if ($priority < 0 || $priority > Put::MAX_PRIORITY) {
            throw new Exception\Command('Job priority must be between 0 and ' . Put::MAX_PRIORITY);
        }
        if ($delay < 0) {
            throw new Exception\Command('Job delay must be a positive integer');
        }

        $this->commandName = Interfaces\Command::RELEASE;

        $this->jobId    = $jobId;
        $this->priority = $priority;
        $this->delay    = $delay;
    }

    /**
     * @return string
     */
    public function getCommandStr() :string {
        return $this->commandName . ' ' . $this->jobId . ' ' . $this->priority . ' ' . $this->delay;
    }

    /**
     * @param array       $responseHeader
     * @param null|string $responseStr
     *
     * @return null|string
     * @throws \xobotyi\beansclient\Exception\Command
     */
    public function parseResponse(array $responseHeader, ?string $responseStr) :?string {
        if ($responseStr) {
            throw new Exception\Command("Unexpected response data passed");
        }
        else if ($responseHeader[0] === Response::RELEASED || $responseHeader[0] === Response::BURIED) {
            return $responseHeader[0];
        }
        else if ($responseHeader[0] === Response::NOT_FOUND) {
            return null;
        }

        throw new Exception\Command("Got unexpected status code [${responseHeader[0]}]");
    }
}