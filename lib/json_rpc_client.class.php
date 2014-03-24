<?php
/*
                    COPYRIGHT

Copyright 2007 Sergio Vaccaro <sergio@inservibile.org>

This file is part of JSON-RPC PHP.

JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * An exception class to uniquely identify jsonrpc exceptions
 */
class jsonrpc_exception extends Exception {};

/**
 * An exception class to uniquely identify jsonrpc connection exceptions
 */
class jsonrpc_connect_exception extends jsonrpc_exception {};

/**
 * An exception class to uniquely identify jsonrpc server error responses
 */
class jsonrpc_error_exception extends jsonrpc_exception {};


/**
 * The object of this class are generic jsonRPC 1.0 clients
 * http://json-rpc.org/wiki/specification
 *
 * @author sergio <jsonrpcphp@inservibile.org>
 */
class json_rpc_client {
    
    /**
     * Debug state
     *
     * @var boolean
     */
    private $debug;
    
    /**
     * The server URL
     *
     * @var string
     */
    private $url;
    /**
     * The request id
     *
     * @var integer
     */
    private $id;
    /**
     * If true, notifications are performed instead of requests
     *
     * @var boolean
     */
    private $notification = false;
    
    /**
     * Takes the connection parameters
     *
     * @param string $url
     * @param boolean $debug
     */
    public function __construct($url,$debug = false) {
        // server URL
        $this->url = $url;
        // proxy
        empty($proxy) ? $this->proxy = '' : $this->proxy = $proxy;
        // debug state
        empty($debug) ? $this->debug = false : $this->debug = true;
        // message id
        $this->id = 1;
    }

    public function geturl() {
        return $this->url;
    }
    
    /**
     * Sets the notification state of the object. In this state, notifications are performed, instead of requests.
     *
     * @param boolean $notification
     */
    public function setRPCNotification($notification) {
        empty($notification) ?
                            $this->notification = false
                            :
                            $this->notification = true;
    }
    
    /**
     * Performs a jsonRCP request and gets the results as an array
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function __call($method,$params) {
        $debug_buf = '';

        logger::log( "in json_rpc_client::__call( $method )", logger::debug );
        
        // check
        if (!is_scalar($method)) {
            throw new jsonrpc_exception('Method name has no scalar value');
        }
        
        // check
        if (!is_array($params)) {
            throw new jsonrpc_exception('Params must be given as array');
        }

        // use associate parameters if we are passed an associative
        // array as first and only parameter.
        if( count($params) == 1 && is_array( $params[0] )  ) {
            $is_assoc = false;
            foreach( $params[0] as $k => $v ) {
                $is_assoc = !is_int( $k );
                break;
            }
            if( $is_assoc ) {
                $params = $params[0];
            }
        }
        
        // sets notification or request task
        if ($this->notification) {
            $currentId = NULL;
        } else {
            $currentId = $this->id ++;
        }

        
        // prepares the request
        $request = array(
                        'jsonrpc' => '2.0',
                        'method' => $method,
                        'params' => $params,
                        'id' => $currentId
                        );
        $request = @json_encode($request);

        logger::log( sprintf( "Sending JsonRPC request to %s  method: %s", $this->url, $method ), logger::info );
        
        $msg = "\n***** JsonRPC Request *****\n".$request."\n".'***** End Of request *****'."\n";
        logger::log( $msg, logger::debug );        
        
        // performs the HTTP POST
        $opts = array ('http' => array (
                            'method'  => 'POST',
                            'header'  => 'Content-type: application/json',
                            'content' => $request,
                            'ignore_errors' => true,
                            ));
        $context  = stream_context_create($opts);
        if ($fp = @fopen($this->url, 'r', false, $context)) {
            $response = '';
            while($row = fgets($fp)) {
                $response.= $row;
            }
            $msg = "\n***** JsonRPC Server response headers *****\n" . print_r($http_response_header, true ) . '***** End of server response headers *****'."\n";
            logger::log( $msg, logger::debug );        

            $maxlen = 256;
            if( logger::instance()->log_level <= logger::debugextra || strlen( $response ) < $maxlen ) {
                $msg = "\n***** JsonRPC Server response *****\n".$response."\n***** End of server response *****\n";
                logger::log( $msg, strlen( $response ) < $maxlen ? logger::debug : logger::debugextra );
            }
            else {
                $msg = "\n***** JsonRPC Server truncated response *****\n". substr( $response , 0, $maxlen )." ...\n" .'***** End of truncated server response *****';
                $msg .= "      ---> Enable debugextra flag to log full server responses";
                logger::log( $msg, logger::debug );
            }
            $response = @json_decode($response);
        } else {
            throw new jsonrpc_connect_exception( sprintf( 'Unable to communicate with %s while calling method %s', $this->url, $method ) );
        }
        
        // final checks and return
        if (!$this->notification) {
            // check
            if (@$response->id != $currentId) {
                throw new jsonrpc_exception('Incorrect response id (request id: '.$currentId.', response id: '.@$response->id.')');
            }
            if (!is_null( $response->error)) {
                throw new jsonrpc_error_exception($response->error->message, $response->error->code );
            }
            
            return $response->result;
            
        } else {
            return true;
        }
    }
}
?>
