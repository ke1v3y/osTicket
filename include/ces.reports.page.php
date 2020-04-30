<?PHP

include('../include/ces.reports.php');
$thisReport = new CEReport();
$thisReport->getUsers();


//print_r($thisReport); 
//print_r($thisReport->var[0]);
/*
$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
$txt = print_r($thisReport,true);
fwrite($myfile, $txt);
fclose($myfile);
*/


// I think the best way to do this by date is to get todays date
// Then go back $x days
// Then increment forward 24 hours at a time
// if the date mattches add it to the result set_error_handler
// do this until it is todays
//Should be able to build string for wijmo graph this way

// How far back should these calculations go
$daysBack = 10;

for($i = date('U') - (24*60*60*$daysBack) ; $i < date('U'); $i = $i + (24*60*60))
{
	//reset these arrays
	$serviceTime = array();
	$responseTime = array();
	
	foreach ($thisReport->user as $user)
	{
		//print_r($user);
		foreach( $user->threads as $thread)
		{
			if ( date('m/d/y',$thread->createDate) == date('m/d/y',$i) )
			{
				// we need to throw out results that are 0
				if($thread->serviceTime != 0)
				{
					array_push($serviceTime,$thread->serviceTime);
				}
				if($thread->responseTime != 0)
				{
				array_push($responseTime,$thread->responseTime);
				}
			
			}

		}
	}
	
	// Will need to do something to make sure we dont divide by zero if data gets weird
	echo("Response Time Average for " . date('m/d/y',$i) . ": " . array_sum($responseTime)/count($responseTime));
	echo ("</br>");
	echo("Service Time Average for " . date('m/d/y',$i) . ": " . array_sum($serviceTime)/count($serviceTime));
	echo ("</br>");
}

