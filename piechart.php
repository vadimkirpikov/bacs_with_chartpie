<?php
global $PAGE, $OUTPUT, $DB, $CFG;
require_once('config.php');
require_once 'mod/bacs/lib.php';
require_once("$CFG->libdir/formslib.php");

class database_manipulator{
    public static function get_visible_courses(){
        $all_courses = get_courses();
        foreach ($all_courses as $course){
            $context = context_course::instance($course->id);
            if (has_capability("mod/bacs:viewany", $context)){
                $visible_courses[] = $course;
            }
        }
        return $visible_courses;
    }
    public static function get_contests_from_the_course($course_id){
        global $DB;
        return $DB->get_records_sql("SELECT id FROM {bacs} WHERE course=$course_id;");
    }
    public static function get_tasks_from_the_contest($contest_id){
        global $DB;
        return $DB->get_records_sql("SELECT t2.task_id,t2.name FROM {bacs_tasks_to_contests} AS t1 INNER JOIN {bacs_tasks} as t2 ON t1.task_id=t2.task_id WHERE t1.contest_id=$contest_id;");
    }
    public static function get_contests_from_any_courses($courses){
        $result_contests = array();
        foreach ($courses as $course){
            $contests = self::get_contests_from_the_course($course->id);
            if (count($contests)>0){
                foreach ($contests as $contest){
                    $result_contests[] = $contest;
                }
            }
        }
        return $result_contests;
    }
    public static function get_all_visible_tasks($courses){
        $result_contests = self::get_contests_from_any_courses($courses);
        if (count($result_contests)>0){
            foreach ($result_contests as $contest){
                $tasks = self::get_tasks_from_the_contest($contest->id);
                foreach ($tasks as $task){
                    $all_visible_tasks[$task->task_id] = $task->name;
                }
            }
        }
        return $all_visible_tasks;
    }
}
class chart_controller{
    public static function make_pie($test_form, $DB){
        $view_bag = new stdClass();
        $data = $test_form->get_data();
        $all_courses = database_manipulator::get_visible_courses();
        $view_bag->task_id = database_manipulator::get_all_visible_tasks($all_courses)[$data->task];
        $view_bag->course = $data->course;
        if ($data->course==0){
            $contests = database_manipulator::get_contests_from_any_courses($all_courses);
        }
        else{
            $contests = database_manipulator::get_contests_from_the_course($data->course);
        }
        for ($i = 0; $i<19; $i++){
            $count = 0;
            foreach ($contests as $contest){
                $count += $DB->get_field_sql("SELECT COUNT(*) FROM {bacs_submits} WHERE result_id=$i AND contest_id=$contest->id AND task_id=$data->task AND submit_time>=$data->from AND submit_time<=$data->to;");
            }
            if ($count>0)
            {
                $view_bag->counts[] = $count;
                $view_bag->label_values[] = format_verdict($i);
            }
        }

        return $view_bag;
    }
}
class chart_view{
    public static function display_data($test_form, $view_bag)
    {
        global $OUTPUT;
        echo $OUTPUT->header();
        echo "<div>SELECT ID OF THE TASK AND TIME PERIOD</div>";
        $test_form->display();
        if (count($view_bag->counts)>0) {
            echo "<div style='text-align: center;'>TASK $view_bag->task_id</div>";
            $serie = new core\chart_series("COUNT", $view_bag->counts);
            $chart = new core\chart_pie();
            $chart->set_labels($view_bag->label_values);
            $chart->add_series($serie);
            echo $OUTPUT->render($chart);
        }
        else{
            echo "<div style='text-align: center;'>THERE ARE NO RESULTS</div>";
        }
        echo $OUTPUT->footer();
    }
}
class moodle_filter_form extends moodleform{
    public function __construct()
    {

        $all_courses = database_manipulator::get_visible_courses();
        $tasks = database_manipulator::get_all_visible_tasks($all_courses);
        $keys = array_keys($tasks);
        $from = new DateTime("now", core_date::get_server_timezone_object());
        $from->setTime(0, 0, 0);
        $to = new DateTime("now", core_date::get_server_timezone_object());

        $this->default_data = new stdClass();
        $this->default_data->course = 0;
        $this->default_data->from = $from->getTimestamp();
        $this->default_data->to = $to->getTimestamp();
        $this->default_data->task = $keys[0];
        parent::__construct();
    }
    public function get_data(){
        $data = parent::get_data();
        if (!$data) $data = $this->default_data;
        return $data;
    }
    public function definition()
    {
        $courses = database_manipulator::get_visible_courses();
        foreach ($courses as $course){
            $courses_select_name[$course->id] = $course->shortname;
        }
        if (count($courses_select_name)>0)
            $courses_select_name[0] = "ALL";
        $task_ids = database_manipulator::get_all_visible_tasks($courses);
        $mform = $this->_form;
        $mform->addElement("select", "course", "COURSE", $courses_select_name);
        $mform->addElement("select", "task", "TASK", $task_ids);
        $mform->addElement("date_time_selector", "from", "FROM");
        $mform->addElement("date_time_selector", "to", "TO");
        $mform->addElement('submit', 'submitbutton', "SAVE");
        $this->set_data($this->default_data);
    }
}

require_login();
$PAGE->set_url("/mod/bacs/piechart.php");
$PAGE->set_pagelayout('standard');
$PAGE->set_title('SUBMISSION RESULTS CHART');
$PAGE->set_heading('SUBMISSION RESULTS CHART');
$test_form = new moodle_filter_form();
$view_bag = chart_controller::make_pie($test_form, $DB);
chart_view::display_data($test_form, $view_bag);

