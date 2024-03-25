<?php
global $PAGE, $OUTPUT, $DB, $string, $CFG;
require_once('config.php');
require_once 'mod\bacs\lang\en\bacs.php';
require_once("$CFG->libdir/formslib.php");

$PAGE->set_url("/mod/bacs/piechart.php");
$PAGE->set_pagelayout('standard');
$PAGE->set_title('SUBMISSION RESULTS CHART');
$PAGE->set_heading('SUBMISSION RESULTS CHART');

class chart_controller{
    private static function default_data($test_form, $DB)
    {
        $default_data = new stdClass();
        $mas = $DB->get_records_sql("SELECT DISTINCT task_id as id FROM mdl_bacs_submits_copy;");
        foreach ($mas as $m){
            $default_data->task = $m->id;
        }
        $default_data->from = time()-100000000;
        $default_data->to = time();
        $test_form->set_data($default_data);
        return $default_data;
    }
    public static function make_pie($test_form, $string, $DB){
        $view_bag = new stdClass();
        if ($req_data = $test_form->get_data()){
            $data = $req_data;
        }
        else{
            $data = self::default_data($test_form, $DB);
        }
        $view_bag->task_id = $data->task;
        for ($i = 0; $i<19; $i++){
            $count = $DB->get_field_sql("SELECT COUNT(*) AS count FROM mdl_bacs_submits_copy WHERE result_id=$i AND task_id=$data->task AND submit_time>=$data->from AND submit_time<=$data->to;");
            if ($count>0)
            {
                $view_bag->counts[] = $count;
                $view_bag->label_values[] = $string['submit_verdict_'.$i];
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
            echo "<div style='text-align: center;'>TASK ID $view_bag->task_id</div>";
            $serie = new core\chart_series("COUNT", $view_bag->counts);
            $chart = new core\chart_pie();
            $chart->set_labels($view_bag->label_values);
            $chart->add_series($serie);
            echo $OUTPUT->render($chart);
        }
        else{
            echo "<div style='text-align: center;'>THIS TASK WAS NOT SUBMITTED IN THAT PERIOD</div>";
        }
        echo $OUTPUT->footer();
    }
}
class moodle_filter_form extends moodleform{

    public function definition()
    {
        global $DB;
        $mas = $DB->get_records_sql("SELECT DISTINCT task_id as id FROM mdl_bacs_submits_copy;");
        foreach ($mas as $m){
            $task_ids[$m->id] = $m->id;
        }
        $mform = $this->_form;
        $mform->addElement("select", "task", "ID", $task_ids);
        $mform->addElement("date_time_selector", "from", "from");
        $mform->addElement("date_time_selector", "to", "to");
        $mform->addElement('submit', 'submitbutton', "Save");
    }
}


$test_form = new moodle_filter_form();
$view_bag = chart_controller::make_pie($test_form, $string, $DB);
chart_view::display_data($test_form, $view_bag);



