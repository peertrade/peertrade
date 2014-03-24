<?php

/**
 * This file makes three things happen:
 *
 *   1) a global error handler for php errors that causes an exception to be
 *      thrown instead of standard php error handling.
 *
 *   2) a global exception handler for any exceptions that are somehow not
 *      caught by the application code.
 *
 *   3) error_reporting is set to E_STRICT, so that even notices cause an
 *      exception to be thrown.  This way we are forced to deal with even
 *      the minor issues during development, and hopefully fewer issues
 *      will make it out into the world.
 */

error_reporting( E_ALL | E_STRICT );
set_error_handler( '_global_error_handler' );
set_exception_handler ( '_global_exception_handler' );

/**
 * aspire to write solid code. everything is an exception, even minor warnings.
 **/
function _global_error_handler($errno, $errstr, $errfile, $errline ) {
    
    /* from php.net
     *  error_reporting() settings will have no effect and your error handler will
     *  be called regardless - however you are still able to read the current value of
     *  error_reporting and act appropriately. Of particular note is that this value will
     *  be 0 if the statement that caused the error was prepended by the @ error-control operator.
     */
    if( !error_reporting() ) {
        return;
    }

    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}


function _global_exception_handler( Exception $e ) {
    echo sprintf( "\nUncaught Exception: %s\n%s : %s\n\nStack Trace:\n%s\n", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString() );
    while( ( $e = $e->getPrevious() ) ) {
        echo sprintf( "Previous Exception: %s\n%s : %s\n\nStack Trace:\n%s\n", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString() );
    }
    echo "\n\nNow exiting.  Please report this problem to the software author\n\n";
    exit;
}

