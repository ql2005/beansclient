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
 * Class Stats
 *
 * @package xobotyi\beansclient\Command
 */
class Stats extends CommandAbstract
{
    /**
     * Stats constructor.
     */
    public function __construct() {
        $this->commandName = Interfaces\Command::STATS;
    }

    /**
     * @return string
     */
    public function getCommandStr() :string {
        return $this->commandName;
    }

    /**
     * @param array       $responseHeader
     * @param null|string $responseStr
     *
     * @return array
     * @throws \Exception
     * @throws \xobotyi\beansclient\Exception\Command
     */
    public function parseResponse(array $responseHeader, ?string $responseStr) :?array {
        if ($responseHeader[0] !== Response::OK) {
            throw new Exception\Command("Got unexpected status code [${responseHeader[0]}]");
        }
        else if (!$responseStr) {
            throw new Exception\Command('Got unexpected empty response');
        }

        return Response::YamlParse($responseStr, true);
    }
}