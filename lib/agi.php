<?PHP
/*
 * Asterisk AGI helper functions
 *
 *	Copyright 2012, CyCore Systems, Inc.
 */

$GLOBALS['agi_end_on_hangup'] = true;

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

	/*
	 * Optional argument $parse declares that what is read should be parsed,
	 * and only the result code is returned from the read() function.
	 *
	 * Return values are (default) the input from Asterisk or (with "parse") 
	 * the result code from Asterisk.
	 * */
	public function read( $parse = false ) 
	{
		$input = str_replace("\n", "", fgets( STDIN, 4096 ) );
		trigger_error("read from Asterisk AGI: $input");
	
		if( $parse == 'parse' )
			return substr( strchr( $input, "("),1,-1);
	
		return $input;
	}

	public function write( $line )
	{
		fwrite( STDOUT, $line ."\n" );
		fflush( STDOUT );
		trigger_error("wrote to Asterisk AGI: $line");
	}

	public function verbose( $line, $level=1 )
	{
		$this->write( "verbose \"". addslashes($line) ."\" $level");
		return $this->read( 'parse' );
	}

	public function getVar( $varname )
	{
		$this->write( "get variable $varname" );
		return $this->read('parse');
	}

	public function setVar( $varname, $varval = 0 )
	{
		$this->write( "set variable $varname \"$varval\"" );
		return $this->read('parse');
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
