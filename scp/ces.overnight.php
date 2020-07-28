<?PHP

set_time_limit(600);

include('../include/ces.reports.php');

// Get our new data
$thisReport = new CEReport();

// Call file handler for later 
$fileHandler = new CEFileHandler();


//Grab last 30 days, except 4 days that will be loaded with graphs
$thisReport->getThreads(date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-1 days')));

//Can uncomment this to grab a timezone to reload data
//$thisReport->getThreads('2020-05-01','2020-08-01');


/* Not using json anymore
//Get the old data
$inp = file_get_contents('reportingData.json');
$tempArray = json_decode($inp, true);
//empty input
$inp = null;
//$tempArray = json_decode(file_get_contents('reportingData.json'), true);
//$tempArray = json_decode(file_get_contents('reportingData.json'), true);
*/

//Merge old and new data (overwriting old with new)
//$result = array_replace_recursive((array) $tempArray,(array) $thisReport->thread);


//Get old data
$oldObj = $fileHandler->toObject(file_get_contents('reportingData.sts'));


$newObj = array_replace_recursive($oldObj->thread,$thisReport->thread);

$stringT = $fileHandler->toString($newObj);



//echo($stringT);



$myfile = fopen("reportingData.sts", "w") or die("Unable to open file!");
//$txt = json_encode($result);
//$txt = ((array) $thisReport->thread);
//$txt = $tempArray;
fwrite($myfile, $stringT);
fclose($myfile);


//echo "<pre>".print_r($newObj,true)."</pre>";