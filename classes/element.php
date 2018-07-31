<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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

/**
 * This file contains the customcert completion table element.
 *
 * @package     customcertelement_completiontable
 * @copyright   2018 Nathan Nguyen  <nathannguyen@@catalyst-au.net>
 * @copyright   2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_completiontable;


defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {
    /**
    * Default max number of date ranges .
    */
    const DEFAULT_MAX_RANGES = 11;

    /**
     * Element Name 'Completion Table'
     */
    const PLUGIN_NAME = 'customcertelement_completiontable';

    /**
     * Render form element.
     * @param \mod_customcert\edit_element_form $mform
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function render_form_elements($mform) {
        // Content Of The table.
        $mform->addElement('textarea', 'content', get_string('content', self::PLUGIN_NAME), 'wrap="virtual" rows="20" cols="100"');
        $mform->setType('content', PARAM_RAW);
        $mform->addHelpButton('content', 'content', self::PLUGIN_NAME);
        parent::render_form_elements($mform);

        // Date Range Setting.
        $mform->addElement('header', 'dateranges', get_string('dateranges', self::PLUGIN_NAME));
        $mform->addElement('static', 'help', '', get_string('dateranges_help', self::PLUGIN_NAME));

        // Fallback string.
        $mform->addElement('text', 'fallbackstring', get_string('fallbackstring', self::PLUGIN_NAME));
        $mform->addHelpButton('fallbackstring', 'fallbackstring', self::PLUGIN_NAME);
        $mform->setType('fallbackstring', PARAM_NOTAGS);

        // Date Ranges.
        if (!$maxranges = get_config(self::PLUGIN_NAME, 'maxranges')) {
            $maxranges = self::DEFAULT_MAX_RANGES;
        }

        $mform->addElement('hidden', 'numranges', $maxranges);
        $mform->setType('numranges', PARAM_INT);

        for ($i = 0; $i < $maxranges; $i++) {
            $datarange = array();
            // Start Date.
            $datarange[] = $mform->createElement(
                'date_selector',
                $this->build_element_name('startdate', $i),
                get_string('startdate', self::PLUGIN_NAME)
            );
            // End Date.
            $datarange[] = $mform->createElement(
                'date_selector',
                $this->build_element_name('enddate', $i),
                get_string('enddate', self::PLUGIN_NAME)
            );
            // Date String.
            $datarange[] = $mform->createElement(
                'text',
                $this->build_element_name('datestring', $i),
                get_string('datestring', self::PLUGIN_NAME)
            );
            // Enable.
            $datarange[] = $mform->createElement(
                'checkbox',
                $this->build_element_name('enabled', $i),
                get_string('enable')
            );

            $mform->addElement(
                'group',
                $this->build_element_name('group', $i),
                get_string('daterange', self::PLUGIN_NAME, $i + 1),
                $datarange, '', false);

            $mform->disabledIf($this->build_element_name('group', $i), $this->build_element_name('enabled', $i), 'notchecked');
            $mform->setType($this->build_element_name('datestring', $i), PARAM_NOTAGS);
        }
    }
    /**
     * A helper function to build consistent form element name.
     *
     * @param string $name form element name
     * @param string $num form element number
     * @return string
     */
    protected function build_element_name($name, $num) {
        return $name . $num;
    }

    /**
     * Save form data
     * @param \stdClass $data
     * @return string json object of form data
     * @throws \dml_exception
     */
    public function save_unique_data($data) {
        $arrtostore = array(
            'content' => $data->content,
            'fallbackstring' => $data->fallbackstring,
            'numranges' => 0,
            'dateranges' => [],
        );

        // Set Max Width.
        if ($data->width == 0) {
            $maxwidth = $this->get_max_width();
            $data->width = $maxwidth;
        }

        for ($i = 0; $i < $data->numranges; $i++) {
            $startdate = $this->build_element_name('startdate', $i);
            $enddate = $this->build_element_name('enddate', $i);
            $datestring = $this->build_element_name('datestring', $i);
            $enabled = $this->build_element_name('enabled', $i);

            if (!empty($data->$datestring)) {
                $arrtostore['dateranges'][] = [
                    'startdate' => $data->$startdate,
                    'enddate' => $data->$enddate,
                    'datestring' => $data->$datestring,
                    'enabled' => !empty($data->$enabled),
                ];
                $arrtostore['numranges']++;
            }
        }

        return json_encode($arrtostore);
    }

    /**
     * render PDF File
     * @param \pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     * @throws \coding_exception
     */
    public function render($pdf, $preview, $user) {
        $content = $this->get_decoded_data()->content;
        $htmltext = \mod_customcert\element_helper::render_html_content($this, $this->render_table($content, $user, $preview));

        \mod_customcert\element_helper::render_content($pdf, $this, $htmltext);
    }

    /**
     * Completion date based on completion status of an course module
     * @param $cmid course module id
     * @param $user user
     * @param $preview preview mode
     * @return string completion date string
     * @throws \coding_exception
     */
    private function get_completion_date($cmid, $user, $preview) {
        global $DB;
        $cm = null;
        try {
            $cm = $DB->get_record('course_modules', array('id' => $cmid));
        } catch ( \dml_exception $e) {
            $cm = null;
        }

        if (!$cm) {
            return '<div style="color: red"> Invalid ID </div>';
        }

        if ($preview) {
            $completiondate = '-';
        } else {
            $modulecompletion = null;
            try {
                $modulecompletion = $DB->get_record('course_modules_completion', array(
                    'coursemoduleid' => $cmid,
                    'userid' => $user->id));
            } catch ( \dml_exception $e) {
                $modulecompletion = null;
            }

            $completiondate = $modulecompletion ? $this->get_daterange_string($modulecompletion->timemodified) : '-';
        }

        return $completiondate;
    }

    /**
     * Render completion table
     * @param $text : content of the table
     * @param $user : current user
     * @param $preview : preview mode
     * @return string
     * @throws \coding_exception
     */
    private function render_table($text, $user, $preview) {
        global $DB;

        /* Adding '^' and '|' for easier mark up header and section row.
        *  eg, transform ^ header 1 ^ header 2 ^ header 3 ^ into
        *   ^ header 1 ^^ header 2 ^^ header 3 ^
        */
        $patterns = array(
            "/.\^:/m",
            "/.(\|)[^\r\n]/m",
        );
        $replacements = array(
            "^^:",
            "||",
        );
        $text = preg_replace($patterns, $replacements, $text);

        // Completion ID.
        preg_match_all("/\{completion:(.+?)\}/m", $text, $matches);
        // The function preg_match_all return 2 dimension array.
        if ($matches && count($matches) >= 2) {
            foreach ($matches[0] as $key => $origin) {
                $completionid = ($matches[1][$key]);
                $completiondate = $this->get_completion_date($completionid, $user, $preview);
                // Evaluation Completion status.
                if (!$preview) {
                    preg_match_all("/^\|(.+?\{completion:$completionid)\}.\|/m", $text, $completionmatch);
                    // The function preg_match_all return 2 dimension array.
                    if ($completionmatch && count($completionmatch) >= 2) {
                        foreach ($completionmatch[0] as $completionkey => $completionorigin) {
                            if (trim($completiondate) == '-') {
                                $text = str_replace($completionorigin, $completionorigin . ':notcompleted:', $text);
                            } else {
                                $text = str_replace($completionorigin, $completionorigin. ':completed:', $text);
                            }
                        }
                    }

                }
                $text = str_replace($origin, $completiondate, $text);
            }
        }

        // Header row.
        preg_match_all("/^\^(.+?)\^/m", $text, $matches);
        // The function preg_match_all return 2 dimension array.
        if ($matches && count($matches) >= 2) {
            foreach ($matches[0] as $key => $origin) {
                $headerrow = ($matches[1][$key]);
                $text = str_replace($origin, ':header:^'    .$headerrow  .'^', $text);
            }
        }

        // Group row.
        preg_match_all("/^#(.+?)#/m", $text, $matches);
        // The function preg_match_all return 2 dimension array.
        if ($matches && count($matches) >= 2) {
            foreach ($matches[0] as $key => $origin) {
                $grouprow = ($matches[1][$key]);
                $text = str_replace($origin, ':group:#'    .$grouprow  .'#', $text);
            }
        }

        // Section row.
        preg_match_all("/^\|(.+?)\|/m", $text, $matches);
        // The function preg_match_all return 2 dimension array.
        if ($matches && count($matches) >= 2) {
            foreach ($matches[0] as $key => $origin) {
                $sectionlabel = ($matches[1][$key]);
                $courseid = \mod_customcert\element_helper::get_courseid($this->get_id());

                $sections = null;
                try {
                    $sections = $DB->get_records('course_sections', array('name' => trim($sectionlabel), "course" => $courseid));
                } catch (\dml_exception $e) {
                    $sections = null;
                }
                // Section Not Found.
                if ($sections == null) {
                    $text = str_replace($origin, ':invalid:|'   .$sectionlabel  .'|', $text);
                } else {
                    $visibility = 0;
                    foreach ($sections as $section) {
                        $visibility = $section->visible;
                    }
                    // Identify section rows to hide/show or alert if it is invalid (Cannot find the section).
                    if (count($sections) > 0) {

                        if ($visibility == 0) { // Hidden sections.
                            $text = str_replace($origin, ':hidden:|'    .$sectionlabel  .'|', $text);
                        } else { // Visible sections.
                            $text = str_replace($origin, ':visible:|'   .$sectionlabel  .'|', $text);
                        }
                    } else { // Invalid Sections.
                        $text = str_replace($origin, ':invalid:|'   .$sectionlabel  .'|', $text);
                    }
                }
            }
        }

        // Transform to table.
        $patterns = array(

            "/:header:(.+?)$/m", // Header.
            "/:group:(.+?)$/m",  // Group.

            "/:hidden:(.+?)$/m",  // Hidden section.
            "/:invalid:(.+?)$/m", // Invalid Section.

            "/:visible:(.+?):completed:/m", // Visible Section.
            "/:visible:(.+?):notcompleted:/m", // Visible Section.
            "/:visible:(.+?)$/m", // Visible Section.

            "/\^:(\d+):(.+?)\^/m", // Header Content.
            "/#(.+?)#/m", // Group Content
            "/\|(.+?)\|/m", // Section Content.
        );

        if ($preview) {
            $replacements = array(

                '<tr style="color: black; font-style: normal;">$1</tr>', // Header.
                '<tr style="color: black; font-style: normal;">$1</tr>', // Group.

                '<tr style="color: black;font-style: italic;">$1</tr>', // Hidden.
                '<tr style="color: red; font-style: normal;">$1</tr>', // Invalid.

                '<tr style="color: black; font-style: normal;">$1</tr>', // Visible, completed.
                '<tr style="color: black; font-style: normal;">$1</tr>', // Visible, not completed.
                '<tr style="color: black; font-style: normal;">$1</tr>', // Visible, in general.

                '<th style="text-align: center; width: $1%;"><h3>$2</h3></th>', // Header Content
                '<td style="word-wrap: break-word; text-align: center" colspan="3" >$1</td>', // Group Content.
                '<td style="word-wrap: break-word; text-align: left">   $1</td>', // Section Row Content.
            );
        } else {
            $replacements = array(

                '<tr style="color: black; font-style: normal;">$1</tr>', // Header.
                '<tr style="color: black; font-style: normal;">$1</tr>', // Group.

                '', // Hidden.
                '', // Invalid.

                '<tr style="color: black; font-style: normal;">$1</tr>', // Visible, completed.
                '<tr style="color: grey; font-style: normal;">$1</tr>', // Visible, not completed.
                '<tr style="color: black; font-style: normal;">$1</tr>', // Visible, in general.

                '<th style="text-align: center; width: $1%;"><h3>$2</h3></th>', // Header Content
                '<td style="word-wrap: break-word; text-align: center" colspan="3" >$1</td>', // Group Content.
                '<td style="word-wrap: break-word; text-align: left">   $1</td>', // Section Row Content.
            );
        }

        $output = preg_replace($patterns, $replacements, $text);

        return '<table border="1" style="width: 100%">'
            .$output.
            '</table>';
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     * @throws \coding_exception
     */
    public function render_html() {
        global $USER;
        $courseid = \mod_customcert\element_helper::get_courseid($this->get_id());
        $content = $this->get_decoded_data()->content;
        $text = format_text($content, FORMAT_HTML, ['context' => \context_course::instance($courseid)]);

        return \mod_customcert\element_helper::render_html_content($this, $this->render_table($text, $USER, true));
    }

    /**
     * Retrieve saved data
     * @param \mod_customcert\edit_element_form $mform
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data()) && !$mform->isSubmitted()) {
            // Content of the table.
            $element = $mform->getElement('content');
            $element->setValue($this->get_decoded_data()->content);
            // Fall back string.
            $element = $mform->getElement('fallbackstring');
            $element->setValue($this->get_decoded_data()->fallbackstring);
            // Number of date range.
            $element = $mform->getElement('numranges');
            $numranges = $element->getValue();

            if ($numranges < $this->get_decoded_data()->numranges) {
                $element->setValue($this->get_decoded_data()->numranges);
            }

            foreach ($this->get_decoded_data()->dateranges as $key => $range) {
                $groupelement = $mform->getElement($this->build_element_name('group', $key));
                $groupelements = $groupelement->getElements();
                $mform->setDefault($groupelements[0]->getName(), $range->startdate);
                $mform->setDefault($groupelements[1]->getName(), $range->enddate);
                $mform->setDefault($groupelements[2]->getName(), $range->datestring);
                $mform->setDefault($groupelements[3]->getName(), $range->enabled);
            }
        }

        parent::definition_after_data($mform);
    }

    /**
     * Get decoded data stored in DB.
     *
     * @return \stdClass
     */
    protected function get_decoded_data() {
        return json_decode($this->get_data());
    }

    /**
     * Find the corresponding string of specified date
     * @param $date
     * @return string
     * @throws \coding_exception
     */
    protected function get_daterange_string($date) {
        $outputstring = '';
        // Check date string.
        foreach ($this->get_decoded_data()->dateranges as $key => $range) {
            if ($date >= $range->startdate && $date <= $range->enddate) {
                $outputstring = $range->datestring;
                break;
            }
        }
        // Fall back string.
        if ($outputstring == '' && !empty($this->get_decoded_data()->fallbackstring)) {
            $outputstring = $this->get_decoded_data()->fallbackstring;
        }
        // Display date if there is no fall back string.
        if ($outputstring == '') {
            $outputstring = $date ? userdate($date, get_string('strftimedatetimeshort')) : ' ';
        }

        return $outputstring;
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the date element is pointing to as it will
     * have changed in the course restore.
     *
     */
    public function after_restore($restore) {
        global $DB;

        $data = $this->get_decoded_data();
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $data->dateitem)) {
            $data->dateitem = $newitem->newitemid;
            try {
                $DB->set_field('customcert_elements', 'data', $this->save_unique_data($data), array('id' => $this->get_id()));
            } catch (\dml_exception $e) {
                unset($e);
            }
        }
    }

    /**
     * Determine max width of the element.
     * @return int
     * @throws \dml_exception
     */
    private function get_max_width() {
        global $DB;
        $pageid = $this->get_pageid();
        $page = $DB->get_record('customcert_pages', array( 'id' => $pageid));
        // 10mm: easier to reposition the element.
        $maxwidth = $page ? $page->width - $page->leftmargin - $page->rightmargin - 10 : 0;
        return $maxwidth;
    }

    /**
     * Performs validation on the element values.
     *
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);

        // Check if width is less than 0.
        if (isset($data['width']) && ($data['width'] < 0)) {
            $errors['width'] = get_string('error:elementwidthlessthanzero', self::PLUGIN_NAME);
        }
        // Check if width is greater than maximum width.
        $maxwidth = $this->get_max_width();
        if ($maxwidth > 0 && isset($data['width']) && ($data['width'] > $maxwidth)) {
            $errors['width'] = get_string('error:elementwidthgreaterthanmaxwidth', self::PLUGIN_NAME, $maxwidth);
        }

        // Check that datestring is set for enabled dataranges.
        for ($i = 0; $i < $data['numranges']; $i++) {
            $enabled = $this->build_element_name('enabled', $i);
            $datestring = $this->build_element_name('datestring', $i);
            if (!empty($data[$enabled]) && empty($data[$datestring])) {
                $errors[$this->build_element_name('group', $i)] = get_string('error:datestring', self::PLUGIN_NAME);
            }
        }

        // Check that date is correctly set.
        for ($i = 0; $i < $data['numranges']; $i++) {
            $enabled = $this->build_element_name('enabled', $i);
            $startdate = $this->build_element_name('startdate', $i);
            $enddate = $this->build_element_name('enddate', $i);

            if (!empty($data[$enabled]) && $data[$startdate] >= $data[$enddate] ) {
                $errors[$this->build_element_name('group', $i)] = get_string('error:date', self::PLUGIN_NAME);
            }
        }

        return $errors;
    }
}
