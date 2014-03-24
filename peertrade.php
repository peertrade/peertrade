#!/usr/bin/env php
<?php

//   PeerTrade.  Peer to peer cryptocoin exchange software.
//   Copyright (C) 2014  peertrade.org
//   
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of the GNU General Public License
//   as published by the Free Software Foundation; version 2
//   of the License.
//   
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//   
//   You should have received a copy of the GNU General Public License
//   along with this program; if not, write to the Free Software
//   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

require_once( dirname(__FILE__) . '/lib/strict_mode.funcs.php' );  // force all our code to meet strict mode requirements.
require_once( dirname(__FILE__) . '/lib/bitcoin.inc' );
require_once( dirname(__FILE__) . '/lib/peertrade.class.php' );

exit( main( $argv ) );

/**
 * main entry point into the program.
 */
function main( $argv ) {
    
    // trick php into shutting up about the timezone.
    date_default_timezone_set( @date_default_timezone_get() );
    
    $opt = getopt(null, array( 'log-level:',
                               'help',
                               ) );
    try {
        $config = new peertradeconfig();
        evaluate_argv( $config, $opt, $argv );
        
        $peertrade = new peertrade( $config );
        $peertrade->run_interactive();
        
    }
    catch( Exception $e ) {
        echo $msg = sprintf( "\n\nCaught exception %s:%s\n%s\nTrace:\n%s\n\n", $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString() );
        logger::log( $msg, logger::fatalerror );
        
        echo "\n\nNow exiting.  Please report this problem to the software author\n\n";
        
        return -2;
    }
    
    return 0;
}

/**
 * evaluates CLI arguments and populates config object
 */
function evaluate_argv( peertradeconfig $config, $opt, $argv ) {
    
    if( isset( $opt['help'] ) ) {
        print_help();
        exit(0);
    }
    
    $config->set_if_not_null( 'rpc_debug', isset( $opt['rpc-debug'] ) );
    $config->set_if_not_null( 'log_level', @$opt['log-level'] );
}

/**
 * prints usage information.
 */
function print_help() {
   
   echo <<< END

   peertrade - p2p cryptocurrency exchange with known minimal risk.
   
   Optional Arguments:

    --log-level         Prints log messages matching level or higher.
                          Levels:
                            0 = debugextra
                            1 = debug
                            2 = info
                            3 = specialinfo
                            4 = warning
                            5 = fatalerror
                            
    --help              Print this help.    

END;

}

    
    

