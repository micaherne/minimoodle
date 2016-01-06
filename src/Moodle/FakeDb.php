<?php

namespace Minimoodle\Moodle;

/**
 * Fake Moodle database class to allow plugin_manager to be used.
 *
 * For plugin types like auth, qtype etc that check whether a plugin is actually used.
 *
 * @author Michael Aherne
 *
 */
class FakeDb {

    public function record_exists() {
        return false;
    }

}