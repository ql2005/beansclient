<?php
    /**
     * @Author : a.zinovyev
     * @Package: beansclient
     * @License: http://www.opensource.org/licenses/mit-license.php
     */

    namespace xobotyi\beansclient;

    use PHPUnit\Framework\TestCase;
    use xobotyi\beansclient\Command\Put;
    use xobotyi\beansclient\Encoder\Json;
    use xobotyi\beansclient\Exception\Client;
    use xobotyi\beansclient\Exception\Command;
    use xobotyi\beansclient\Exception\Server;

    class PutTest extends TestCase
    {
        const HOST    = 'localhost';
        const PORT    = 11300;
        const TIMEOUT = 2;

        public
        function testPut() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->withConsecutive()
                 ->willReturnOnConsecutiveCalls("INSERTED 1", "BURIED 2");

            $client = new BeansClient($conn);

            self::assertEquals(['id' => 1, 'status' => 'INSERTED'], $client->put('test'));
            self::assertEquals(['id' => 2, 'status' => 'BURIED'], $client->put('test'));
        }

        // test if server says that CRLF is missing
        public
        function testPutException1() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("EXPECTED_CRLF"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test'));
        }

        // test if server says that job's payload is too big
        public
        function testPutException2() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("JOB_TOO_BIG"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test'));
        }

        // test if server is in draining mode
        public
        function testPutException3() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("DRAINING"));
            $client = new BeansClient($conn);

            $this->expectException(Server::class);
            self::assertEquals([], $client->put('test'));
        }

        // test if priority is less than 0
        public
        function testPutException4() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test', -1));
        }

        // test if delay id less than 0
        public
        function testPutException5() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test', 0, -1));
        }

        // test if ttr is set to 0
        public
        function testPutException6() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test', 0, 0, 0));
        }

        // test if priority is too big
        public
        function testPutException7() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('test', Put::MAX_PRIORITY + 1));
        }

        // test if payload is non-string value and encoder is not set
        public
        function testPutException8() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put([1, 2, 3]));
        }

        // test if priority is not a number
        public
        function testPutException9() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn);

            $this->expectException(Command::class);
            self::assertEquals([], $client->put('', ''));
        }

        // test if payload is too big;
        public
        function testPutException10() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn, new Json());

            $str   = '';
            $chars = 'abdefhiknrstyzABDEFGHKNQRSTYZ23456789';
            for ($i = 0; $i <= Put::MAX_SERIALIZED_PAYLOAD_SIZE + 1; $i++) {
                $str .= $chars[rand(0, 36)];
            }

            $this->expectException(Command::class);
            self::assertEquals([], $client->put($str));
        }

        // test if job id somewhy is missing;
        public
        function testPutException11() {
            $conn = $this->getConnection();
            $conn->method('readln')
                 ->will($this->returnValue("INSERTED"));
            $client = new BeansClient($conn, new Json());

            $this->expectException(Command::class);
            self::assertEquals([], $client->put(''));
        }

        private
        function getConnection(bool $active = true) {
            $conn = $this->getMockBuilder('\xobotyi\beansclient\Connection')
                         ->disableOriginalConstructor()
                         ->getMock();

            $conn->expects($this->any())
                 ->method('isActive')
                 ->will($this->returnValue($active));

            return $conn;
        }
    }