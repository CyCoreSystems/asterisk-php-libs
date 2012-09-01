asterisk-php-libs
=================

PHP Classes/Libraries for Asterisk

agi.php (class AGI)
===================

A simple class for building AGI-based PHP applications.  Loads AGI variables into the class object.  

The script may optionally be set to NOT end (default: end) on hangup by setting the PHP global variable `$GLOBALS['agi\_end\_on\_hangup']` to boolean `false`.  Note that this is handled by attaching to SIGHUP, so if your application already handles or uses this signal, you'll have problems.

read ( [ bool $parse ] )
------------------------

Reads reads a line from stdin (Asterisk).  If the optional $parse argument is set to true (default: false), then a result is attemped to be found and returned.

write ( $line )
---------------

Writes a(n otherwise unformatted) line to stdout (Asterisk).

verbose ( $line, [ $level = 1 ] )
---------------------------------

Sends the Verbose AGI command with the first argument as the content.  The optional second argument is the verbose level (default: 1) to set.

getVar ( $varname )
-------------------

Requests the value of a (channel) variable and returns it.

setVar ( $varname, [ $varval = 0 ] )
------------------------------------

Sets or creates a variable with the value given by the second argument.  If there is no second argument defines, then the variable is set to 0.


ami.php (class AMI)
===================

A simple class for building AMI-based PHP applications.

The host (or IP) for the Asterisk box may be passed as an argument to the class constructor, can be read from the PHP global `$GLOBALS['AMIHost']`, or passed as the first argument to AMI::login.  The port number is hard-coded to the standard 5038, but exists in only one place, so it can be easily changed, as needed.

Note on use:  The AMI sends multiple lines of key-value pairs back and forth with each command-response dialog.  In order to maximize flexibility of each, these pairs are parsed and stored in class variables.  This makes commands easy to construct and results easy to parse, but mandates that you "clear" these variables between dialogs.  Hence, between each command, you should call `AMI::clear()`

Errors are handled by exception throws.

login( $host, $username, $password )
------------------------------------

Log into the AMI interface of Asterisk.  This method must be called before any other communication methods are available.

All arguments are optional. 
- `host` will be pulled from the class variable 'host' (which may have been set during instantiation by parameter or PHP global 'AMIHost').
- `username` will be pulled from the PHP global `$GLOBALS['AMIUser']` if not supplied
- `password` will be pulled from the PHP global `$GLOBALS['AMIPass']` if not supplied


logout ()
---------

Explicitly log out from an AMI session.


clear ()
--------

AMI requests, results, and variables are stored as class variable arrays.  Hence, between each communication, this method should be called to reset these variables to null arrays.

addVar ( $key, $val )
---------

Adds a Variable to the pending AMI dialog. (Interpreted in AMI as `Variable: $key=$val`)

addReq ( $key, $val )
---------------------

Adds a request to the pending AMI dialog. (Interpreted in AMI as `$key: $val`)

setReq ( $key, $val )
---------------------

Adds a request to the pending AMI dialog, replacing the given key if it already exists.

execute ()
----------

Send the pending AMI dialog request (and load the response into the `AMI::amires` class variable).

send ()
-------

Send the pending AMI dialog request (only)... this should probably never be called directly: use `AMI::execute()`.

read( [ $maxlines = NULL ], [ $timeout = 15] )
----------------------------------------------

Read the response dialog from AMI... this should probably never be called directly: use `AMI::execute()`.

getRes ( $key )
---------------

Return the result value whose key is `$key`


### The following methods are specific macros for commonly-executed actions

parkCall ( $chan )
------------------

Park the given channel.  Returns the 'Exten' variable of the call on success.

getParkedCalls ()
-----------------

Returns the list of parked calls.

getChannels ()
--------------

Returns the list of active channels.

bestPRI ()
----------

Returns the span number of the least-loaded PRI.

checkPRI ( $span = 0 )
-----------

Checks the status of the given PRI span (default: 0).  Note:  this method only checks up to span 4.  If you have more than 4 spans, you'll need to change the `$max` (local method) variable.

getChannelsWith( $key, $val )
-----------------------------

Returns the list of active channels who have channel variables `$key` with values `$val`.







