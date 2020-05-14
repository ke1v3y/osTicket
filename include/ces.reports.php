<?PHP
// Man who makes mistake in elevator, is wrong on many levels... much like this class
// This class returns an object with all the data needed for statistics on the ticketing system
 class CEReport 
 {
	 
	
	 
	public $user = array();
	 
	 // Function for sql queries, just needs passed sql statment
	function cesQuery(string $sql)
	{
		 //Sql query to get some thread details
		$servername = "localhost";
		$username = "";
		$password = "";
		$dbname = "helpdeskces_ostick";

		// Create connection
		$conn = new mysqli($servername, $username, $password, $dbname);
		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		$result = $conn->query($sql);

		$conn->close();
		
		//$row = null;
		
		return $result;
	}
	 
	 // This is the "main function"
	 // I should probably rename this main and get the users in another function
	function getUsers()
	{
		$sql = "SELECT DISTINCT poster, staff_id FROM ost5h_thread_entry WHERE staff_id != 0";
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			$x=0;
			// output data of each row
			while($row = $result->fetch_assoc()) 
			{
				//TO DO:
				// Need to make sure users are internal only
				$this->user[$x]= new CEUser;
				$this->user[$x]->username=$row['poster'];
				//Populate team
				$this->user[$x]->team= $this->getTeam($row['staff_id']);
				//Need to get threads per user now
				$this->user[$x]->threads = $this->getThreads($row['staff_id']);
				$x++;
			}
		}
		return $this->user;
	}
	
	function getThreads($staffID)
	{

		
		// May be able to get create date with a join here - increase program effeciancy -
		//$sql = "SELECT DISTINCT thread_id FROM ost5h_thread_entry WHERE poster ='" . $nameOfUser . "' AND ost5h_thread_entry.created >= DATE_ADD(CURDATE(), INTERVAL -5 DAY)"; 
		//$sql = "SELECT DISTINCT thread_id FROM ost5h_thread_entry WHERE staff_id ='" . $nameOfUser . "' AND ost5h_thread_entry.created >= DATE_ADD(CURDATE(), INTERVAL -30 DAY)"; 
		//$sql = "SELECT DISTINCT thread_id FROM ost5h_thread_entry WHERE staff_id ='" . $staff_id . "' AND ost5h_thread_entry.created >= DATE_ADD(CURDATE(), INTERVAL -30 DAY)"; 
		//going to try pulling from ost5h_thread_event instead so we can filter by person who closed the ticket
		$sql = "SELECT thread_id FROM ost5h_thread_event WHERE staff_id ='" . $staffID . "' AND event_id = '2' AND timestamp >= DATE_ADD(CURDATE(), INTERVAL -5 DAY)";
		$result = $this->cesQuery($sql);
		
		//Dump all of this into a normal type array
		$threads = array();
		if ($result->num_rows > 0) 
		{
			
			$x=0;
			// output data of each row
			while($row = $result->fetch_assoc()) 
			{	
				$threads[$x] = new ThreadDetails;
				$threads[$x]->id = $row['thread_id'];
				$threads[$x]->debugID = $this->fillDebugID($row['thread_id']);
				// need to call response/ service time functions here
				//calling function as a test for now
				$threads[$x]->responseTime = $this->getResponseTime($row['thread_id'], $staffID);
				$threads[$x]->serviceTime = $this->getServiceTime($row['thread_id']);
				$threads[$x]->createDate = $this->getCreateDate($row['thread_id']);
				
				$x++;
			}
		}
		return $threads;
	}
	
	function getResponseTime($threadID, $staffID)
	{


		
		// Get all the time stamps for thread
		// I  dont even think we actually need ost5h_thread, being created shows up as an entry
		//$sql = "SELECT created, poster, staff_id FROM ost5h_thread_entry WHERE thread_id = " . $threadID . " UNION SELECT created, created, created FROM ost5h_thread WHERE id = " . $threadID . " ORDER BY created ASC";
		$sql = "SELECT created, poster, staff_id, user_id FROM ost5h_thread_entry WHERE thread_id = " . $threadID . " ORDER BY created ASC";
		
		$row = null;
		$result = null;
		
		$result = $this->cesQuery($sql);

		// These will come in handy later, not sure if I need to declare them now but here they are
		$prevName;
		$prevDate;
		
		
		$times = array();
		
		// Loop through them
		if ($result->num_rows > 0) 
		{
			
			$x=0;
			while($row = $result->fetch_assoc()) 
			{
				
				// Need to set who cerated ticket
				if ($x == 0)
				{
					$ogPoster = $row['poster'];
					//$ogPosterEmail = $this->getPosterEmail($row['poster']);
					// Switching to get email by userid for performance reason
					$ogPosterEmail = $this->getUserEmail($row['user_id']);
				}
				
				
				
				// need to make sure prevName/prevDate are not null
				// If they are null this is presumably the first loop and we can skip stuff
				
				//not being used anymore
				/*//Get poster email once now instead of twice later
				if( $row['staff_id'] != 0)
				{
				$posterEmail = $this->getstaffEmail($row['staff_id']);
				}
				else
				{
					$posterEmail = 0;
				}*/
				/*echo( "row staff id: " . $row['staff_id'] . "</br>");
				echo( "staff id: " . $staffID . "</br>");
				echo( "Poster: " . $row['poster'] . "</br>");
				echo( "OGPoster: " . $ogPoster . "</br>");*/
				
				//scanario one
				// This is not the first run, Last message is external, previous message did not come from user AND POST WAS MADE BY USER
				$s1 = (isset($prevName) && ($row['poster'] != $prevName) && $prevStaffID == '0' && $staffID == $row['staff_id']);
				
				//scenario two
				// This is not the first run, last messsage is the ticket creater who is internal AND POST WAS MADE BY USER
				$s2 = ( isset($prevName) && $ogPoster == $row['poster'] && $this->checkEmail($ogPosterEmail) && $staffID == $row['staff_id'] );
				
				//echo( "s1: " . $s1 . "</br>");
				//echo( "s2: " . $s2 . "</br>");
				
				// Also if poster is a C&E address make sure to count it as a response
				if ($s1 || $s2)
				{
					// make string into a timestamp we can use
					//$timestamp = strtotime($row['created']);
					$timestamp = $row['created'];
					
					// Call function to compare date times (with filtering of work hours)
					// need to bump theese
					//$times[$x] = $this->timeStampDIfference($timestamp,$prevDate);
					array_push($times,$this->timeStampDIfference($timestamp,$prevDate));
					//echo("timestamp: " . $timestamp . "\r\n" . "prevDate: " . $prevDate . "\r\n");
					//moved x++ from here
					

				}
				
			// end of this loop, set values for next go
			$prevName = $row['poster'];
			//$prevDate = strtotime($row['created']);
			$prevDate = $row['created'];
			// previous staff id, if 0 user is external - might be able to ditch email checks
			$prevStaffID = $row['staff_id'];
			
			$x++;
			}
			

		
		}
		//print_r($times);
			// take average
			// need to handle 0's
			if(count($times) != 0)
			{
				if(array_sum($times)/count($times) < 0)
				{
					return 0;
				}
				else
				{
				return (array_sum($times)/count($times));
				}
			}
			else
			{
				return 0;
			}
	}
	function getServiceTime($threadID)
	{

		//get thread createion date and thread end date
		//$sql = 'SELECT ost5h_thread.id, ost5h_thread.created, ost5h_thread_event.timestamp FROM ost5h_thread, ost5h_thread_event WHERE ost5h_thread_event.thread_id = ost5h_thread.id AND ost5h_thread_event.data = "{\"status\":[3,\"Closed\"]}" AND ost5h_thread.id = '.$threadID;
		$sql = 'SELECT ost5h_thread.id, ost5h_thread.created, ost5h_thread_event.timestamp FROM ost5h_thread, ost5h_thread_event WHERE ost5h_thread_event.thread_id = ost5h_thread.id AND ost5h_thread_event.event_id = "2" AND ost5h_thread.id = '.$threadID;
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				//Get differnece
				$created = $row['created'];
				$closed =  $row['timestamp'];
			}
		}
		
		// Debug
		//echo("Created: " . $created . "---- Closed: " . $closed . "</br>");
		
		
		// Calculate Difference
		$serviceTime = $this->timeStampDIfference($created,$closed);
		
		//Debug
		//echo ("ServiceTime 1: " . $serviceTime . " </br> ");
		
		
		// Subtract Time the ticket was closed
		$serviceTime = $serviceTime - $this->getClosedTime($threadID);
		
		//Debug
		//echo ("ServiceTime 2: " . $serviceTime . " </br> ");
	 
		if($serviceTime < 0)
		{
			return 0;
		}
		else
		{
			return $serviceTime;
		}
		
	}
	
	// Needs time as a string
	// Gets differnece without off hours
	function timeStampDIfference($stampOne, $stampTwo)
	{
		//echo("</br> </br> </br> Start timeStampDIfference Function </br> </br> </br>");
		/*
		echo($stampOne);
		echo("</br>");
		echo($stampTwo);
		echo("</br>");
			*/
	
		$stampOne = strtotime($stampOne);
		$stampTwo = strtotime($stampTwo);
		
		/*
		echo($stampOne);
		echo("</br>");
		echo($stampTwo);
		echo("</br>");
		*/
		
		// Need to set time zone to eastern here,
		// Time zone is used for some of the off hour calculations,
		// This needs to be done after strtotime because OSTicket seems
		// to be handling time zone outside of normal php paramater
		// also need to set timezone back to utc at end of this function
		//date_default_timezone_set("US/Eastern");
		//date_default_timezone_set("UTC");
		//Need to make sure stampTwo is smaller... its just easier that way
		if($stampOne < $stampTwo)
		{
			$switchAroo = $stampOne;
			$stampOne = $stampTwo;
			$stampTwo = $switchAroo;
		}
		
	
		// Get differnece between the two dates
		// keeping it in seconds for now
		$diff = $stampOne - $stampTwo;
		
		$diff = $diff - $this->getOffHours($stampOne,$stampTwo);
		
		// set time zone back to utc
		//date_default_timezone_set("UTC");
		
		//echo("</br> </br> </br> End timeStampDIfference Function </br> </br> </br>");
		
		return $diff;
	}
	
	//M
	function getOffHours($stampOne, $stampTwo)
	{
		//echo("</br> </br> </br> Start GetOffHours Function </br> </br> </br>");
		
		//Need to make sure stampTwo is smaller... its just easier that way
		if($stampOne < $stampTwo)
		{
			$switchAroo = $stampOne;
			$stampOne = $stampTwo;
			$stampTwo = $switchAroo;
		}
		
		// Get holidays for use in for loop
		$CEHolidays = new CEHolidays();
		$CEHolidays->loadHolidays();
		
		
		
		// Go hour by hour, see if its an off hour, if it is - remove it from $diff
		//3600 seconds = one hour
		//60 seconds = one minute (duh)
		$interval = 60;
		for( $x = $stampTwo; $x < $stampOne; $x = $x + $interval)
		{
			
			// is X a weekend? - 6 or 7 is saturday or sunday respectivly
			if(date('N', $x) >= 6)
			{
				$isWeekend = true;
			} 
			else 
			{
				$isWeekend = false;
			}
			
			
			//echo("stampOne: " . $stampOne . "------- x: " . $x . "------- diff: " . $diff . "--------- G of x: " . date('G',$x) . "</br>");
			// check and see if its an hour after hours
			// maybe at some point this should be poitned to DB for working hours of company
			if (date('G',$x) < 8 || date('G',$x) > 16)
			{
				$afterHours = true;
			}
			else
			{
				$afterHours = false;
			}
			//date_default_timezone_set("US/Eastern");
			//echo($isWeekend);
			//echo("</br>");
			//($afterHours);
			//echo("</br>");
			//echo($diff);
			
			// if its after hours or a weekend hour, remove hour from $$diff
			if( $afterHours || $isWeekend || $this->checkHoliday($x,$CEHolidays->holidays) )
			{
				
				$offTime = $offTime + $interval;
			}
			
			/*
			// if date minutes is 00 (example 1:00) switch to hours
			if( date('i',$x) == '00')
			{
				$interval = 3600;
			}
			*/	
	
		}
		
			//echo("</br> </br> </br> END GetOffHours Function </br> </br> </br>");
		
		//return ammount of time elapsed that was off hours in seconds
		return $offTime;
	}
	
	
	// Function to get ticket creation date
	function getCreateDate($threadID)
	{
		$sql = 'SELECT created FROM ost5h_thread WHERE ost5h_thread.id = '.$threadID;
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				$created = $row['created'];
			}
		}
		
		return strtotime($created);
	}
	
	// This function will get the email of a given user in osticket
	function getUserEmail($userID)
	{
		$sql = "SELECT address FROM ost5h_user_email WHERE id = " . $userID;
		
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				$email = $row['address'];
			}
		}
		
		return $email;
	}
	
	// This function will get the email of a given staff in osticket
	function getstaffEmail($staffID)
	{
		$sql = "SELECT email FROM ost5h_staff WHERE staff_id = " . $staffID;
		
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				$email = $row['email'];
			}
		}
		
		return $email;
	}
	
	
	
	// This is the same as getUserEmail but with a different input parameter
	function getPosterEmail($poster)
	{
		//need to go from ost5h_thread_entry.poster -> ost5h_user_email.address
		$sql = "SELECT DISTINCT ost5h_thread_entry.user_id, ost5h_thread_entry.poster, ost5h_user_email.address FROM ost5h_user_email, ost5h_thread_entry WHERE ost5h_user_email.user_id = ost5h_thread_entry.user_id AND ost5h_thread_entry.poster= '" . $poster . "'";
		
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				$email = $row['address'];
			}
		}
		
		return $email;
	}
	 
	 // check email to see if it has a CE address
	function checkEmail($email)
	{
		$ceatDomains = array("ceat.io", "ceadvancedtech.com", "cesales.com");

		foreach ($ceatDomains as $domainName) 
		{
			if (stripos($email, $domainName) != false) 
			{		
				return true;
			}
		}
		
		return false;
	}
	
	// This function need to return the ammount of time a ticket has been closed for
	// Used for service time
	function getClosedTime($threadID)
	{
		//debug
		//echo("</br> </br> </br> Start Get Closed Time Function </br> </br> </br>");
		
		
		$sql = "SELECT username, data, timestamp, event_id FROM ost5h_thread_event where thread_id = " . $threadID . " ORDER BY timestamp ASC";
		$result = $this->cesQuery($sql);
		
		//event id 2  = closed
		//event id 3  = re-opened
		// Any event after a close seems to re-open ticket
		
		$closedTime = 0;
		
		// Loop through them
		if ($result->num_rows > 0) 
		{
			while($row = $result->fetch_assoc()) 
			{
				
				//echo("Username: " . $row['username'] . "----- Data: " . $row['data'] . "----- event_id: " . $row['event_id'] . "----- Timestamp: " . $row['timestamp'] . "</br>");
				
				// If its set that means last event was a closure.
				if(isset($lastClosed))
				{
					$closedTime = $closedTime + (strtotime($row['timestamp']) - strtotime($lastClosed));
					//echo($closedTime . "=" . $closedTime . "+ ABS(" . strtotime($row['timestamp']) . " - " . strtotime($lastClosed) . ")</br>");
					// smh I think we need to add back in off hours - I think they are getting removed twice so we need to NOT count them here
					// just lett timestampDiff do its thing
					// maybe move getting off hours to a function
					// and here it is
					//echo($this->getOffHours(strtotime($row['timestamp']),strtotime($lastClosed)) . "</br>");
					$closedTime = $closedTime - $this->getOffHours(strtotime($row['timestamp']),strtotime($lastClosed));
					
				}
				
				
				
				// check if ticket has been closed
				if ($row['event_id'] == "2")
				{
					// set last closed to timestamp of closd event
					$lastClosed = $row['timestamp'];
					
				}
				else
				{
					$lastClosed = null;
				}
				//echo("Last Closed: " . $lastClosed . "</br>");
			}
		}
		
		//echo("</br> </br> </br> End Get Closed Time Function </br> </br> </br>");
		
		
		return $closedTime;
	}
	
	// This function needs to take poster from the get users function
	// And be able to return the team the user belongs to
	function getTeam($staffID)
	{	
	$sql = "SELECT ost5h_team.name FROM ost5h_team_member, ost5h_team WHERE ost5h_team_member.staff_id = " . $staffID . " AND ost5h_team_member.team_id = ost5h_team.team_id";
		
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				$team = $row['name'];
			}
		}
		
		return $team;
	}
	
	function checkHoliday($date, $holidays)
	{

		
		// determine if date passed through to function is one of these predetermined holidays
		foreach ($holidays as $holiday) 
		{
			if ($holiday == date('Y-m-d', $date))
			{
				return true;
			}
		}
			
		// if we dont get a return true, return false
		return false;

	}
	
	// This populates the ID we can punch into a browser to pull up the ticket - probably wont need this on in production
	function fillDebugID($threadID)
	{
		$sql = "SELECT object_id FROM ost5h_thread WHERE id = '".$threadID."'";
		
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				return $row['object_id'];
			}
		}
		
		
	}
	
 }
 

 
 class CEUser
 {
	 public $username;
	 public $team;
	 public $threads = array();
 }
 
 class ThreadDetails
 {
	 public $id;
	 public $debugID;
	 public $createDate;
	 public $serviceTime = '0';
	 public $responseTime = '0';
	 
 }
 
 // Moving holidays to their own class
 // Calculating holidays each time causes the program to run too slow
 // This way we can do the calculations only once
 class CEHolidays
 {
	//Holidays
	public $MDay; //Memorial DAY
	public $LD;   //Labor Day
	public $TH;   //Thanksgiving
	public $Bf;   //Black Friday
	public $Ch;   //Christmas
	public $Che;  //Christmas Eve
	public $Ny;   //New years
	public $Nye;  //New Years Eve
	public $Fj;   //Fourth of July
	public $holidays = array(); //An array of all the holidays
	
	// Load all the CE holidays
	// returns an array of all the holidays
	// Using return is optional
	function loadHolidays()
	{
		//copied from https://stackoverflow.com/questions/14907561/how-to-get-date-for-holidays-using-php
		$curYir = date("Y");//current year

		//$MLK = date('Y-m-d', strtotime("january $curYir third monday")); //marthin luthor king day
		//$PD = date('Y-m-d', strtotime("february $curYir third monday")); //presidents day
		$Est =  date('Y-m-d', easter_date($curYir)); // easter 
		$MDay = date('Y-m-d', strtotime("may $curYir first monday")); // memorial day
		//("may $curYir last monday") will give you the last monday of may 1967
		//much better to determine it by a loop
			  $eMDay = explode("-",$MDay);
			  $year = $eMDay[0];
			  $month = $eMDay[1];
			  $day = $eMDay[2];

			  while($day <= 31){
				  $day = $day + 7;
			  }
			  if($day > 31)
			  $day = $day - 7;

			  $MDay = $year.'-'.$month.'-'.$day;
		$LD = date('Y-m-d', strtotime("september $curYir first monday"));  //labor day
		//$CD = date('Y-m-d', strtotime("october $curYir third monday")); //columbus day
		$TH = date('Y-m-d', strtotime("november $curYir first thursday")); // thanks giving 
		//("november $curYir last thursday") will give you the last thursday of november 1967
		//much better to determine it by a loop
			  $eTH = explode("-",$TH);
			  $year = $eTH[0];
			  $month = $eTH[1];
			  $day = $eTH[2];

			  while($day <= 30){
				  $day = $day + 7;
			  }
			  if($day > 30)
			  //watch out for the days in the month November only have 30
			  $day = $day - 7;

			  $TH = $year.'-'.$month.'-'.$day;
	
		// end stack overflow copy
		
		// add some more holidays
		// Christmas
		$Ch = date('Y-m-d', strtotime("December 25"));
		
		// Christmas Eve
		$Che = date('Y-m-d', strtotime("December 24"));
		
		// New Years
		$Ny = date('Y-m-d', strtotime("January 1"));
		
		// New Years Eve
		$Nye = date('Y-m-d', strtotime("December 31"));
		
		// 4th of July
		$Fj = date('Y-m-d', strtotime("July 4"));
		
		//Black Friday
		$Bf = $year.'-'.$month.'-'.($day + 1);
		
		// Set local variables to public class variables
		$this->MDay = $MDay; 
		$this->LD = $LD; 
		$this->TH = $TH;
		$this->Bf = $Bf;
		$this->Ch = $Ch;
		$this->CHe = $Che;
		$this->Ny = $Ny;
		$this->Nye = $Nye;
		$this->Fj = $Fj;
		$this->holidays = array($MDay, $LD, $TH, $Bf, $Ch, $Che, $Ny, $Nye, $Fj);
		
		
		// stick all these dates into an array
		// We only are of these days
		return array($MDay, $LD, $TH, $Bf, $Ch, $Che, $Ny, $Nye, $Fj);
		
	}
 }