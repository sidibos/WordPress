<?php

class TestLogHandler extends FTLabs\AbstractLogHandler {

    public $logs = array();

    function handleLogMessage($level, $str, array $ctx, FTLabs\ErrorLog $bla = null) {
        $str = preg_replace_callback('/{([A-Za-z0-9._]+)}/', function($m)use($ctx){
            if (isset($ctx[$m[1]]) && is_string($ctx[$m[1]])) {
                return $ctx[$m[1]];
            }
            return $m[0];
        }, $str);
        $this->logs[] = "$level $str";
    }
}

class PsrLoggerTest extends \Psr\Log\Test\LoggerInterfaceTest {

    function setUp() {
        putenv("TRACE=1");
        parent::setUp();
    }

    function tearDown() {
        putenv("TRACE");
        parent::tearDown();
    }

    function getLogs() {
        return $this->logHandler->logs;
    }

    function getLogger() {
        $this->logHandler = new TestLogHandler();
        return new FTLabs\Logger(array(
            'foo'=>array('handler'=>$this->logHandler),
        ));
    }

}
