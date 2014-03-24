PeerTrade - Decentralized P2P CryptoCoin exchange software.

Author's Note
=============

This software is something of an experiment, and is released in an early
text-mode prototype form, to determine if the altcoin community is interested in
this novel manner of trading. If there does seem to be real interest, feedback
and adoption ( and author tips! ) then further advances in the software,
documentation and associated website will be forthcoming.

Enjoy PeerTrade!


What is PeerTrade for?
======================

PeerTrade facilitates the exchange of cryptocurrencies directly between two
parties without any third party involvment. This means: no centralized exchange
and no escrow agent.

The problem we face when two parties wish to exchange digital coins of one
cryptocurrency for another is that neither party wants to go first because the
other party might simply run off with all the coins.

PeerTrade is software that aims to minimize and manage this risk, rather than
solve the problem altogether.

PeerTrade does not do any fancy cryptography and does not utilize any form of
escrow. It simply breaks an exchange up into many smaller exchanges so that the
risk is much smaller if the other party does not come through.

This way large amounts can be traded back and forth, but only small amounts will
be risked by either party.

A trade can be broken into any number of rounds, so the risked amount can be
made very small indeed. The tradeoff is that the more rounds of exchange, the
longer the trade will take.
             
PeerTrade is sort of like paying half up-front, half-on delivery to limit your
risk. But PeerTrade breaks the payment into much smaller chunks, and automates
the process for both parties.

So a trade of 100 ABCcoin for 10 XYZcoin can be split into 10 exchanges of
10 ABC and 1 XYZ.   Or 100 exchanges of 1 ABC and 0.1 XYZ.  Or 1000 exchanges
of 0.1 ABC and 0.001 XYZ.  You get the picture.

The number of rounds and the number of receive confirmations per round is
determined by the first party to initiate a trade, and is visible to both
parties before trade begins.

Still it must be emphasized that it is possible (and likely) to lose one round's
worth of funds should you deal with an untrustworthy person. As such, PeerTrade
users are encouraged to:

1. Trade only with individuals with established reputations.
2. Only enter into trades when you can absorb losing 1 round's worth of coins.

It should also be emphasized that the only communication that PeerTrade performs
between the two trading parties is sending and receiving coins via the
blockchains of the two crypocurrencies involved. All other communication is
performed out-of-band by the two parties.


Use at your own Risk
====================

The PeerTrade author makes no guarantees of correct operation and takes NO
responsibility for any losses you may incur for any reason while using the
software, for whatever reason.

You use the software at your own risk!


Features
========

 - Direct 2 party trades between cryptocoins.  ( Escrow not required )
 - Breaks a trade up into many smaller automated trades with much lower risk.
 - PeerTrade tokens encode trade parameters for sending to other party.
 - Trade any coin for any other. Not limited to primary markets.
 - Trade new or unpopular coins that are not listed on any exchange.
 - No account signup needed.
 - Can resume incomplete trades.
 - Completed trades may be (optionally) published to peertrade.org
 - Coin list with default wallet parameters auto refreshed from peertrade.org
 - Works with any cryptocoin wallet that supports the basic bitcoin RPC methods.
 - Detects encrypted wallets and assists with unlocking for send.


How to Trade
============

The PeerTrade model is two party peer to peer only. So you need to (somehow)
find someone that is willing to trade a given amount of coins at a given price.
Eg, I will sell you 20 XYZ coins for 50 ABC coins.

When both parties come to agreement then either party can initiate a trade
with PeerTrade. The software will generate a token that the first party gives to
the second, and then the second party must give a matching token back to the
first. These tokens contain payment amounts, coin addresses, number of rounds,
etc. Once the tokens have been swapped and verified, trading can begin.

If the underlying PeerTrade exchange mechanism catches on, it is expected that
over time the trading community will devise efficient platforms for announcing,
matching and accepting trade offers. For now, just starting out, it can be as
simple as starting a forum thread: "WTS 300 abcCoin for xyzCoin. Send offers".

Going forward, one can imagine exchange website(s) that accept limit orders and
performs order matching, but do not execute any orders. Instead, when two
orders are matched, the site notifies the two parties so that they can trade
between themselves.

Possibly something similar could be done using blockchain metadata protocols
such as counterparty and mastercoin.


Network Fees
============

Each cryptocoin has its own network fee policies. Sometimes payments can be sent
for free, but often a fee is automatically added to the payment by the wallet
software based on various criteria such as coin age, amount, and transaction
size. PeerTrade is not involved in the fee calculation and does not take it into
account at this time.

Given the potentially large number of transactions that can be made by
PeerTrade, the network fee could become significant. As such, PeerTrade users
would be well advised to perform some small test trades with each coin you will
be sending before embarking on any large trades with tens or hundreds of rounds.

In general, the larger each individual send is, the smaller the network fee will
be as a percentage of that send, and by extension of the total trade amount.


Number of Confirmations
=======================

The PeerTrade software acknowledges received funds for each round when they have
reached the confirmation target that was chosen by the person that initiated the
trade. The confirmation target will typically be a number between 0 and 6, with
0 being the fastest and 6 being the safest but slowest.

The estimated total time for a trade can be calculated as:

 ((num_rounds+1)/2 * num_confirmations * coin1_avg_confirm_time) +
 ((num_rounds+1)/2 * num_confirmations * coin2_avg_confirm_time)

Where coin1 and coin2 represent the two cryptocurrencies being traded.

Many variables affect how long it takes transactions to propogate and confirm
in the network.  In practice, the trades usually take considerably longer than
the formula would indicate.

So here are a few example scenarios, trading 2 coins each with
60 second average confirmation times:

**Ex 1: 10 rounds, 60 second avg confirms both coins, varying minconf targets**

```
minconf     secs    minutes     hours
0		    0	    0	        0
1		    720	    12	        0.20
2		    1440	24	        0.40
3		    2160	36	        0.60
4		    2880	48	        0.80
5		    3600	60	        1.00
6		    4320	72	        1.20
```

So we can see that a minconf=1 trade could take as litle as 12 minutes while a
minconf=6 trade would clock in over 72 minutes.

In reality even a minconf=0 trade of 10 rounds would typically take 60-120 secs.
For minconf > 0 cases, a reasonable estimate can often be arrived at by doubling
the calculated times.

**Ex 2: 10 rounds, 60 second avg confirm coin1, 10 minute avg confirm coin2, varying minconf targets. (ie: coin2 is Bitcoin)**

```      
minconf     secs    minutes	    hours
0           0       0           0.00
1           3960    66          1.10
2           7920    132         2.20
3           11880   198         3.30
4           15840   264         4.40
5           19800   330         5.50
6           23760   396         6.60
```

**Ex 3: varying rounds, 60 second avg confirm both coins,  minconf target: 1**

```
num_rounds	secs	minutes	    hours
5           420     7           0.12
10          720     12          0.20
20          1320    22          0.37
40          2520    42          0.70
80          4920    82          1.37
160         9720    162         2.70
320         19320   322         5.37
```

Examples can be found in spreadsheet format in doc/trade_time_estimates.ods

At present, the PeerTrade software requires that both parties use the same
min_confirmations settings.  The reasoning is that if Sally wants to do a quick
trade with 0 confirms with Bob, then Sally can initiate that trade and Bob can
either agree to it or not perform the trade. But Bob cannot change his
confirmation setting to 5 and thus waste a lot of Sally's time.

The PeerTrade author does not give any recommendations for "best" settings.
That is for indivuals to decide. With time and experimentation, the trading
community will likely determine best practices given the tradeoffs between
speed, confirmation safety and minimizing risk per round.


Unique Payment Addresses
========================

The peertrade software works by generating a unique payment address for each
party per trade. During the trade it constantly checks how much has been sent
from and received by this address.

Therefore if you send or receive other transactions while a trade is occurring
it could potentially affect the balance of your unique trade address and cause
the trade to terminate early or send too much. This is unlikely, but
theoretically possible in certain circumstances.

For this reason, it is recommended that you do not:

 1) Give out the unique trade address to anyone.
 2) Initiate any 'Send' payments during a trade.
 
 
Requirements
============

 - PHP 5.3 or greater, on Windows, Linux, or Mac.
 - Wallet software must be running locally for each coin that will be traded.
   eg: bitcoind, litecoin-qt, quarkcoind, primecoind, ronpaulcoind, etc, etc.
 - The blockchain for each traded coin must be fully downloaded.


Installation and Running
========================

1) Unzip download package to a local directory anywhere.

2) Ensure that the wallet software is running in RPC mode.  For example using
   bitcoin, run either:

```    
       bitcoind
```

OR

```    
       bitcoin-qt -server
       
       Or add server=1 to your bitcoin.conf file.
```       
The wallet software must be running for both coins that are being traded.
Eg if trading BTC and LTC, then both Bitcoin and Litecoin wallets must be
running.

Wallet software for each coin may be downloaded and installed from the
particular coin's website. Details are outside the scope of this README.

Ensure that the blockchain is fully downloaded for both coins that you will
be trading.
     
3) Run Command:

```
      php peertrade.php
```

Or on Linux just:
```   
      ./peertrade.php
```       
The software runs as an interactive text program with text menus.


Usage
=====

  To be completed.  For now, just follow the menu prompts!

  First time users are recommended to start with trading small amounts and 0
  confirmations in 5-10 rounds. That way you can get a feel for the overall
  process pretty quickly.

  First time users may wish to perform a trade with themselves to gain
  familiarity with the system. If you do this, it is highly recommended to use
  two separate operating system accounts ( eg 2 different unix users, windows
  users, mac users, etc) because otherwise both sides of the trade will share
  the same trade history file and see duplicate trades which can be confusing.

  It is okay to use a single coin daemon when performing a trade with yourself.
  Just keep in mind that you will see both the send and receive transactions when
  checking the transaction history.
  
  If anyone has time to create a nice tutorial with screenshots or youtube
  walkthough I will gladly link to it here.


Wallet Backups - Important!!!
=============================

Attention: You could lose your wallet funds.  Read this section with care.

PeerTrade generates many send transactions. The way that bitcoind/qt wallets are
implemented, this can quickly render your wallet backups obsolete. The backups
still work, but some or all of your funds may have been moved to new addresses
that are not in your older backup.  So when you restore the backup, you could
find an empty or partially emptied wallet.

Yes, this is a serious problem with bitcoind/qt and all the altcoin clones.  It
needs to be fixed.  But until it is, you had better be aware of it.

Best practice is to backup your wallet before and **especially** after each trade.

It is also a good idea to use a larger keypool size in xcoin.conf, eg:
```
 keypool=10000
```
Please read this article for all the details.  Seriously, read it.
http://bitzuma.com/posts/five-ways-to-lose-money-with-bitcoin-change-addresses/

PeerTrade reminds you to make wallet backups after a trade completes or
if any error occurs.


Using PeerTrade with Encrypted Wallets
======================================

If your wallet is encrypted then it must be unlocked before
performing a trade. You can do this within your wallet software or from within
PeerTrade when it detects an encrypted wallet during a send.

An advantage to unlocking the wallet within PeerTrade is that it will
automatically relock the wallet when the trade is finished.

Some cautious people may not wish to entrust their wallet passphrase to any
software but the wallet itself, and that is fine.  PeerTrade works either way.

When a wallet is unlocked it is for a limited time only. So it is possible that
the wallet may relock during a long exchange. PeerTrade will detect if this
happens and prompt you again for the password, or for you to unlock the wallet
manually.

When unlocking a wallet manually you should set the unlock time period long
enough for the type of trade you will be performing. If unsure, you can use
86400 seconds (1 day). If set too low you may walk away and the wallet will
relock during the trade, which stalls the trade for both parties until you
notice and unlock the wallet again.



Configuration Files
===================

The PeerTrade software uses the following configuration files:

**installdir/coin_defaults.json**

    Contains coin RPC parameters and defaults, for example the default
    rpcuser and rpcport.
    
    This file is installed with PeerTrade and will tend to become outdated
    quickly as new coins are created by the cryptocurrency community.
    
**userdir/coin_defaults.json**

    Contains the same data as coin_defaul

    This file will be auto refreshed from peertrade.org periodically and
    should not be manually edited.

                                 
**userdir/coin_user.json**

    Contains the same type of information as coin_defaults.json, however
    these values have been entered by the user via interaction with the
    PeerTrade software.
    
    This file will never be overwritten and may be manually edited if
    necessary.  Be careful not to create a JSON syntax error however.

**coindatadir/<coin>.conf**

    wallet config file. bitcoin.conf, litecoin.conf, etc.
    
    Contains settings used by the wallet software.  In particular
    PeerTrade will look for the rpcuser, rpcport and rpcpassword settings.
    
    On Linux, typically in ~/.bitcoin, ~/.litecoin, etc.
    Consult google for location on other platforms if you need to.

 
- *installdir*   = Directory that PeerTrade software is located.
- *userdir*      = PeerTrade user data directory.  ~/.peertrade on Linux.
- *coindatadir*  = Wallet user data directory for each coin.
 
#### How settings for each coin are found.

PeerTrade tries to make it as easy as possible to connect to your running
wallets.

PeerTrade loads coin_defaults.json initially. Those defaults are then overridden
by relevant settings in the wallet config file if available. And those are
overridden by settings in the coin_user.json file. If the settings are not found
in any of these, then the user is prompted when necessary.
 

Website
=======
 
   http://peertrade.org
   

Development
===========

Development is taking place on github.
Bug Reports, Patches and Proposals are welcome.

   http://github.org/peertrade/peertrade
   
   
Contact Author
==============

   peertrade -- @t -- live  -- d0t -- com

   If you don't receive a prompt response, try github or the website.


How You Can Help
================

Here's a short list of things you could do that would be very helpful to grow
the peertrade community.

 - Trade!  :-)
 - Report bugs and feature requests/ideas.
 - Write a tutorial on how to make trades with PeerTrade
 - Make a YouTube video tutorial
 - Create a PeerTrade Trading Forum for people to arrange trades.
 - Create an automated orderbook for efficiently matching PeerTrade orders.
 - Create a Wiki
 - Create some nice PeerTrade artwork.
 - Get the word out. Talk about PeerTrade on social media.

If any of those things fit your skillset and interest, go for it!


TODOS
=====
 
 [ ] Add mechanism for adding (suggesting?) new coins to PeerTrade.org website
     including coin name, default connection settings, min send amount, etc.
 [ ] Implement a reputation system based on number of successfully completed 
     trades and comments from trading partners.
 [ ] Develop a GUI Client
 [ ] More / better coin defaults. espec min_send (hello). Community can help.
 [X] Run strict. Code should throw exception on any E_NOTICE, E_WARNING, etc.
 [ ] Add mechanism/protocol for placing offers (limit orders) and receiving
     notifications of matches.  In theory this could facilitate trading.


Open Issues
===========
 - If this manner of trading ever becomes popular, it could create a
   significantly higher volume of transactions on the various blockchains. In
   theory a market-driven network fee model should regulate this effectively,
   but at the present time most cryptocoin transaction fees are not properly
   market driven.  Time will tell.
   
 - It is possible that network fees may prove cost prohibitive for this type of
   trading with many small transactions. That would be a pity because the
   present alternative (centralized exchanges) require complete trust in unknown
   persons, have been known to run off with customers' funds, and themselves
   charge fees that could be going instead to the blockchain miners.
   
 - An unscrupulous person could continuously enter into trades and quit the
   trade while ahead. At present, the other party's only recourse is to
   publicize the fraud. A public key based reputation system could be
   implemented that should discourage this type of behavior.
