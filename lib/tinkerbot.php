<?php

require_once('mzk.php');

class Tinkerbot extends MezzengerKlient {

  // config variables (set in database!)
  var $debugBot = true;                     // see config db table (show debug output for this bot)
  var $inuseTimeout = 3600;                 // time (in seconds) before automatically unlocking an account (should be initialized by bot)
  var $rockEvery = 15;                      // how often (# of loops) the rockDeleteInvite function should run
  var $dbtp = '';                           // database table prefix
  var $inviteBlock = 100;                   // amount at which to process the inviteQueue
  var $maxcontacts = 1000;                  // see config db table
  var $maxcontactsType = 'accepted';        // 'accepted' or 'invited' => how to calculate maxcontacts (# of contacts who accepted invitations or who received invitations)
  var $initializeContactCountFrom = 'db';   // where to initially calculate contact count: 'database' or 'msn' (msn might be inaccurate)
  var $deleteFromMsn = 0;                   // true=flag as deleted in db AND delete from msn, false=only flag as deleted in msn
  var $enableLogging = false;               // enable event logging
  var $logPath = '';                        // directory path to store log files (make sure it exists and is writable)
  var $inviteContactOnce = true;            // TRUE  => contacts will receive one invitation *only*, regardless of acceptance or account
                                            // FALSE => contact may continue to receive invitations until:
                                            //                            they accept an invitation -OR- all accounts have sent an invitation
                                            //          (note: if $reInviteContacts=false, contact will *never* receive more than one invitation from the same account)
  var $reInviteContacts = false;            // re-send invitation from same account if contact has *never* been online
                                            // reason: contact can reject invitation, but not block future invites (so beneficial to invite again from same account)
                                            //          (note: $reInviteContacts IS ONLY USED IF $inviteContactOnce = false)
  var $tinkerIframe = false;                // TRUE => tinker is operating within an iframe
  // variables for internal use (do not modify!)
  var $tinkerbot_version = '3.0';           // current version of Tinkerbot
  var $currentAccount;                      // email of currently logged in MSN account
  var $currentId;                           // id (in 'account' db table) of currently logged in MSN account
  var $blcount = 0;                         // tracks number of botlog messages have been outputed
  var $botName;                             // name of this instance (for reference in database)
  var $contactCount = 0;                    // running total of contacts for account
  var $nextChatId = 0;                      // unique id for chat sessions (gets incremented with init of each chat session)
  var $chatSession = array();               // keeps track of id/contact for each chat session
  var $messages = array();                  // messages to send to contacts (grabbed *once* when script starts to reduce queries - to change messages, restart script)
  var $messageCount = 0;                    // total number of messages per chat session
  var $friendsToSend = array();             // list of online friends which will receive chat messages
  var $rockIt = 0;                          // running counter of main loop
  var $deleteQueue = array();               // list of contacts queued for deletion
  var $inviteQueue = 0;                     // running count of how many invitations to send
  var $destruct = false;                    // (internal use) whether or not to run __destruct sequence on class destruct
  var $destructMSN = false;                 // (internal use) whether or not to kill MSN session on class destruct
  var $time_start;                          // time bot started running (used for manually timing-out script)
  // internal event logging (do not modify!)
  var $botLog;                              // internal html bot log file stream (do not modify)
  var $botLogTxt;                           // internal text bot log file stream (do not modify)
  var $startBotlog = false;                 // output starting log vars only after log file is configured
  // debugging options (for dev use only)
  var $disableNewInvitations = false;       // TRUE => disable sending out new invitations
  var $simulateNewInvitations = false;      // TRUE => process database queries, but do not send actual MSN invites
  var $disableChatting = false;             // TRUE => disable sending chat requests/messages to online contacts
  var $dieOnSqlErrors = false;              // TRUE => script will stop if a SQL query produces an error

  // make sure you connect to db *before* instantiating this class!!
  function __construct() {
    // make sure server meets bot requirements
    $err = '';
    if(!defined('SOCK_STREAM')) $err .= '<li>No socket support. You must enable socket support in PHP to run the MSN bot.</li>';
    if(!defined('CURL_VERSION_SSL') || curl_init()===false) $err .= '<li>No cURL support. You must enable cURL support (with SSL) in PHP to run the MSN bot.</li>';
    if($err != '') die("<strong style='color:#c00;font-size:20px;'>CANNOT RUN TINKERBOT!</strong><br><ul style='color:#c00'>$err</ul>");
    $this->botName = 'bot'.rand(100000000,999999999);
  }

  // mimik constructor/destructor func in PHP < 5
  function Tinkerbot() {
    if(version_compare(PHP_VERSION,"5.0.0","<")) {
      $this->__construct();
      register_shutdown_function(array($this,"__destruct"));
    }
  }

  // end MSN session
  function __destruct() {
    if(isset($this->destruct) && $this->destruct) {
      $this->bl('Tinkerbot is destructing! (Ahhh)',0,1);
      $this->killMSN();
    }
    if($this->enableLogging) {
      fwrite($this->botLog, "\r\n\r\n========================================================================================================\r\n\r\n\r\n</pre>");
      fwrite($this->botLogTxt, "\r\n\r\n========================================================================================================\r\n\r\n");
      fclose($this->botLog); // close log file (html)
      fclose($this->botLogTxt); // close log file (text)
    }
  }

  /**
   * Simple function to replicate PHP 5 behaviour
   */
  function microtime_float()
  {
      list($usec, $sec) = explode(" ", microtime());
      return ((float)$usec + (float)$sec);
  }

  // botlog - logs debug message and outputs it if in debug mode
  // 1=function, 2=sql, 3=kil, 4=mzk, 5=var, 6=note
  function bl($msg,$type=0,$bold=0) {
    if(!$this->debugBot && $type!=3) return; // only output if in debug mode but *always* output fatal errors (non-debug errors are non-descriptive)
    if($this->startBotlog) {
      $pre = array('<span style="font-weight:700;color:#060;">BOTLOG #(0)&gt; &lt;&gt&lt;&gt WELCOME TO TINKERBOT &lt;&gt&lt;&gt</span>',
                   '<span style="color:#060;">BOTLOG #(0)&gt; My name is '.$this->botName.' :)</span>',
                   '<span style="color:#666;">BOTLOG #(0)&gt; PHP Version: '.PHP_VERSION.'</span>',
                   '<span style="color:#666;">BOTLOG #(0)&gt; Tinkerbot Version: '.$this->tinkerbot_version.'</span>',
                   '<span style="color:#666">====================================================================================================</span>');
      $pretxt = array('BOTLOG #(0)> <><> WELCOME TO TINKERBOT <><>',
                      'BOTLOG #(0)> My name is '.$this->botName.' :)',
                      'BOTLOG #(0)> PHP Version: '.PHP_VERSION,
                      'BOTLOG #(0)> Tinkerbot Version: '.$this->tinkerbot_version,
                      '====================================================================================================');
      $this->logthis('<span style="color:#666">====================================================================================================</span>',0,-1,1);
      for($i=0;$i<count($pre);$i++)
        $this->logthis($pre[$i],0,-1,1);
      if($this->enableLogging) {
        fwrite($this->botLog,implode("\r\n",$pre)."\r\n");
        fwrite($this->botLogTxt,implode("\r\n",$pretxt)."\r\n");
      }
      $this->startBotlog = false;
    }
    $this->blcount++;
    $bold = $bold ? 'font-weight:700;' : '';
    switch($type) {
      case 1: $msg = "CALLED FUNC: $msg"; $color = '30f'; break;            // blue
      case 2: $msg = "SQL SUCCESS: $msg"; $color = 'f90'; break;            // orange
      case 3: $msg = "CRITICAL ERROR (kil): $msg"; $color = 'c00'; break;   // red
      case 4: $msg = "CALLING MZK: $msg"; $color = '60c'; break;            // purple
      case 5: $msg = "VAR: $msg"; $color = 'FF2FC0'; break;                 // pink
      case 6: $color = '666'; break;                                        // grey
      default: $color = '060';                                              // green (default)
    }
    if($this->enableLogging) fwrite($this->botLogTxt, "BOTLOG #($this->blcount)> $msg\r\n");
    $msg = htmlentities($msg);
    $msg = "<span style='color:#$color;$bold'>BOTLOG #($this->blcount)&gt; $msg</span>";
    if($this->enableLogging) fwrite($this->botLog, $msg."\r\n");
    $this->logthis($msg,0,-1,1);
  }

  // kill the script (on error)
  function kil($error, $killMSN=true, $die=true) {
    if(!$this->debug)
      $error = 'Error. Script stopped. (enable debugging in the config file to see more details about errors)';
    elseif($error == 'mysql') {
      $error = 'MySQL Error '.mysql_errno().': '.mysql_error();
      $die = $this->dieOnSqlErrors;
    }
    $this->bl($error,3,1);
//    if($killMSN) $this->killMSN();  <<< this is handled by __destruct
    if($die) die(); // destruct script
    else $this->bl('Attempting to continue after last SQL error...');
  }

  function killMSN() {
    $this->bl('killMSN()',1);
    $this->setAcctInUse(0);
    if($this->destructMSN) {
      $this->bl('quit()',4);
      $this->quit();
    }
  }

  // mysql query with error logging and debugging
  function botq($sql) {
    $result = mysql_query($sql) or $this->kil('mysql');
    $this->bl($sql,2);
    return $result;
  }

  // It can accept files or folders. Folders must end with a trailing slash!!
  // The function attempts to actually write a file, so it will correctly return true when a file/folder can be written to when the user has ACL write access to it.
  // (will work in despite of Windows ACLs bug)
  function is__writable($path) {
    if($path{strlen($path)-1}=='/') // recursively return a temporary file path
      return $this->is__writable($path.uniqid(mt_rand()).'.tmp');
    elseif(is_dir($path))
      return $this->is__writable($path.'/'.uniqid(mt_rand()).'.tmp');
    // check tmp file for read/write capabilities
    $rm = file_exists($path);
    $f = @fopen($path, 'a');
    if($f===false)
      return false;
    fclose($f);
    if(!$rm)
      unlink($path);
    return true;
  }

  // connect to database and sets config options
  function dbConnect($db_host, $db_user, $db_pass, $db_name, $db_table_prefix='') {
    $this->bl("dbConnect($db_host, $db_user, $db_pass, $db_name, $db_table_prefix)",1);
    // connect & select db (first try a persistent connection)
    $con = @mysql_pconnect($db_host, $db_user, $db_pass);
    if($con === false)
      mysql_connect($db_host, $db_user, $db_pass) or $this->kil('mysql',0);
    mysql_select_db($db_name) or $this->kil('mysql',0);
    $this->dbtp = $db_table_prefix;
    // get config options (don't log this query)
    $sql = $this->botq("SELECT * FROM `{$this->dbtp}config` LIMIT 0,1");
    $rec = mysql_fetch_assoc($sql);
    // configure event logging -- BEFORE CALLING bl
    if($rec['enable_logging']==1) {
      // add trailing slash if not already present
      $this->logPath = substr($rec['log_path'],-1)=='/' ? $rec['log_path'] : $rec['log_path'].'/';
      // create log file name (example filename: tikerbotlog_20091220154427.html = 20 Dec 09, 3:44:27 PM )
      $fn = '../'.$this->logPath.'tinkerbotlog_'.date('YmdHis').'.html';
      $fn2 = '../'.$this->logPath.'tinkerbotlog_'.date('YmdHis').'.txt';
      // attempt to create file (or open if exists)
      $this->botLog = @fopen($fn,'a');
      $this->botLogTxt = @fopen($fn2,'a');
      if($this->botLog !== false && $this->botLogTxt !== false) {
        $write = @fwrite($this->botLog, '<pre>TINKERBOT LOG FILE FOR BOT STARTED AT: '.date('r')."\r\n====================================================================================================\r\n\r\n");
        $write2 = @fwrite($this->botLogTxt, 'TINKERBOT LOG FILE FOR BOT STARTED AT: '.date('r')."\r\n====================================================================================================\r\n\r\n");
        if($write !== false && $write2 !== false)
          $this->enableLogging = true;
      }
      if($this->enableLogging)
        $this->bl("Event logging enabled. Log files: $fn (html) and $fn2 (text)",6);
      else
        $this->bl("Log path '$rec[log_path]' does not exist or is not writable. Fix this problem to enable event logging.",6);
    }
    // delay bl output until MSN init if in an iframe
    if(!$this->tinkerIframe) $this->startBotlog = true;
    // set config options
    $this->debug = $rec['debug']=='all' || $rec['debug']=='msn';
    $this->debugBot = $rec['debug']=='all';
    $this->inviteBlock = $rec['invite_block'];
    $this->maxcontacts = $rec['max_contacts'];
    $this->initializeContactCountFrom = $rec['initialize_contacts_from'] == 'msn' ? 'msn' : 'db';
    $this->maxcontactsType = $rec['maxcontacts_type'] == 'accepted' ? 'accepted' : 'invited';
    // the host might not let us modify this value
    set_time_limit($rec['timeout']);
//    // failsafe: try ini_set if set_time_limit ineffective
//    if(ini_get('max_execution_time') > 0 && ini_get('max_execution_time') <= 30) ini_set('max_execution_time', $rec['timeout']);
//    if(ini_get('max_execution_time') > 0 && ini_get('max_execution_time') <= 30) ini_set('max_execution_time', 3600);
    // get actual value from server to be sure we have correct execution time
    $refresh = ini_get('max_execution_time');
    $this->bl("ini_get('max_execution_time') = $refresh",5);
    // ini_get returns empty string on failure, indefinite (0) is set to 10hrs as failsafe (in case script stops)
    $refresh = $refresh==='' ? 300 : ($refresh===0 ? 36000 : $refresh);
    $this->bl("refresh = $refresh",5);
    $this->inuseTimeout = $refresh;
    if($rec['debug']=='all') error_reporting(E_ALL);
    // check if we should delete contacts from MSN
    $this->deleteFromMsn = $rec['delete_from_msn']==1 ? 1 : 0;
    // set invitation options
    $this->inviteContactOnce = $rec['invite_contact_once']==1 ? 1 : 0;
    $this->reInviteContacts = $rec['reinvite_contacts']==1 ? 1 : 0;;
    // from this point, run __destruct sequence
    $this->destruct = true;
  }

  // retrieve a *random* login from list of MSN accounts in db
  function getLogin($forcedUn='', $forcedPw='') {
    $this->bl('getLogin()',1);
    // first, unlock any in_use accounts that have timed out
    $unlock = $this->botq("UPDATE `{$this->dbtp}accounts` SET is_locked=0 WHERE now() > unlock_at");
    // check that there are available accounts in database (not in use)
    $sql2 = $this->botq("SELECT id FROM `{$this->dbtp}accounts` WHERE is_locked=0");
    if(!mysql_num_rows($sql2)) $this->kil('All MSN accounts in database are currently in use!');
    if($forcedUn!='' && $forcedPw!='') {
      $sql = $this->botq("SELECT id, `email`, `pass` FROM `{$this->dbtp}accounts` WHERE is_locked=0 AND `email`='$forcedUn' AND `pass`='$forcedPw' LIMIT 0,1");
      if(!mysql_num_rows($sql))
        $this->kil("Cannot login with $forcedUn (pw: $forcedPw). Either login is incorrect or account is in use.");
    }
    else {
      // get random account
      $sql = $this->botq("SELECT id, `email`, `pass` FROM `{$this->dbtp}accounts` WHERE is_locked=0 ORDER BY RAND() LIMIT 0,1");
      if(!mysql_num_rows($sql))
        $this->kil('All MSN accounts in database are currently in use. Nothing left to do. Goodbye.');
    }
    $rec = mysql_fetch_assoc($sql);
    // assign it as current account
    $this->currentAccount = $rec['email'];
    $this->currentId = $rec['id'];
    $this->bl('this->currentAccount: '.$this->currentAccount,5);
    $this->bl('this->currentId: '.$this->currentId,5);
    return array($rec['email'],$rec['pass']);
  } // end of getLogin

  // initialize MSN session
  function initMSN($forcedUn='', $forcedPw='') {
    // start bl output now, if not started at dbconnect
    if($this->tinkerIframe) $this->startBotlog = true;
    $this->bl('initMSN()',1);
    // retrieve messages from database
    $this->getMessages();
    // grab an account for login
    list($un,$pw) = $this->getLogin($forcedUn, $forcedPw);
    // init mzk class
    $this->bl("init('$un', '$pw')",4);
//    $this->init($un,$pw);
    $this->init($un,$pw);
    // now kill MSN session on class destruct
    $this->destructMSN = true;
    // login to account
    $this->bl('login()',4);
    $this->login();
    // enter infinite events loop (or until script times out)
    $this->bl('main()',4);
    $this->main();
  }

  // sets the account to in use and get online friends (first func that runs after login)
  function setAcctInUse($inUse) {
    $this->bl("setAcctInUse($inUse)",1);
    $mail = $this->currentAccount;
    // if in use, add current login to used accounts
    if($inUse) {
      $timeout = $this->inuseTimeout;
      // lock this account while in use
      $sql = $this->botq("UPDATE `{$this->dbtp}accounts` SET is_locked=1, unlock_at=DATE_ADD(NOW(),INTERVAL $timeout SECOND) WHERE email='$mail'");
      // start running count of contacts
      if($this->initializeContactCountFrom == 'db') {
        if($this->maxcontactsType == 'accepted') {
          $msndel = ($this->deleteFromMsn) ? 'AND is_deleted=0' : ''; // if we never delete from MSN account, this is not relevant
          $count = $this->botq("SELECT count(id) FROM {$this->dbtp}contacts WHERE friend_of='$this->currentAccount' $msndel");
          $this->contactCount = mysql_result($count,0);
        }
        elseif($this->maxcontactsType == 'invited') {
          $count = $this->botq("SELECT count(id) FROM {$this->dbtp}invites WHERE sent_from='$this->currentAccount'");
          $this->contactCount = mysql_result($count,0);
        }
        $this->bl('Contact count initialized from database.');
      }
      // initialize from MSN
      else {
        $this->contactCount = count($this->mycontacts);
        $this->bl('Contact count initialized from MSN.');
      }
      $this->bl("Initial contact count: $this->contactCount",5);
      // record login in database
      $contacts = implode(',', $this->mycontacts);
//      $contactsdb = mysql_real_escape_string($contacts);  //// DON'T SAVE THIS IN DATABASE
      $onlinefriends = implode(',', $this->onlinefriends);
      $onlinefriendsdb = mysql_real_escape_string($onlinefriends);
      $sql = $this->botq("INSERT INTO {$this->dbtp}logins(bot_name, `account`, onlinefriends, `timeout`)
                          VALUES('$this->botName', '$mail', '$onlinefriendsdb', $timeout)");
      // build online friend lists
      $this->getOnlineFriends();
      // output variables after login
      $this->bl('this->onlinefriends: ['.$onlinefriends.']',5,1);
      $this->bl('this->mycontacts: ['.$contacts.']',5,1);
    }
    // else clear the current account variables
    else {
      // update status in database
      $sql = $this->botq("UPDATE `{$this->dbtp}accounts` SET is_locked=0, unlock_at=null WHERE email='$mail'");
      $this->currentAccount = $this->currentId = null;
    }
  } // end of setAcctInUse

  // returns an array of all messages to send to contact
  function getMessages() {
    $this->bl('getMessages()',1);
    $sql = $this->botq("SELECT `message`, `order`, wait_after_sending FROM `{$this->dbtp}messages` ORDER BY `order`");
    if(!mysql_num_rows($sql)) $this->kil('No messages found in database to send to contacts.');
    $this->messageCount = mysql_num_rows($sql);
    while($rec = mysql_fetch_assoc($sql))
      $this->messages[] = array('msg' => $rec['message'], 'wait' => $rec['wait_after_sending'], 'order' => $rec['order']);
  }

  // set the friendsToSend array and update contact statuses in database
  function getOnlineFriends() {
    $this->bl('getMessages()',1);
    $sqlFriends="'nothing'";
    foreach($this->onlinefriends as $c) {
      $tmp = explode(' ',$c);
      $sqlFriends.=",'$tmp[0]'";
    }
    if($sqlFriends == "'nothing'") {
      $this->bl('No friends found online.'); return; }
    else {
      // update contact status
      $updateContacts = $this->botq("UPDATE `{$this->dbtp}contacts` SET friend_of='$this->currentAccount', is_invited=1,
                                     has_been_online=1, last_online_at=now() WHERE `email` IN ($sqlFriends)");
      // check which contacts have already received messages
      $sql = $this->botq("SELECT email, is_chatted, is_deleted FROM `{$this->dbtp}contacts` WHERE `email` IN ($sqlFriends)");
      while($rec = mysql_fetch_array($sql)) {
        if($rec['is_chatted'] || $rec['is_deleted']) {
          $toDeleteSql[] = "'$rec[email]'";
          if($this->deleteFromMsn) $this->delContact($rec['email']);
          $this->inviteQueue++;
          $this->contactCount--;
        }
        else
          $this->friendsToSend[] = $rec['email'];
      }
      // update status of any lingering contacts that should have been deleted
      if(isset($toDeleteSql)) {
        $toDeleteSql = implode(',', $toDeleteSql);
        $sql = $this->botq("UPDATE `{$this->dbtp}contacts` SET is_chatted=1, is_deleted=1 WHERE `email` IN ($toDeleteSql)");
      }
      $this->bl('friendsToSend: '.implode(',',$this->friendsToSend),5,1);
    }
  } // end of getOnlineFriends

  function updateContactStatus($contact) {
    $this->bl("updateContactStatus($contact)",1);
    $sql = $this->botq("UPDATE `{$this->dbtp}contacts` SET friend_of='$this->currentAccount', is_invited=1,
                        has_been_online=1, last_online_at=now() WHERE `email`='$contact'");
  }

  // initialize a chat with a contact, $callback=function to call once chat is loaded
  function startNewChat($contact, $callback='botInviteToChat') {
    $this->bl("startNewChat($contact, $callback)",1);
    if($this->disableChatting) {
      $this->bl('Chatting disabled. Not requesting chat session or sending messages.',0,1);
      return;
    }
    // don't re-send messages to contacts which have been chatted with
    if($this->contactReceivedChat($contact)) return;
    // check if a chat session has been initiated for this contact
    // (if they changed status from NLN to BSY, for example)
    if(in_array($contact, $this->chatSession)) {
      $chatSessionId = array_keys($this->chatSession, $contact);
      $this->bl("Chat session with $contact already started (id: $chatSessionId[0]), still waiting for $contact to accept chat request.",0,1);
      return;
    }
//    $id = $this->sbsindx;
    $this->nextChatId++;
    $id = $this->nextChatId;
    $this->chatSession[$id] = $contact;
    $this->bl("No chat session pending for $contact, sending new chat request.");
    $this->bl("Starting chat session $id for $contact...",0,1);
    $this->onChatLoad_[$id] = $callback;
    $this->NewChat(); // must set onChatLoad first (generated chat session id is same as $id)
  }

  // check if contact already received messages
  function contactReceivedChat($contact) {
    $sql = $this->botq("SELECT email, is_chatted, is_deleted FROM `{$this->dbtp}contacts` WHERE `email`='$contact' LIMIT 1");
    $rec = mysql_fetch_array($sql);
    if($rec['is_chatted']==1 || $rec['is_deleted']==1) {
      // update status: already chatted, should have been deleted (may have timed out after chat and before rockDeleteInvite ran)
      if($rec['is_deleted']==1) {
        $sql = $this->botq("UPDATE `{$this->dbtp}contacts` SET is_chatted=1, is_deleted=1 WHERE `email`='$contact'");
        // if we are not deleting our contacts from MSN, once we're at max we always remain there
        if($this->deleteFromMsn) {
          $this->delContact($rec['email']);
          $this->inviteQueue++;
          $this->contactCount--;
        }
      }
      $this->bl("$contact already received chat, not re-sending messages.",0,1);
      $receivedchat = true;
    }
    else
      $receivedchat = false;
    return $receivedchat;
  }

  // $message MUST be $messages array element
  function saveChat($contact, $message) {
    $this->bl('saveChat([array])',1);
    $message_total = count($this->messages);
    $msg = mysql_real_escape_string($message['msg']);
    $this->botq("INSERT INTO {$this->dbtp}chats(sent_from, sent_to, message, wait_after_sending, message_order_number, message_total)
                 VALUES('$this->currentAccount', '$contact', '$msg', $message[wait], $message[order], $message_total)");
    if($message['order'] == $message_total)
      $this->botq("UPDATE `{$this->dbtp}contacts` SET is_chatted=1 WHERE `email`='$contact'");
  }

  // queues a contact for deletion from MSN
  function queueForDelete($contact) {
    $this->bl("queueForDelete($contact)",1);
    // delay delete to give time to finish up any msn processing
    $this->deleteQueue[$this->rockIt+$this->rockEvery][] = $contact;
  }

  // delete any contact in the delete queue (who's time has come) & invites new ones
  function rockDeleteInvite() {
    // only run periodically (to save on processing)
    if($this->rockIt % $this->rockEvery) return;
    $this->bl('rockDeleteInvite()',1);
    // delete queued contacts
    for($i=0;$i<$this->rockEvery;$i++) {
      if(isset($this->deleteQueue[$this->rockIt-$i])) {
        foreach($this->deleteQueue[$this->rockIt-$i] as $cont) {
          $toDeleteSql[] = "'$cont'";
          // if we are not deleting our contacts from MSN, once we're at max we always remain there
          if($this->deleteFromMsn) {
            $this->delContact($cont);
            $this->inviteQueue++;
            $this->contactCount--;
          }
        }
      }
    }
    // update deleted status in database
    if(isset($toDeleteSql)) {
      $toDeleteSql = implode(',', $toDeleteSql);
      $sql = $this->botq("UPDATE `{$this->dbtp}contacts` SET is_chatted=1, is_deleted=1 WHERE `email` IN ($toDeleteSql)");
    }
    // process new invitations if inviteQueue is large enough
    // this will (obviously) never be true if deleteFromMsn=false (cause no contacts ever actually get removed)
    if($this->inviteQueue >= $this->inviteBlock) {
      $this->batchInvite($this->inviteQueue);
      $this->inviteQueue = 0;
    }
  } // end of rockDeleteInvite

  // deletes ALL contacts in delete queue before restarting script
  function timeoutDelete() {
    $this->bl('timeoutDelete()',1);
    // delete queued contacts
    for($i=0;$i<$this->rockEvery;$i++) {
      if(isset($this->deleteQueue[$this->rockIt-$i])) {
        foreach($this->deleteQueue[$this->rockIt-$i] as $cont) {
          $toDeleteSql[] = "'$cont'";
          // if we are not deleting our contacts from MSN, once we're at max we always remain there
          if($this->deleteFromMsn) {
            $this->delContact($cont);
            $this->inviteQueue++;
            $this->contactCount--;
          }
        }
      }
    }
    // update deleted status in database
    if(isset($toDeleteSql)) {
      $toDeleteSql = implode(',', $toDeleteSql);
      $sql = $this->botq("UPDATE `{$this->dbtp}contacts` SET is_chatted=1, is_deleted=1 WHERE `email` IN ($toDeleteSql)");
    }
  } // end of timeoutDelete
  
  // process invites for $count contacts
  function batchInvite($count) {
    $this->bl("batchInvite($count)",1);
    if($this->disableNewInvitations) {
      $this->bl('Invitations disabled. No new invitations sent.',0,1);
      return;
    }
    if($count=='max' || $this->contactCount+$count >= $this->maxcontacts)
      $count = $this->maxcontacts - $this->contactCount;
    if($count <= 0) {
      $this->bl("Cannot add anymore contacts. $this->currentAccount is at the maximum imposed contact limit.");
      return;
    }
    // get new batch of contacts...
    if($this->inviteContactOnce) {
      $sql = $this->botq("SELECT email FROM `{$this->dbtp}contacts` WHERE is_invited=0 LIMIT $count");
    }
    else {
      if($this->reInviteContacts) {
        // just check if they've ever been online. if not, send another invite.
        // REASONING: contact can reject invite, but not block future invites (so beneficial to send invite again from same account)
        $sql = $this->botq("SELECT email FROM `{$this->dbtp}contacts` WHERE has_been_online=0 ORDER BY RAND() LIMIT $count");
      }
      else {
        // (resist urge to use subqueries -- older mysql servers don't support them)
        $invites = $this->botq("SELECT sent_to FROM {$this->dbtp}invites WHERE sent_from='$this->currentAccount'");
        while($inv = mysql_fetch_assoc($invites))
          $invited[] = "'$inv[sent_to]'";
        $invited = isset($invited) ? 'AND email NOT IN ('.implode(',',$invited).')' : '';
        $sql = $this->botq("SELECT email FROM `{$this->dbtp}contacts` WHERE is_chatted=0 $invited ORDER BY RAND() LIMIT $count");
      }
    }
    if(!mysql_num_rows($sql)) {
      $this->bl('Every contact that can be invited has been sent an invitation. (according to your config settings)',0,1);
      return;
    }
    // ...and invite them
    while($rec = mysql_fetch_assoc($sql)) {
      $this->bl("$this->currentAccount is sending an invitation request to $rec[email]...");
      if($this->simulateNewInvitations)
        $this->bl('Simulation Mode: No invitations are being sent to MSN contacts.',0,1);
      else
        $this->addContact($rec['email']);
      // save record of sent invitation in the database
      $this->botq("INSERT INTO {$this->dbtp}invites(sent_from, sent_to) VALUES('$this->currentAccount', '$rec[email]')");
      $added[] = "'$rec[email]'";
    }
    if(isset($added)) {
      $added = implode(',',$added);
      $this->botq("UPDATE `{$this->dbtp}contacts` SET is_invited=1 WHERE email IN ($added)");
    }
    // update the internal contact count
    $this->contactCount += $count;
  } // end of batchInvite

  // output to screen contact's current status (invoked on status change)
  function checkStatus($contact, $status) {
    $sendChatRequest = true;
    switch($status) {
      case 'FLN':
        $sendChatRequest = false;
        $this->bl("$contact has signed off MSN Messanger.",0,1); break;
      case 'AWY':
        $this->bl("$contact is AWAY (probably not at computer).",0,1);
        $this->bl("$contact may not respond to chat session requests until they are available again.",0,1); break;
      case 'IDL':
        $this->bl("$contact is IDLE (probably not at computer).",0,1);
        $this->bl("$contact may not respond to chat session requests until they are available again.",0,1); break;
      case 'BSY':
        $this->bl("$contact set their status to BUSY (may not be available).",0,1);
        $this->bl("$contact is NOT accepting chat session requests.",0,1); break;
      case 'BRB':
        $this->bl("$contact set their status to BE RIGHT BACK (may not be available).",0,1);
        $this->bl("$contact might not accept chat session requests.",0,1); break;
      case 'PHN':
        $this->bl("$contact set their status to ON THE PHONE (might not be available).",0,1);
        $this->bl("$contact might not accept chat session requests.",0,1); break;
      case 'LUN':
        $this->bl("$contact set their status to OUT TO LUNCH (might not be available).",0,1);
        $this->bl("$contact might not accept chat session requests.",0,1); break;
      case 'NLN':
        $this->bl("$contact is AVAILABLE.",0,1); break;
    }
    return $sendChatRequest;
  }

  // reselt/clear all database data except the 'logins' and 'messages' tables
  function onlyDanShouldUseThis_resetdb() {
    return; // uncomment to activate this function
//    $q1 = mysql_query("UPDATE {$this->dbtp}accounts SET is_locked=0, unlock_at=null") or die(mysql_error());
//    $q1 = mysql_query("DELETE FROM {$this->dbtp}chats") or die(mysql_error());
//    $q1 = mysql_query("DELETE FROM {$this->dbtp}invites") or die(mysql_error());
//    $q2 = mysql_query("UPDATE {$this->dbtp}contacts SET friend_of=null, is_invited=0, is_chatted=0,
//                       is_deleted=0, has_been_online=0, last_online_at=null") or die(mysql_error());
  }

} // end of class Tinkerbot