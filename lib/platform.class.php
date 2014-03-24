<?php

//   PeerTrade.  Peer to peer cryptocoin exchange software.
//   Copyright (C) 2014  peertrade.org
//   
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of the modified GNU General Public License
//   version 2 included with this program.
//   
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//   
//   You should have received a copy of the modified GNU General Public License
//   version 2 along with this program; if not, refer to
//   http://github.com/peertrade/peertrade/LICENSE


/**
 * a class to encapsulate some platform specific things so that the rest of
 * the code doesn't have to care.
 */
class platform {
    
    /**
     * returns path to peertrade user data.
     */
    static public function savedir() {
        
        $savedir = self::home_dir() . '/.peertrade';
        
        if( !is_dir( $savedir )) {
            $rc = @mkdir( $savedir );
            if( !$rc ) {
                throw new Exception( "Unable to create directory $savedir" );
            }
        }
        
        return $savedir;
    }
    
    /**
     * returns path to user's home dir, or equivalent.
     */
    static function home_dir() {

        // should work on unix/linux and mac osx, and maybe other things.
        $home = @$_SERVER['HOME'];
        
        // should work on windows.
        if( !$home && @$_SERVER['APPDATA'] ) {
            $home = @$_SERVER['APPDATA'];
        }
        
        return $home;
    }
    
}
