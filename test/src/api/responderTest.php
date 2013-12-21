<?php

class responderTest extends PHPUnit_Framework_TestCase {

    /**
     * @var object  responder object which will be tested
     * @var dummy   dummy_responder object used to check the live responder
     */
    protected $object;
    protected $dummy;
    protected $user_ids;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {

        // setup the test interface
        if (file_exists('../test/src/database/mysql.test.inc.php')) {
            require '../test/src/database/mysql.test.inc.php';
        } else {
            require 'test/src/database/mysql.test.inc.php';
        }

        require_once 'api/responder.php';
        $this->object = new responder(True);

        require_once 'api/dummy_responder.php';
        $this->dummy = new dummy_responder(True);

        // Cache user IDs parsed from the DB for use within a test
        $this->user_ids = array();
    }

    /**
     * Check two PHP arrays to see if their structures match to a depth of one level:
     * * Do the arrays have the same sets of keys?
     * * Does each key have the same type of value for each array?
     */
    protected function object_structures_match($obja, $objb, $inspect_child_arrays=False) {
        foreach ($obja as $akey => $avalue) {
            if (!(array_key_exists($akey, $objb))) {
                return False;
            }
            if (gettype($obja[$akey]) != gettype($objb[$akey])) {
                return False;
            }
            if (($inspect_child_arrays) and (gettype($obja[$akey]) == 'array')) {
                if ((array_key_exists(0, $obja[$akey])) || (array_key_exists(0, $objb[$akey]))) {
                    if (gettype($obja[$akey][0]) != gettype($objb[$akey][0])) {
                        return False;
                    }
                }
            }
        }
        foreach ($objb as $bkey => $bvalue) {
            if (!(array_key_exists($bkey, $obja))) {
                return False;
            }
        }
        return True;
    }

    /**
     * Make sure users responder001-004 exist, and get
     * fake session data for responder003.
     */
    protected function mock_test_user_login() {

        // make sure responder001 and responder002 exist
        $args = array('type' => 'createUser',
                      'username' => 'responder001',
                      'password' => 't');
        $this->object->process_request($args);
        $args['username'] = 'responder002';
        $this->object->process_request($args);

        // now make sure responder003 exists and get the ID
        if (!(array_key_exists('responder003', $this->user_ids))) {
            $args['username'] = 'responder003';
            $ret1 = $this->object->process_request($args);
            if ($ret1['data']) {
                $ret1 = $this->object->process_request($args);
            }
            $matches = array();
            preg_match('/id=(\d+)/', $ret1['message'], $matches);
            $this->user_ids['responder003'] = (int)$matches[1];
        }

        // now make sure responder004 exists and get the ID
        if (!(array_key_exists('responder004', $this->user_ids))) {
            $args['username'] = 'responder004';
            $ret1 = $this->object->process_request($args);
            if ($ret1['data']) {
                $ret1 = $this->object->process_request($args);
            }
            $matches = array();
            preg_match('/id=(\d+)/', $ret1['message'], $matches);
            $this->user_ids['responder004'] = (int)$matches[1];
        }

        // now return $_SESSION variable style data for responder003
        return array('user_name' => 'responder003', 'user_id' => $this->user_ids['responder003']);
    }

    public function test_request_invalid() {
        $args = array('type' => 'foobar');
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);
        $expected = array(
          'data' => NULL,
          'message' => NULL,
          'status' => 'failed',
        );
        $this->assertEquals($expected, $retval,
                            "return failure when invoked with a nonexistent request");

	// This test result should hold for all functions, since
	// the structure of the top-level response doesn't depend
	// on the function
        $this->assertEquals($retval, $dummyval);
        $this->assertTrue(
            $this->object_structures_match($retval, $dummyval),
            "Real and dummy return values should have matching structures");
    }

    public function test_request_createUser() {

        $created_real = False;
        $maxtries = 999;
        $trynum = 1;

	// Tests may be run multiple times.  Find a user of the
	// form responderNNN which hasn't been created yet and
	// create it in the test DB.  The dummy interface will claim
	// success for any username of this form.
        while (!($created_real)) {
            $this->assertTrue($trynum < $maxtries,
                "Internal test error: too many responderNNN users in the test database. " .
                "Clean these out by hand.");
            $username = 'responder' . sprintf('%03d', $trynum);
            $real_new = $this->object->process_request(
                            array('type' => 'createUser',
                                  'username' => $username,
                                  'password' => 't'));
            if ($real_new['status'] == 'ok') {
                $created_real = True;
            }
            $trynum += 1;
        }
        $dummy_new = $this->dummy->process_request(
                         array('type' => 'createUser',
                               'username' => $username,
                               'password' => 't'));
        $this->assertEquals($dummy_new, $real_new,
            "Creation of $username user should be reported as success");
    }

    public function test_request_createGame() {
        $_SESSION = $this->mock_test_user_login();
        $args = array(
            'type' => 'createGame',
            'playerNameArray' => array('responder003', 'responder004'),
            'buttonNameArray' => array('Avis', 'Avis'),
            'maxWins' => '3',
        );
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);
        $this->assertEquals('ok', $retval['status'], 'Game creation should succeed');

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($dummydata, $retdata),
            "Real and dummy game creation return values should have matching structures");
    }

    public function test_request_loadActiveGames() {
        $_SESSION = $this->mock_test_user_login();

        // make sure there's at least one game
        $args = array(
            'type' => 'createGame',
            'playerNameArray' => array('responder003', 'responder004'),
            'buttonNameArray' => array('Hammer', 'Stark'),
            'maxWins' => '3',
        );
        $this->object->process_request($args);

        $args = array('type' => 'loadActiveGames');
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);

        $this->assertEquals('ok', $retval['status'], 'Loading games should succeed');

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($dummydata, $retdata, True),
            "Real and dummy game lists should have matching structures");
    }

    public function test_request_loadButtonNames() {
        $args = array('type' => 'loadButtonNames');
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);
        $this->assertEquals('ok', $retval['status'], "responder should succeed");
        $this->assertEquals('ok', $dummyval['status'], "dummy responder should succeed");

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($retdata, $dummydata, True),
            "Real and dummy button lists should have matching structures");

	// Each button in the dummy data should exactly match a
	// button in the live data
        foreach ($dummydata['buttonNameArray'] as $dummyidx => $dbuttonname) {
            $foundButton = False;
            foreach ($retdata['buttonNameArray'] as $retidx => $rbuttonname) {
                if ("$dbuttonname" === "$rbuttonname") {
                    $foundButton = True;
                    $this->assertEquals(
                        array("buttonName"            => $dummydata['buttonNameArray'][$dummyidx],
                              "recipe"                => $dummydata['recipeArray'][$dummyidx],
                              "hasUnimplementedSkill" => $dummydata['hasUnimplementedSkillArray'][$dummyidx]),
                        array("buttonName"            => $retdata['buttonNameArray'][$retidx],
                              "recipe"                => $retdata['recipeArray'][$retidx],
                              "hasUnimplementedSkill" => $retdata['hasUnimplementedSkillArray'][$retidx]),
                        "Dummy and live information about button $dbuttonname match exactly");
                }
            }
            $this->assertTrue($foundButton, "Dummy button $dbuttonname was found in live data");
        }
    }

    public function test_request_loadGameData() {
        $_SESSION = $this->mock_test_user_login();

        // create a game so we have the ID to load
        $args = array(
            'type' => 'createGame',
            'playerNameArray' => array('responder003', 'responder004'),
            'buttonNameArray' => array('Avis', 'Avis'),
            'maxWins' => '3',
        );
        $retval = $this->object->process_request($args);
        $real_game_id = $retval['data']['gameId'];
        $dummy_game_id = '1';

        // now load real and dummy games
        $retval = $this->object->process_request(array('type' => 'loadGameData', 'game' => $real_game_id));
        $dummyval = $this->dummy->process_request(array('type' => 'loadGameData', 'game' => $dummy_game_id));
        $this->assertEquals('ok', $retval['status'], "responder should succeed");
        $this->assertEquals('ok', $dummyval['status'], "dummy responder should succeed");

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($dummydata, $retdata, True),
            "Real and dummy button lists should have matching structures");

	// Now hand-modify a few things we know will be different
	// and check that the data structures are entirely identical otherwise
        $dummydata['gameData']['data']['gameId'] = $retdata['gameData']['data']['gameId'];
        $dummydata['gameData']['data']['playerIdArray'] = $retdata['gameData']['data']['playerIdArray'];
        $dummydata['playerNameArray'] = $retdata['playerNameArray'];
        $dummydata['timestamp'] = $retdata['timestamp'];
        $this->assertEquals($dummydata, $retdata);
    }

    public function test_request_loadPlayerName() {
        $_SESSION = $this->mock_test_user_login();
        $args = array('type' => 'loadPlayerName');
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);

        $this->assertEquals('ok', $retval['status'], "responder should succeed");
        $this->assertEquals('ok', $dummyval['status'], "dummy responder should succeed");

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($dummydata, $retdata, True),
            "Real and dummy player names should have matching structures");
    }

    public function test_request_loadPlayerNames() {
        $args = array('type' => 'loadPlayerNames');
        $retval = $this->object->process_request($args);
        $dummyval = $this->dummy->process_request($args);

        $this->assertEquals('ok', $retval['status'], "responder should succeed");
        $this->assertEquals('ok', $dummyval['status'], "dummy responder should succeed");

        $retdata = $retval['data'];
        $dummydata = $dummyval['data'];
        $this->assertTrue(
            $this->object_structures_match($dummydata, $retdata, True),
            "Real and dummy player names should have matching structures");
    }

    public function test_request_submitSwingValues() {
        $_SESSION = $this->mock_test_user_login();

        // create a game so we have the ID to load
        $args = array(
            'type' => 'createGame',
            'playerNameArray' => array('responder003', 'responder004'),
            'buttonNameArray' => array('Avis', 'Avis'),
            'maxWins' => '3',
        );
        $retval = $this->object->process_request($args);
        $real_game_id = $retval['data']['gameId'];
        $dummy_game_id = '1';

        // now ask for the game data so we have the timestamp to return
        $args = array(
            'type' => 'loadGameData',
            'game' => "$real_game_id");
        $retval = $this->object->process_request($args);
        $timestamp = $retval['data']['timestamp'];

        // now submit the swing values
        $args = array(
            'type' => 'submitSwingValues',
            'roundNumber' => 1,
            'timestamp' => $timestamp,
            'swingValueArray' => array('7'));
        $args['game'] = $real_game_id;
        $retval = $this->object->process_request($args);
        $args['game'] = $dummy_game_id;
        $dummyval = $this->dummy->process_request($args);
        $this->assertEquals('ok', $retval['status'], "responder should succeed");
        $this->assertEquals($dummyval, $retval, "swing value submission responses should be identical");
    }

    public function test_request_submitTurn() {
        $this->markTestIncomplete("No test for submitTurn responder yet");
    }

    public function test_request_login() {
        $this->markTestIncomplete("No test for login responder yet");
    }

    public function test_request_logout() {
        $this->markTestIncomplete("No test for logout responder yet");
    }
}