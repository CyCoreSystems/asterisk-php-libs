<?PHP
/*
 * Asterisk AGI helper functions
 *
 *	Copyright 2012, CyCore Systems, Inc.
 */

$GLOBALS['agi_end_on_hangup'] = true;

/**
 **  Associated Exceptions
 **/

/* Non-200 response code exception */
class AGICodeException extends Exception;

/* Empty or Null data exception */
class AGINoDataException extends Exception;

/* Failure to write to Asterisk exception */
class AGIWriteException extends ErrorException;

/**
 * Class AGI
 **/
class AGI
{
	public $agivars = Array();

	public function __construct()
	{
		/* Load the AGI variables as class variables */
		$agivars = array();
		while (!feof(STDIN)) {
			$agivar = trim(fgets(STDIN));
			if ($agivar === '') {
				break;
			}
			$agivar = explode(':', $agivar);
			$key = $agivar[0];
			$val = $agivar[1];
			$this->agivars[$key] = trim($val);
		}
	}

	/**
	 * read: Reads (and parses) a response from Asterisk AGI
	 *
	 * @return array Associative array containing:
	 *    'raw': the full, unparsed response from Asterisk
	 *    'code': the three-digit result code from Asterisk (200=OK,etc)
	 *    'result': the command-specific numeric result code
	 *    'data': the command-specific data (value in parentheses) from Asterisk
	 * */
	public function read()
	{
		$input = str_replace("\n", "", fgets( STDIN, 4096 ) );
		trigger_error("read from Asterisk AGI: $input");
	
		/* Parse the result */
		$pat = '/^\d{3}(?: result=.*?(?: \(?.*\))?)|(?:-.*)$/';
		preg_match($pat,$input,$matches);
		$ret = array(
			'raw' => $matches[0],
			'code' => $matches[1],
			'result' => $matches[2],
			'data' => $matches[3]
		);

		/* Throw exception is code is not 200 */
		if( $ret['code'] != '200' )
		{
			throw new AGICodeException( "Failure code from Asterisk: {$ret['raw']}", $ret['code'] );
		}

		/* Otherwise, return the response array */
		return $ret;
	}

	/**
	 * write: Writes a command to Asterisk AGI
	 **/
	public function write( $line )
	{
		if( !fwrite( STDOUT, $line ."\n" ) )
		{
			throw new AGIWriteException( "Failed to write to Asterisk" );	
		}
		if( !fflush( STDOUT ) )
		{
			throw new AGIWriteException( "Failed to flush write buffer to Asterisk" );
		}
		trigger_error("wrote to Asterisk AGI: $line");
	}

	/**
	 * getVar: retrieves the value of an Asterisk channel variable
	 *   @param string $varname Variable name
	 *   @param bool $exception_on_empty (optional) Whether to throw an exception if the variable is empty or undefined.  If not specified, the default is FALSE, in which case this method will return FALSE on empty or undefined.
	 *
	 *   @return string Value of variable (or FALSE if undefined)
	 **/
	public function getVar( $varname, $exception_on_empty = FALSE )
	{
		$this->write( "get variable $varname" );
		$ret = $this->read();

		/* Check for NULL data */
		if( $ret['result'] == '0' )
		{
			/* If we were told to throw an exception on an empty or undefined */
			if( $exception_on_empty )
			{
				throw new AGINoDataException( "No data received from Asterisk: ${ret['raw']}", 0 );
			}
			else // Otherwise, simply return FALSE
			{
				return FALSE;
			}
		}

		/* Return the data */
		return $ret['data'];
	}

	/**
	 * setVar: sets the value of an Asterisk channel variable
	 *   @param string $varname Variable name
	 *   @param string $varval Value to set
	 *
	 *   @return bool TRUE on success, FALSE on failure
	 **/
	public function setVar( $varname, $varval = 0 )
	{
		$this->write( "set variable $varname \"$varval\"" );
		$ret = $this->read();
		if( $ret['result'] == '1' )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * setDB: set a key(field) in the Asterisk database
	 *  @param string $family The "family" or "table" of the database key
	 *  @param string $key The key(name)
	 *  @param string $val The value to be stored
	 *
	 *  @return bool TRUE on success, FALSE on failure
	 **/
	public function setDB( $family, $key, $val )
	{
		$this->write( "database put $family $key $val" );
		$ret = $this->read();
		if( $ret['result'] == '1' )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * getDB: get a key(field) from the Asterisk database
	 *  @param string $family The "family" or "table" of the database key
	 *  @param string $key The key(name)
	 *  @param bool $exception_on_empty (optional) Whether to throw an exception if the variable is empty or undefined.  If not specified, the default is FALSE, in which case this method will return FALSE on empty or undefined.
	 *
	 *  @return string Value of the key(field) (or FALSE if undefined)
	 **/
	 public function getDB( $family, $key, $exception_on_empty = FALSE )
	{
		$this->write( "database put $family $key $val" );
		$ret = $this->read();

		/* Check for NULL data */
		if( $ret['result'] == '0' )
		{
			/* If we were told to throw an exception on an empty or undefined */
			if( $exception_on_empty )
			{
				throw new AGINoDataException( "No data received for Asterisk DB $family/$key: ${ret['raw']}", 0 );
			}
			else // Otherwise, simply return FALSE
			{
				return FALSE;
			}
		}

		/* Return the data */
		return $ret['data'];
	}

	/***
	 ***  Macro functions for common actions
	 ***/

	/**
	 * verbose: send a "verbose" message to Asterisk
	 *  @return bool TRUE on success, FALSE on failure
	 **/
	public function verbose( $line, $level=1 )
	{
		$this->write( "verbose \"". addslashes($line) ."\" $level");
		$ret = $this->read();
		if( $ret['result'] == '1' )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * answer:  answer the call
	 *  @return bool TRUE on success, FALSE on failure
	 **/
	public function answer()
	{
		$this->write( "answer" );
		$ret = $this->read();
		if( $ret['result'] == '1' )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * hangup:  hangup the call
	 *  @return bool TRUE on success, FALSE on failure
	 **/
	public function hangup()
	{
		$this->write( "hangup" );
		$ret = $this->read();
		if( $ret['result'] == '1' )
		{
			return TRUE;
		}
		return FALSE;
	}

}


/* Prepare hangup handler */
pcntl_signal( SIGHUP, "agi_hangup_handler" );
function agi_hangup_handler( $signo )
{
	trigger_error('Got hangup');
	if( $GLOBALS['agi_end_on_hangup'] )
		exit();
}

?>
