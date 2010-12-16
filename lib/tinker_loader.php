<pre>
<?php
//////////////////////////////////////////////////
// THIS FILE IS MEANT TO BE LOADED IN AN IFRAME //
// USE THE TINKER.PHP FILE INSTEAD              //
//////////////////////////////////////////////////

/******************************************************************************
 ***** DO NOT MODIFY BELOW THIS LINE **************************************
 *****************************************************************************/

// db config
require_once("../database.php");

// main bot class
require_once("tinkerbot.php");

///////////////////////////////////////////////////
// instantiate the bot
$t = new Tinkerbot;
$t->debug = true;  // will be re-set to user config value in initMSN

echo '<pre>';
// connect to database & set config options
$t->dbConnect($db_host, $db_user, $db_pass, $db_name, $db_table_prefix);
echo '</pre>';

// don't start bot until logged in
$t->onLogin="botStart";

// initialize MSN session with random account
$t->initMSN();

// script should never reach this point (failsafe)
$t->killMSN();

// initialize bot and set in motion
function botStart() {
  global $t;
  $t->bl('botStart()',1);
  $t->bl('Running Tinker 3.0',6);
  // start timing script
  $t->time_start = $t->microtime_float();
  $t->bl('Starting event listeners... done.');
  // start event listeners
  $t->onChatJoin     = "botSendMessages";   // send messages once contact joins chat
  $t->onStatusChange = "botCheckOnline";    // monitors if a contact comes online
  $t->onRock         = "botRock";           // check rocker status on each loop
  $t->rockEvery      = 15;                  // how many loop revolutions before the rock function should run
  // set account in use
  $t->setAcctInUse(1);
  // invite all online friends to chat sessions
  while(count($t->friendsToSend)) {
    $cont = array_pop($t->friendsToSend);
    $t->startNewChat($cont);
  }
  // invite maximum amount of contacts
  $t->batchInvite('max');
  // now just wait for more contacts to sign on or accept invitation
}

// invite new contact to chat
function botInviteToChat($id, $members) {
  global $t;
  $cont = $t->chatSession[$id];
  $t->bl("Inviting $cont to chat session...");
  $t->InviteToChat($id, $cont);
}
// sends messages when contact joins chat
function botSendMessages($id, $who) {    // $who ("$name $mail") - The nick and the mail of the user.
  global $t;
  $cont = $t->chatSession[$id];
  $t->bl("$cont joined chat, sending messages...");
  // send messages
  foreach($t->messages as $rec) {
    $t->SendText($id, $rec['msg']);
    $t->saveChat($cont, $rec);
    $t->bl("Sent message $rec[order] to $cont");
    if($rec['wait']) {
      $t->bl("...waiting $rec[wait] seconds...");
      sleep($rec['wait']);
    }
  }
  // once all messages have been sent, delete contact
  $t->bl("All messages have been sent, queuing $cont for deletion...");
  $t->queueForDelete($cont);
}
function botCheckOnline($who, $avatar) {
  global $t;
  $t->bl("STATUS CHANGE: $who");
  list($mail, $nick, $status) = explode(' ', $who);
  // print contact status change
  $sendChatRequest = $t->checkStatus($mail, $status);
  // if contact is able to receive a chat request
  if($sendChatRequest) {
    // update contact status
    $t->updateContactStatus($mail);
    // invite them to a chat session
    $t->startNewChat($mail);
  }
}
// handles the deleteQueue
function botRock() {
  global $t;
  // TIMEOUT IS HANDLED VIA IFRAME IN THIS VERSION (this code is used with tinker_noframe.php)
//  // check if we should time-out script
//  $time_now = $t->microtime_float();
//  $total_time = intval($time_now - $t->time_start);
//  $t->bl("($total_time of $t->inuseTimeout seconds before restarting)",6);
//  // if timed-out, process deleteQueue and restart script
//  if($total_time > $t->inuseTimeout) {
//    $t->timeoutDelete();
//    $t->kil('Script timing out (and restarting)',0,1);
//  }
  // added by request of client - don't let script run
  // only allow script to go for two rock revolutions before restarting (regardless of 'timeout' setting)
  if($t->rockIt == $t->rockEvery*2) {
    $t->timeoutDelete();
    $t->kil('Script restarting...',0,1);
  }
  // only run periodically (to save on processing)
  elseif($t->rockIt % $t->rockEvery == 0) $t->rockDeleteInvite();
  $t->rockIt++;
}
?>
</pre>