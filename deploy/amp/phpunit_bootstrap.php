<?php
/* 
 * Bootstrap for phpunit under AMP/NetBeans
 */

// Setup bm_rand() override for unrandom tests
$BM_RAND_VALS = array();
$BM_RAND_REQUIRE_OVERRIDE = FALSE;

function bm_rand($min = FALSE, $max = FALSE) {
    global $BM_RAND_VALS, $BM_RAND_REQUIRE_OVERRIDE;

    if (count($BM_RAND_VALS) > 0) {
        return array_shift($BM_RAND_VALS);
    }
    if ($BM_RAND_REQUIRE_OVERRIDE) {
        throw new Exception("Called bm_rand() from a test requiring overrides, but BM_RAND_VALS is empty");
    }

    if (is_numeric($min) && is_numeric($max)) {
        return mt_rand($min, $max);
    }
    return mt_rand();
}

// Now include the bootstrap file from the code itself
// NetBeans will find it in this relative path
require_once( "../src/lib/bootstrap.php" );
