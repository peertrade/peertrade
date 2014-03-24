<?php

class logger {
    
    const debugextra = 0;
    const debug = 1;
    const info = 2;
    const specialinfo = 3;
    const warning = 4;
    const fatalerror = 5;
        
    public $log_level;
    public $echo_log;
    protected $log_file;
    
    static $instance;
    
    public function __construct( $log_file = null, $echo_log = false, $log_level = logger::info ) {
        $this->log_file = $log_file;
        $this->log_level = $log_level;
        
        $this->level_map = array( self::debugextra => 'debugextra',
                                  self::debug => 'debug',
                                  self::info => 'info',
                                  self::specialinfo => 'specialinfo',
                                  self::warning => 'warning',
                                  self::fatalerror => 'fatalerror'
                              );
    }

    /**
     * returns global logger. A new global instance can also be set.
     */
    public static function instance( logger $instance = null ) {
        
        if( $instance ) {
            self::$instance = $instance;
        }
        
        else if( !self::$instance ) {
            self::$instance = new logger();
        }
        
        return self::$instance;
    }
    
    public function set_log_file( $log_file ) {
        $this->log_file = $log_file;
    }

    public function get_log_file() {
        return $this->log_file;
    }
    
    public static function log( $msg, $log_level ) {
        
        self::instance()->_log( $msg, $log_level );
    }
    
    public function _log( $msg, $log_level ) {
        
        if( $log_level < $this->log_level ) {
            return;
        }
        
        if( !$this->log_file ) {
            throw new Exception( "Log file not yet specified." );
        }
        

        $time_buf = '';        
        if( $this->log_level == self::debug || $this->log_level == self::debugextra ) {
            $time = microtime(true);
            $duration = @$this->_last_time ? $time - $this->_last_time : null;
            $this->_last_time = $time;
            $time_buf = $duration ? " [lastlog: $duration secs] " : '';
        }

        $fh = @fopen( $this->log_file, 'a' );
        if( !$fh ) {
            throw new Exception( sprintf( "Unable to open log file %s", $this->log_file ) );
        }
            
        fwrite( $fh, $line = sprintf( "%s%s [%s] -- %s\n", date('c'), $time_buf, @$this->level_map[$log_level], $msg ));
        fclose( $fh );
        
        if( $this->echo_log ) {
            echo $line;
        }
    }
    
}