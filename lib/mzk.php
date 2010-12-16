<?php
/** MezzengerKlient v 0.02
 Copyright 2007 sirdarckcat@gmail.com

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
**/
class MezzengerKlient {
	// Client Information
	var $mail = '';// mail
	var $email = '';// mail urlencoded
	var $pass = '';// pass urlencoded
	var $log = '';// log of events
	var $sblog = Array();// log of events SB
	var $socket = false;// the real deal socket
	var $sbsocks = Array();// the switchboard sockets
	var $ftsocks = Array(); // The file transfer sockets
	var $sbsndxs = Array();// the status of the switchboard sockets
	var $sbsindx = 1;// the index of the switchboard sockets
	var $sbconv = Array(); // The details of the other persons in the conversation
	var $sbchat = Array(); // What is being said in the CHAT :D
	var $trid = 0;// the trid
	var $otrid = Array();// the trid for swithboard sockets
	var $sblastrcv = Array(); // last received message SB
	var $sblastsnd = Array(); // last sent message SB
	var $lastrcv = ''; // last received message
	var $lastsnd = ''; // last sent message
	var $done_login = false; // dont do anything until I am logged in
	var $debug = false; // for just getting the contacts
	var $handleChat = true; // I am a bot, or a client?
	var $sleep = 0; // For keeping the flame alive
	// Login Information
	var $mynick = '';
	var $mygroups = Array();
	var $mycontacts = Array();
	var $contacts = Array();
	var $onlinefriends = Array();
	var $friendnick = Array();
	var $BPR = Array();
	var $notification = "U";
	var $status = "NLN";
	var $GTC = "A";
	var $BLP = "BL";
	var $format = "FN=Arial; EF=I; CO=0; CS=0; PF=22";
	var $format_ = Array();
	// Events in NotificationServer
	var $onRock = false; // To execute every bucle
	var $onPong = false; // Called every pong
	var $onStatusChange = false; // When someone changes its status
	var $onNotice = false; // When I receive a Notice.
	var $onMessage = false; // When I receive a Message.
	var $onLogin = false; // When I login
	// Events in SwitchBoard Server
	var $onRoll = false;
	var $onChatStart = false;
	var $onChatLoad = false;
	var $onChatMessage = false;
	var $onChatLeave = false;
	var $onChatJoin = false;
	var $onMessageConfirm = false;
	// Events for each switch board
	var $onRoll_ = Array();
	var $onPong_ = Array();
	var $onChatLoad_ = Array();
	var $onChatMessage_ = Array();
	var $onChatLeave_ = Array();
	var $onChatJoin_ = Array();
	var $onMessageConfirm_ = Array();

	// Functions for general pourposes
	function urlencode($x){
		return strtr(urlencode($x),Array("+"=>"%20"));/// note to me, change this to rawurlencode.
	}

  // DH: I added botlog to work with tinkerbot
	function logthis($action,$type,$conn=-1,$botlog=0){
	/*
		This function will log the specified action
	*/
		if (empty($action))return;
		if ($this->debug){
			if (!($type%2)){
				if($type==0 || $type==10){
					echo "|| ";
				}else{
					echo "<< ";
				}
			}else{
				echo ">> ";
			}
			if($type>=10){
				echo "($conn) ";
			}
			echo $action."\r\n";
      echo '<script type="text/javascript">window.scrollTo(0,window.scrollMaxY)</script>'; // <<<<< DH: auto-scroll output
			while (@ob_end_flush());
		}
    if(!$botlog) // <<<<< DH
		  $this->log.="$type ".base64_encode($action)."\t$conn\r\n";
	}

	function Event($function,$paramcount,$param1=false,$param2=false,$param3=false,$param4=false){
		/*
			This function will call the event handlers
		*/
		if ($function && @function_exists($function)){
			$command="call_user_func(\"$function\"";
			for ($i=1;$i<=$paramcount;$i++){
				$command.=",\$param$i";
			}
			$command.=");";
			if($this->debug)echo "!-E->$command\r\n";
			eval($command);
		}else
		if(isset($function[(int)$param1]) && function_exists($function[(int)$param1])){
			$id=(int)$param1;
			$command="call_user_func(\"".$function[(int)$param1]."\",$id";
			for ($i=1;$i<=$paramcount;$i++){
				$command.=",\$param$i";
			}
			$command.=");";
			if($this->debug)echo "!-E->$command\r\n";
			eval($command);
		}
	}

	function tellmylog(){
		/*
			This function will return the log.
		*/
		$t="";
		$m=split("\r\n",$this->log);
		foreach($m as $w){
			$w=split("\t",$w);
			$w=join(" ",$w);
			$q=split(" ",$w);
			if(isset($q[1])){
				$type=$q[0];
				$conn=$q[2];
				if (!($type%2)){
					if($type==0 || $type==10){
						$t.= "|| ";
					}else{
						$t.= "<< ";
					}
				}else{
					$t.= ">> ";
				}
				if($type>=10){
					$t.= "($conn) ";
				}
				$t.=base64_decode($q[1])."\r\n";
			}
		}
		return $t;
	}

	// Functions for Notification Server
	function sendplain($mess,$timeout=1){
		/*
			This function will send a message to the Notification Server
		*/
		$this->logthis($mess,1);
		@fputs($this->socket,$mess);
		stream_set_timeout($this->socket, $timeout);
		$this->lastsnd=$mess;
	}

	function sendplainSB($mess,$conn,$timeout=1){
		/*
			This function will send a message to the SwitchBoard Server
		*/
		$this->logthis($mess,11,$conn);
		@fputs($this->sbsocks[$conn],$mess);
		stream_set_timeout($this->sbsocks[$conn], $timeout);
		$this->sblastsnd[$conn]=$mess;
	}

	function send($mess,$timeout=1){
		/*
			This function will send a message to the Notification Server with a \r\n at the end
		*/
		$this->logthis($mess,1);
		@fputs($this->socket,$mess."\r\n");
		stream_set_timeout($this->socket, $timeout);
		$this->lastsnd=$mess;
	}

	function sendSB($mess,$conn){
		/*
			This function will send a message to the SwitchBoard Server with a \r\n at the end
		*/
		$this->logthis($mess,11,$conn);
		@fputs($this->sbsocks[$conn],$mess."\r\n");
		stream_set_timeout($this->sbsocks[$conn], 5);
		$this->sblastsnd[$conn]=$mess;
	}

	function sendSocket($mess,$sock){
		/*
			This function will send a message to the socket specified
		*/
		$this->logthis($mess,11,7);
		@fputs($sock,$mess."\r\n");
		stream_set_timeout($sock, 1);
	}

	function receiveSocket($sock){
		/*
			This function will receive a message to the socket specified
		*/
		stream_set_timeout($sock, 1);
		$w=@fgets($sock);
		$this->logthis($w,12,7);
		return $w;
	}

	function receive($length=false,$mim=2){
		/*
			This function will receive a message from the Notification Server
		*/
		stream_set_timeout($this->socket, 2);
		if ($length){
			$rec="";
			$tot=0;
			while($tot<$length){
				$rec.=fread($this->socket,$length);
				$tot=strlen($rec);
			}
		}else{
			$rec=fgets($this->socket);
		}
		$this->logthis($rec,$mim);
		$this->lastrcv=$rec;
		return $rec;
	}

	function receiveSB($conn,$length=false,$mim=12){
		/*
			This function will receive a message from the SwitchBoard Server
		*/
		if ($length){
			$rec="";
			$tot=0;
			while($tot<$length){
				$rec.=fread($this->sbsocks[$conn],$length);
				$tot=strlen($rec);
			}
		}else{
			stream_set_timeout($this->sbsocks[$conn], 2);
			if(!is_resource($this->sbsocks[$conn]))return "";
			$rec=fgets($this->sbsocks[$conn]);
		}
		$this->logthis($rec,$mim,$conn);
		$this->sblastrcv[$conn]=$rec;
		return $rec;
	}

	function opensocket($server,$port,$type=0){
		/*
			This function will return a socket to the specified server and port
		*/
		$this->logthis("$server:$port",$type);
		return fsockopen($server,$port);
	}

	function nexusLogin($chal){
		/*
			This program will use cURL for loging into a passport session
		*/
		$arr[] = "GET /rdr/pprdr.asp HTTP/1.0\r\n\r\n";
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://nexus.passport.com:443/rdr/pprdr.asp");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		curl_setopt($curl, CURLOPT_HEADER,1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $arr);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		$data = curl_exec($curl);
		curl_close($curl);
		preg_match("/DALogin=(.+?),/",$data,$matches);
		$split = explode("/",$matches[1]);
		$headers[0] = "GET /login2.srf HTTP/1.1\r\n";
		$headers[1] = "Authorization: Passport1.4 OrgVerb=GET,OrgURL=http%3A%2F%2Fmessenger%2Emsn%2Ecom,sign-in=" . $this->email . ",pwd=" . $this->pass . ", " . trim($chal) . "\r\n";
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://" . $split[0] . ":443/". $split[1]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		@curl_setopt($curl,CURLOPT_FOLLOWLOCATION,1);//open base dir
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER,1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		$data = curl_exec($curl);
		curl_close($curl);
		preg_match("/t=(.+?)'/",$data,$matches);
		if(!isset($matches[1])){
			die("Error NEXUS\r\n".$this->log);
		}
		$tt = str_replace("\n","",$matches[1]);
		return "t=$tt";
	}

	function process($con,$adt=true,$rec=true){
		/*
			This is the most important function, this will process the commands sent by the Notification Server
		*/
		$coms = explode(" ",$con." 0");
		if (is_numeric(trim($con))){
			echo("Error $con");
			return $con;
		}
		if(empty($con)){
			if($rec){
				while($this->receive()==null);
				return $this->process($this->lastrcv);
			}else{
				return false;
			}
		}
		if($adt)$this->trid++;
		switch($coms[0]){
			case "VER":
				// Nothing to do here
			break;
			case "CVR":
				// Nothing to do here
			break;
			case "CVQ":
				// Same as last one
			break;
			case "XFR":
				// We are being transferred
				switch($coms[2]){
					case "NS":
						// Notification Server, Login again..
						fclose($this->socket);
						$new = explode(':',$coms[3]);
						$this->socket=$this->opensocket($new[0],(int)$new[1]);
						$this->login(true);
					break;
					case "SB":
						// Yeah, a new SwitchBoard, lets CHAT!
						$con = split('\r',$con);
						$con = $con[0];
						$con = split('\n',$con);
						$con = $con[0];
						$coms = explode(" ",$con." 0");
						return $this->XFRSB($coms);
					break;
				}
			break;
			case "USR": // Lets Rock and Roll and Login
				switch($coms[2]){
					case "TWN":
						preg_match("/lc\=(.*)/",$coms[4],$matches);
						$auth=$this->nexusLogin("lc=".$matches[1]);
						$this->send("USR $this->trid TWN S $auth",50);
						while($this->receive()==null);
						$this->process($this->lastrcv);
					break;
					case "OK":
						$this->mynick = $coms[4];
						$this->process($this->receive());
					break;
				}
			break;
			case "MSG":
				// Some message wooo
				$whomail=$coms[1];
				$whonick=$coms[2];
				$content=$this->receive((int)$coms[3],3);
				$this->Event($this->onMessage,2,"$whomail $whonick",$content);
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
			break;
			case "GTC":
				// My default config..
				if (!isset($coms[3])){
					$this->GTC=$coms[1];
				}else{
					$this->GTC=$coms[3];
				}
			break;
			case "BLP":
				// More default config
				if (!isset($coms[3])){
					$this->BLP=$coms[1];
				}else{
					$this->BLP=$coms[3];
				}
			break;
			case "PRP":
				// The phone numbers..
				$this->BPR[]=$con;
			break;
			case "ADG":
				// I added a group, I return the group ID
				return $coms[4];
			break;
			case "RMG":
				// Erased a group.. ok
			break;
			case "REG":
				// I changed the name of the group.. ok
			break;
			case "LSG":
				// Groups
				$this->mygroups[]=$coms[2];
				$adt=false;
			break;
			case "LST":
				$contactmail = $coms[1];
				$contactnick = $coms[2];
				$contactlist = $coms[3];
				if(isset($coms[4])){
					$contactgrps = $coms[4];
				}else{
					$contactgrps = "-1";
				}
				$contacthavehe = ($contactlist&1)?1:0;
				$contacthavead = ($contactlist&2)?1:0;
				$contactbloked = ($contactlist&4)?1:0;
				$contacthaveme = ($contactlist&8)?1:0;
				$this->mycontacts[] = $contactmail;
				$this->contacts[] = "$contactmail $contactnick $contacthavehe $contacthavead $contactbloked $contacthaveme $contactgrps";

				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
			break;
			case "BPR":
				// Contact phone
				$this->BPR[]=$con;
			break;
			case "ILN":
				$status = $coms[2];
				$mail = $coms[3];
				$nick = $coms[4];
				$this->friendnick[$mail]=$nick;
				$this->onlinefriends[]="$mail $nick $status";
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
				$adt=false;
			break;
			case "NLN":
				$status = $coms[1];
				$mail = $coms[2];
				$nick = $coms[3];
				$ddoonnee = 0;
				foreach($this->onlinefriends as $a=>$b){
					if(strstr($b,$mail)){
						$this->onlinefriends[$a]="";
					}
					if (!$ddoonnee){
						$this->onlinefriends[$a]="$mail $nick $status";
						$ddoonnee=1;
					}
				}
				if (!$ddoonnee){
					$this->onlinefriends[]="$mail $nick $status";
					$ddoonnee=1;
				}
				$this->friendnick[$mail]=$nick;
				$this->setNick($nick,$mail);
				$this->Event($this->onStatusChange,2,"$mail $nick $status",$coms[4]);
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
			break;
			case "FLN":
				$mail = strtr(trim($coms[1]),"\n","");
				foreach($this->onlinefriends as $a=>$b){
					if(strstr($b,$mail)){
						$this->onlinefriends[$a]="";
					}
				}
				$this->Event($this->onStatusChange,2,"$mail {$this->friendnick[$mail]} FLN",0);
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
			break;
			case "ADD":
				// I added someone to some list, or someone added me..
				if ($coms[2] == "RL" && (trim($this->GTC) == 'A' || trim($this->BLP) == 'BL')) {//thnks nigel.kane
					// New contact?
					$mail=$coms[4];
					$this->send("ADD $this->trid AL $mail $mail");
					//$this->process($this->receive());
					$this->mycontacts[]=$mail;
				}
			break;
			case "REM":
				// Removed from a list
				$mail=$coms[4];
				if ($coms[2]=="RL"){
					// The SOB erased me
					$this->send("REM $this->trid AL $mail");
					//$this->process($this->receive());
				}
			break;
			case "RNG":
				// Someone invited me to chat :D
				if($this->handleChat){
					$new = explode(':',$coms[2]);
					$this->sbsocks[$this->sbsindx]=$this->opensocket($new[0],(int)$new[1],10);
					$this->sbsndxs[$this->sbsindx]=true;
					$this->otrid[$this->sbsindx]=0;
					$this->authSB($this->sbsindx,$coms[4],$coms[1]);
					$this->sbsindx++;
				}else{
					return $con;
				}
				$this->Event($this->onChatStart,1,$this->sbsindx-1);
				$adt=false;
			break;
			case "CHL":
				// CHALLENGE!!
				$what = $coms[2];
				$hash = md5(trim($what)."Q1P7W2E4J9R8U3S5");
				$this->sendplain("QRY $this->trid msmsgs@msnmsgr.com 32\r\n$hash");
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
				$adt=false;
			break;
			case "QRY":
				// Yeah, I rock
				if(!$this->done_login){
					$this->done_login=true;
					$this->Event($this->onLogin,0);
				}else{
					$this->send("PNG");
				}
			break;
			case "QNG":
				// PONG!!
				$this->Event($this->onPong,0);
			break;
			case "NOT":
				// Some Notice
				$content=$this->receive((int)$coms[1],4);
				$this->Event($this->onNotice,1,$content);
				if ($this->receive()!=null){
					$this->process($this->lastrcv);
				}
			break;
			case "OUT":
				// Well.. good bye
			break;
		}
		if(!$adt)$this->trid--;
		return false;
	}

	function rock(){
		/*
			This will receive and process a message from all the open servers
		*/
		for ($i=0;$i<=$this->sbsindx;$i++){
			if(isset($this->sbsndxs[$i]) && $this->sbsndxs[$i] && is_resource($this->sbsocks[$i])){
				$this->Event($this->onRoll,1,$i);
				$this->Event($this->onRoll_,1,$i);
				$this->processSB($this->receiveSB($i),$i);
			}else{
				$this->sbsndxs[$i]=false;
			}
		}
		if (is_resource($this->socket)){
			$this->Event($this->onRock,0);
			$this->process($this->receive(),true,false);
		}
		if(!$this->debug){
			echo " ";
			while (@ob_end_flush());
		}
	}

	function load_login(){
		/*
			This will rock() until I receive the first challenge
		*/
		while(!$this->done_login){
			$this->rock();
		}
	}

	function init($mail,$pass,$status=false,$iamabot=false){
		/*
			This function will initiate the variables and sockets for loging in.
		*/
		ini_set("max_execution_time",0);
		ob_implicit_flush (1);
		if($iamabot){
			ignore_user_abort(1);
		}
		if(!strstr("|NLN|BSY|IDL|BRB|AWY|PHN|LUN|","|".substr("NLN$status$status",3,3)."|")){
			$status=$this->status;
		}
		$this->socket = $this->opensocket("messenger.hotmail.com",1863);
		$this->mail=$mail;
		$this->email=$this->urlencode($mail);
		$this->pass=$this->urlencode($pass);
		$this->status=$status;
		return;
	}

	function login($lead=false){
		/*
			This will login into the Notification Server
		*/
		$this->send("VER $this->trid MSNP9 MSNP8 CVR0");
		$this->process($this->receive());
		$this->send("CVR $this->trid 0x0409 win 4.10 i386 MSNMSGR 6.2.0137 MSMSGS ".$this->mail);
		$this->process($this->receive());
		$this->send("USR $this->trid TWN I ".$this->mail);
		$this->process($this->receive());
		// The Next step is done in process()
		if($lead){
			$this->send("SYN $this->trid 0");
			$this->process($this->receive());
			$this->send("CHG $this->trid $this->status 0");
			$this->process($this->receive());
			$this->send("PNG");
		}
	}

	function main(){
		/*
			This will loop the client into an infinite loop, or until the socket closes connection
		*/
		while(is_resource($this->socket)){
			$this->rock();
		}
	}

	function quit(){
		/*
			This will close the servers connection
		*/
		if(is_resource($this->socket)){
			$this->send("OUT");
			for ($i=0;$i<=$this->sbsindx;$i++){
				if(isset($this->sbsndxs[$i]) && $this->sbsndxs[$i] && is_resource($this->sbsocks[$i])){
					$this->sendSB("OUT",$i);
				}else{
					$this->sbsndxs[$i]=false;
				}
			}
		}
		@fclose($this->socket);
	}

	function ping($f=false){
		if(!$f)return $this->send("PNG");
		return $this->sendSB("PNG",$f);
	}

	function setFormat($save=false,$fontname="Times New Roman",$effects="",$color=0,$cs=0,$pf=12,$rl=0,$sb=false){
		/*
			This will change your messages format
		*/
		$fontname=$this->urlencode($fontname);
		if($save)return "FN=$fontname; EF=$effects; CO=$color; CS=$cs; PF=$pf; RL=$rl";
		if($sb){
			$this->format_[$sb]="FN=$fontname; EF=$effects; CO=$color; CS=$cs; PF=$pf; RL=$rl";
		}else{
			$this->format="FN=$fontname; EF=$effects; CO=$color; CS=$cs; PF=$pf; RL=$rl";
		}
	}

	function setGTC($new){
		/*
			This will set your GTC
		*/
		if ($new=="A" || $new=="N"){
			$this->send("GTC $this->trid $new");
			//$this->process($this->receive());
		}
	}

	function setBLP($new){
		/*
			This will set your BLP
		*/
		if (strstr("BL|AL|FL",substr($new,0,2))){
			$this->send("BLP $this->trid $new");
			//$this->process($this->receive());
		}
	}

	function setStatus($status){
		/*
			This will change your Status
		*/
		$this->send("CHG $this->trid $status 0");
	}

	function setNick($newnick,$who=false){
		if(!$who)$who=$this->mail;
		$newnick=$this->urlencode($newnick);
		$this->send("REA $this->trid $who $newnick");
		//$this->process($this->receive());
	}

	function addGroup($name){
		$this->send("ADG $this->trid $name 0");
		while(($id=$this->process($this->receive(),false,true))===false);
		return $id;
	}

	function delGroup($gid){
		$gid=(int)$gid;
		$this->send("RMG $this->trid $gid");
		//$this->process($this->receive());
	}

	function renGroup($gid,$name){
		$gid=(int)$gid;
		$name=$this->urlencode($name);
		$this->send("REG $this->trid $gid $name 0");
		//$this->process($this->receive());
	}

	function addContact($new,$list="FL"){
		/*
			This will add a contact to your list
		*/
		$this->send("ADD $this->trid $list $new $new");
		//$this->process($this->receive());
		return $list;
	}

	function delContact($old,$list="FL"){
		/*
			This will remove a contact from your list
		*/
		$this->send("REM $this->trid $list $old");
		$this->send("REM $this->trid AL $old");
		//$this->process($this->receive());
		return $list;
	}

	// Functions for chat :D (SB)
	function processSB($con,$which,$rec=false){
		/*
			This will process all the commands sent by the SwitchBoard servers
		*/
		$coms = explode(" ",$con);
		if (is_numeric(trim($con))){
			echo "Error $con";
			switch ($coms[0]){
				case 205:
					echo "\r\nInvalid mail";
				break;
				case 216:
					echo "\r\nThis user may blocked you";
				break;
				case 217:
					echo "\r\nThis user is not online";
				break;
				case 911:
					echo "\r\nCrash and burn";
				break;
			}
			echo "\r\n";
		}
		if(empty($con) && $rec){
			if ($this->receiveSB($which)!=null){
				return $this->processSB($this->sblastrcv[$which],$which);
			}else{
				return $this->processSB($this->receiveSB($which),$which);
			}
		}
		$this->otrid[$which]++;
		switch($coms[0]){
			case "ANS":
				$this->sbconv[$which][]="$this->mynick $this->mail";
				$this->Event($this->onChatLoad,2,$which,$this->sbconv[$which]);
				$this->Event($this->onChatLoad_,2,$which,$this->sbconv[$which]);
			break;
			case "IRO":
				// Who was before me ah?
				$name=$coms[5];
				$mail=$coms[4];
				$this->sbconv[$which][]="$name $mail";
				if ($this->receiveSB($which)!=null){
					$this->processSB($this->sblastrcv[$which],$which);
				}
			break;
			case "USR":
				// OK
				$this->sbconv[$which][]="$this->mynick $this->mail";
				$this->Event($this->onChatLoad,2,$which,$this->sbconv[$which]);
				$this->Event($this->onChatLoad_,2,$which,$this->sbconv[$which]);
			break;
			case "CAL":
				// You invited someone
				//   The "waiting for.." in the oficial client waits for this command after a CAL was sent :P
			break;
			case "JOI":
				// Someone camed in
				$name=$coms[2];
				$mail=$coms[1];
				$this->sbconv[$which][]="$name $mail";
				$this->Event($this->onChatJoin,2,$which,$con);
				$this->Event($this->onChatJoin_,2,$which,$con);
			break;
			case "OUT":
				// BYEEE
				$this->sbsndxs[$which]=false;
				fclose($this->sbsocks[$which]);
			break;
			case "BYE":
				// Leaving
				$this->Event($this->onChatLeave,2,$which,$con);
				$this->Event($this->onChatLeave_,2,$which,$con);
				break;
			case "MSG":
				// WHAT!!? A MESSAGE? HELLOWW
				$mail = $coms[1];
				$name = $coms[2];
				$length = $coms[3];
				$message = $this->receiveSB($which,$length);
				$this->sbchat[$which][]=$message;
				$this->Event($this->onChatMessage,3,$which,$con,$message);
				$this->Event($this->onChatMessage_,3,$which,$con,$message);
			break;
			case "QNG":
				$this->Event($this->onPong_,1,$which);
			break;
			case "ACK":
				// My Message was received
				$this->Event($this->onMessageConfirm,2,$which,$con);
				$this->Event($this->onMessageConfirm_,2,$which,$con);
			break;
			case "NTK":
				// My Message wasnt received
				$this->Event($this->onMessageConfirm,2,$which,$con);
				$this->Event($this->onMessageConfirm_,2,$which,$con);
			break;
			default:
				$this->otrid[$which]--;
			break;
		}
	}

	function NewChat(){
		/*
			This will ask for a new SwitchBoard session
		*/
		$this->send("XFR ".$this->trid." SB");
		while(($id=$this->process($this->receive(),false,true))===false);
		return $id;
	}

	function XFRSB($coms,$direct=false){
		/*
			This will start a new SwitchBoard session, or return the switch value
		*/
		if($this->handleChat || $direct){
			$new = explode(':',$coms[3]);
			$this->sbsndxs[$this->sbsindx]=true;
			$this->otrid[$this->sbsindx]=0;
			$this->sbsocks[$this->sbsindx]=$this->opensocket($new[0],(int)$new[1],10);
			preg_match("/[0-9\.]+/",$coms[5],$cred);
			$cki_auth=$cred=$cred[0];
			$this->loginSB($this->sbsindx,$cki_auth);
			$this->Event($this->onChatStart,1,$this->sbsindx);
			return $this->sbsindx++;
		}else{
			return @join(' ',$coms);
		}
	}

	function loginSB($server_i,$cki_auth){
		/*
			When creating a switchboard session, and logging in
		*/
		$this->sendSB("USR {$this->otrid[$server_i]} $this->mail $cki_auth",$server_i);
		$this->processSB($this->receiveSB($server_i),$server_i);
	}

	function authSB($server_i,$cki_auth,$session){
		/*
			When joining to a switchboard session, and logging ing
		*/
		$this->sendSB("ANS {$this->otrid[$server_i]} $this->mail $cki_auth $session",$server_i);
		$this->processSB($this->receiveSB($server_i),$server_i);
	}

	function InviteToChat($i,$w){
		/*
			This will ask someone to join to a SwitchBoard Session
		*/
		$this->sendSB("CAL {$this->otrid[$i]} $w",$i);
		$this->processSB($this->receiveSB($i),$i);
	}

	function sendMessage($which,$what,$notification=false){
		/*
			This will send a message to a specified switchboard session
		*/
		if(!$notification)$notification=$this->notification;
		$this->sendplainSB("MSG {$this->otrid[$which]} $notification ".strlen($what)."\r\n".$what,$which);
		return $this->otrid[$which];
	}

	function sendTyping($where){
		/*
			This will simply send a "Typing" message
		*/
		return $this->sendMessage($where,"MIME-Version: 1.0\r\nContent-Type: text/x-msmsgscontrol\r\nTypingUser: $this->mail\r\n\r\n\r\n");
	}

	function sendText($which,$what,$format=false,$notification=false){
		/*
			This function will send a message using the default headers
		*/
		if(!$notification)$notification=$this->notification;
		if(isset($this->format_[$which]))$format=$this->format_[$which];
		if(!$format)$format=$this->format;
		$what="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nX-MMS-IM-Format: $format\r\n\r\n$what";
		$this->sendplainSB("MSG {$this->otrid[$which]} $notification ".strlen($what)."\r\n".$what,$which);
		return $this->otrid[$which];
	}

	function MessageToNew($quien,$mensaje){
		/*
			This will send $mensaje to $quien ($message to $who)
		*/
		global $t;
		$t->onChatLoad_[$t->sbsindx]=create_function('$id,$quienes','global $t;$t->InviteToChat($id,urldecode("'.$this->urlencode($quien).'"));$t->onChatJoin_[$id]=create_function(\'$id,$mes\',\'global $t;$t->SendText($id,urldecode("'.$this->urlencode($mensaje).'"));\');');
		return $t->NewChat();
	}

	function ProcessMessage($c){
		/*
			This will return an array with:
				[0]-> Headers
				[1]-> Content
		*/
		$content = @split("\r\n\r\n",$c);//[0] = header ;[1] = content
		$headerz = @split("\r\n",$content[0]);
		$content = $content[1];
		$temp=Array();
		foreach($headerz as $h){
			$h=@split(': ',$h);
			if(isset($h[0],$h[1]))
			$temp[$h[0]]=$h[1];
		}
		return Array($temp,$content);
	}

	function exitChat($donde){
		/*
			This will close a switchboard session
		*/
		$this->sendSB("OUT",$donde);
	}
}
?>