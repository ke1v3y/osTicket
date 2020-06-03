<?PHP


session_start();
include('../include/ces.reports.php');

$thisReport = new CEReport();
//$thisReport->getThreads('2020-01-01','2020-01-30');
// Get new data
//$thisReport->getThreads(date('Y-m-d', strtotime('-5 days')), date('Y-m-d'));
/*
Not using json anymore
//Get the old data
$inp = file_get_contents('reportingData.json');
$jsonData = json_decode($inp);

//Merge json data and 'live' data
$reportData = array_replace_recursive((array) $jsonData,(array) $thisReport->thread);

//Need this to convert from array to object in a way php can use
//probably a better way to do this
$reportData = json_decode(json_encode($reportData));


$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
$txt = print_r($thisReport->thread,true);
fwrite($myfile, print_r($txt,true));
fclose($myfile);
*/



//Get old data
$fileHandler = new CEFileHandler();
$fileHandler->toObject(file_get_contents('reportingData.seth'));



$reportData = $fileHandler->thread;

//print("<pre>".print_r($thisReport->thread,true)."</pre>");
//echo('delemiter');
//print("<pre>".print_r($reportData,true)."</pre>");

// set user changeable variables here (default values)

//set end date as today
$endDate = date('m/d/y');

//set begin date as 30 days ago
$startDate = date('m/d/y', strtotime('-30 days'));

// Set user posted values ( if applicable)
if( isset($_SESSION["sDate"]) && isset($_SESSION["eDate"]) )
{
	$startDate = $_SESSION["sDate"];
	$endDate = $_SESSION["eDate"];
	
}


$serviceTimes;
$responseTimes;

$serviceTimesBlue;
$responseTimesBlue;

$serviceTimesGreen;
$responseTimesGreen;

$serviceTimesOrange;
$responseTimesOrange;

$serviceTimesRed;
$responseTimesRed;

$dates;

// This could be more effeciant - maybe by going though data and sticking them into an array or object based on date
// Something like this maybe:  team -> date -> times
// not sure if its worth it ATM, when done I will check performance
//for($i = date('U') - (24*60*60*$daysBack) ; $i < date('U'); $i = $i + (24*60*60))
for($i = date('U', strtotime($startDate)) ; $i < date('U',strtotime($endDate)); $i = $i + (24*60*60))
{
	
	
	//reset these arrays
	$serviceTimeAvg = array();
	$responseTimeAvg = array();
	
	$serviceTimeAvgBlue = array();
	$responseTimeAvgBlue = array();
	
	$serviceTimeAvgGreen = array();
	$responseTimeAvgGreen = array();
	
	$serviceTimeAvgOrange = array();
	$responseTimeAvgOrange = array();
	
	$serviceTimeAvgRed = array();
	$responseTimeAvgRed = array();
	
	foreach ($reportData as $thread)
	{
		//reset some variables
		$blueServ = null;
		$blueResp = null;
		$greenServ = null;
		$greenResp = null;
		$orangeServ = null;
		$orangeResp = null;
		$redServ = null;
		$redResp = null;
		
			if ( date('m/d/y',(int) $thread->createDate) == date('m/d/y',$i) )
			{
				
				// Get data per team
				// Need to only add one to array per team, will set a variable here
				// Then add it to array later in case there are mutlipe users on the ticket
				foreach ($thread->users as $user)
				{
					foreach ($user->team as $team)
					{
						switch ($team) {
							case "Blue Team":
								//echo("</br>" . $thread->serviceTime ."</br>");
								$blueServ = $thread->serviceTime;
								$blueResp = $thread->responseTime;
								break;
								
							case "Green Team":
								$greenServ = $thread->serviceTime;
								$greenResp = $thread->responseTime;
								break;
								
							case "Orange Team":
								$orangeServ = $thread->serviceTime;
								$orangeResp = $thread->responseTime;
								break;
								
							case "Red Team":
								$redServ = $thread->serviceTime;
								$redResp = $thread->responseTime;
								break;
						}
					}
				}
				
				
				// we need to throw out results that are 0
				if($thread->serviceTime != 0)
				{
					
					array_push($serviceTimeAvg,$thread->serviceTime);
				}
				if($thread->responseTime != 0)
				{
					array_push($responseTimeAvg,$thread->responseTime);
				}
				
				// Put team data into arrays
				// TO DO - throw out results that are 0 here
				if($blueServ != 0 && $blueServ != null)
					array_push($serviceTimeAvgBlue,$blueServ);
			
				if($blueResp != 0 && $blueResp != null)
					array_push($responseTimeAvgBlue,$blueResp);
				
				if($greenServ != 0 && $greenServ != null)
					array_push($serviceTimeAvgGreen,$greenServ);
				
				if($greenResp != 0 && $greenResp != null)
					array_push($responseTimeAvgGreen,$greenResp);
				
				if($orangeServ != 0 && $orangeServ != null)
					array_push($serviceTimeAvgOrange,$orangeServ);
				
				if($orangeResp != 0 && $orangeResp != null)
					array_push($responseTimeAvgOrange,$orangeResp);
				
				if($redServ != 0 && $redServ != null)
					array_push($serviceTimeAvgRed,$redServ);
				
				if($redResp != 0 && $redResp != null)
					array_push($responseTimeAvgRed,$redResp);
			
			}
			
			
			
			
			
	}

	
	
	
	
	//sorry
	if(count($serviceTimeAvg) != 0 && count($responseTimeAvg) != 0 && count($serviceTimeAvgBlue) != 0 && count($responseTimeAvgBlue) != 0 && count($serviceTimeAvgGreen) != 0 && count($responseTimeAvgGreen) != 0 && count($serviceTimeAvgOrange) != 0 && count($responseTimeAvgOrange) != 0 && count($serviceTimeAvgRed) != 0 && count($responseTimeAvgRed) != 0 )
	{
		//all
		$serviceTimes .= (array_sum($serviceTimeAvg)/count($serviceTimeAvg))/60 . ", ";
		$responseTimes .= (array_sum($responseTimeAvg)/count($responseTimeAvg))/60 . ", ";
		//blue
		$serviceTimesBlue .= (array_sum($serviceTimeAvgBlue)/count($serviceTimeAvgBlue))/60 . ", ";
		$responseTimesBlue .= (array_sum($responseTimeAvgBlue)/count($responseTimeAvgBlue))/60 . ", ";
		//green
		$serviceTimesGreen .= (array_sum($serviceTimeAvgGreen)/count($serviceTimeAvgGreen))/60 . ", ";
		$responseTimesGreen .= (array_sum($responseTimeAvgGreen)/count($responseTimeAvgGreen))/60 . ", ";
		//orange
		$serviceTimesOrange .= (array_sum($serviceTimeAvgOrange)/count($serviceTimeAvgOrange))/60 . ", ";
		$responseTimesOrange .= (array_sum($responseTimeAvgOrange)/count($responseTimeAvgOrange))/60 . ", ";
		//red
		$serviceTimesRed .= (array_sum($serviceTimeAvgRed)/count($serviceTimeAvgRed))/60 . ", ";
		$responseTimesRed .= (array_sum($responseTimeAvgRed)/count($responseTimeAvgRed))/60 . ", ";
		
		
		//dates
		$dates .= "'" . date('m/d/y',$i) . "', ";
	}


	
	
}

?>
<html>

<body>



	<div style="width:95%; margin:0 auto;">
		<table style="width:45%; margin:0 auto;">
			<form action="ces.reports.loader.php" method="post">
			<td>Start Date: <input type="date" name="sDate" value="<? echo(date('Y-m-d',strtotime($startDate))); ?>"></td>
			<td>E-End Date: <input type="date" name="eDate" value="<? echo(date('Y-m-d',strtotime($endDate))); ?>" ></td>
			<td><input type="submit"></td>
			</form>
		<table>
	</div>

	</br>
	
	<div style="width:95%; margin:0 auto;">
		<canvas id="canvas"></canvas>
	</div>

	<div style="width:95%; margin:0 auto;">
		<canvas id="canvas2"></canvas>
	</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.bundle.min.js"></script>
<script type="text/javascript">
		var lineChartData = {
			labels: [<? echo $dates; ?>],
			datasets: [{
				label: 'C&E',
				backgroundColor: 'rgba(150, 150, 150, 0.8)',
				borderColor: 'rgba(150, 150, 150, 0.8)',
				fill: false,
				data: [<? echo $serviceTimes; ?>],
				yAxisID: 'y-axis-1',
			}, {
				label: 'Blue Team',
				backgroundColor: 'rgba(0, 0, 255, 0.8)',
				borderColor: 'rgba(0, 0, 255, 0.8)',
				fill: false,
				data: [<? echo $serviceTimesBlue; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Green Team',
				backgroundColor: 'rgba(0, 255, 0, 0.8)',
				borderColor: 'rgba(0, 255, 0, 0.8)',
				fill: false,
				data: [<? echo $serviceTimesGreen; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Orange Team',
				backgroundColor: 'rgba(255, 165, 0, 0.8)',
				borderColor: 'rgba(255, 165, 0, 0.8)',
				fill: false,
				data: [<? echo $serviceTimesOrange; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Red Team',
				backgroundColor: 'rgba(255, 0, 0, 0.8)',
				borderColor: 'rgba(255, 0, 0, 0.8)',
				fill: false,
				data: [<? echo $serviceTimesRed; ?>],
				yAxisID: 'y-axis-1'
			}]
		};
/*
		window.onload = function() {
			var ctx = document.getElementById('canvas').getContext('2d');
			window.myLine = Chart.Line(ctx, {
				data: lineChartData,
				options: {
					responsive: true,
					hoverMode: 'index',
					stacked: false,
					title: {
						display: true,
						text: 'Service Time'
					},
					scales: {
						yAxes: [{
							type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
							display: true,
							position: 'left',
							id: 'y-axis-1',
						}],
					}
				}
			});
		};
	*/	
		
		
		
			var lineChartData2 = {
			labels: [<? echo $dates; ?>],
			datasets: [{
				label: 'C&E',
				backgroundColor: 'rgba(150, 150, 150, 0.8)',
				borderColor: 'rgba(150, 150, 150, 0.8)',
				fill: false,
				data: [<? echo $responseTimes; ?>],
				yAxisID: 'y-axis-1',
			}, {
				label: 'Blue Team',
				backgroundColor: 'rgba(0, 0, 255, 0.8)',
				borderColor: 'rgba(0, 0, 255, 0.8)',
				fill: false,
				data: [<? echo $responseTimesBlue; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Green Team',
				backgroundColor: 'rgba(0, 255, 0, 0.8)',
				borderColor: 'rgba(0, 255, 0, 0.8)',
				fill: false,
				data: [<? echo $responseTimesGreen; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Orange Team',
				backgroundColor: 'rgba(255, 165, 0, 0.8)',
				borderColor: 'rgba(255, 165, 0, 0.8)',
				fill: false,
				data: [<? echo $responseTimesOrange; ?>],
				yAxisID: 'y-axis-1'
			}, {
				label: 'Red Team',
				backgroundColor: 'rgba(255, 0, 0, 0.8)',
				borderColor: 'rgba(255, 0, 0, 0.8)',
				fill: false,
				data: [<? echo $responseTimesRed; ?>],
				yAxisID: 'y-axis-1'
			}]
		};

		window.onload = function() {
			var ctx2 = document.getElementById('canvas2').getContext('2d');
			window.myLine = Chart.Line(ctx2, {
				data: lineChartData2,
				options: {
					responsive: true,
					hoverMode: 'index',
					stacked: false,
					title: {
						display: true,
						text: 'Response Time'
					},
					scales: {
						yAxes: [{
							type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
							display: true,
							position: 'left',
							id: 'y-axis-1',
						}],
					}
				}
			});
			var ctx = document.getElementById('canvas').getContext('2d');
			window.myLine = Chart.Line(ctx, {
				data: lineChartData,
				options: {
					responsive: true,
					hoverMode: 'index',
					stacked: false,
					title: {
						display: true,
						text: 'Service Time'
					},
					scales: {
						yAxes: [{
							type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
							display: true,
							position: 'left',
							id: 'y-axis-1',
						}],
					}
				}
			});
		};


	</script>
</body>
</html>
