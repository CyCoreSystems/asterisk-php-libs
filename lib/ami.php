<?PHP
/*
 * Asterisk AMI helper functions
 *
 * Copyright 2012, CyCore Systems, Inc.
 */

require_once('include/base.php');

/**
 * This is an Asterisk Manager Interface class to facilitate communication
 * to Asterisk via the AMI protocol.
 *
 * @category	Net
 * @author		Seán C. McCord <scm@cycoresys.com>
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GPL 2
 */
class AMI
{
	public $amireq = Array();	// Elements of the AMI request 
	public $amivar = Array();	// Dialplan Variables set for AMI request 
	public $amires = Array();	// Elements of the AMI response

	public $host;	// IP Address / hostname of destination AMI 

	private $socket;
	public $error;

	public function __construct( $host = NULL )
	{
		if( $host )
			$this->host = $host;
		else
			$this->host = $GLOBALS['AMIHost'];

		$this->socket	= FALSE;
		$this->error	= "";
	}

	/**
	 * Login to an AMI
	 *
	 * @param string $host (optional) Connect to a particular host.  If not specified, the default will be used.
	 * @param string $username (optional) Specify a username.  If not specified, the default will be used.
	 * @param string $password  (optional) Specify a password.  If not specified, the default will be used.
	 *
	 * @return bool
	 */
	public function login( $host = NULL, $username = NULL, $password = NULL )
	{
		if( ! $host ) $host = $this->host;
		if( ! $username ) $username = $GLOBALS['AMIUser'];
		if( ! $password ) $password = $GLOBALS['AMIPass'];
		$errno = 0;
		$errstr = "";
		$this->socket = @fsockopen( $host, "5038", $errno, $errstr, 1 );
		if( ! $this->socket )
		{
			fclose( $this->socket );
			$this->socket = FALSE;
			throw new Exception( "Failed to connect to manager interface" );
		}
		
		stream_set_timeout( $this->socket, 1 );


		try
		{
			/* Clear out the preamble */
			$this->read( 1 );

			$this->clear();
			$this->addReq( "Action", "login" );
			$this->addReq( "Username", $username );
			$this->addReq( "Secret", $password );
			$this->addReq( "Events", "off" );
			if( $this->execute() )
				return TRUE;
			else
				throw new Exception( "Failed to log into manager interface" );
		}
		catch( Exception $e )
		{
			fclose( $this->socket );
			$this->socket = FALSE;
			throw new Exception("AMI Login failed: (". $e->getCode .") ". $e->getMessage());
			return FALSE;
		}
	}

	/**
	 * Logout of a presently logged-in AMI session
	 */
	public function logout()
	{
		try
		{
			$this->clear();
			$this->addReq( "Action", "Logoff" );
			$this->send();
		}
		catch( Exception $e )
		{
			trigger_error( "Failed to log off AMI connection.  
				Forcefully disconnecting, anyway.", E_USER_WARNING );
		}

		fclose( $this->socket );
		$this->socket = FALSE;
	}

	/**
	 * Clear all request, variable, and response data related to this AMI session
	 */
	public function clear()
	{
		$this->amireq = array();
		$this->amivar = array();
		$this->amires = array();
		if( ! empty( $this->amireq ) )
			throw new Exception( "Unable to clear request array" );
		if( ! empty( $this->amivar ) )
			throw new Exception( "Unable to clear variable array" );
		if( ! empty( $this->amires ) )
			throw new Exception( "Unable to clear result array" );
	}

	/* Add a variable */
	public function addVar( $key, $val )
	{
		$this->amivar[] = array( $key, $val );
	}

	/* Add a request */
	public function addReq( $key, $val )
	{
		$this->amireq[] = array( $key, $val );
	}

	/* Add a request, replacing one of the same key, if it exists */
	public function setReq( $key, $val )
	{
		foreach( $this->amireq as $r )
		{
			if( $r[0] == $key )
			{
				$r[1] = $val;
				return 0;
			}
		}
		$this->amireq[] = array( $key, $val );
	}

	public function execute()
	{
		$this->send();
		return $this->read();
	}

	public function send()
	{
		$ss = "";

		if( $this->socket === FALSE )
			throw new Exception( "Tried to send AMI without a connection" );

		/* Append request key/value pairs */
		foreach( $this->amireq as $r )
			$ss .= join( ": ", $r ) ."\r\n";

		/* Append Dialplan Variable settings */
		foreach( $this->amivar as $r )
			$ss .= "Variable: ". join( "=", $r ) ."\r\n";
		$ss .= "\r\n";
		
		debug( "Sending to AMI: $ss" );
		if( ! fputs( $this->socket, $ss ) )
			throw new Exception( "Failed to write to AMI socket" );
	}

	public function read( $maxlines = NULL, $timeout = 15 )
	{
		$count = 0;
		$line = NULL;
		$success  = FALSE;

		$info = Array();
		$info['timed_out'] = FALSE;

		stream_set_timeout( $this->socket, $timeout );

		while( ($line != "\n") && ($line != "\r\n") )
		{
			$parts = array();

			/* Get the next line */
			if( ! ($line = fgets( $this->socket, 4096 ) ) )
			{
				trigger_error( "Timed out waiting for a response in AMI", E_USER_ERROR );
				return $result;
			}

			debug("Received line from AMI: $line");
			/* Parse line */
			$parts = explode( ':', $line );
			if( count( $parts ) == 2 )
			{
				/* Load the line into the result array */
				$this->amires[] = array( trim( $parts[0] ), trim( $parts[1] ) );
	
				/* Check to see if this is a Response tag */
				if( trim( $parts[0] ) == "Response" )
				{
					if( trim($parts[1]) == "Success" )
						$success = TRUE;
					elseif( trim($parts[1]) == "Follows" )
						$success = TRUE;
					else
						error_log("Failed with response: $line");
				}
			}

			$info = stream_get_meta_data( $this->socket );
			if( $info['timed_out'] )
			{
				debug("AMI read() encountered a timeout waiting for the next line");
				return $success;
			}

			if( $maxlines )
			{
				++$count;
				if( $count >= $maxlines )
					break;
			}
		}

		return $success;
	}


	public function parkCall( $chan )
	{
		/* Park the given call */
		$this->clear();
		$this->addReq( 'Action', 'Park' );
		$this->addReq( 'Channel', $chan );
		$this->addReq( 'Timeout', 90 );
		if( ! $this->execute() )
			throw new Exception( "Unable to park call" );

		/* Now find where that call was parked */
		$parked = $this->getParkedCalls();
		foreach( $parked as $p )
		{
			/* Return the extension on match */
			if( $p['Channel'] == $chan )
				return $p['Exten'];
		}
		throw new Exception( "Call which was parked was not found" );
	}

	public function getParkedCalls()
	{
		$this->clear();
		$this->addReq( 'Action', 'ParkedCalls' );

		$parked = NULL;
		if( $this->execute() )
		{
			/* Each parked call is one event.  Parse until we receive
			 * an event which is ParkedCallsComplete
			 */
			$complete = FALSE;
			while( ! $complete )
			{
				$this->clear();
				$this->read();
				
				$tmpc = Array();
				foreach( $this->amires as $r )
				{
					/* If this is an event header, see what type it is */
					if( $r[0] == 'Event' )
					{
						switch( $r[1] )
						{
							case 'ParkedCallsComplete' :
								$complete = TRUE;
								break;;
							case 'ParkedCall' :
								continue;
								break;;
							default :
								debug("ERROR- Unknown event encountered waiting for parked call list: ". $r[1] );
								$complete = TRUE;
								break;;
						}
						if( $complete )
						   	break;
						continue;
					}

					$tmpc[ $r[0] ] = $r[1];
				}

				$parked[] = $tmpc;
			}
			return $parked;
		}
	}

	public function getChannels()
	{
		/* Now grab the list of Active Channels  */
		$this->clear();
		$this->addReq( 'Action', 'CoreShowChannels' );

		$channels = NULL;
		if( $this->execute() )
		{
			/* Each event is one channel.  Parse until we receive
			 * an event which is CoreShowChannelsComplete
			 */
			$complete = FALSE;
			while( ! $complete )
			{
				$this->clear();
				$this->read();
				
				$tmpc = Array();
				foreach( $this->amires as $r )
				{
					/* If this is an event header, see what type it is */
					if( $r[0] == 'Event' )
					{
						switch( $r[1] )
						{
							case 'CoreShowChannelsComplete' :
								$complete = TRUE;
								break;;
							case 'CoreShowChannel' :
								continue;
								break;;
							default :
								debug("ERROR- Unknown event encountered waiting for channel list: ". $r[1] );
								$complete = TRUE;
								break;;
						}
						if( $complete )
						   	break;
						continue;
					}

					$tmpc[ $r[0] ] = $r[1];
				}

				$channels[] = $tmpc;
			}
			return $channels;
		}
	}

	public function dumpChannelData( $chan )
	{
		debug("ChannelDump:");
		foreach( $chan as $d )
		{
			debug( "   ". key($chan) .": ". $d );
		}
		debug("EndChannelDump");
	}

	/* Find the best (least-loaded and up) PRI */
	public function bestPRI()
	{
		$pris = $this->checkPRI( 'ALL' );
		foreach( $pris as $pri )
		{
debug("PRI ". $pri[0] ." is ". $pri[1] );
			if( $pri[1] == 'Up' )
				$avail[$pri[0]] = 23;	// Set the available channel count to 23 to start
			if( $pri[1] == 'Down' )
				$avail[$pri[0]] = 0;	// PRI is down, so no channels are available
debug("PRI ". $pri[0] ." has ". $avail[$pri[0]] ." channels remaining");
		}

		/* Now assign calls to PRIs (if applicable) and reduce available channels accordingly */
		$channels = $this->getChannels();

debug("Working through ". sizeof( $channels ) ." channels.");

		foreach( $channels as $c )
		{
$this->dumpChannelData( $c );
			$parts = explode( '/', $c['Channel'] );
			if( $parts[0] == 'DAHDI' )
			{
				$cp = explode( '-', $parts[1] );

				/* Determine which PRI this DAHDI channel is a member of: 
				 *  because both channels and PRIs are indexed starting 
				 *  at one instead of zero, we have to offset both by one */
				$thispri = floor( ($cp[0] - 1) / 24.0 ) + 1;

debug("Channel ". $c['Channel'] ." is a member of PRI $thispri");

				/* Subtract the channel from those available to the associated PRI */
				$avail[$thispri] = $avail[$thispri] - 1;

debug("Reduced available channels on pri $thispri to ". $avail[$thispri] );
			}
			else {
debug("Channel ". $c['Channel'] ." is not a DAHDI channel");
			}
		}

		/* Now return the PRI with the highest positive number of available channels */
		$highest = array( 0, 0 );
		foreach( $avail as $t )
		{
			if( $t > $highest[1] )
				$highest = array( key($avail) , $t );
		}
		if( $highest[1] > 0 )
			return $highest[0];

		throw new Exception( "No available PRI channels available" );
	}

	public function checkPRI( $span = 0 )
	{
		$min = 0;
		$max = 4;
		if( $span )
		{
			/* If the span is set to ALL, then return an array of all
			 * active spans and their statii
			 */
			if( $span == 'ALL' )
			{
				$multiple = TRUE;
				$ret = Array();
			}
			else // Otherwise, they are asking for a particular span only
			{
				$min = $span;
				$max = $span;
			}
		}
		for( $i = $min; $i < ( $max + 1 ); $i++ )
		{
			$this->clear();
			$this->addReq( 'Action', 'Command' );
			$this->addReq( 'Command', "pri show span $i" );
			if( $this->execute() )
			{
				$status = $this->getRes( 'Status' );
				if( strpos( $status, 'Up' ) )
				{
					if( $multiple )
						$ret[] = array( $i, 'Up' );
					else
						return TRUE;
				}
				elseif( strpos( $status, 'Down' ) )
				{
					if( $multiple )
						$ret[] = array( $i, 'Down' );
				}
			}
		}

		if( $multiple )
			return $ret;

		/* If we failed to find a working PRI, return FALSE */
		return FALSE;
	}

	public function getChannelsWith( $key, $val )
	{
		$channels = $this->getChannels();

		$matches = Array();

		foreach( $channels as $c )
		{
			/* 
			 * Get the $key channel variable for each channel
			 * and try to match it with the given $val
			 */
			$this->clear();
			$this->addReq( 'Action', 'GetVar' );
			$this->addReq( 'Channel', $c['Channel'] );
			$this->addReq( 'Variable', $key );
			if( $this->execute() )
			{
				if( $this->getRes( 'Variable' ) == $checkvar )
					$testval = $this->getRes( 'Value' );

				if( $testval == $val )
					$matches[] = $c['Channel'];
			}
			// If execute failed, the channel disappeared.  Continue 
		}

		/* Return the matches */
		return $matches;
	}

	public function getRes( $key )
	{
		foreach( $this->amires as $r )
		{
			if( $r[0] == $key )
				return $r[1];
		}
		return FALSE;
	}

}


?>
