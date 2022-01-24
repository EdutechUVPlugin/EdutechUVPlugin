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
 * Helper class for making HTTP requests.
 * 
 * @package repository_edutech
 * @copyright 2021 EduTech
 * @author Mauricio Cruz Portilla <mauricio.portilla@hotmail.com>
 * @author Ricardo Moguel Sánchez <>
 * @author Fernando Orozco Martínez <>
 * @author Francisco Sánchez Vásquez <fransanchez@uv.mx>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requester{
    
    /**
     * URL to where should be sent the request.
     *
     * @var string
     */
    public $url;
    /**
     * Request headers.
     * Format for each array value must be 'HEADER_NAME: VALUE'
     *
     * @var array
     */
    public $headers;
    /**
     * Request body content.
     *
     * @var array
     */
    public $requestcontent;
    /**
     * Request type. Available options: get, post.
     *
     * @var string
     */
    public $requesttype;
    /**
     * Request response.
     *
     * @var string
     */
    public $response;
    /**
     * Request response code.
     *
     * @var integer
     */
    public $responsecode;
    /**
     * Create a new instance.
     *
     * @param string $url
     */
    public function __construct($url){
        $this->url = $url;
    }
    /**
     * Make GET request.
     *
     * @param array $headers Format for each array value must be 'HEADER_NAME: VALUE'
     * @return array
     */
    public function get($headers = []){
        $this->headers = $headers;
        $this->requesttype = "get";
        $this->_send();
        return json_decode($this->response, true);
    }
    /**
     * Make POST request.
     *
     * @param array $requestcontent
     * @param array $headers Format for each array value must be 'HEADER_NAME: VALUE'
     * @return array
     */
    public function post($requestcontent, $headers = []){
        $this->headers = $headers;
        $this->requesttype = "post";
        $this->requestcontent = $requestcontent;
        $this->_send();
        return json_decode($this->response, true);
    }
    /**
     * Send request.
     *
     * @throws \repository_exception
     * @return void
     */
    private function _send(){
        try {
            $headers = [
                "Content-type: application/json; charset=UTF-8",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache",
            ];
            $headers = \array_merge($headers, $this->headers);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 400);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            if ($this->requesttype == "post"){
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->requestcontent));
            }
            $result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            $this->response = $result;
            $this->response_code = $code;
        } catch (\Exception $e){
            throw new \repository_exception(get_string("unavailableapi", "repository_edutech"));
        }
    }
}
