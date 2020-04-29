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
		$username = "root";
		$password = "";
		$dbname = "helpdeskces";

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
		$sql = "SELECT DISTINCT poster FROM ost_thread_entry";
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
		$sql = "SELECT DISTINCT thread_id FROM ost_thread_entry WHERE poster ='" . $nameOfUser . "'"; 
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
		
		// Need to get poster of the thread for later
		
		
		
		
		// Get all the time stamps for thread
		$sql = "SELECT created, poster FROM ost_thread_entry WHERE thread_id = " . $threadID . " UNION SELECT created, created FROM ost_thread WHERE id = " . $threadID . " ORDER BY created ASC";
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
				// need to make sure prevName/prevDate are not null
				// If they are null this is presumably the first loop and we can skip stuff
				
				// Action item - if prev user has a C&E address dont count it
				// Also if poster is a C&E address make sure to count it as a response
				if (isset($prevName) && ($row['poster'] != $prevName) && )
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
		//ACTION ITME
		// if a ticket is closed and opened multiple times we need to have handling for that
		
		
		//get thrad createion date and thread end date
		$sql = 'SELECT ost_thread.id, ost_thread.created, ost_thread_event.timestamp FROM ost_thread, ost_thread_event WHERE ost_thread_event.thread_id = ost_thread.id AND ost_thread_event.data = "{\"status\":[3,\"Closed\"]}" AND ost_thread.id = '.$threadID;
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
		
		// Get the difference
		return $this->timeStampDIfference($created,$closed);
		
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
		$sql = 'SELECT created FROM ost_thread WHERE ost_thread.id = '.$threadID;
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
		$sql = "SELECT address FROM ost_user_emaail WHERE id = " . $userID;
		
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
 }
 
 // This is the same as getUserEmail but with a different input parameter
 function getPosterEmail($poster)
 {
	//need to go from ost_thread_entry.poster -> ost_user_email.address
	$sql = "SELECT DISTINCT ost_thread_entry.user_id, ost_thread_entry.poster, ost_user_email.address FROM ost_user_email, ost_thread_entry WHERE ost_user_email.user_id = ost_thread_entry.user_id AND ost_thread_entry.poster= '" . $poster . "'";
	
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