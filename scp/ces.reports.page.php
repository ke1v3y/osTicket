<?PHP
session_start();

include('../include/ces.reports.php');

$thisReport = new CEReport();

//$thisReport->getThreads('2020-01-01','2020-01-30');
// Get new data
//$thisReport->getThreads(date('Y-m-d', strtotime('-5 days')), date('Y-m-d'));



//Get old data
$fileHandler = new CEFileHandler();
$fileHandler->toObject(file_get_contents('reportingData.sts'));


// Will need to merge this with new data before going live using array_replace_recursive()
$reportData = $fileHandler->thread;


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

$ceCount = 0;
$blueCount = 0;
$greenCount = 0;
$orangeCount = 0;
$redCount = 0;

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
	
	$serviceTimeAvgcca = array();
	$responseTimeAvgcca = array();
	
	foreach ($reportData as $thread)
	{
		/* Moving these
		//reset some variables
		$blueServ = null;
		$blueResp = null;
		$greenServ = null;
		$greenResp = null;
		$orangeServ = null;
		$orangeResp = null;
		$redServ = null;
		$redResp = null;
		*/
			if ( date('m/d/y',(int) $thread->createDate) == date('m/d/y',$i) )
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
				$ccaServ = null;
				$ccaResp = null;
				
				$blueFlg = 0;
				$greenFlg = 0;
				$orangeFlg = 0;
				$redFlg = 0;
				
				$ceCount++;
				
				
				// Get data per team
				// Need to only add one to array per team, will set a variable here
				// Then add it to array later in case there are mutlipe users on the ticket

				switch ($thread->team) {
					case "Blue Team":
						$blueServ = $thread->serviceTime;
						$blueResp = $thread->responseTime;
						$blueCount++;
						break;
						
					case "Green Team":
						$greenServ = $thread->serviceTime;
						$greenResp = $thread->responseTime;
						$greenCount++;
						break;
						
					case "Orange Team":
						$orangeServ = $thread->serviceTime;
						$orangeResp = $thread->responseTime;
						$orangeCount++;;
						break;
						
					case "Red Team":
						$redServ = $thread->serviceTime;
						$redResp = $thread->responseTime;
						$redCount++;
						break;
						
					case "CCA Team":
						$ccaServ = $thread->serviceTime;
						$ccaResp = $thread->responseTime;
						$ccaCount++;
						break;
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
				
				if($ccaServ != 0 && $ccaServ != null)
					array_push($serviceTimeAvgcca,$ccaServ);
				
				if($ccaResp != 0 && $ccaResp != null)
					array_push($responseTimeAvgcca,$ccaResp);
			
			}
	}

	
	
	
	

		//all
		if(count($serviceTimeAvg) != 0 && count($responseTimeAvg) != 0 )
		{
			$serviceTimes .= round((array_sum($serviceTimeAvg)/count($serviceTimeAvg))/60) . ", ";
			$responseTimes .= round((array_sum($responseTimeAvg)/count($responseTimeAvg))/60) . ", ";
		}
		else
		{
			$serviceTimes .= "0, ";
			$responseTimes .= "0, ";
		}
		
		//blue
		if(count($serviceTimeAvgBlue) != 0 && count($responseTimeAvgBlue) != 0 )
		{
			$serviceTimesBlue .= round((array_sum($serviceTimeAvgBlue)/count($serviceTimeAvgBlue))/60) . ", ";
			$responseTimesBlue .= round((array_sum($responseTimeAvgBlue)/count($responseTimeAvgBlue))/60) . ", ";
		}
		else
		{
			$serviceTimesBlue .= "0, ";
			$responseTimesBlue .= "0, ";
		}
		
		//green
		if(count($serviceTimeAvgGreen) != 0 && count($responseTimeAvgGreen) != 0 )
		{
			$serviceTimesGreen .= round((array_sum($serviceTimeAvgGreen)/count($serviceTimeAvgGreen))/60) . ", ";
			$responseTimesGreen .= round((array_sum($responseTimeAvgGreen)/count($responseTimeAvgGreen))/60) . ", ";
		}
		else
		{
			$serviceTimesGreen .= "0, ";
			$responseTimesGreen .= "0, ";
		}
		
		//orange
		if(count($serviceTimeAvgOrange) != 0 && count($responseTimeAvgOrange) != 0 )
		{
			$serviceTimesOrange .= round((array_sum($serviceTimeAvgOrange)/count($serviceTimeAvgOrange))/60) . ", ";
			$responseTimesOrange .= round((array_sum($responseTimeAvgOrange)/count($responseTimeAvgOrange))/60) . ", ";
		}
		else
		{
			$serviceTimesOrange .= "0, ";
			$responseTimesOrange .= "0, ";
		}
		
		//red
		if(count($serviceTimeAvgRed) != 0 && count($responseTimeAvgRed) != 0 )
		{
			$serviceTimesRed .= round((array_sum($serviceTimeAvgRed)/count($serviceTimeAvgRed))/60) . ", ";
			$responseTimesRed .= round((array_sum($responseTimeAvgRed)/count($responseTimeAvgRed))/60) . ", ";
		}
		else
		{
			$serviceTimesRed .= "0, ";
			$responseTimesRed .= "0, ";
		}
		
		//cca
		if(count($serviceTimeAvgcca) != 0 )
		{
			$serviceTimescca .= round((array_sum($serviceTimeAvgcca)/count($serviceTimeAvgcca))/60) . ", ";
		}
		else
		{
			$serviceTimescca .= "0, ";
		}
		
		if(count($responseTimeAvgcca) != 0)
		{
			$responseTimescca .= round((array_sum($responseTimeAvgcca)/count($responseTimeAvgcca))/60) . ", ";
		}
		else
		{
			$responseTimescca .= "0, ";
		}
		
		//dates
		$dates .= "'" . date('m/d/y',$i) . "', ";
	
	
}


// Calcaultions for table at the bottom
$serviceTimeAvgArray = explode(", ", $serviceTimes);
$serviceTimeAvgTotal = array_sum($serviceTimeAvgArray)/count($serviceTimeAvgArray);

$responseTimeAvgArray = explode(", ", $responseTimes);
$responseTimeAvgTotal = array_sum($responseTimeAvgArray)/count($responseTimeAvgArray);

//Blue
$serviceTimeAvgArrayBlue = explode(", ", $serviceTimesBlue);
$serviceTimeAvgTotalBlue = array_sum($serviceTimeAvgArrayBlue)/count($serviceTimeAvgArrayBlue);

$responseTimeAvgArrayBlue = explode(", ", $responseTimesBlue);
$responseTimeAvgTotalBlue = array_sum($responseTimeAvgArrayBlue)/count($responseTimeAvgArrayBlue);

//Green
$serviceTimeAvgArrayGreen = explode(", ", $serviceTimesGreen);
$serviceTimeAvgTotalGreen = array_sum($serviceTimeAvgArrayGreen)/count($serviceTimeAvgArrayGreen);

$responseTimeAvgArrayGreen = explode(", ", $responseTimesGreen);
$responseTimeAvgTotalGreen = array_sum($responseTimeAvgArrayGreen)/count($responseTimeAvgArrayGreen);

//Orange
$serviceTimeAvgArrayOrange = explode(", ", $serviceTimesOrange);
$serviceTimeAvgTotalOrange = array_sum($serviceTimeAvgArrayOrange)/count($serviceTimeAvgArrayOrange);

$responseTimeAvgArrayOrange = explode(", ", $responseTimesOrange);
$responseTimeAvgTotalOrange = array_sum($responseTimeAvgArrayOrange)/count($responseTimeAvgArrayOrange);

//Red
$serviceTimeAvgArrayRed = explode(", ", $serviceTimesRed);
$serviceTimeAvgTotalRed = array_sum($serviceTimeAvgArrayRed)/count($serviceTimeAvgArrayRed);

$responseTimeAvgArrayRed = explode(", ", $responseTimesRed);
$responseTimeAvgTotalRed = array_sum($responseTimeAvgArrayRed)/count($responseTimeAvgArrayRed);

//cca
$serviceTimeAvgArraycca = explode(", ", $serviceTimescca);
$serviceTimeAvgTotalcca = array_sum($serviceTimeAvgArrayRed)/count($serviceTimeAvgArrayRed);

$responseTimeAvgArraycca = explode(", ", $responseTimescca);
$responseTimeAvgTotalcca = array_sum($responseTimeAvgArraycca)/count($responseTimeAvgArraycca);


?>
<html>

<body>



	<div style="width:95%; margin:0 auto;">
		<table style="width:65%; margin:0 auto;">
			<form action="ces.reports.loader.php" method="post">
			<td>Start Date: <input type="date" name="sDate" value="<? echo(date('Y-m-d',strtotime($startDate))); ?>"></td>
			<td>End Date: <input type="date" name="eDate" value="<? echo(date('Y-m-d',strtotime($endDate))); ?>" ></td>
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
			}, {
				label: 'CCA Team',
				backgroundColor: 'rgba(128, 0, 128, 0.8)',
				borderColor: 'rgba(128, 0, 128, 0.8)',
				fill: false,
				data: [<? echo $serviceTimescca; ?>],
				yAxisID: 'y-axis-1'
			}]
		};
	
		
		
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
			}, {
				label: 'CCA Team',
				backgroundColor: 'rgba(128, 0, 128, 0.8)',
				borderColor: 'rgba(128, 0, 128, 0.8)',
				fill: false,
				data: [<? echo $responseTimescca; ?>],
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
							scaleLabel: {
								display: true,
								labelString: 'Minutes',
							},
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
							scaleLabel: {
								display: true,
								labelString: 'Minutes',
							},
						}],
					}
				}
			});
		};


	</script>
	</br>
	<table style="width:95%; margin:0 auto; border: 1px solid grey; text-align: center; ">
		<tr>
			<th>Team</th>
			<th>Opened Tickets</th>
			<th>Service Time</th>
			<th>Response Time</th>
		</tr>
		<tr>
			<td>C&E Overall</td>
			<td><? echo($ceCount); ?></td>
			<td><? echo round($serviceTimeAvgTotal); ?> Minutes</td>
			<td><? echo round($responseTimeAvgTotal); ?> Minutes</td>
		</tr>
		<tr>
			<td>Blue Team</td>
			<td><? echo($blueCount); ?></td>
			<td><? echo round($serviceTimeAvgTotalBlue); ?> Minutes</td>
			<td><? echo round($responseTimeAvgTotalBlue); ?> Minutes</td>
		</tr>
		<tr>
			<td>Green Team</td>
			<td><? echo($greenCount); ?></td>
			<td><? echo round($serviceTimeAvgTotalGreen); ?> Minutes</td>
			<td><? echo round($responseTimeAvgTotalGreen); ?> Minutes</td>
		</tr>
		<tr>
			<td>Orange Team</td>
			<td><? echo($orangeCount); ?></td>
			<td><? echo round($serviceTimeAvgTotalOrange); ?> Minutes</td>
			<td><? echo round($responseTimeAvgTotalOrange); ?> Minutes</td>
		</tr>
		<tr>
			<td>Red Team</td>
			<td><? echo($redCount); ?></td>
			<td><? echo round($serviceTimeAvgTotalRed); ?> Minutes</td>
			<td><? echo round($responseTimeAvgTotalRed); ?> Minutes</td>
		</tr>
	
	
	
	</table>
</body>
</html>
