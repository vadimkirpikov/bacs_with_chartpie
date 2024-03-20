<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once 'C:\Users\Vadim\Desktop\moodle\moodle\mod\bacs\lang\en\bacs.php';
global $PAGE, $OUTPUT, $DB, $string, $CFG;
$PAGE->set_url("/mod/bacs/piechart.php");
$PAGE->set_pagelayout('standard');
$PAGE->set_title('SUBMISSION RESULTS CHART');
$PAGE->set_heading('SUBMISSION RESULTS CHART');
require_once("$CFG->libdir/formslib.php");
function make_pie($data, $string, $DB, $OUTPUT){
    $task_id = $data->task;
    for ($i = 0; $i<19; $i++){
        $count = $DB->get_record_sql("SELECT COUNT(*) AS count FROM mdl_bacs_submits_copy WHERE result_id=$i AND task_id=$task_id AND submit_time>=$data->from AND submit_time<=$data->to;");
        if ($count->count > 0)
        {
            $counts[] = $count->count;
            $label_values[] = $string['submit_verdict_'.$i];
        }
    }
    echo "<div style='text-align: center;'>TASK ID $task_id</div>";
    if (count($counts)>0){
        $serie = new core\chart_series("COUNT", $counts);
        $chart = new core\chart_pie();
        $chart->set_labels($label_values);
        $chart->add_series($serie);
        echo $OUTPUT->render($chart);
    }
    else{
        echo "<div style='text-align: center;'>THIS TASK WAS NOT SUBMITTED IN THAT PERIOD</div>";
    }
}
class moodle_filter_form extends moodleform{
    public function definition()
    {
        global $task_ids;
        $mform = $this->_form;
        $mform->addElement("select", "task", "ID", $task_ids);
        $mform->addElement("date_time_selector", "from", "from");
        $mform->addElement("date_time_selector", "to", "to");
        $mform->addElement('submit', 'submitbutton', "Save");
    }
}
$default_data = new stdClass();
$mas = $DB->get_records_sql("SELECT DISTINCT task_id as id FROM mdl_bacs_submits_copy;");
foreach ($mas as $m){
    $task_ids[$m->id] = $m->id;
    $default_data->task = $m->id;
}
$default_data->from = time()-100000000;
$default_data->to = time();
$testform = new moodle_filter_form();
echo $OUTPUT->header();
echo "<div>SELECT ID OF THE TASK AND TIME PERIOD</div>";
$testform->set_data($default_data);
$testform->display();
if ($data = $testform->get_data()){
    make_pie($data, $string, $DB, $OUTPUT);
}
else{
   make_pie($default_data, $string, $DB, $OUTPUT);
}
echo $OUTPUT->footer();

