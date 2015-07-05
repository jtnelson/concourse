<?php
require_once dirname(__FILE__) . "/../src/Concourse.php";
/*
 * Copyright 2015 Cinchapi, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Base class for unit tests that use Mockcourse.
 *
 * @author jnelson
 */
abstract class IntegrationBaseTest extends PHPUnit_Framework_TestCase {

    /**
     * The PID of the bash script that actually launches the Mockcourse groovy
     * process. We need to keep this around in order to (mostly) relaibly figure
     * out the correct Groovy process to kill when all the tests are done.
     * @var int 
     */
    static $PID;
    
    /**
     * A reference to the Concourse client to use in all the unit tests.
     * @var Concourse 
     */
    static $client;

    /**
     * Fixture to start Mockcourse and connect before the tests run.
     * @throws Exception
     */
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        $script = dirname(__FILE__) . "/../../../../mockcourse/mockcourse";
        static::$PID = shell_exec("bash " . $script . " > /dev/null 2>&1 & echo $!");
        $tries = 5;
        while($tries > 0 && empty(static::$client)){
            $tries-= 1;
            sleep(1); // wait for Mockcourse to start
            try {
                static::$client = Concourse::connect("localhost", 1818, "admin", "admin");
            } 
            catch (Exception $ex) {
                if($tries == 0){
                    throw $ex;
                }
                else{
                    continue;
                }
            }
        }
    }

    /**
     * Fixture to kill Mockcourse after all the tests have run.
     */
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        $pid = static::getMockcoursePid();
        shell_exec("kill -9 ".$pid);
    }
    
    /**
     * "Logout" and clear all the data that the client stored in Mockcourse after
     * each test. This ensures that the environment for each test is clean and
     * predicatable.
     */
    public function tearDown() {
        parent::tearDown();
        static::$client->logout();
    }

    /**
     * PHP seemingly does not have a good way to setsid and exec/fork a 
     * background process whilist keeping up with the parent process id so
     * that we can kill it upon termination and stop Mockcourse. To get around
     * that we have to store the PID of the bash script that launches the 
     * Groovy process for Mockcourse and then query for all the groovy 
     * processes and get the PID that is closes to the one we stored and assume
     * that is the PID of the Mockcourse groovy process. Killing that process
     * will kill all Mockcourse related processes that we indeed started
     * @return the process id that we want to kill (int)
     */
    private static function getMockcoursePid() {
        $script = dirname(__FILE__) . "/../../../../mockcourse/getpid";
        $pid = shell_exec("bash " . $script);
        $pids = explode("\n", $pid);
        foreach ($pids as $p) {
            if (!empty($p)) {
                $delta[$p] = abs($p - static::$PID);
            }
        }
        asort($delta);
        $delta = array_keys($delta);
        return $delta[0];
    }

}
