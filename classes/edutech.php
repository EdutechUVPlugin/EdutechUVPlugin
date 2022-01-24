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
namespace repository_edutech;
defined('MOODLE_INTERNAL') || die();
/**
 * Helper class for handling communication with EduTech API.
 * 
 * @package repository_edutech
 * @copyright 2021 EduTech
 * @author Mauricio Cruz Portilla <mauricio.portilla@hotmail.com>
 * @author Ricardo Moguel Sánchez <>
 * @author Fernando Orozco Martínez <>
 * @author Francisco Sánchez Vásquez <fransanchez@uv.mx>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edutech{
    private static $loginendpoint = "https://repositorio.edutech-project.org/api/v1/login/";
    private static $verifytokenendpoint = "https://repositorio.edutech-project.org/api/v1/token/verify/";
    private static $refreshtokenendpoint = "https://repositorio.edutech-project.org/api/v1/token/refresh/";
    private static $learningobjectsendpoint = "https://repositorio.edutech-project.org/api/v1/learning-objects/populars/";
    private static $learningobjectssearchendpoint = "https://repositorio.edutech-project.org/api/v1/learning-objects/search/";
    private static $filtersendpoint = "https://repositorio.edutech-project.org/api/v1/endpoint-filter";
    /**
     * Attempt to log in using email and password. Throws exception
     * in case of failure. Returns access token in case of success.
     *
     * @param string $email
     * @param string $password
     * @throws \repository_exception
     * @return string
     */
    public static function authenticate(string $email, string $password){
    global $SESSION;
        $requester = new requester(self::$loginendpoint);
        $response = $requester->post([
            "email" => $email,
            "password" => $password,
        ]);
        if ($requester->response_code != 200){
            if ($requester->response_code == 401){
                throw new \repository_exception(get_string("incorrectdata", "repository_edutech"));
            }
            if (isset($response["detail"])){
                throw new \repository_exception($response["detail"]);
            }
            throw new \repository_exception(
                get_string("unavailableapi", "repository_edutech")
            );
        }
        $SESSION->edutech = (object) [
            "access_token" => $response["access"],
            "refresh_token" => $response["refresh"],
        ];
        return $response["access"];
    }
    /**
     * Unset all info about the plugin from the current session.
     *
     * @return void
     */
    public static function logout(){
        global $SESSION;
        unset($SESSION->edutech);
    }
    /**
     * Check if exists an access token in current session and if
     * it is not expired yet.
     *
     * @return boolean
     */
    public static function is_authenticated(){
        global $SESSION;
        if (isset($SESSION->edutech) && isset($SESSION->edutech->access_token)){
            $requester = new requester(self::$verifytokenendpoint);
            $response = $requester->post([
                "token" => $SESSION->edutech->access_token,
            ]);
            if ($requester->response_code == 200){
                return true;
            }
            $requester = new requester(self::$refreshtokenendpoint);
            $response = $requester->post([
                "refresh" => $SESSION->edutech->refresh_token,
            ]);
            if ($requester->response_code != 200){
                if (isset($response["detail"])){
                    self::logout();
                    throw new \repository_exception($response["detail"]);
                }
                self::logout();
                throw new \repository_exception(get_string("unavailableapi", "repository_edutech"));
            }
            $SESSION->edutech->access_token = $response["access"];
            return true;
        }
        return false;
    }
    /**
     * Get all learning objects by page and filters.
     *
     * @param integer|string $page
     * @param array $filters array will be converted to URL-encoded query string.
     * @throws \repository_exception
     * @return array
     */
    public static function get_learning_objects($page, $filters = []){
        global $SESSION;
        if (!self::is_authenticated()){
            throw new \repository_exception(get_string("unauthenticated", "repository_edutech"));
        }
        $url = self::$learningobjectsendpoint;
        $filtersquery = http_build_query($filters);
        if (strlen($filtersquery) > 0){
            $filtersquery = "&" . $filtersquery;
            $url = self::$learningobjectssearchendpoint;
        }
        $requester = new requester($url . "?page=$page" . $filtersquery);
        $response = $requester->get([
            "Authorization: " . $SESSION->edutech->access_token,
            "Accept-Language: " . explode("_", current_language())[0]
        ]);
        if ($requester->response_code != 200){
            if (isset($response["detail"])){
                throw new \repository_exception($response["detail"]);
            }
            throw new \repository_exception(get_string("unavailableapi", "repository_edutech"));
        }
        return $response;
    }
    /**
     * Get all available filters.
     *
     * @throws \repository_exception
     * @return array
     */
    public static function get_filters(){
        global $SESSION;
        if (!self::is_authenticated()){
            throw new \repository_exception(get_string("unauthenticated", "repository_edutech"));
        }
        $requester = new requester(self::$filtersendpoint);
        $responsefilters = $requester->get([
            "Authorization: " . $SESSION->edutech->access_token,
            "Accept-Language: " . explode("_", current_language())[0]
        ]);
        if ($requester->response_code != 200){
            if (isset($response["detail"])){
                throw new \repository_exception($response["detail"]);
            }
            throw new \repository_exception(get_string("unavailableapi", "repository_edutech"));
        }
        $filters = [];
        foreach ($responsefilters as $filter){
            $endpoint = $filter["endpoint"];
            if (substr($endpoint, -1) != "/"){
                $endpoint .= "/";
            }
            $filterrequester = new requester($endpoint);
            $filterdata = $filterrequester->get([
                "Authorization: " . $SESSION->edutech->access_token,
                "Accept-Language: " . explode("_", current_language())[0]
            ]);
            if ($requester->response_code != 200){
                if (isset($response["detail"])){
                    throw new \repository_exception($response["detail"]);
                }
                throw new \repository_exception(get_string("unavailableapi", "repository_edutech"));
            }
            $filters[] = $filterdata;
        }
        $SESSION->edutech->language = current_language();
        return $filters;
    }
}
