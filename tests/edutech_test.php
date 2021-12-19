<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use repository_edutech\edutech;

/**
 * Tests for the EduTech API.
 * 
 * @package repository_edutech
 * @copyright 2021 EduTech
 * @author Mauricio Cruz Portilla <mauricio.portilla@hotmail.com>
 * @author Ricardo Moguel Sánchez <>
 * @author Fernando Orozco Martínez <>
 * @author Francisco Sánchez Vásquez <fransanchez@uv.mx>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_edutech_edutech_testcase extends advanced_testcase {

    /**
     * Test to check a failed authentication.
     *
     * @return void
     */
    public function test_failure_authenticate() {
        global $SESSION;
        $this->expectException(repository_exception::class);
        $accessToken = edutech::authenticate("", "");
        $this->assertNotEmpty($accessToken);
        $this->assertFalse(edutech::is_authenticated());
        $this->assertFalse(isset($SESSION->edutech));
    }

    /**
     * Test to check that data is unset on log out.
     *
     * @return void
     */
    public function test_logout() {
        global $SESSION;
        $SESSION->edutech = (object) [
            "access_token" => "token",
            "refresh_token" => "token",
        ];
        $this->assertTrue(isset($SESSION->edutech));
        edutech::logout();
        $this->assertFalse(isset($SESSION->edutech));
    }
}