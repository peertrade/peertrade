<?php

// So we can use json_encode with pretty print.
if (!defined("JSON_HEX_TAG")) {
   define("JSON_HEX_TAG", 1);
   define("JSON_HEX_AMP", 2);
   define("JSON_HEX_APOS", 4);
   define("JSON_HEX_QUOT", 8);
   define("JSON_FORCE_OBJECT", 16);
 }
if (!defined("JSON_NUMERIC_CHECK")) {
   define("JSON_NUMERIC_CHECK", 32);      // 5.3.3
 }
if (!defined("JSON_UNESCAPED_SLASHES")) {
   define("JSON_UNESCAPED_SLASHES", 64);  // 5.4.0
   define("JSON_PRETTY_PRINT", 128);      // 5.4.0
   define("JSON_UNESCAPED_UNICODE", 256); // 5.4.0
   define("EMULATE_JSON_ENCODE", 1);
 }


/**
 * A class to encapsulate some json utilities
 */
class jsonutil {
   
    /**
     * reads a file and decodes json within
     */
    static public function load_json_file( $file ) {
        
        $buf = @file_get_contents( $file );
        $json = @json_decode( $buf, true );
        
        return $json;
    }

    /**
     * json encodes data and writes to file
     */
    static public function write_json_file( $file, $json_data ) {
        
        $buf = jsonutil::json_encode( $json_data, JSON_PRETTY_PRINT );
        $rc = @file_put_contents( $file, $buf, LOCK_EX );
        
        if( !$rc ) {
            throw new Exception( "Unable to write json file $file.");
        }
    }
   
    static public function json_encode( $var, $options = 0) {
        if( defined( 'EMULATE_JSON_ENCODE') ) {
            return self::json_encode_5_4( $var, $options );
        }
        return json_encode( $var, $options );
    }

    // emulates json_encode from php 5.4, including JSON_PRETTY_PRINT
    static private function json_encode_5_4($var, $options=0, $_indent="") {

        #-- prepare JSON string
        $obj = ($options & JSON_FORCE_OBJECT);
        list($_space, $_tab, $_nl) = ($options & JSON_PRETTY_PRINT) ? array(" ", "    $_indent", "\n") : array("", "", "");
        $json = "$_indent";
        
        if ($options & JSON_NUMERIC_CHECK and is_string($var) and is_numeric($var)) {
            $var = (strpos($var, ".") || strpos($var, "e")) ? floatval($var) : intval($var);
        }
        
        #-- add array entries
        if (is_array($var) || ($obj=is_object($var))) {
    
           #-- check if array is associative
           if (!$obj) {
              $keys = array_keys((array)$var);
              $obj = !($keys == array_keys($keys));   // keys must be in 0,1,2,3, ordering, but PHP treats integers==strings otherwise
           }
    
           #-- concat individual entries
           $empty = 0; $json = "";
           foreach ((array)$var as $i=>$v) {
              $json .= ($empty++ ? ",$_nl" : "")    // comma separators
                     . $_tab . ($obj ? (self::json_encode_5_4($i, $options, $_tab) . ":$_space") : "")   // assoc prefix
                     . (self::json_encode_5_4($v, $options, $_tab));    // value
           }
    
           #-- enclose into braces or brackets
           $json = $obj ? "{"."$_nl$json$_nl$_indent}" : "[$_nl$json$_nl$_indent]";
        }
    
        #-- strings need some care
        elseif (is_string($var)) {
           if ( utf8_decode($var) === false ) {
              trigger_error("json_encode: invalid UTF-8 encoding in string, cannot proceed.", E_USER_WARNING);
              $var = NULL;
           }
           $rewrite = array(
               "\\" => "\\\\",
               "\"" => "\\\"",
             "\010" => "\\b",
               "\f" => "\\f",
               "\n" => "\\n",
               "\r" => "\\r", 
               "\t" => "\\t",
               "/"  => $options & JSON_UNESCAPED_SLASHES ? "/" : "\\/",
               "<"  => $options & JSON_HEX_TAG  ? "\\u003C" : "<",
               ">"  => $options & JSON_HEX_TAG  ? "\\u003E" : ">",
               "'"  => $options & JSON_HEX_APOS ? "\\u0027" : "'",
               "\"" => $options & JSON_HEX_QUOT ? "\\u0022" : "\"",
               "&"  => $options & JSON_HEX_AMP  ? "\\u0026" : "&",
           );
           $var = strtr($var, $rewrite);
           //@COMPAT control chars should probably be stripped beforehand, not escaped as here
           if (function_exists("iconv") && ($options & JSON_UNESCAPED_UNICODE) == 0) {
              $var = preg_replace("/[^\\x{0020}-\\x{007F}]/ue", "'\\u'.current(unpack('H*', iconv('UTF-8', 'UCS-2BE', '$0')))", $var);
           }
           $json = '"' . $var . '"';
        }
    
        #-- basic types
        elseif (is_bool($var)) {
           $json = $var ? "true" : "false";
        }
        elseif ($var === NULL) {
           $json = "null";
        }
        elseif (is_int($var) || is_float($var)) {
           $json = "$var";
        }
    
        #-- something went wrong
        else {
           trigger_error("json_encode: don't know what a '" .gettype($var). "' is.", E_USER_WARNING);
        }
        
        #-- done
        return($json);
     }
    
}