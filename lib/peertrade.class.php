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

require_once( dirname(__FILE__) . '/bitcoin.inc' );
require_once( dirname(__FILE__) . '/jsonutil.class.php' );
require_once( dirname(__FILE__) . '/logger.class.php' );
require_once( dirname(__FILE__) . '/platform.class.php' );
require_once( dirname(__FILE__) . '/peertradeconfig.class.php' );
require_once( dirname(__FILE__) . '/coinmeta.class.php' );


/**
 * This is the main application class that interacts with the user and
 * performs the exchange.
 */
class peertrade {
    
    protected $config;
    protected $daemon_receive;
    protected $daemon_send;

    // set to true during development to avoid contacting peertrade.org    
    protected $devbox = false;
    
    protected $author_donation_percentage = 0.005;  // tip 1/2 of 1 percent by default.
    
    protected $relock_wallet = false;  // indicates if wallet should be locked after trade completes.
    
    const trade_status_incomplete = 'incomplete';
    const trade_status_complete = 'complete';
    const trade_status_cancelled = 'cancelled';
    
    /**
     * constructor
     */
    public function __construct( peertradeconfig $config ) {

        // first, setup logger for all classes to use.
        $logfile = platform::savedir() . '/debug.log';
        $logger = new logger( $logfile, false, $config->log_level );
        logger::instance( $logger );

        $this->config = clone( $config );
    }
    
    /**
     * run in user-interactive mode.  batch mode is no longer supported.
     */
    public function run_interactive() {

        logger::log( "peertrade::run_interactive() called", logger::debug );
        
        $action = null;
        do {
            $continue = true;
            $exception_msg = null;
            try {
                $continue = $this->present_main_menu();
            }
            catch( jsonrpc_connect_exception $e ) {
                $exception_msg = sprintf( "A wallet communication error occurred.\n --> %s", $e->getMessage() );
            }
            catch( Exception $e ) {
                $exception_msg = sprintf( "An error occurred.\n --> %s", $e->getMessage() );
            }
            if( $exception_msg ) {
                echo sprintf( "\n\n********** Error **********\n%s\n***************************\n\n", $exception_msg );
                
                $logmsg = sprintf( "\n\nCaught exception %s:%s\n%s\nTrace:\n%s\n\n", $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString() );
                logger::log( $logmsg, logger::fatalerror );
                
                $this->get_user_input( "Press Enter to continue: ", null, 'C');
                
                // whatever the error, user is safer if they backup their wallet.
                // this way they are informed if an error occurs during a trade.
                $this->wallet_backup_inform();
            }
            
        } while( $continue );
        
    }

    /**
     * Presents and processes the main menu
     */
    private function present_main_menu() {
                
        $this->config->reset();
        
        $this->retrieve_coin_defaults();
        
        $incompleted = $this->find_incomplete_trades();
        $completed = $this->find_complete_trades();
                    
        echo "\n=== PeerTrade Main Menu ===\n\n";
                
        echo sprintf( "You have %s incomplete trades pending.\n", count( $incompleted ) );
        echo sprintf( "You have completed %s trades to date.\n\n", count( $completed ) );
        
        echo "Your options:\n\n";
        echo "  [B]egin a new trade\n";
        echo "  [V]iew a token\n";
        echo "  [I]ncomplete trades list.  Resume/Cancel\n";
        echo "  [C]ompleted trades list\n";
        echo "  [A]ll trades list\n";
        echo "  [Q]uit\n\n";
        
        if( $this->devbox ) {
            echo "  ---->  devbox mode is ON  <----\n\n";
        }
        
        echo "What's your pleasure? ";
        $action = strtoupper( trim( fgets( STDIN ) ) );
        
        if( !in_array( $action, array( 'B', 'I', 'C', 'Q', 'V', 'A' ))) {
            echo "Unrecognized option.\n\n";
            $action = null;
        }

        if( $action == 'Q' ) {
            echo "\nGoodBye!\n\n";
            return false;
        }

        if( $action == 'B' ) {
            $this->process_begin_menu();            
        }
        
        if( $action == 'I') {
            $this->process_incomplete_list_menu( $incompleted );
        }
        
        if( $action == 'C') {
            $this->process_completed_list_menu( $completed );
        }

        if( $action == 'A') {
            $this->process_all_trades_list_menu();
        }
        
        if( $action == 'V') {
            $this->process_token_view();
        }
        
        return true;

    }
    
    /**
     * retrieves the coin defaults file from website.
     *
     * TODO: make this optional, possibly with command-line switch.
     */
    protected function retrieve_coin_defaults( $always = false ) {
        
        logger::log( "peertrade::retrieve_coin_defaults( $always ) called", logger::debug );
        
        $file = coinmeta::coin_defaults_user_filename();
        if( $always || !file_exists($file) || filemtime( $file ) < time() - 86400 ) {
            
            $url = $this->devbox ? 'http://api.peertrade.dev/coin_defaults.json' :
                                   'http://api.peertrade.org/coin_defaults.json';

            $parts = @parse_url( $url );
            $domain = @$parts['host'];
                                   
            echo "\n\n--- Update Coin Defaults ---\n\n";
            echo "The coin_defaults.json file contains a list of known cryptocoins.\n";
            echo "The most recent list will now be retrieved from $domain.\n\n";
            echo "Fetching $url ...\n";
            
            logger::log( "fetching $url", logger::info );
            
            $buf = @file_get_contents($url);
            
            $data = @json_decode( $buf, true );
            $rc = false;
            if( $data && @count( $data ) ) {
                $rc = @file_put_contents( $file, $buf, LOCK_EX );
            }
            $msg = $rc ? "Coin Defaults updated!" :
                         "Coin Defaults were not updated.";
                         
            echo $msg . "\n";
                         
            logger::log( $msg, logger::info );
        }
    }

    /**
     * displays list of completed trades to user.
     */
    protected function process_all_trades_list_menu() {
        
        $history = $this->load_trade_history();
        $trades = $history['trades'];
        
        echo "\n--- All Trades ---\n";
        $index = 1;
        foreach( $trades as $t ) {
            $this->print_history( $index ++, $t, $show_status = true );
            echo "\n";
        }
        if( $index == 1) {
            echo "   None\n";
        }
        
        $this->get_user_input("\nAny key to continue: ", null, 'Q');
        return;
    }
    
    
    /**
     * displays list of completed trades to user.
     */
    protected function process_completed_list_menu( $completed ) {
        
        echo "\n--- Completed trades ---\n";
        $index = 1;
        foreach( $completed as $com ) {
            $this->print_history( $index ++, $com );
            echo "\n";
        }
        if( $index == 1) {
            echo "   None\n";
        }
        
        $this->get_user_input("\nAny key to continue: ", null, 'Q');
        return;
    }
    
    /**
     * displays list of incomplete trades to user.
     * Enables user to cancel or resume an incomlete trade.
     */
    protected function process_incomplete_list_menu( $incompleted ) {
        
        echo "\n--- Incomplete trades ---\n";
        $index = 1;
        foreach( $incompleted as $inc ) {
            $this->print_history( $index ++, $inc );
            echo "\n";
        }
        if( $index == 1) {
            echo "   None\n";
            $this->get_user_input("\nAny key to continue: ", null, 'Q');
            return;
        }
        
        do {
            $choice = $this->get_user_input( "\n\nEnter a trade index or any other key to exit: ", null, 'Q' );
            
            if( is_numeric( $choice )) {
                if( $choice < 1 || $choice > count( $incompleted ) ) {
                    echo "Invalid index.\n";
                    continue;
                }

                echo "\n\nHere is the token for this trade. Send it to the counter party if they lost it.\n\n";
                echo sprintf( "%s\n\n", @$inc['token'] );
                
                $action = $this->get_user_input( "\n[R] to resume trade, [C] to cancel trade, any other key to exit: ", null, 'Q' );
                echo "\n";
                
                if( strtoupper($action) == 'R') {
                    $this->resume_trade( $incompleted[ $choice - 1 ] );
                }
                if( strtoupper($action) == 'C') {
                    $this->cancel_trade( $incompleted[ $choice - 1 ] );
                }
                
                $quit = true;
                break;  // back to top of main menu loop.
            }
            else {
                break;
            }
        } while( 1 );
        
    }

    /**
     * Prompts user for token and displays token details or error message.
     */
    protected function process_token_view() {
        
        do {
            echo "\n=== View Token Details ===\n\n";
            
            $token = $this->get_user_input_token( "\nEnter the PeerTrade token you wish to view, or [C]ancel:\n\n" );
            
            if( !$token ) {
                return;
            }
            
            $token_data = $this->decode_counterparty_token( $token );
            if( !$token_data ) {
                echo "\nThis token is invalid.  Please verify that you copy/pasted it correctly.\n";
                return;
            }
    
            echo "\n";        
            foreach( $token_data as $k => $v ) {
                echo sprintf( "%s : %s\n", str_pad( $k, 20 ), $v );
            }
            
            $choice = $this->get_user_input( "\n\nView another token? [Y/n] ", null, 'Y', 'Y:N');
            if( $choice == 'N' ) {
                break;
            }
        } while( 1 );
        
    }

    /**
     * Initiates a trade with token received from counter party.
     */
    protected function process_begin_via_token_menu() {
        
        echo "\n=== Begin a trade with token from counter party ===\n\n";
        
        echo "The token you received from the counter party contains trade details.\n\n";
                
        $token = $this->get_user_input_token();
        if( !$token ) {
            return;
        }
        echo "\n\n";
        
        // note: ::set_config_from_token generates our new receive address if not present in token.
        $token_data = $this->set_config_from_token( $token );
        if( !$token_data ) {
            $this->get_user_input( "\nPress Enter to continue ", null, 'C' );
            echo "\n";
            return;            
        }

        // note that ::gen_counterparty_token sets $this->config->token.
        $counterparty_token = $this->gen_counterparty_token();
        
        // if the token did not have a send address (matching our receive address)
        // then we need to generate a new token and send to counterparty.
        // if we got here and token has a send_address, then we know it matches
        // our receive address because that is validated by ::set_config_from_token()
        if( !@$token_data['send_address'] ) {

            echo "\n--- Trade Parameters ---\n\n";
            $this->print_trade_params( $showtip = false );
            
            $choice = strtoupper( $this->get_user_input( "\nThe trade will use these parameters. [C]ancel or [N]ext [c/N] : ", null, 'N', 'C:N' ) );
            if( $choice == 'C' ) {
                $this->get_user_input( "\nYou have cancelled this trade.  Any key to continue. \n", null, 'C' );
                return;
            }
                    
            echo "\n\n--- Send Token to Counterparty ---\n";
            echo "\nWe created a token containing your payment address and trade parameters.\n";
            echo "Please send this token to the counter party you are exchanging with.\n( via email, chat, sms, bitmessage, etc )\n\n";
            echo "$counterparty_token\n";
            
            echo "\nOnce the counter party enters your token, the trade can begin.\n";
            
            $this->get_user_input( "Any key to continue ", null, 'C' );
            
        }

        $this->show_params_and_kickoff_trade();
    }

    /**
     * Initiates a trade
     */
    protected function process_begin_menu() {

        logger::log( "in peertrade::process_begin_menu", logger::debug );
        
        echo "\n=== Begin a new trade. ===\n\n";
        
        echo "We need to establish the trade parameters.\n";
        echo "You will be given a chance to review all details after they are entered.\n\n";
        
        $choice = $this->get_user_input( "Have you received a PeerTrade token from the counter party? [y/N] : ", null, 'N', 'Y:N' );
        if( $choice == 'Y' ) {
            $this->process_begin_via_token_menu();
            return;
        }
        
        $symbol = strtoupper( $this->get_user_input( "\nWhat is the symbol of the currency you are buying? " ) );
        $coin = coinmeta::getcoin( $symbol );
        $coin = $this->ensure_coin_values( $coin, $symbol );
        $this->config->set_receive_defaults( $coin );

        $symbol = strtoupper( $this->get_user_input( "What is the symbol of the currency you are selling? " ) );
        $coin = coinmeta::getcoin( $symbol );
        $coin = $this->ensure_coin_values( $coin, $symbol );
        $this->config->set_send_defaults( $coin );
        
        echo "\n\n";
        $continue = $this->init_daemons();
        if( !$continue ) {
            return;
        }

        $this->config->receive_coind_amount = $this->get_user_input( sprintf( "\nHow many %s are you buying? ", $this->config->receive_coind_symbol ), "float", null, null, $this->config->receive_coind_hello_amount, null, 8 );
        do {
            $this->config->send_coind_amount = $this->get_user_input( sprintf( "How many %s are you selling? ", $this->config->send_coind_symbol ), "float", null, null, $this->config->send_coind_hello_amount, null, 8 );

            logger::log( sprintf( "fetching %s balance", $this->config->send_coind_symbol ), logger::info );
            
            $balance = $this->daemon_send->getbalance();
            if( $this->config->send_coind_amount > $balance ) {
                $msg = sprintf( "%s %s is greater than your wallet balance ( %s %s ).", $this->config->send_coind_amount, $this->config->send_coind_symbol, $balance, $this->config->send_coind_symbol );
                echo $msg . "\n";
                logger::log( $msg, logger::warning );

                continue;
            }
        } while ( $this->config->send_coind_amount <= 0 );
        
        $this->config->author_donate_amount = $this->trunc( $this->config->send_coind_amount * $this->author_donation_percentage, 8 );
        
        $this->config->num_rounds = $this->get_user_input( "How many rounds of exchange? [10] ", "int", 10, null, $min = 0, $max = 1000 );
        $this->config->minconf = $this->get_user_input( "How many receive confirmations do you require? [0] ", "int", 0, null, $min = 0, $max = 6 );
        
        $this->config->receive_coind_address = $this->daemon_receive->getnewaddress();
        
        $counterparty_token = $this->gen_counterparty_token();

        // echo sprintf( "\nYour %s payment address for this trade is: %s\n", $this->config->receive_coind_symbol, $this->config->receive_coind_address );
        // echo "Please exchange payment addresses with the counterparty to proceed.\n\n";
        
        echo "\n--- Trade Parameters Thus Far ---\n\n";
        $this->print_trade_params();

        $this->get_user_input( "\nWe will obtain the counter party payment address via a token exchange.  Any key to continue. ", null, 'C' );        
        
        echo "\n\n--- Exchange Tokens with Counterparty ---\n";
        echo "\nWe created a token containing your payment address and trade parameters.\n\n";
        echo "SEND THIS TOKEN TO THE COUNTER PARTY NOW\n( via email, chat, sms, bitmessage, etc )\n\n";
        echo "$counterparty_token\n";
        
        do {
            $choice = $this->get_user_input( "Have you sent the token? [Y]es, [N]o, [C]ancel ", null, null, 'Y:N:C' );
            if( $choice == 'Y' ) { break; }
            if( $choice == 'N') { echo "\n You must send the token to the counter party before we can proceed.\n\n"; }
            if( $choice == 'C') { echo "\n\nYou have cancelled this trade.\n"; return; }
        } while( 1 );
        
        echo "\n\n--- Now we wait ---\n\n";
        echo "The counter party should initiate a trade with your token, and send you a reply token.\n";
        echo "Don't worry about me. I'm a computer.  I don't mind waiting.   ;-)\n";

        logger::log( "Waiting for counterparty token", logger::info );
        
        do {
            $valid = false;
            $token = $this->get_user_input_token();
            if( !$token ) {
                echo "You have cancelled this trade.\n";
                $this->get_user_input( "Any key to continue", null, 'C' );
                
                logger::log( "user cancelled trade.", logger::info );
                
                return;
            }
            echo "\n";
            
            if( $this->normalize_counterparty_token($token) == $this->normalize_counterparty_token($counterparty_token) ) {
                echo "Error. You have entered your own token by mistake.\n";
                echo "Instead you need to send this token to the counter party\n";
                echo "and input here the token they supply to you.\n";
                
                logger::log( "user entered own token instead of counterparty token.  ignoring token.", logger::warning );
                
                continue;
            }
            
            $token_data = $this->set_config_from_token( $token );
            if( $token_data ) {
                break;
            }
            else {
                $msg = "This token cannot be used!  Please obtain a valid token from the counter party.";
                
                echo "\n$msg\n\n";
                logger::log( $msg, logger::warning );
            }
            
        } while( true );
        
        $this->show_params_and_kickoff_trade();
        
    }
    
    /**
     * Displays trade parameters and allows user to abort, change tip, or begin.
     */
    private function show_params_and_kickoff_trade() {

        logger::log( "in peertrade::show_params_and_kickoff_trade()", logger::debug );
        
        do {
            echo "\n== Ready To Begin Trade ==\n\n";
            $this->print_trade_params( $showtip = true );
            
            echo "\nPlease note that if the counter party sends more coins than expected per round, then we will also to catch up.\n";
            
            $msg = $this->config->author_donate_address ?
                      "\nAll systems go.  [A]bort, [B]egin, or [C]hange Tip? " :
                      "\nAll systems go.  [A]bort or [B]egin? ";
            
            $choice = $this->get_user_input( $msg, null, null, 'A:B:C' );
            $choice = strtoupper( $choice );
            if( $choice == 'A' ) {
                logger::log( "trade aborted by user", logger::info );
                $this->get_user_input( "\nThis trade has been aborted.  Any key to continue. ", null, 'Y' );
                return;
            }
            else if( $choice == 'B') {
                break;  // let's do it!
            }
            else if( $choice == 'C' && $this->config->author_donate_address ) {
                $this->config->author_donate_amount = $this->get_user_input( "New tip amount? ", 'float', null, null, null, null, 8 );
                logger::log( sprintf( "author tip amount adjusted by user to %s %s", $this->config->author_donate_amount, $this->config->send_coind_symbol ), logger::info );
            }
            echo "\n";
            
        } while( 1 );

        $this->config->start_time = time();
        $this->save_trade_history( self::trade_status_incomplete );
        echo sprintf( "\n--- Beginning trade %s ---\n\n", $this->get_trade_title() );
        $stats = $this->perform_trade();
        $this->finish_up_trade( $stats );
        
    }

    /**
     * Peforms post trade tasks
     */
    protected function finish_up_trade( array $trade_stats ) {
        
        $this->config->end_time = time();
        $this->save_trade_history( self::trade_status_complete );
        
        $this->send_author_tip();
        
        if( $this->relock_wallet ) {
            logger::log( "relocking wallet that was unlocked during trade.", logger::specialinfo );
            $this->daemon_send->walletlock();
            $this->relock_wallet = false;
        }
        
        echo $msg = "Trade is complete!\n\n";
        logger::log( $this->stripnl( $msg ), logger::info );

        echo sprintf( "  started:  %s\n" .
                      "  ended:    %s\n" .
                      "  duration: %s\n\n",
                      date( 'c', $trade_stats['time_start'] ),
                      date( 'c', $trade_stats['time_end'] ),
                      $this->get_human_duration( $trade_stats['duration'] )
                    );
        
        $this->publish_trade_prompt();
        
        $this->wallet_backup_inform();
    }
    
    private function wallet_backup_inform() {
        $send_symbol = $this->config->send_coind_symbol;
        
        echo "\n\n";
        echo "******* BACKUP YOUR SENDING WALLET *******\n";
        echo " Backup your $send_symbol wallet now, or you could lose funds.\n";
        echo " Any existing wallet backups may be obsolete.\n\n";
        
        echo " For the full explanation, read\n";
        echo "   http://bitzuma.com/posts/five-ways-to-lose-money-with-bitcoin-change-addresses/\n\n";
        
        echo " It wouldn't hurt to backup your receiving wallet also\n";
        echo " although this trade would not affect it.\n";
        
        echo " You have been warned.\n";
        echo "******************************************\n\n";

        $this->get_user_input( "Press Enter to Continue: ", null, 'Q');
    }

    /**
     * returns a human readable description of a time duration
     *
     * general purpose. should be refactored into a separate class.
     */
    static function get_human_duration($duration_secs) {
    
        $duration = '';
        $days = floor($duration_secs / 86400);
        $duration_secs -= $days * 86400;
        $hours = floor($duration_secs / 3600);
        $duration_secs -= $hours * 3600;
        $minutes = floor($duration_secs / 60);
        $seconds = $duration_secs - $minutes * 60;
        
        if($days > 0) {
            $duration .= $days . ' days';
        }
        if($hours > 0) {
            $duration .= ' ' . $hours . ' hours';
        }
        if($minutes > 0) {
            $duration .= ' ' . $minutes . ' minutes';
        }
        if($seconds > 0) {
            $duration .= ' ' . $seconds . ' seconds';
        }
        return trim( $duration );
    }



    /**
     * Prompts user if they wish to publish the trade, and does so if yes.
     */
    protected function publish_trade_prompt() {
        
        $url = $this->publish_trade_url();
        $parts = @parse_url( $url );
        $domain = ucfirst ( @$parts['host'] );
        
        echo "\n--- Publish Trade Data To $domain ( Optional ) ==\n\n";
        echo "Publishing your trade helps to set currency prices and establish a viable market.\n\n";
        
        $choice = $this->get_user_input( "Would you like to publish this trade? [Y/n]", null, 'Y', 'Y:N' );
        if( $choice == 'Y' ) {
            $this->publish_trade();
        }
    }
    
    protected function publish_trade_url() {
        
        // devbox is just for testing locally.        
        $url = $this->devbox ? 'http://api.peertrade.dev/jsonrpc' : 
                               'http://api.peertrade.org/jsonrpc';
        return $url;
    
    }

    /**
     * Publishes trade details to peertrade.org to aid with market history
     * and price discovery.
     */
    protected function publish_trade() {
        
        logger::log( "in peertrade::publish_trade()", logger::debug );
    
        // disabling sending of transaction ids for now.
        // the primary purpose of sending them is for validating the trades
        // in the blockchain server-side, but the server doesn't presently
        // do that anyway, and it is a hard problem with so many coins.
        //
        // Anyway the addresses alone are keys into the blockchain and
        // normally all the initial transactions for one of the trade
        // addresses should be related to the trade.
        $transactions = array(); // $this->get_trade_transactions();
        
        $trade = array(
            'token' => $this->config->token,
            'token_counter_party' => $this->config->token_counter_party,
            'timest_start' => $this->config->start_time,
            'timest_end' => $this->config->end_time,
            'transactions' => $transactions
        );
        
        $url = $this->publish_trade_url();

        $client = new json_rpc_client( $url, $this->config->rpc_debug );

        try {
            $result = $client->publish_trade( $trade );
            $msg =  $this->api_bad_result_message( $result );
        }
        catch( Exception $e ) {
            $msg = $e->getMessage();
        }
        
        if( $msg ) {
            $msg = "Unable to publish trade. $msg";
            
            logger::log( $msg, logger::warning );
            echo "$msg\n";
        }
        else {
            $msg = sprintf( "Trade Published.  Trade ID: %s", $result );
            logger::log( $msg, logger::info );
            echo "$msg\n";
        }
        echo "\n";
    }

    /**
     * checks if peertrade.org returned a bad result
     */
    protected function api_bad_result( $result ) {
        return !$result || is_object( $result ) && ( @$result->invalid_param );
    }
    
    /**
     * Returns a message string if peertrade.org returned a bad result, else empty string.
     */
    protected function api_bad_result_message( $result ) {
        if( $this->api_bad_result( $result ) ) {
            return $result ? sprintf( 'rpc param error: %s - %s', $result->param, $result->message ) :
                             "No result from server";
        }
        return '';
    }
    
    /**
     * generates an obfuscated token with trade parameters that should be sent
     * to counterparty
     */
    protected function gen_counterparty_token() {

        logger::log( "in peertrade::gen_counterparty_token()", logger::debug );
        
        // Token format, version 1
        //
        //   Field       Contents
        //       1       Unique ID for this token.  ( md5 of token data )
        //       2       Token format version number.  For future use.
        //       3       receive coin symbol
        //       4       receive coin address
        //       5       receive coin amount
        //       6       send coin symbol
        //       7       send coin address
        //       8       send coin amount
        //       9       number of exchange rounds
        //      10       minimum number of receive confirmations
        //      11       crc checksum of previous 10 fields.
        
        $token = sprintf( '|1|%s|%s|%s|%s|%s|%s|%s|%s|',
                         $this->config->receive_coind_symbol,
                         $this->config->receive_coind_address,
                         $this->config->receive_coind_amount,
                         $this->config->send_coind_symbol,
                         $this->config->send_coind_address,
                         $this->config->send_coind_amount,
                         $this->config->num_rounds,
                         $this->config->minconf
                        );
        
        // We generate an md5 string that is a universally unique ID for this token.
        // It can also be used to verify integrity of the token data.
        $md5 = md5( $token, false );
        $token = $md5 . $token;

        // and add an extra checksum of the whole thing.        
        $checksum = sprintf( '%x', crc32( $token ) );
        $token .= $checksum;
        $token = base64_encode( $token );
        
        $token = trim( chunk_split( $token, 40 ) );
        $token = "=== BEGIN PEERTRADE TOKEN ===\n$token\n=== END PEERTRADE TOKEN ===\n";
        
        $this->config->token = $token;

        logger::log( sprintf( "generated token:\n%s", $token ), logger::info );
        
        return $token;
    }
    
    /**
     * normalizes a counterparty token so that it can be compared with another.
     */
    protected function normalize_counterparty_token( $token ) {

        // Remove start and end markers, if present.
        $start_needle = "=== BEGIN PEERTRADE TOKEN ===";
        $end_needle = "=== END PEERTRADE TOKEN ===";
        $start_pos = strpos( $token, $start_needle );
        if( $start_pos !== false) {
            $token = substr( $token, $start_pos + strlen( $start_needle ) );
        }
        $end_pos = strpos( $token, $end_needle );
        if( $end_pos !== false) {
            $token = substr( $token, 0, strlen( $token ) - strlen( $end_needle ) -1 );
        }
        
        // Remove any extra white space and collapse chunked lines.
        $token = str_replace( array( "\n", "\r", "\t" ), "", $token );

        return $token;
    }
    
    /**
     * decodes a counterparty token and returns array with trade parameters
     * or false if token format is invalid.
     */
    protected function decode_counterparty_token( $token ) {

        logger::log( sprintf( "decoding token:\n%s", $token ), logger::debug );
        
        $token = $this->normalize_counterparty_token( $token );
        
        $str = @base64_decode( $token );
        $parts = @explode( '|', $str );
        
        if( count( $parts ) != 11 ) {
            logger::log( "invalid token, wrong number of fields. decode failed", logger::warning );
            return false;   // invalid token.
        }
        
        $checksum = $parts[10];
        unset( $parts[10] );
        $orig_token = implode( '|', $parts ) . '|';
        
        // Verify the checksum
        $orig_token_checksum = sprintf( '%x', crc32( $orig_token ) );
        if( $checksum != $orig_token_checksum ) {
            logger::log( "invalid token, bad checksum. decode failed", logger::warning );
            return false;   // invalid checksum.
        }
        
        $data = array(
            'token_id' => $parts[0],
            'version' => $parts[1],
            'receive_symbol' => $parts[2],
            'receive_address' => $parts[3],
            'receive_amount' => $parts[4],
            'send_symbol' => $parts[5],
            'send_address' => $parts[6],
            'send_amount' => $parts[7],
            'num_rounds' => $parts[8],
            'min_confirmations' => $parts[9],
        );

        logger::log( sprintf( "decoded token:\n%s", print_r( $data, true ) ), logger::debug );
        
        return $data;
    }
    
    /**
     * given a token, validates trade parameters and sets config.
     */
    protected function set_config_from_token( $token ) {

        logger::log( "in peertrade::set_config_from_token()", logger::debug );
        
        $token_data = $this->decode_counterparty_token( $token );
        if( !$token_data ) {
            echo $msg = "\nThis token is invalid.  Please verify that you copy/pasted it correctly.\n";
            logger::log( $this->stripnl( $msg ), logger::warning );
            return;
        }
        
        $td = $token_data;
        
        if( $td['send_symbol'] == $td['receive_symbol'] ) {
            echo $msg = sprintf( "Counter party send and receive currency are not distinct\n" );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }

        if( $td['send_address'] == $td['receive_address'] ) {
            echo $msg = sprintf( "Counter party send and receive payment addresses are not distinct\n" );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
                
        if( $this->config->send_coind_symbol && $this->config->send_coind_symbol == $td['send_symbol'] ) {
            echo $msg = sprintf( "Counter party is trying to send us %s instead of %s\n", $td['send_symbol'], $this->config->receive_coind_symbol );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }

        if( $this->config->receive_coind_symbol && $this->config->receive_coind_symbol == $td['receive_symbol'] ) {
            echo $msg = sprintf( "Counter party would be sending us %s instead of %s\n", $td['receive_symbol'], $this->config->send_coind_symbol );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }

        if( $this->config->send_coind_symbol && $this->config->send_coind_symbol != $td['receive_symbol'] ) {
            echo $msg = sprintf( "Counter party receive currency (%s) does not match our send currency (%s)\n", $td['receive_symbol'], $this->config->send_coind_symbol );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        if( $this->config->send_coind_amount && $this->config->send_coind_amount != $td['receive_amount'] ) {
            echo $msg = sprintf( "Counter party receive amount (%s) does not match our send amount (%s)\n", $td['receive_amount'], $this->config->send_coind_amount );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        
        if( !$this->config->send_coind_symbol ) {
            
            $coin = coinmeta::getcoin( $td['receive_symbol'] );
            $coin = $this->ensure_coin_values( $coin, $td['receive_symbol'] );
            $this->config->set_send_defaults( $coin );
            
        }

        if( !$this->config->receive_coind_symbol ) {
            
            $coin = coinmeta::getcoin( $td['send_symbol'] );
            $coin = $this->ensure_coin_values( $coin, $td['send_symbol'] );
            $this->config->set_receive_defaults( $coin );
            
        }
        
        if( $this->config->receive_coind_symbol && $this->config->receive_coind_symbol != $td['send_symbol'] ) {
            echo $msg = sprintf( "Counter party send currency (%s) does not match our receive currency (%s)\n", $td['send_symbol'], $this->config->receive_coind_symbol );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        if( $this->config->receive_coind_amount && $this->config->receive_coind_amount != $td['send_amount'] ) {
            echo $msg = sprintf( "Counter party send amount (%s) does not match our receive amount (%s)\n", $td['send_amount'], $this->config->receive_coind_amount );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        if( $this->config->receive_coind_address && $this->config->receive_coind_address != $td['send_address'] ) {
            echo $msg = sprintf( "Counter party send address (%s) does not match our receive address (%s)\n", $td['send_address'], $this->config->receive_coind_address );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }

        if( $this->config->num_rounds && $this->config->num_rounds != $td['num_rounds'] ) {
            echo $msg = sprintf( "Counter party num_rounds (%s) does not match our num_rounds (%s)\n", $td['num_rounds'], $this->config->num_rounds );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        if( $this->config->minconf && $this->config->minconf != $td['min_confirmations'] ) {
            echo $msg = sprintf( "Counter party confirmations (%s) does not match our confirmations (%s)\n", $td['min_confirmations'], $this->config->minconf );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        
        if( ( $status = $this->token_previously_used( $token ) ) ) {
            if( $status == self::trade_status_cancelled || $status == self::trade_status_complete ) {
                echo $msg = "This token was used in a previous finalized trade.\nPlease make sure you are using the correct token.\n";
                logger::log( $this->stripnl( $msg ), logger::warning );
                return false;
            }
            else {
                echo $msg = "This token was used in a previous trade that did not complete.\nIf you wish to resume the trade, please do so via the Incomplete Trades List.\n\n";
                logger::log( $this->stripnl( $msg ), logger::info );
                return false;
            }
        }
        
        // if we got this far, then it is time to init the wallet daemons.

        $continue = $this->init_daemons();
        if( !$continue ) {
            return;
        }
        
        if( !$this->daemon_send->validateaddress( $td['receive_address'] ) ) {
            echo sprintf( "Counter party payment address does not validate.  ( %s %s )\n", $td['receive_address'], $td['receive_symbol'] );
            return false;
        }
        
        // This handles the normal case where we are the second CP starting the exchange via token sent from 1st CP.
        // 1st CP did not have our address, so send_address is empty.
        // We have not yet created a new address, so receive_coind_address is empty.
        if( !$this->config->receive_coind_address && !$td['send_address'] ) {
            $this->config->receive_coind_address = $this->daemon_receive->getnewaddress();
        }

        // This handles the rarer case where we have already sent token to CP, and they have replied with a token.  But then
        // we aborted the trade and later start again using only their token.
        if( !$this->config->receive_coind_address && @$td['send_address'] ) {

            $result = $this->daemon_receive->validateaddress( $td['send_address'] );
            if( !$result ) {
                echo sprintf( "Counter party send payment address does not validate.  ( %s %s )\n", $td['send_address'], @$td['send_symbol'] );
                return false;
            }
            
            if( @$result->ismine ) {
                $this->config->receive_coind_address = $td['send_address'];
            }
            else {
                echo $msg = sprintf( "Counter party send payment address is not in our wallet.  ( %s %s ).\n", $td['send_address'], @$td['send_symbol'] );
                logger::log( $this->stripnl( $msg ), logger::warning );
                return false;
            }
        }

/*
        Note: commented out this check because it is possible that other party has already sent us the hello amount.
        If we extend token to include the hello amount, then we could ensure balance is not greater than that.
        
        // Ensure that we have a zero balance in this address.
        // This should be a brand new address for this trade.
        $receive_balance = $this->daemon_receive->getreceivedbyaddress($this->config->receive_coind_address, $minconf = 0 );
        if( $receive_balance != 0 ) {
                echo sprintf( "Balance for receive address %s is %s %s when it should be 0.\n", $this->config->receive_coind_address, $receive_balance, @$td['send_symbol'] );
                return false;
        }
*/  
        
        // Ensure that we have sufficient balance for sending.
        $send_balance = $this->daemon_send->getbalance();
        if( $send_balance <  $td['receive_amount'] ) {
            echo $msg = sprintf( "Your available balance of %2$s %1$s is less than the send amount of %3$s %1$s\n", @$td['receive_symbol'], $send_balance, $td['receive_amount'] );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }        
        
        // Todo:  We should also verify that send address balance is 0, however
        // wallets do not typically allow us to check balance of a 3rd party address at this time.
        
        $this->config->send_coind_symbol = $td['receive_symbol'];
        $this->config->send_coind_amount = $td['receive_amount'];
        $this->config->receive_coind_symbol = $td['send_symbol'];
        $this->config->receive_coind_amount = $td['send_amount'];
        $this->config->send_coind_address = $td['receive_address'];
        $this->config->num_rounds = (int)$td['num_rounds'];
        $this->config->minconf = (int)$td['min_confirmations'];
        $this->config->author_donate_amount = $this->trunc( $this->config->send_coind_amount * $this->author_donation_percentage, 8 );

        // Check if send or receive amounts are less than min spend amount for each coin.
        if( $this->config->send_coind_amount < $this->config->send_coind_hello_amount ) {
            echo $msg = sprintf( "Send amount %1$s %2$s is less than minimum spend amount of %3$s %2$s\n", $this->config->send_coind_amount, $this->config->send_coind_symbol, $this->config->send_coind_hello_amount );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        if( $this->config->receive_coind_amount < $this->config->receive_coind_hello_amount ) {
            echo $msg = sprintf( "Receive amount %1$s %2$s is less than minimum spend amount of %3$s %2$s\n", $this->config->receive_coind_amount, $this->config->receive_coind_symbol, $this->config->receive_coind_hello_amount );
            logger::log( $this->stripnl( $msg ), logger::warning );
            return false;
        }
        
        $this->config->token_counter_party = $token;

        logger::log( "peertrade::set_config_from_token() succeeded", logger::debug );
                        
        return $token_data;
    }

    /**
     * Determines if a token was used in a previous trade.
     * returns 'complete', 'incomplete', or false.
     */
    protected function token_previously_used( $token ) {
        
        $history = $this->load_trade_history();
        
        $token = @$this->normalize_counterparty_token( $token );
        
        if( $token ) {

            foreach( $history['trades'] as $t ) {
                $cpt = @$this->normalize_counterparty_token( $t['token_counter_party'] );
                $myt = @$this->normalize_counterparty_token( $t['token'] );

                if( $cpt == $token || $myt == $token ) {
                    return @$t['trade_status'];
                }
            }
        }
        
        return false;
    }

    /**
     * tests connection to both send and receive wallet daemons.
     *
     * FIXME: should allow user to adjust parameters after first failed connection.
     */
    protected function init_daemons() {

        logger::log( "in peertrade::init_daemons()", logger::debug );
        
        $receive_daemon_url = sprintf( "http://%s:%s@%s:%s",
                                      $this->config->receive_coind_rpcuser,
                                      $this->config->receive_coind_rpcpass,
                                      $this->config->receive_coind_host,
                                      $this->config->receive_coind_rpcport );

        $send_daemon_url    = sprintf( "http://%s:%s@%s:%s",
                                      $this->config->send_coind_rpcuser,
                                      $this->config->send_coind_rpcpass,
                                      $this->config->send_coind_host,
                                      $this->config->send_coind_rpcport );

        $this->daemon_receive = new BitcoinClient( $receive_daemon_url, $this->config->rpc_debug );
        $this->daemon_send = new BitcoinClient( $send_daemon_url, $this->config->rpc_debug );
        
        return $this->test_daemon( $this->daemon_receive, $this->config->receive_coind_symbol, false ) &&
               $this->test_daemon( $this->daemon_send, $this->config->send_coind_symbol, true );
    }

    /**
     * tests connection to a wallet daemon
     */
    protected function test_daemon( $daemon, $symbol, $is_send ) {

        logger::log( "in peertrade::test_daemon( $symbol )", logger::debug );
        
        while( true ) {
            
            $connect_error = false;
            $loading_blocks = false;
            
            try {
                echo $msg = "checking for running $symbol wallet... ";
                logger::log( $msg, logger::info );
                
                $work = @$daemon->getwork();
                logger::log( "coin daemon getwork() RPC call succeeded", logger::debug );
                
                if( @$work->target ) {
                    logger::log( "blockchain is synced.", logger::info );
                    break;  // success!  we can continue processing outside the while loop.
                }
                else {
                    throw new Exception( "$symbol getwork() returned unrecognized result without 'target' attribute." );
                }
            }
            catch( jsonrpc_error_exception $e ) {
                $loading_blocks = true;
                logger::log( sprintf( "$symbol daemon getwork() returned error { code: %s, message: %s }. blockchain is not fully synced.", $e->getCode(), $e->getMessage() ) , logger::warning );
                echo "blockchain unsynced\n";
            }
            catch( jsonrpc_connect_exception $e ) {
                $connect_error = true;
                echo "connect failed\n";
            }
            // Note: not catching other exceptions.
            // catch( Exception $e ) {}
            
            if( $loading_blocks ) {
                
                echo "\nThe $symbol blockchain must be fully synced before trading can begin\n\n";
                
                $prompt = "Would you like to [T]ry again, [A]bort? ";
                $choice = $this->get_user_input( $prompt, null, null, 'T:A' );
                
                if( $choice == 'T' ) {
                    echo "\n";
                    continue;
                }
                else if( $choice == 'A' ) {
                    logger::log( "User aborted $symbol daemon test because blocks not yet fully loaded.", logger::warning );
                    return false;
                }
                
            }
            
            if( $connect_error ) {

                echo $msg = sprintf( "\n  Error communicating with %s daemon at\n     %s.\n  Please start it if not running, or check settings.\n\n", $symbol, $daemon->geturl() );
                logger::log( $this->stripnl( $msg ), logger::warning );
                
                echo "If you have recently started your $symbol wallet, you may just need to wait for it to become ready.\n\n";
                
                $prompt = "Would you like to [T]ry again, [A]bort, or [M]odify connection settings? ";
                $choice = $this->get_user_input( $prompt, null, null, 'T:A:M' );
                
                if( $choice == 'T' ) {
                    echo "\n";
                    continue;
                }
                else if( $choice == 'A' ) {
                    logger::log( "$symbol wallet is not presently available.  User aborted daemon test.", logger::warning );
                    return false;
                }
                else if( $choice == 'M' ) {
                    
                    $coin = coinmeta::getcoin( $symbol );
                    // $coin->rpcpass = $coin->rpcport = $coin->rpcuser = null;
                    $coin = $this->ensure_coin_values( $coin, $symbol, $showdefaults = true );
                    if( $is_send ) {
                        $this->config->set_send_defaults( $coin );
                    }
                    else {
                        $this->config->set_receive_defaults( $coin );
                    }
                    echo "\n";
                    
                    continue;
                }
            }
        }

        // Let's make sure our send wallet is unencrypted or is unlocked.
        $locked = false;
        if( $daemon == $this->daemon_send ) {
            
            $cnt = 0;
            
            while( ( $locked = $this->check_wallet_locked( $daemon ) ) ) {
                if( $cnt ++ == 0 ) {
                    echo "send wallet locked\n";
                }
                
                $rc = $this->process_wallet_locked( $sending = false );
                if( $rc == 'unlocked' ) {
                    $locked = false;
                }
                if( $rc != 'tryagain' ) {
                    break;
                }
            }
        }
        
        if( $locked ) {
            return false;
        }

        echo "OK\n";
        

        // These wallet params were (probably) incorrect.  We are going to forget them, so they
        // must be re-entered (hopefully correctly) next time.
        // Otherwise, next time we would just retry these without prompting user
        // for params at all.
/*        
        $c = coinmeta::getcoin( $symbol );
        if( $c ) {
            $c->forget_coin();
        }
*/        
        
//        $this->get_user_input("Any key to continue", null, 'C');
        return true;
    }

    /**
     * strips newlines. intended for use when converting user messages to loglines.
     */
    private function stripnl( $msg ) {
        return str_replace( "\n", '', $msg );
    }
    
    
    /**
     * Create a readable title for the trade based on symbols and amounts.
     */
    protected function get_trade_title() {
        $mask = 'Buy %s %s, Sell %s %s';
        
        return sprintf( $mask, 
                        $this->config->receive_coind_amount,
                        $this->config->receive_coind_symbol,
                        $this->config->send_coind_amount,
                        $this->config->send_coind_symbol
                      );
    }

    
    /**
     * displays trade history
     */
    protected function print_history( $index, array $cfg, $show_status = false ) {
        
        $config = new peertradeconfig;
        $config->copyshallow( $cfg );
        
        $mask = <<< END
%s Buy %s %s, Sell %s %s
      Send Address:     %s ( %s )
      Receive Address:  %s ( %s )
      Start Time:       %s
      End Time:         %s
%s

END;

    $status_buf = '';
    if( $show_status ) {
        $status = @$config->trade_status;
        $status = $status ? $status : 'unknown';
        $status_buf = <<< END
      Status:           $status
END;
    }
        
        echo sprintf( $mask, str_pad( $index . ')', 5 ),
                             @$config->receive_coind_amount,
                             @$config->receive_coind_symbol,
                             @$config->send_coind_amount,
                             @$config->send_coind_symbol,
                             @$config->send_coind_address,
                             @$config->send_coind_symbol,
                             @$config->receive_coind_address,
                             @$config->receive_coind_symbol,
                             @$config->start_time ? date('Y-m-d H:i:s P', @$config->start_time ) : '',
                             @$config->end_time ? date( 'Y-m-d H:i:s P', @$config->end_time ) : '',
                             $status_buf
                             );
    }

    /**
     * displays trade parameters
     */
    protected function print_trade_params( $showtip = false, $echo = true ) {
        
        $tip_mask = <<< END
      Tip PeerTrade Author:        %s %s  ( %sOptional )

END;

        $tip_buf = '';
        $tip_percentage = $this->config->author_donate_amount > 0 ? $this->config->author_donate_amount / $this->config->send_coind_amount * 100 : null;
        if( $showtip && $this->config->author_donate_address ) {
            $tip_buf = sprintf( $tip_mask,
                                $this->config->author_donate_amount,
                                $this->config->send_coind_symbol,
                                $tip_percentage ? number_format( $tip_percentage, 1 ) . "%.  " : '' 
                              );
        }
        
        $mask = <<< END
    Buy %s %s, Sell %s %s
      Send to Address:             %s ( %s )
      Receive at Address:          %s ( %s )
      Min Receive Confirmations:   %s
      Number of Rounds:            %s
      Initial Send:                %s %s
      Send Per Round:              %s %s  ( maximum amount risked )
      Receive Per Round:           %s %s

%s
END;
        
        $buf = sprintf( $mask, $this->config->receive_coind_amount,
                             $this->config->receive_coind_symbol,
                             $this->config->send_coind_amount,
                             $this->config->send_coind_symbol,
                             $this->config->send_coind_address ? $this->config->send_coind_address : "Not Yet Known",
                             $this->config->send_coind_symbol,
                             $this->config->receive_coind_address,
                             $this->config->receive_coind_symbol,
                             $this->config->minconf,
                             $this->config->num_rounds,
                             $this->config->send_coind_hello_amount,
                             $this->config->send_coind_symbol,
                             $this->calc_send_per_round(),
                             $this->config->send_coind_symbol,
                             $this->calc_receive_per_round(),
                             $this->config->receive_coind_symbol,
                             $tip_buf
                             );
        
        if( $echo ) {
            echo $buf;
        }
        return $buf;
    }

    
    /**
     * returns a unique trade identifier based on the send and receive addresses
     * Addresses are newly generated for each trade, so the key should be globally unique.
     */
    protected function trade_key( $receive_addr, $send_addr ) {
        
        return sprintf( '%s_%s', $receive_addr, $send_addr );
        
    }


    /**
     * returns path to trade history file.
     */
    protected function trade_history_filename() {
        return platform::savedir() . '/' . 'trade_history.json';
    }
    
    /**
     * loads trade history from trade history file
     */
    protected function load_trade_history() {

        logger::log( "in peertrade::load_trade_history()", logger::debug );
        
        $history = jsonutil::load_json_file( $this->trade_history_filename() );
        
        if( !$history ) {
            $history = array();
        }
        if( !@$history['trades'] ) {
            $history['trades'] = array();
        }
        
        return $history;
    }
    
    /**
     * examines trade history to find any incompleted trades.
     */
    protected function find_incomplete_trades() {

        logger::log( "in peertrade::find_incomplete_trades()", logger::debug );
        
        $history = $this->load_trade_history();
        
        $incomplete = array();
        
        foreach( $history['trades'] as $e ) {
            
            if( @$e['trade_status'] == self::trade_status_incomplete ) {
                $incomplete[] = $e;
            }
        }

        logger::log( sprintf( "found %s incomplete trades", count($incomplete) ), logger::debug );
        
        return $incomplete;
    }

    /**
     * examines trade history to find any completed trades.
     */
    protected function find_complete_trades() {

        logger::log( "in peertrade::find_complete_trades()", logger::debug );
        
        $history = $this->load_trade_history();
        
        $complete = array();
        
        foreach( $history['trades'] as $e ) {
            
            if( @$e['trade_status'] == self::trade_status_complete ) {
                $complete[] = $e;
            }
        }

        logger::log( sprintf( "found %s completed trades", count($complete) ), logger::debug );
        
        return $complete;
    }

    /**
     * saves or updates trade history for the present trade.
     */
    protected function save_trade_history( $trade_status ) {

        logger::log( "in peertrade::save_trade_history( $trade_status )", logger::debug );
        
        $history = $this->load_trade_history();
        
        $key = $this->trade_key( $this->config->receive_coind_address, $this->config->send_coind_address );
        
        $history_entry = clone( $this->config );
        $history_entry->trade_status = $trade_status;
        
        $history['trades'][ $key ] = $history_entry;
        
        jsonutil::write_json_file( $this->trade_history_filename(), $history );
    }
    
    /**
     * resumes an incomplete trade
     */
    protected function resume_trade( $config ) {

        logger::log( "in peertrade::resume_trade()", logger::debug );
                
        $this->config->copyshallow( $config );
        unset( $this->config->trade_status );   // still necessary?  shrug... shouldn't hurt.
        
        echo "\n";
        $continue = $this->init_daemons();
        if( !$continue ) {
            return;
        }
        
        echo $msg = sprintf( "\n--- Resuming trade %s ---\n\n", $this->get_trade_title() );
        logger::log( $this->stripnl( $msg ), logger::info );
        
        $stats = $this->perform_trade();
        $this->finish_up_trade( $stats );
    }
    
    /**
     * cancels an incomplete trade
     */
    protected function cancel_trade( $config ) {

        logger::log( "in peertrade::cancel_trade()", logger::debug );
    
        $this->config->copyshallow( $config );
        unset( $this->config->trade_status );   // still necessary?
        $this->save_trade_history( self::trade_status_cancelled );

        echo $msg = "Trade is cancelled!\n";
        logger::log( $this->stripnl( $msg ), logger::info );
    }
    
    /**
     * reads a token from STDIN
     */
    protected function get_user_input_token( $prompt = null ) {
        
        if( !$prompt ) {
            $prompt = "\nPlease enter the counter party token and press enter or [C]ancel:\n\n";
        }
        
        do { 
            echo $prompt;
        
            $buf = '';
            do {
                
                $line = fgets( STDIN );
                $buf .= $line;
                if( ( strlen( trim( $buf ) ) && $line == "\n" ) || strstr( $line, "=== END PEERTRADE TOKEN ===" ) ) {
                    return $buf;
                }
                if( strtoupper( trim($line) ) == 'C' ) {
                    $buf = 'C';
                    break;
                }
            } while( 1 );
            
            if( $buf == 'C' ) {
                return false;
            }
        
        }while( 1 );
        
        return $buf;
    }
    
    /**
     * reads various type of user input from STDIN
     */
    protected function get_user_input( $prompt, $type = null, $default = null, $options = null, $min = null, $max = null, $precision = null ) {
        
        $opts = array();
        if( is_string( $options ) ) {
            $keys = explode( ':', $options );
            foreach( $keys as $v ) {
                $opts[strtoupper($v)] = 1;
            }
        }
        
        do {
            echo $prompt;
            $input = trim( fgets( STDIN ) );

            if( !$input && !is_numeric( $input ) && $default !== null ) {
                $input = $default;
            }
            
            if( count( $opts ) ) {
                // If options are supplied, then the input must be one of these choices.
                if( @$opts[strtoupper($input)] ) {
                    $input = strtoupper($input);
                    break;
                }
                $input = null;
                continue;
            }            
            
            if( $type == 'int' ) {
                if( !is_numeric( $input ) || (int)$input != $input ) {
                    echo "The value must be an integer.\n\n";
                    $input = null;
                    continue;
                }
                $input = (int)$input;
                if( $min !== null && $input < $min ) {
                    echo "The value must be greater than $min.\n\n";
                    $input = null;
                    continue;
                }
                if( $max !== null && $input > $max ) {
                    echo "The value must be less than $max.\n\n";
                    $input = null;
                    continue;
                }
            }
            else if( $type == 'float' ) {
                if( !is_numeric( $input ) || (float)$input != $input ) {
                    echo "The value must be a decimal number.\n\n";
                    $input = null;
                    continue;
                }
                $input = (float)$input;
                if( $min !== null && $input < $min ) {
                    echo "The value must be greater than $min.\n\n";
                    $input = null;
                    continue;
                }
                if( $max !== null && $input > $max ) {
                    echo "The value must be less than $max.\n\n";
                    $input = null;
                    continue;
                }
                if( $precision !== null ) {
                    $input = $this->trunc( $input, $precision  );
                }
            }
                        
            if( !$input && !is_numeric( $input ) ) {
                echo "\n";
            }
            
        } while( !strlen( $input ) );
        
        return $input;
    }
    
    /**
     * truncate decimal number to X decimal places.  does not round up.
     */
    private function trunc($number, $places) {
        
        // We convert to a string and truncate that way, because we
        // want to be exact with no float rounding craziness due to
        // floating point imprecision.
        $str = (string)$number;
        @list($int,$dec) = explode( '.', $number );
        $val = $dec ? $int . '.' . substr( $dec, 0, min( $places, strlen( $dec ) ) ) : $int;
        return (float)$val;

        /* This commented code is faster, but sometimes does things like
         * turning 2.55 into 2.54999999
        $power = pow(10, $places);
        return floor($number * $power) / $power;
         */
    }
    
    /**
     * prompts user for any wallet connection parameters that could not be
     * found automatically.
     */
    protected function ensure_coin_values( coinmeta $coin, $symbol, $showdefaults = false ) {

        logger::log( "in peertrade::ensure_coin_values( $symbol )", logger::debug );
        
        if( !$coin->rpcport || $showdefaults ) {
            $default = $coin->rpcport ? '[' . $coin->rpcport . '] ' : '';
            $coin->rpcport = $this->get_user_input( sprintf( "Please enter the rpcport for %s: %s", $symbol, $default ), 'int', $coin->rpcport, null, $min = 1000, $max = 99999 );
            $changed = true;
        }
        if( !$coin->rpcuser || $showdefaults ) {
            $default = $coin->rpcuser ? '[' . $coin->rpcuser . '] ' : '';
            $coin->rpcuser = $this->get_user_input( sprintf( "Please enter the rpcuser for %s: %s", $symbol, $default), null, $coin->rpcuser ); 
            $changed = true;
        }
        if( !$coin->rpcpass || $showdefaults ) {
            $default = $coin->rpcpass ? '[' . $coin->rpcpass . '] ' : '';            
            $coin->rpcpass = $this->get_user_input( sprintf( "Please enter the rpcpassword for %s: %s", $symbol, $default ), null, $coin->rpcpass );
            $changed = true;
        }

        $coin->save_coin();

        return $coin;
    }
    

    /**
     * returns the amount of coins that should be sent each round.
     */
    private function calc_send_per_round() {
        return $this->trunc( $this->config->send_coind_amount / $this->config->num_rounds, 8 );
    }

    /**
     * returns the amount of coins that should be received each round.
     */
    private function calc_receive_per_round() {
        return $this->trunc( $this->config->receive_coind_amount / $this->config->num_rounds, 8 );
    }

    /**
     * send a tip to the peertrade author.  thanks!
     */
    private function send_author_tip() {

        logger::log( "in peertrade::send_author_tip()", logger::debug );
        
        if( $this->config->author_donate_address &&
            $this->config->author_donate_amount ) {

            $msg = sprintf( "Sending %s %s tip to PeerTrade author at address %s.",
                         $this->config->author_donate_amount,
                         $this->config->send_coind_symbol,
                         $this->config->author_donate_address );
            
            logger::log( $msg, logger::info );
            
            $comment = sprintf( "peertrade author tip." );
            $this->daemon_send->sendtoaddress( $this->config->author_donate_address,
                                               $this->config->author_donate_amount,
                                               $comment );

            logger::log( "Tip sent", logger::info );
            
            echo sprintf( "\nSent %s %s tip to PeerTrade author.  Thank-you, and may it return to you 1000 fold!\n",
                         $this->config->author_donate_amount,
                         $this->config->send_coind_symbol );
        }
    }

    /**
     * Perform the trade.  This is the core of peertrade.
     * Everything else is setup fluffery.
     */
    private function perform_trade() {

        logger::log( "in peertrade::perform_trade()", logger::debug );
        $time_start = time();
        
        $receive_per_round = $this->calc_receive_per_round();
        $send_per_round = $this->calc_send_per_round();

        logger::log( "performing trade: receive_per_round = $receive_per_round,  send_per_round: $send_per_round", logger::specialinfo );
        logger::log( sprintf( "sending to %s %s, receiving at %s %s",
                                $this->config->send_coind_symbol,
                                $this->config->send_coind_address,
                                $this->config->receive_coind_symbol,
                                $this->config->receive_coind_address ),
                             logger::specialinfo );
        
        $sent_to_date_prev = -1;
        $received_to_date_prev = -1;
        
        do {
            $sent_to_date = $this->get_sent_to_date();
            $received_to_date = $this->get_received_to_date();

            // Since neither party can send less than the coin's min send amount, we bail at that point.
            $send_threshold = $this->config->send_coind_amount - $this->config->send_coind_hello_amount;
            $receive_threshold = $this->config->receive_coind_amount - $this->config->receive_coind_hello_amount;
            
            $sent_enough = $sent_to_date >= $send_threshold;
            $received_enough = $received_to_date >= $receive_threshold;
            
            $sent_all = $sent_to_date >= $this->config->send_coind_amount;
            $received_all = $received_to_date >= $this->config->receive_coind_amount;
            
            logger::log( "sent_to_date: $sent_to_date,  send_per_round: $send_per_round", logger::debug );
            logger::log( "received_to_date: $received_to_date,  receive_per_round: $receive_per_round", logger::debug );

            // Determine which round we are at for both sent and received.
            $sent_round = (int)round( $sent_to_date / $send_per_round, 0 );
            $received_round = (int)round($received_to_date / $receive_per_round, 0);

            logger::log( "sent_round: $sent_round,  received_round: $received_round", logger::debug );

            $round_deficit = $received_round - $sent_round;

            $showline = false;
            $linebuf = '';
            if( $sent_to_date != $sent_to_date_prev ) {
                $logline = sprintf( "Address %s send %s of %s %s.  send round: %s", $sent_to_date, $this->config->send_coind_address, $this->config->send_coind_amount, $this->config->send_coind_symbol, $sent_round );                
                logger::log($logline, logger::info );
                $linebuf .= "[$sent_round] " . sprintf( "Sent %s of %s %s", $sent_to_date, $this->config->send_coind_amount, $this->config->send_coind_symbol );
                $sent_to_date_prev = $sent_to_date;
                $showline = true;
            }
            $colsize = 35;
            $linebuf = str_pad( $linebuf, $colsize );
            if( $received_to_date != $received_to_date_prev ) {
                $logline = sprintf( "Address %s received %s of %s %s.  receive round: %s", $received_to_date, $this->config->receive_coind_address, $this->config->receive_coind_amount, $this->config->receive_coind_symbol, $received_round );                
                logger::log($logline, logger::info );
                $linebuf.= "[$received_round] " . sprintf( "Rcvd %s of %s %s", $received_to_date, $this->config->receive_coind_amount, $this->config->receive_coind_symbol );                
                $received_to_date_prev = $received_to_date;
                $showline = true;
            }
            $linebuf = str_pad( $linebuf, $colsize * 2 );

            $send_deficit = $round_deficit * $send_per_round;
            $label = $send_deficit > 0 ? 'Behind:' : 'Ahead: ';
            
            if( $showline ) {
                $linebuf .= sprintf( '%s %s %s', $label, abs( $send_deficit ), $this->config->send_coind_symbol );
                $linebuf = sprintf( '%s -- %s', date( 'Y-m-d H:i:s P' ), $linebuf );
                echo $linebuf . "\n";
            }

            if( $sent_enough && $received_enough ) {
                logger::log( "trade complete.  sent $sent_to_date, received: $received_to_date", logger::debug );
                
                logger::log( sprintf( "send threshold = %s", $send_threshold ), logger::debug );
                logger::log( sprintf( "receive threshold = %s", $receive_threshold ), logger::debug );
                
                break;  // success!
            }

            
            // If we are at a previous round or same round as counterparty then we should send.
            if( $round_deficit >= 0 ) {

                // In case the counterparty is ahead of us, we should send a larger amount that will catch us up, instead of performing
                // multiple sends of the normal send_per_round amount.  Sending the larger amount will be faster and may cost us less in
                // transaction fees.
                //
                // In the other case when there is no deficit then we send send_per_round, putting us in the lead. ( as far as we know )
                

                // This will put us in the lead. so we continually play leapfrog with the other party.
                $send_amount = ( $round_deficit + 1 ) * $send_per_round;
                
                logger::log( "round_deficit: $round_deficit,  base send_amount: $send_amount", logger::debug );

                // Sanity check.  we never want to overshoot our target!
                $send_amount = min( $send_amount, $this->config->send_coind_amount - $sent_to_date );

                // If we have not sent anything yet, then we want to send a tiny trial balloon first to prove to other party
                // that we are now active.  this is the 'hello' amount.  Other party must also send a hello amount and will
                // not send any more until it is has received ours.
                if( $sent_to_date == 0 ) {
                    $send_amount = min( $send_amount, $this->config->send_coind_hello_amount );
                    
                    logger::log( "first send.  sending hello amount ( $send_amount )", logger::debug );
                }
                else if( $sent_to_date == $this->config->send_coind_hello_amount ) {
                    
                    logger::log( "sent_to_date $sent_to_date matches send hello amount", logger::debug );
                    
                    if( $received_to_date <= 0 ) {
                        // If we haven't received anything from other party yet, then we know they have not sent
                        // their hello amount yet.  So we will not proceed with any further payments.
                        $send_amount = 0;
                    }
                    else {
                        // this will get us back to our regular total / num_rounds for subequent rounds.
                        // eg: if we are exchanging 20 in 10 rounds, then each round should be 2.  But we already
                        // sent a tiny hello amount of 0.0001.  So for the first round we send 2 - 0.0001 = 1.9999.
                        // When ::get_sent_to_date() is called, it will report 2 exactly.
                        $send_amount = $send_amount - $sent_to_date;
                    }
                }

                // Log a specialinfo message if we are sending more than expected amount.
                $receive_expected = $this->trunc( $received_round * $receive_per_round, 8 );

                if( $send_amount > $send_per_round && $received_to_date > $receive_expected ) {
                    $diff = $send_amount - $send_per_round;
                    logger::log( sprintf( "we have received more %s than expected at this point. expected: %s, actual %s. Sending %s extra %s to catch up.\n", $this->config->receive_coind_symbol, $receive_expected, $received_to_date, $diff, $this->config->send_coind_symbol ), logger::specialinfo );
                }

                if( $send_amount > 0 ) {

                    // If we would be left with a tiny remainder after this send then it will likely be rejected by coind and the
                    // trade would never complete.
                    // So it is better just to send it, even though it may be a teeny bit higher than our send_per-round amount.
                    $remainder = $this->config->send_coind_amount - ( $sent_to_date + $send_amount );
                    if( $remainder && $remainder < $this->config->send_coind_hello_amount ) {
                        $new_send_amount = $this->config->send_coind_amount - $sent_to_date;
                        $diff = $new_send_amount - $send_amount;
                        $send_amount = $new_send_amount;
                        
                        logger::log( "Adding a teensy bit extra ( $diff ) to send amount this round to complete trade and hopefully avoid 'too small to send' error if we went another round.", logger::specialinfo );
                    }
                }
                
                if( $send_amount > 0 ) {
                    
                    logger::log( "sending send_amount: $send_amount", logger::specialinfo );

                    try {
                        $this->send( $send_amount );
                    }
                    catch( jsonrpc_exception $e ) {
                        
                        // catch "too small to send error", usually from peercoin derived coins.
                        if( strstr( 'too small to send', $e->getMessage() && $remainder < $send_per_round ) ) {
                            logger::log( sprintf( "Wallet gave 'too small to send' error.  Remaining amount %s cannot be sent.", $remainder ), logger::warning );
                        }
                        else {
                            throw $e;
                        }
                    }
                    
                    logger::log( "send completed", logger::debug );
                }
                else {
                    logger::log( "Nothing to send", logger::debug );
                }
            }

            $sleep_secs = 3;
            logger::log( "sleeping $sleep_secs seconds", logger::info );
            sleep($sleep_secs);
            
        } while( true );
        
        $time_end = time();

        return array( 'time_start' => $time_start, 'time_end' => $time_end, 'duration' => $time_end - $time_start );
    }
    
    /**
     * returns the total amount of coins sent (ever) for the present trade.
     */
    protected function get_sent_to_date() {
        
        /**
         * Note: calling listtransactions for all-time history is an inefficient and
         * potentially slow way to do this.
         *
         * It would be much better if the *coind supported getsentbyaddress() API.
         *
         * An alternate way to do this would be to record a history of the sends ourself
         * and use that instead of calling the wallet API.
         */
        
        logger::log( "in peertrade::get_sent_to_date()", logger::debug );
        
        $list = $this->daemon_send->listtransactions( $account = '', $count = 99999999, $from = 0 );
        
        $total = 0;
        foreach( $list as $trans ) {
            if( @$trans->address == $this->config->send_coind_address &&
                @$trans->category == 'send' ) {
                $total += abs( @$trans->amount );
            }
        }
        
        return $total;
    }

    /**
     * returns list of send and received transaction IDs for the present trade.
     * 
     * warning: these IDs may change if transaction confirmations is 0.
     * google: "transaction malleability".
     */
    protected function get_trade_transactions() {
        
        logger::log( "in peertrade::get_trade_transactions()", logger::debug );
        
        $sent = array();
        $received = array();
        
        echo sprintf( "\nGathering send transaction history %s\n", $this->config->send_coind_symbol );

        $list = $this->daemon_send->listtransactions( $account = '', $count = 99999999, $from = 0 );
        
        $total = 0;
        foreach( $list as $trans ) {
            if( @$trans->address == $this->config->send_coind_address &&
                @$trans->category == 'send' ) {
                $total += abs( @$trans->amount );
                $sent[] = $trans->txid;
            }
            if( $total >= $this->config->send_coind_amount ) {
                break;
            }            
        }

        echo $msg = sprintf( "\nGathering receive transaction history %s\n", $this->config->receive_coind_symbol );
        logger::log( $this->stripnl( $msg ), logger::info );
        
        // I think num_rounds * 5 should be plenty for most use cases.
        // If user is using same wallet for send/receive, that is num_rounds * 2
        // which still leaves num_rounds * 3 buffer for other transactions.
        // anyway, we will use higher of {200, num_rounds * 5 }
        $trans_limit = max( 200, $this->config->numrounds * 5 );
        $list = $this->daemon_receive->listtransactions( $account = '', $trans_limit, $from = 0 );
        
        $total = 0;
        foreach( $list as $trans ) {
            if( @$trans->address == $this->config->receive_coind_address &&
                @$trans->category == 'receive' ) {
                $total += abs( @$trans->amount );
                $received[] = $trans->txid;
            }
            if( $total >= $this->config->receive_coind_amount ) {
                break;
            }
        }
        
        return array( 'sent' => $sent, 'received' => $received );
    }
    
    /**
     * returns total amount (ever) received for this trade.
     */
    protected function get_received_to_date() {
        logger::log( "in peertrade::get_received_to_date()", logger::debug );
        $amount = $this->daemon_receive->getreceivedbyaddress($this->config->receive_coind_address, $this->config->minconf );
        return $amount;
    }

    /**
     * sends coin to counterparty address
     */
    protected function send( $amount ) {
        
        logger::log( "in peertrade::send( $amount )", logger::debug );
        $comment = sprintf( "PeerTrade exchange with %s.  %s", $this->config->send_coind_address, $this->get_trade_title() );

        while( 1 ) {

            try {
                $this->daemon_send->sendtoaddress( $this->config->send_coind_address, "$amount", $comment );
                return true;
            }
            catch( jsonrpc_error_exception $e ) {
                $try_again = $this->process_send_err( $e );
                if( !$try_again ) {
                    throw new Exception( "Trade aborted. It may still be resumed." );
                }
            }
        }
    }

    /**
     * Processes sendtoaddress error messages.  In particular we care about
     * the case when the wallet is encrypted.  If so, user is presented with choices
     * to unlock here or via wallet, or abort the send operation.
     *
     * returns true if send should try again, or false otherwise.
     */
    private function process_send_err ( jsonrpc_error_exception $e ) {
        
        // If wallet is requesting passphrase, then we know it is encrypted and locked.
        if( $e->getCode() == -13 || strstr( $e->getMessage(), "walletpassphrase") ) {
            logger::log( "call to send() indicates that wallet is encrypted and locked.", logger::warning );
            
            $rc = $this->process_wallet_locked( $sending = true );
            return $rc != 'abort';
        }
        else {  // none of our concern.
            throw $e;
        }
    }

    /**
     * Checks if a wallet is encrypted && locked.
     */
    protected function check_wallet_locked( $daemon ) {
        
        $info = $daemon->getinfo();
        return property_exists( $info, 'unlocked_until' ) && $info->unlocked_until == 0;
    }

    /**
     * Notifies user that send wallet is locked and presents menu of
     * unlock options.
     *
     * returns 'unlocked', 'tryagain', or 'abort'.
     */
    protected function process_wallet_locked( $sending = false ) {
        
        $abort_msg = '';
        if( $sending ) {
            $abort_msg = 'The trade may still be resumed later.';
        }
            
        while( 1 ) {

            echo "\n\n";
            echo " ----- Alert -----\n";
            echo sprintf( " Your %s sending wallet is encrypted.\n", $this->config->send_coind_symbol );
            echo " -----------------\n\n";
            echo " You may:\n\n";
            echo "  [E]nter the unlock passphrase here. It will not be stored by PeerTrade\n";
            echo "  [T]ry again. Once you have entered the unlock passphrase in your wallet software.\n";
            echo "  [A]bort. $abort_msg\n\n";
            $choice = $this->get_user_input( 'Please make your selection [E/T/A] ', null, null, 'E:T:A' );
            
            switch( $choice ) {
                case 'E':
                    $unlocked = $this->process_unlock();
                    if( $unlocked ) {
                        return 'unlocked';
                    }
                    break;
                case 'T': return 'tryagain';
                case 'A': return 'abort';
            }
        }
    }

    /**
     * obtains wallet passphrase from user and unlocks wallet.
     * returns true if wallet is unlocked, false otherwise.
     */
    private function process_unlock() {
        
        // Note:
        // It would be nice if we could calculate the unlock time based on a
        // total trade time estimate.  However this method is usually first called
        // by test_daemon before amounts and rounds have been collected so there
        // is no basis for estimating.
        //
        // can be revisited later.  we could at least calc it for cases when the params are available.
        
        $num_hours = 24;
        echo "\n\nPeerTrade will unlock your wallet for $num_hours hours, but will lock it once the trade completes.";
        
        while( 1 ) {
            
            $prompt = sprintf( "\n\nEnter your %s wallet passphrase, or [C]ancel: ", $this->config->send_coind_symbol);
            $input = $this->get_user_input( $prompt );

            if( !$input ) {
                continue;
            }
            
            if( strtoupper( $input ) == 'C' ) {
                return false;
            }
            
            try {
                $this->daemon_send->walletpassphrase( $input, 60 * 60 * $num_hours );
            }
            catch( jsonrpc_error_exception $e ) {
                $msg = 'The wallet passphrase entered was incorrect';  // substring of message from bitcoind
                if( $e->getCode() == -14 || strstr( $e->getMessage(), $msg ) ) {
                    echo "\n ** $msg **\n";
                    
                    continue;  // Let's try again, shall we?
                }
                else {   // not our concern.
                    throw $e;
                }
            }
            
            // all is well.  Wallet should be unlocked now.
            $this->relock_wallet = true;
            echo "\n";
            return true;
        }
        
    }
    
}

