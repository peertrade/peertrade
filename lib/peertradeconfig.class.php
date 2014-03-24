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

require_once( dirname(__FILE__) . '/logger.class.php' );

/**
 * A class to hold configuration settings needed for performing a trade.
 */
class peertradeconfig {
    
    public $send_coind_symbol; 
    public $send_coind_name;
    public $send_coind_host;
    public $send_coind_rpcport;
    public $send_coind_rpcuser;
    public $send_coind_rpcpass;
    public $send_coind_address;
    public $send_coind_amount;
    public $send_coind_hello_amount;
    
    public $author_donate_address;
    public $author_donate_amount;
    
    public $receive_coind_symbol; 
    public $receive_coind_name;
    public $receive_coind_host;
    public $receive_coind_rpcport;
    public $receive_coind_rpcuser;
    public $receive_coind_rpcpass;
    public $receive_coind_address;
    public $receive_coind_amount;

    public $receive_coind_hello_amount;
    
    public $num_rounds;
    public $minconf;
    
    public $start_time;
    public $end_time;

    public $token;
    public $token_counter_party;
    
    public $rpc_debug = false;
    public $log_level = logger::debug;   // set default to debug during dev/beta for easier troubleshooting of user issues.
    
    /**
     * make a shallow copy of this object
     */
    public function copyshallow( array $data ) {
        foreach ($this as $key => $value) {
            $this->$key = null;
        }
        foreach ($data as $key => $value) {
            if( is_scalar( $value )) {
                $this->{$key} = $value;
            }
        }
    }
    
    /**
     * reset all settings back to null.
     */
    public function reset() {

        logger::log( "peertradeconfig::reset() called", logger::debug );
        
        $keep = array( "rpc_debug", "log_level" );
        
        $vars = get_object_vars( $this );
        foreach( $vars as $k => $v ) {
            if( !in_array( $k, $keep ) ) {
                $this->{$k} = null;
            }
        }
        
    }
    
    /**
     * sets a property if it is not null.
     */
    public function set_if_not_null( $attr, $value ) {
        
        if( !property_exists( $this, $attr ) ) {
            throw new Exception( "$attr is not a property of " . get_class( $this ) );
        }
        
        if( $value !== null ) {
            $this->$attr = $value;
        }
    }
    
    /**
     * Sets send settings from coinmeta object
     */
    public function set_send_defaults( coinmeta $coin ) {
        $this->send_coind_host = $coin->host;
        $this->send_coind_rpcport = $coin->rpcport;
        $this->send_coind_rpcuser = $coin->rpcuser;
        $this->send_coind_rpcpass = $coin->rpcpass;
        $this->send_coind_symbol = $coin->symbol;
        $this->send_coind_name = $coin->name;
        
        $this->author_donate_address = $coin->author_donate_address;
        
        $this->send_coind_hello_amount = $coin->hello_amount;
    }

    /**
     * Sets receive settings from coinmeta object
     */    
    public function set_receive_defaults( coinmeta $coin ) {
        $this->receive_coind_host = $coin->host;
        $this->receive_coind_rpcport = $coin->rpcport;
        $this->receive_coind_rpcuser = $coin->rpcuser;
        $this->receive_coind_rpcpass = $coin->rpcpass;
        $this->receive_coind_symbol = $coin->symbol;
        $this->receive_coind_name = $coin->name;
        $this->receive_coind_hello_amount = $coin->hello_amount;
    }

}
