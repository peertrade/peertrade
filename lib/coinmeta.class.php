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
require_once( dirname(__FILE__) . '/platform.class.php' );

/**
 * a class that represents default metadata for a cryptocurrency.
 */
class coinmeta {
    
    public $host;
    public $rpcport;
    public $rpcuser;
    public $rpcpass;
    public $confpaths;
    public $symbol;
    public $name;
    public $hello_amount;
    public $author_donate_address;
    
    /**
     * constructor.  takes a currency symbol.
     */
    public function __construct( $symbol ) {
        $this->symbol = $symbol;
        
        $this->set_defaults();
    }
    
    // How coin settings work.   
    // 1. Defaults in install_dir/coin_defaults.json
    // 2. Override by settings in ~/.peertrade/coin_defaults.json
    // 3. Override by settings in .coin/coin.conf
    // 4. Override by settings in ~/.peertrade/coin_user.json
    
    /**
     * returns path to coin defaults file in install_dir
     */
    static public function coin_defaults_filename() {
        // distributed with software. replaced with new software releases.
        return dirname( __FILE__ ) . '/' . 'coin_defaults.json';
    }

    /**
     * returns path to coin defaults file in user conf dir
     */
    static public function coin_defaults_user_filename() {
        // distributed with software. replaced with new software releases.
        return platform::savedir() . '/' . 'coin_defaults.json';
    }    
    
    /**
     * returns path to user settings filename
     */
    static public function coin_user_filename() {
        // generated for user.  unaffected by new software releases.
        return platform::savedir() . '/' . 'coin_user.json';
    }
    
    /**
     * loads json settings for all coins.
     * either from user settings file or coin defaults file.
     */
    private function load_all_coin( $for_user ) {
        
        // use downloaded coin_defaults in userdir if available. Else use coin_defaults included with peertrade install.
        $coin_defaults_path = file_exists( $this->coin_defaults_user_filename() ) ? $this->coin_defaults_user_filename() : $this->coin_defaults_filename();
        $json = jsonutil::load_json_file( $for_user ? $this->coin_user_filename() : $coin_defaults_path );
        
        if( !$json ) {
            $json = array();
        }
        
        return $json;
    }
    
    /**
     * loads settings for the present coin.
     * either from user settings file or coin defaults file.
     */
    private function load_coin( $for_user ) {
        $settings = $this->load_all_coin( $for_user );
        $cm = @$settings[$this->symbol];
        
        $this->copysettings( $cm );
    }
    
    /**
     * saves present settings for this coin to the user settings file.
     */
    public function save_coin() {
        $allcoins = $this->load_all_coin( $for_user = true );
        $allcoins[$this->symbol] = get_object_vars( $this );
        
        jsonutil::write_json_file( $this->coin_user_filename(), $allcoins );
    }

    /**
     * removes this coin from the user settings file if present.
     */
    public function forget_coin() {
        $allcoins = $this->load_all_coin( $for_user = true );
        unset( $allcoins[$this->symbol] );
        
        jsonutil::write_json_file( $this->coin_user_filename(), $allcoins );
    }
    
    /**
     * loads coin settings from all available sources.
     *
     *   user settings override wallet settings.
     *   wallet settings override default settings.
     */
    static public function getcoin( $symbol ) {
        $c = new coinmeta( $symbol );
        
        // 1. Load default settings for coin.
        $c->load_coin( $for_user = false );

        // 1a.  If we do not have name and confpaths yet, then we have no
        // way to load settings from coin wallet config.  But there is a chance
        // that we have name or confpaths in the user settings, so we will load
        // them now, out of order to find out.
        if( !$c->name && !$c->confpaths ) {
            $c->load_coin( $for_user = true );
        }
        
        // 2. Override with coin wallet settings for coin.
        $c->load_conf();
        
        // 3. Override with user settings for coin.
        $c->load_coin( $for_user = true );
        
        return $c;
    }
    
    /**
     * copy relevant settings from input array.
     */
    private function copysettings( $settings ) {
        if( !is_array( $settings ) ) {
            return;
        }
        
        foreach( $settings as $k => $v ) {
            
            if( property_exists( $this, $k )) {
                $this->{$k} = $v;
            }
        }
        
        $this->set_defaults();
    }

    /**
     * Set some coin defaults
     */
    private function set_defaults() {
        
        if( !$this->hello_amount ) {
            $this->hello_amount = 0.00100000;
        }
        
        if( !$this->host ) {
            $this->host = '127.0.0.1';
        }
    }

    /**
     * loads relevant settings from wallet config file, if it can be found.
     */
    protected function load_conf() {
        
        if( !$this->confpaths && !$this->name ) {
            return;  // no way we can load conf.
        }
        
        $paths = $this->confpaths ? $this->confpaths . ':' : '';

        // Now we add some default paths for linux, windows, mac.
        $pathsarr = array(
                       '~/.%1$s/%1$s.conf',                               // linux/unix
                       '%%APPDATA%%\%1$s\%1$s.conf',                      // windows
                       '~/Library/Application Support/%1$s/%1$s.conf',    // mac osx
                      );
        $pathstmp = implode( ':', $pathsarr );
        $coinname = strtolower( $this->name );
        $paths .= sprintf( $pathstmp, $coinname );
        
        $pathlist = explode( ':', $paths );
        
        $config = array();
        foreach( $pathlist as $path ) {

            // expand tilde for linux/unix/osx.
            $path = str_replace( '~', platform::home_dir(), $path);
            
            // expand %AppData% for windows.
            $path = str_replace( '%APPDATA%', platform::home_dir(), $path);
            
            if( @file_exists( $path )) {
                
                $lines = file( $path );
                
                foreach( $lines as $line ) {
                    if( $line{0} == '#' || strlen( trim( $line ) ) == 0 ) {
                        continue;
                    }
                    @list( $key, $val ) = @explode( '=', $line );
                    $config[$key] = trim( $val );
                }
                break;
            }
        }
        
        if( @$config['rpcport'] ) {
            $this->rpcport = $config['rpcport'];
        }
        if( @$config['rpcuser'] ) {
            $this->rpcuser = $config['rpcuser'];
        }
        if( @$config['rpcpassword'] ) {
            $this->rpcpass = $config['rpcpassword'];
        }
    }
}
