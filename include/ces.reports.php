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
		
		return $result;
	}
	 
	 // This is the "main function"
	 // I should probably rename this main and get the users in another function
	function getUsers()
	{
		$sql = "SELECT DISTINCT poster FROM ost5h_thread_entry";
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			$x=0;
			// output data of each row
			while($row = $result->fetch_assoc()) 
			{
				//array_push($this->user,$row['poster']);
				$this->user[$x]= new CEUser;
				$this->user[$x]->username=$row['poster'];
				//Need to get threads per user now
				$this->user[$x]->threads = $this->getThreads($row['poster']);
				$x++;
			}
		}
		return $this->user;
	}
	
	function getThreads($nameOfUser)
	{
		$sql = "SELECT DISTINCT thread_id FROM ost5h_thread_entry WHERE poster ='" . $nameOfUser . "'"; 
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
				// need to call response/ service time functions here
				//calling function as a test for now
				$threads[$x]->responseTime = $this->getResponseTime($row['thread_id']);
				$threads[$x]->serviceTime = $this->getServiceTime($row['thread_id']);
				$threads[$x]->createDate = $this->getCreateDate($row['thread_id']);
				
				$x++;
			}
		}
		return $threads;
	}
	
	function getResponseTime($threadID)
	{
		

		
		// Get all the time stamps for thread
		$sql = "SELECT created, poster FROM ost5h_thread_entry WHERE thread_id = " . $threadID . " UNION SELECT created, created FROM ost5h_thread WHERE id = " . $threadID . " ORDER BY created ASC";
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
				if ($x = 0)
				{
					$ogPoster = $row['poster'];
				}
				
				
				// need to make sure prevName/prevDate are not null
				// If they are null this is presumably the first loop and we can skip stuff
				
				
				//scanario one
				// This is not the first run, Last message is external, previous message did not come from user
				$s1 = (isset($prevName) && ($row['poster'] != $prevName) && $this->checkEmail($this->getPosterEmail($row['poster'])));
				
				//scenario two
				// This is not the first run, last messsage is the ticket creater who is internal
				$s2 = ( isset($prevName) && $ogPoster == $row['poster'] && $this->checkEmail($this->getPosterEmail($row['poster'])));
				
				// Also if poster is a C&E address make sure to count it as a response
				if ($s1 || $s2)
				{
					// make string into a timestamp we can use
					$timestamp = strtotime($row['created']);
					
					// Call function to compare date times (with filtering of work hours)
					$times[$x] = $this->timeStampDIfference($timestamp,$prevDate);
					$x++;
				}
				
			// end of this loop, set values for next go
			$prevName = $row['poster'];
			$prevDate = strtotime($row['created']);
			
			}
		}
			// take average
			return (array_sum($times)/count($times));
	}
	function getServiceTime($threadID)
	{
		//ACTION ITME - done
		// if a ticket is closed and opened multiple times we need to have handling for that
		// The best way to do this might be to go through events,
		// Then get each closed, and the event after it (if their is one)
		// Then get the difference
		// Then subtract this difference from what would have been the service time
		// I think I will do this in another function called getClosedTime()
		
		
		//get thrad createion date and thread end date
		$sql = 'SELECT ost5h_thread.id, ost5h_thread.created, ost5h_thread_event.timestamp FROM ost5h_thread, ost5h_thread_event WHERE ost5h_thread_event.thread_id = ost5h_thread.id AND ost5h_thread_event.data = "{\"status\":[3,\"Closed\"]}" AND ost5h_thread.id = '.$threadID;
		// This should only be returning one row
		$result = $this->cesQuery($sql);
		
		if ($result->num_rows > 0) 
		{
			// In the unlikely event there are two rows this will just take the last one
			while($row = $result->fetch_assoc())
			{
				//Get differnece
				$created = $row['created'];
				$closed =  $row['timestamp'];
			}
		}
		
		//These need to be actual time stamps, not strings
		$created = strtotime($created);
		$closed  = strtotime($closed);
		
		// Calculate Difference
		$serviceTime = $this->timeStampDIfference($created,$closed);
		// Subtract Time the ticket was closed
		$serviceTime = $serviceTime - $this->getClosedTime($threadID);
		
		// Get the difference
		return $serviceTime;
		
	}
	
	// Needs time as seconds since epoch - NOT a string
	// Gets differnece without off hours
	function timeStampDIfference($stampOne, $stampTwo)
	{
		//... going to just place this here, just in case
		date_default_timezone_set("US/Eastern");
		
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
		
		// Go hour by hour, see if its an off hour, if it is - remove it from $diff
		//3600 seconds = one hour
		//60 seconds = one minute (duh)
		$interval = 60;
		for($x=$stampTwo; $x < $stampOne; $x = $x + $interval)
		{
			// is X a weekend? - 6 or 7 is saturday or sunday respectivly
			if(date('N', $x) >= 6)
			{
				$isWeekend = true;
			} else {
				$isWeekend = false;
			}
			
			// check and see if its an hour after hours
			// maybe at some point this should be poitned to DB for working hours of company
			if (date('G',$x) > 8 && date('G',$x) < 17)
			{
				$afterHours = true;
			}
			else
			{
				$afterHours = false;
			}
			// if its after hours or a weekend hour, remove hour from $$diff
			if( $afterHours || $isWeekend )
			{
				$diff = $diff - $interval;
			}
			
			// if date minutes is 00 (example 1:00) switch to hours
			if( date('i',$x) == '00')
			{
				$interval = 3600;
			}
				
	
		 }

		return $diff;
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
		$sql = "SELECT address FROM ost5h_user_emaail WHERE id = " . $userID;
		
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
			if (stripos($email, $domainName) !== false) 
			{		
				return true;
			}
		}
		
		return false;
	}
	
	// This function need to return the ammount of time a ticket has been closed for
	function getClosedTime($threadID)
	{
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
				// If its set that means last event was a closure.
				if(isset($lastClosed))
				{
					$closedTime = $closedTime + ABS($row['timestamp'] - $lastClosed);
				}
				
				
				
				// check if ticket has been closed
				if ($row['event_id'] == 2)
				{
					// set last closed to timestamp of closd event
					$lastClosed = $row['timestamp'];
				}
				else
				{
					$lastClosed = null;
				}
					
			}
		}
		return $closedTime;
	}
	
	// This function needs to take poster from the get users function
	// And be able to return the team the user belongs to
	function getTeam($poster)
	{
		
		return $team;
	}
 }
 

 
 class CEUser
 {
	 public $username;
	 public $threads = array();
	 // I think I'll need to get user team as well
 }
 
 class ThreadDetails
 {
	 public $id;
	 public $createDate;
	 public $serviceTime = '0';
	 public $responseTime = '0';
	 
 }