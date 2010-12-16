<?php
/////////////////////////////////////////
// CONFIGURE DATABASE
///////////////////////////////////////
if($_SERVER['HTTP_HOST'] == 'localhost') {
  $db_host = 'localhost';
  $db_name = 'msn_tinker';
  $db_user = 'root';
  $db_pass = '';
  $db_table_prefix = '';  // prefix for tables in database (if applicable)
} else { // db login for chat-cam-web.com
  $db_host = '194.213.8.31';
  $db_name = 'netel';
  $db_user = 'netel';
  $db_pass = 'n6e7t5e5l';
  $db_table_prefix = 'zdebug_';
}

/******************************************************************************
 ***** NO NEED TO MODIFY BELOW THIS LINE **************************************
 *****************************************************************************/

// main bot class
require_once("lib/tinkerbot.php");

///////////////////////////////////////////////////
// instantiate the bot
$t = new Tinkerbot;
$t->debug = true;  // will be re-set to user config value in initMSN

echo '<pre>';
// connect to database & set config options
$t->dbConnect($db_host, $db_user, $db_pass, $db_name, $db_table_prefix);
echo '</pre>';

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Tinker: The Tinkerbot Launcher (c)</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="refresh" content="5" />
  </head>
  <body>
    <pre>
<?php

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
  $t->bl('Running Tinker 2.8',6);
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
  // check if we should time-out script
  $time_now = $t->microtime_float();
  $total_time = intval($time_now - $t->time_start);
  $t->bl("(running for $total_time seconds, restarting after $t->inuseTimeout seconds | Contact count: $t->contactCount | Invite Queue: $t->inviteQueue)",6);
  // if timed-out, process deleteQueue and restart script
  if($total_time > $t->inuseTimeout) {
    $t->timeoutDelete();
    $t->kil('Script timing out (and restarting)',0,1);
  }
  // only run periodically (to save on processing)
  if($t->rockIt % $t->rockEvery == 0) $t->rockDeleteInvite();
  $t->rockIt++;
}
?>
    </pre>
  </body>
</html>