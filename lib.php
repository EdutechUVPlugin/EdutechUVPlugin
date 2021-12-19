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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * EduTech repository plugin.
 * 
 * @package repository_edutech
 * @copyright 2021 EduTech
 * @author Mauricio Cruz Portilla <mauricio.portilla@hotmail.com>
 * @author Ricardo Moguel Sánchez <>
 * @author Fernando Orozco Martínez <>
 * @author Francisco Sánchez Vásquez <fransanchez@uv.mx>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_edutech extends repository {

    /**
     * Create a new instance.
     *
     * @param int $repositoryid repository instance id.
     * @param int|stdClass $context a context id or context object.
     * @param array $options repository options.
     * @throws \Exception
     * @return void
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $SESSION;
        parent::__construct($repositoryid, $context, $options);
        $this->email = optional_param('edutech_email', '', PARAM_RAW);
        $this->password = optional_param('edutech_password', '', PARAM_RAW);
        if (!empty($this->email) && !empty($this->password)) {
            repository_edutech\edutech::authenticate($this->email, $this->password);
            $filters = repository_edutech\edutech::get_filters();
            $SESSION->edutech->available_filters = $filters;
        }
    }

    /**
     * Return the login form.
     *
     * @return void|array for ajax.
     */
    public function print_login() {
        if ($this->options['ajax']) {
            $logo = '<a href="https://repositorio.edutech-project.org/#" target="_new">
                    <img src="https://repositorio.edutech-project.org/assets/img/image2vector.svg" alt="Edutech" style="width:30%; margin-left: 50%;"></a><br>';
            $userField = new stdClass();
            $userField->label = $logo . get_string("email", "repository_edutech");
            $userField->id    = "edutech_email";
            $userField->type  = "text";
            $userField->name  = "edutech_email";

            $passwordField = new stdClass();
            $passwordField->label = get_string("password", "repository_edutech");
            $passwordField->id    = "edutech_password";
            $passwordField->type  = "password";
            $passwordField->name  = "edutech_password";

            return [
                "login" => [$userField, $passwordField],
            ];
        } else { // Non-AJAX login form - directly output the form elements
            echo '<table>';
            echo '<tr><td><label>' . get_string("email", "repository_edutech") . '</label></td>';
            echo '<td><input type="text" name="edutech_email" /></td></tr>';
            echo '<tr><td><label>' . get_string("password", "repository_edutech") . '</label></td>';
            echo '<td><input type="password" name="edutech_password" /></td></tr>';
            echo '</table>';
            echo '<input type="submit" value="' . get_string("login", "repository_edutech") . '" />';
        }
    }

    /**
     * Checks whether the user is authenticate or not.
     *
     * @return bool true when logged in.
     */
    public function check_login() {
        return repository_edutech\edutech::is_authenticated();
    }

    /**
     * Return the filter form.
     *
     * @return string search form
     */
    public function print_search() {
        global $SESSION;
        if ($SESSION->edutech->language != current_language()) {
            $filters = repository_edutech\edutech::get_filters();
            $SESSION->edutech->available_filters = $filters;
        }
        $filters = $SESSION->edutech->available_filters;
        $html = '<div class="fp-def-search form-group">';
        $html .= html_writer::span(
            get_string("filterby", "repository_edutech"),
            "font-weight-bold mt-2"
        );
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::start_div("", [
            "style" => "display:flex;flex-flow:wrap;"
        ]);
        foreach ($filters as $filter) {
            $html .= html_writer::start_div();
            $html .= html_writer::tag(
                "label",
                $filter["name"],
                ["for" => "select_" . $filter["key"] . "_filter"]
            );
            $html .= html_writer::empty_tag('br');
            $html .= html_writer::select(
                \array_reduce($filter["values"], function ($prev, $curr) use ($filter) {
                    $prev[$curr[$filter["filter_param_value"]]] = $curr["name"];
                    return $prev;
                }),
                "select_" . $filter["key"] . "_filter",
                '',
                array('' => 'choosedots'),
                [
                    "class" => "select_filter"
                ]
            );
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::empty_tag("input", [
            "class" => "form-control",
            "id" => "reposearch",
            "type" => "hidden",
            "name" => "s"
        ]);
        $html .= html_writer::empty_tag("input", [
            "class" => "filter_btn btn btn-primary",
            "type" => "submit",
            "value" => get_string("filter", "repository_edutech"),
        ]);
        $html .= "</div>";
        return $html;
    }

    /**
     * Checks whether current request has filters or not.
     *
     * @return boolean true if there are filters
     */
    public function is_filtering() {
        global $SESSION;
        if (!isset($SESSION->edutech->available_filters)) {
            return false;
        }
        $filters = [];
        foreach ($SESSION->edutech->available_filters as $filter) {
            $filters[$filter["key"] . "__" . $filter["filter_param_value"]] = optional_param(
                "select_" . $filter["key"] . "_filter",
                "",
                PARAM_RAW_TRIMMED
            );
        }
        $SESSION->edutech->current_filters = $filters;
        return !empty(array_filter($filters, function ($x) { return $x !== ""; }));
    }

    /**
     * Search for content by filters.
     *
     * @param string $search_text not used
     * @param string $page search page
     * @return array results
     */
    public function search($search_text, $page = "") {
        global $SESSION;
        if ($this->is_filtering()) { // SUBMITTED SEARCH WITH FILTERS
            $filters = \array_filter($SESSION->edutech->current_filters, function ($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            $this->filters = $filters;
        } elseif ($page == "") { // SUBMITTED SEARCH WITH NO FILTERS
            $this->filters = [];
        } else { // NEXT PAGE WAS REQUESTED THEN
            if (isset($SESSION->filters)) {
                $this->filters = $SESSION->filters;
            }
        }
        // No matter what happened, we'll save the new filters data.
        $SESSION->filters = $this->filters;
        return $this->search_content($this->filters, "", $page);
    }

    /**
     * Search content by filters.
     *
     * @param array $filters used filters
     * @param string $path not used
     * @param string $page search page
     * @return array results
     */
    public function search_content($filters = [], $path = "", $page = "1") {
        $list = [];
        $list['dynload'] = true;
        $list['issearchresult'] = count($filters) > 0;

        $tree = [];
        if ($page == "") {
            $page = "1";
        }
        $response = repository_edutech\edutech::get_learning_objects($page, $filters);
        $learningObjects = $response["results"];
        foreach ($learningObjects as $learningObject) {
            $dateCreated = strtotime($learningObject["learning_object_file"]["created"]);
            $dateModified = strtotime($learningObject["learning_object_file"]["modified"]);
            $tree[] = [
                "thumbnail" => $learningObject["avatar"],
                "thumbnail_width" => 100,
                "thumbnail_height" => 100,
                "title" => $learningObject["learning_object_file"]["file_name"] . ".zip",
                "shorttitle" => $learningObject["general_title"] . " - " . $learningObject["package_type"],
                "url" => $learningObject["learning_object_file"]["file"],
                "source" => $learningObject["learning_object_file"]["file"],
                "author" => $learningObject["author"],
                "license" => $learningObject["license"]["value"],
                "datecreated" => $dateCreated,
                "datemodified" => $dateModified,
                "size" => $learningObject["learning_object_file"]["file_size"] * 1024, // Originally in KB, but we need it in bytes
            ];
        }
        $list["page"] = (int) $page == "" ? 1 : $page;
        $list["pages"] = $response["pages"];
        $list["list"] = $tree;
        $list["filters"] = $this->filters;

        return $list;
    }

    /**
     * Get learning objects with no filters.
     *
     * @param string $path not used
     * @param string $page search page
     * @return array results
     */
    public function get_listing($path = '', $page = '1') {
        return $this->search_content([], $path, $page);
    }

    /**
     * Return the allowed file types.
     *
     * @return array file types
     */
    public function supported_filetypes() {
        return array("application/zip");
    }

    /**
     * Download file from repository.
     *
     * @param string $url
     * @param string $filename
     * @return array
     */
    public function get_file($url, $filename = '') {
        $path = $this->prepare_file($filename);
        $content = file_get_contents($url);
        if (!file_put_contents($path, $content)) {
            throw new moodle_exception('errorwhiledownload', 'repository', '');
        }
        return array('path' => $path, 'url' => $url);
    }

    /**
     * Log out from EduTech.
     *
     * @return void|array log in form
     */
    public function logout() {
        repository_edutech\edutech::logout();
        return $this->print_login();
    }
}
