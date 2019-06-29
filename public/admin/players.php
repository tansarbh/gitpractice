<?php

//Updated file

// Set TRUE if you need DB access for this page
//
define( 'USE_DB', TRUE );

// Set TRUE if you must be logged in to access this page
//
define( 'LOGIN', TRUE );

// Set TRUE if this is an admin only page
//
define( 'ADMIN', FALSE );

include( $_SERVER['DOCUMENT_ROOT'] . '/code/config.php' );
include( $_SERVER['DOCUMENT_ROOT'] . '/code/initialize.php' );
include( $_SERVER['DOCUMENT_ROOT'] . '/public/admin/permissions.php' );

require_once 'HTML/QuickForm.php';
include_once(  $_SERVER['DOCUMENT_ROOT'] . '/code/phpmailer/class.phpmailer.php' );

include( $_SERVER['DOCUMENT_ROOT'] . '/code/GolfNetAPIClient.php' );
$golfAPI = new GolfNetAPIClient($golfnetAPIInfo);

/*
CREATE TABLE players (
	id SERIAL PRIMARY KEY,
	leagues_id INT REFERENCES leagues(id) NOT NULL,
	firstname VARCHAR(40) NOT NULL,
	lastname VARCHAR(40) NOT NULL,
	UNIQUE (leagues_id,firstname,lastname)
);
*/

// Add or Delete
//
if ( $_GET['type'] == 'add' ) {

	// Checking for previous page
	$url = $_SERVER['HTTP_REFERER'];
	preg_match('/\/[a-z0-9]+.php/', $url, $match);
	$previouspage = array_shift($match);
	//echo $previouspage;
	if($previouspage== '/draftsetup.php' || $_REQUEST['previouspage']=='t'){
		//exit;
		//$form->addElement('hidden', 'previouspage', $previouspage);
		$smarty->assign( 'previouspage', $previouspage);
	}
	
	
	// Start the form object
	//
	$form = new HTML_QuickForm('players','post','/admin/players.php?type=add');
	$form->setDefaults('');
	$form->setJsWarnings ("Please correct the following problem(s):", "");

	$elements = array( "firstname" => "", "lastname" => "");


	// Initial Handicap
	//
	$start = 0;
	$stop = 60;
	unset($handicaps);
	//$handicaps[5000] = "None";

	for ($i = 8; $i >= 1; $i--) {
		$handicaps["-" . $i] = "+" . $i;
	}

	for ($i = $start; $i <= $stop; $i++) {
		$handicaps[$i] = $i;
	}

	$sql = "SELECT id, fullname FROM tees WHERE seasons_id = '$seasons_id' AND hidden = 'f' ORDER BY fullname;";

	$result = $db->query($sql);
	while ($row = $result->fetchRow()) {
		$tees[$row['id']] = $row['fullname'];
	}

	// Elements
	//
	$form->addElement('header', 'add', 'Add a Player');
	$form->addElement('text', 'firstname', 'First Name:', array('size' => 40, 'maxlength' => 40));
	$form->addElement('text', 'lastname', 'Last Name:', array('size' => 40, 'maxlength' => 40));
	//$form->addElement('text', 'initialhandicap', 'Regular 18 Hole Handicap<br />OR 18 Hole Handicap Factor<br />(based on default tee):', array('size' => 4, 'maxlength' => 4));
	$form->addElement('text', 'initialhandicap', 'Regular 18 Hole Handicap:', array('size' => 4, 'maxlength' => 4));
	$form->addElement('text', 'email', 'Email Address:', array('size' => 40, 'maxlength' => 80));
	$form->addElement('select', 'preferred_tee', 'Preferred Tee:', $tees);
	$form->addElement('advcheckbox', 'skins', 'Skins:', '', array('0', '1'));
	$form->addElement('advcheckbox', 'ringerboard', 'Ringer Board:', '', array('0', '1'));
	$form->addElement('advcheckbox', 'deucepot', 'Deuce Pot:', '', array('0', '1'));
	$file =& $form->addElement('file', 'filename', 'Profile Photo:');
	//$form->addElement('advcheckbox', 'sendemail', 'Send Password Email:', '', array('0', '1'));

	if ( $_SESSION['golfnet_club_id'] > 0 && $_SESSION['handicap'] == "golfnet" ) {
		$golfnet_members = $golfAPI->getMembersByClubId($_SESSION['golfnet_club_id']);
		$members[0] = "";
		foreach( $golfnet_members as $key => $value ) {
			$members[$value['gc_networkid']] = strtoupper($value['lastname']) . ', ' . strtoupper($value['firstname'] . ' (' . $value['gc_networkid'] . ')');
		}
		asort($members);
		$form->addElement('header', 'add', '');
		$form->addElement('select', 'golfnet_networkid', 'Golfnet Player ID:', $members);
	}
	
	$form->addElement('submit', 'submit', 'Add');

	// Filters
	//
	$trimlist = array('firstname', 'lastname');
	foreach($trimlist as $field) {
		$form->applyFilter($field, 'trim');
	}

	// Rules
	//
	$form->addRule('firstname', 'First Name is required', 'required', null, 'client');
	$form->addRule('lastname', 'Last Name is required', 'required', null, 'client');
	$form->addRule('initialhandicap', 'Initial Handicap is required', 'required', null, 'client');
	$form->addRule('email', 'Invalid Email Address', 'email', null, 'client');
	
	$form->addRule('filename', 'The Logo you selected is too large - it can only be 2 MB max', 'maxfilesize', 2000000);
	$form->addRule('filename', 'Logo must be a jpeg or gif', 'mimetype', array('image/jpeg', 'image/gif') );

	$form->setDefaults(array('initialhandicap' => "0", 'preferred_tee' => $_SESSION['days_default_tees_id'], 'skins' => true, 'ringerboard' => true, 'deucepot' => true));
	
					

	if ($form->validate()) {

		$data = $form->exportValues();

		$firstname = prepData(trim($data['firstname']));
		$lastname = prepData(trim($data['lastname']));
		$initialhandicap = $data['initialhandicap'];
		$email = $data['email'];
		$preferred_tee = $data['preferred_tee'];
		$skins = $data['skins'] ? 't' : 'f';
		$ringerboard = $data['ringerboard'] ? 't' : 'f';
		$deucepot = $data['deucepot'] ? 't' : 'f';

		$clubs_id = $_SESSION['clubs_id'];
		$leagues_id = $_SESSION['leagues_id'];
		$seasons_id = $_SESSION['seasons_id'];
		$shortname = $_SESSION['clubshortname'];
		
		$golfnet_networkid = "null";
		if ( $_SESSION['golfnet_club_id'] > 0 && $data['golfnet_networkid'] > 0 ) {
			$golfnet_networkid = $data['golfnet_networkid'];
		}
		
		if ($file->isUploadedFile()) {
			$filename = $file->_value['name'];
			preg_match('/.+\.(.+)/',$filename,$matches);
			$extention = $matches[1];
			$time = time().'.';

			$newfilename = $shortname."-photo".$time.$extention;
			
			if ( $email != "" ) {
				$sql = "INSERT INTO players (leagues_id,firstname,lastname,initialhandicap,email,preferred_tee, profilephoto, skins, ringerboard, deucepot, golfnet_networkid)
						VALUES ('$leagues_id',E'$firstname',E'$lastname','$initialhandicap','$email','$preferred_tee','$newfilename', '$skins', '$ringerboard', '$deucepot', $golfnet_networkid);";
			}
			else {
				$sql = "INSERT INTO players (leagues_id,firstname,lastname,initialhandicap,preferred_tee, profilephoto, skins, ringerboard, deucepot, golfnet_networkid)
						VALUES ('$leagues_id',E'$firstname',E'$lastname','$initialhandicap','$preferred_tee','$newfilename', '$skins', '$ringerboard', '$deucepot', $golfnet_networkid);";
			}
		}
		else{
			if ( $email != "" ) {
				$sql = "INSERT INTO players (leagues_id,firstname,lastname,initialhandicap,email,preferred_tee, skins, ringerboard, deucepot, golfnet_networkid)
						VALUES ('$leagues_id',E'$firstname',E'$lastname','$initialhandicap','$email','$preferred_tee','$skins', '$ringerboard', '$deucepot', $golfnet_networkid);";
			}
			else {
				$sql = "INSERT INTO players (leagues_id,firstname,lastname,initialhandicap,preferred_tee,skins, ringerboard, deucepot, golfnet_networkid)
						VALUES ('$leagues_id',E'$firstname',E'$lastname','$initialhandicap','$preferred_tee','$skins', '$ringerboard', '$deucepot', $golfnet_networkid);";
			}
		}
		//$sql = "INSERT INTO players (leagues_id,firstname,lastname,initialhandicap,email,preferred_tee)
		//	VALUES ('$leagues_id',E'$firstname',E'$lastname','$initialhandicap','$email','$preferred_tee');";

		$result = $db->query($sql);
		
		if (DB::isError($result)) {
			//die($result->getMessage());
			$smarty->assign('status', 'Error: Unable to add player');
		}
		else {
			
			// Reset data in the form fields
			//
			$form->setConstants($elements);
			$smarty->assign('status','Player successfully added');

			$sendemail = $data['sendemail'] ? 't' : 'f';
			if ($sendemail == 't') {
				emailPasswordReset( $email );
			}
			
			/**
			 *	For player profile photos
			 */
			$path = $_SERVER['DOCUMENT_ROOT'] . "/public/images/players/".$shortname."/";
			if (!is_dir($path)) {
			    mkdir( $path , 0777 );
			}

			if ($file->isUploadedFile()) {
				$file->moveUploadedFile($path);
				rename($path . $filename , $path . $newfilename);

				/**
				 *	Scale the logo down
				 */
				$width = shell_exec("/usr/local/bin/identify -format %w " . $path . $newfilename);
				$height = shell_exec("/usr/local/bin/identify -format %h " . $path . $newfilename);
				if ($width > $height) {
					$scale = "200x";
				}
				else {
					$scale = "x185";
				}
				$command = "/usr/local/bin/convert -scale " . $scale . " -quality 90 -colorspace RGB +profile \"*\" \"" . $path . $newfilename . "\" \"" .  $path . $newfilename . "\"";
				$result = shell_exec($command);
			}
			
			$cacheDB->get(NSPACE . "_" . $shortname . "_players");
			
			// Checking if the league is tour mode and auto populate player to team is true or not
			//
			$sql2 = "SELECT shortname, auto_playertoteam, auto_teamtopool FROM leagues WHERE id = '$leagues_id' AND clubs_id = '$clubs_id';";
			$result2 = $db->getRow($sql2);
			
			//if($result2['shortname'] == 'tour' && $result2['auto_playertoteam'] == 't'){
			if( preg_match('/tour/', $result2['shortname']) && $result2['auto_playertoteam'] == 't'){

				$teamname = $firstname.' '.$lastname;
				// Insertion of Player name as team name itself
				//
				$sql = "INSERT INTO teams (leagues_id, name, display_name) VALUES ('$leagues_id',E'$teamname',E'$teamname');";
				$result = $db->query($sql);
				
				// Selection of last entered team id from team table
				//
				$sqlteam = "SELECT id as teams_id FROM teams WHERE leagues_id = '$leagues_id' AND LOWER(name) = LOWER('$teamname');";
				$teams_id = $db->getOne($sqlteam);
				
				// Selection of player id from players table itself
				//
				$sqlpl = "SELECT players.id AS players_id FROM players WHERE leagues_id= '$leagues_id' AND LOWER(firstname)= LOWER('$firstname') AND LOWER(lastname) = LOWER('$lastname');";
				$players_id = $db->getOne($sqlpl);
				
				// Insertion of same player to the same team itself
				//
				$sqlteamplayers = "INSERT INTO teamsplayers (seasons_id, teams_id, players_id) VALUES ('$seasons_id','$teams_id',$players_id);";
				$res = $db->query($sqlteamplayers);
				
				/**
				 *  Add Player/Team to Pool if there is only one
				 */
				if ( $result2['auto_teamtopool'] == "t" ) {
				  $qry = "SELECT count(id) FROM medal_pools WHERE seasons_id = '{$seasons_id}';";
				  $poolcount = $db->getOne($qry);
				  if ( $poolcount == 1 ) {
				    $qry = "SELECT id FROM medal_pools WHERE seasons_id = '{$seasons_id}';";
				    $pool_id = $db->getOne($qry);
				    if ( is_numeric($pool_id) ) {
				      $qry = "INSERT INTO medal_poolsteams (medal_pools_id, teams_id) VALUES ('{$pool_id}','{$teams_id}');";
				      $result = $db->query($qry);
				    }
			    }
			  }

			}
			
		}
	}

	$control = @$form->toArray();

	$smarty->assign("control", $control);
	$smarty->display( 'admin/addupdateplayer.tpl' );

}
elseif ( $_GET['type'] == 'edit' && is_numeric($_GET['player']) ) {
	
	// Start the form object
	//
	$form = new HTML_QuickForm('players','post','/admin/players.php?type=edit&player=' . $_GET['player']);
	$form->setDefaults('');
	$form->setJsWarnings ("Please correct the following problem(s):", "");

	$id = $_GET['player'];
	//BITS3 adding comments field
	$sql = "SELECT firstname,lastname,email,initialhandicap,inactive,archived,preferred_tee,ringerboard,deucepot,skins,golfnet_networkid,comments
			FROM players
			WHERE leagues_id = '$leagues_id'
			AND id = '$id';";

	$player = $db->getRow($sql);
	
	$teamname = $player['firstname'].' '.$player['lastname'];

	// Initial Handicap
	//
	$start = 0;
	$stop = 60;
	unset($handicaps);
	//$handicaps[5000] = "None";
	for ($i = $start; $i <= $stop; $i++) {
		$handicaps[$i] = $i;
	}

	$sql = "SELECT id, fullname FROM tees WHERE seasons_id = '$seasons_id' AND hidden = 'f' ORDER BY fullname;";

	$result = $db->query($sql);
	while ($row = $result->fetchRow()) {
		$tees[$row['id']] = $row['fullname'];
	}

	// Elements
	//
	$form->addElement('header', 'add', 'Edit Player');
	$form->addElement('text', 'firstname', 'First Name:', array('size' => 40, 'maxlength' => 40));
	$form->addElement('text', 'lastname', 'Last Name:', array('size' => 40, 'maxlength' => 40));

	//$form->addElement('select', 'initialhandicap', 'Regular 18 Hole Handicap:', $handicaps);
	//$form->addElement('text', 'initialhandicap', 'Regular 18 Hole Handicap<br />OR 18 Hole Handicap Factor<br />(based on default tee):', array('size' => 4, 'maxlength' => 4));
	$form->addElement('text', 'initialhandicap', 'Regular 18 Hole Handicap:', array('size' => 4, 'maxlength' => 4));

	$form->addElement('text', 'email', 'Email Address:', array('size' => 40, 'maxlength' => 80));

	$form->addElement('select', 'preferred_tee', 'Preferred Tee:', $tees);

	$form->addElement('advcheckbox', 'skins', 'Skins:', '', array('0', '1'));
	
	$form->addElement('advcheckbox', 'ringerboard', 'Ringer Board:', '', array('0', '1'));

	$form->addElement('advcheckbox', 'deucepot', 'Deuce Pot:', '', array('0', '1'));

	$form->addElement('advcheckbox', 'sendemail', 'Send Password Email:', '', array('0', '1'));

	$form->addElement('advcheckbox', 'inactive', 'Set Player as Inactive:', '', array('0', '1'));
	
	$form->addElement('advcheckbox', 'archived', 'Archive Player:', '', array('0', '1'));
	//BITS3
	$form->addElement('textarea', 'comments', 'Funny fact for Player:', array('size' => 40, 'maxlength' => 200));
	
	$file =& $form->addElement('file', 'filename', 'Profile Photo:');

	if ( $_SESSION['golfnet_club_id'] > 0 ) {
		$golfnet_members = $golfAPI->getMembersByClubId($_SESSION['golfnet_club_id']);
		$members[0] = "";
		foreach( $golfnet_members as $key => $value ) {
			$members[$value['gc_networkid']] = strtoupper($value['lastname']) . ', ' . strtoupper($value['firstname'] . ' (' . $value['gc_networkid'] . ')');
		}
		asort($members);
		$form->addElement('header', 'add', '');
		$form->addElement('select', 'golfnet_networkid', 'Golfnet Player ID:', $members);
	}
	
	$form->addElement('hidden', 'teamname', $teamname);
	
	$form->addElement('submit', 'submit', 'Update');

	// Filters
	//
	$trimlist = array('firstname', 'lastname');
	foreach($trimlist as $field) {
		$form->applyFilter($field, 'trim');
	}

	// Rules
	//
	$form->addRule('firstname', 'First Name is required', 'required', null, 'client');
	$form->addRule('lastname', 'Last Name is required', 'required', null, 'client');
	$form->addRule('initialhandicap', 'Initial Handicap is required', 'required', null, 'client');
	$form->addRule('email', 'Invalid Email Address', 'email', null, 'client');
	$form->addRule('filename', 'The photo you selected is too large - it can only be 2 MB max', 'maxfilesize', 2000000);
	$form->addRule('filename', 'Photo must be a jpeg or gif', 'mimetype', array('image/jpeg', 'image/gif') );

	/**
	 *	Defaults
	 */
	//BITS3 adding comments field
	if (!$player['preferred_tee']) {
		$player['preferred_tee'] = $_SESSION['days_default_tees_id'];
	}
	$form->setDefaults(array('firstname' => $player['firstname'],
				'lastname' => $player['lastname'],
				'email' => $player['email'],
				'comments'=>$player['comments'],
				'preferred_tee' => $player['preferred_tee'],
				'initialhandicap' => $player['initialhandicap'],
				'inactive' => $player['inactive'] == 't' ? true : false,
				'ringerboard' => $player['ringerboard'] == 't' ? true : false,
				'deucepot' => $player['deucepot'] == 't' ? true : false,
				'skins' => $player['skins'] == 't' ? true : false,
				'archived' => $player['archived'] == 't' ? true : false,
				'golfnet_networkid' => $player['golfnet_networkid']));
				//'initialhandicap' => $player['initialhandicap']

	//BITS3 adding comments field
	if ($form->validate()) {

		$data = $form->exportValues();

		$firstname = prepData(trim($data['firstname']));
		$lastname = prepData(trim($data['lastname']));
		$comments = prepData(trim($data['comments']));
		$oldteamname = prepData(trim($data['teamname']));
		$initialhandicap = $data['initialhandicap'];
		$email = $data['email'];
		$preferred_tee = $data['preferred_tee'];
		$inactive = $data['inactive']? 't' : 'f';
		$ringerboard = $data['ringerboard']? 't' : 'f';
		$deucepot = $data['deucepot']? 't' : 'f';
		$skins = $data['skins']? 't' : 'f';
		
		if($data['archived']){
			$archived = 't';
			$inactive = 't';
		}
		else{
			$archived = 'f';
		}
		//echo $inactive; exit;
		$leagues_id = $_SESSION['leagues_id'];
		$shortname = $_SESSION['clubshortname'];

		$golfnet_networkid = "null";
		if ( $_SESSION['golfnet_club_id'] > 0 && $data['golfnet_networkid'] > 0 ) {
			$golfnet_networkid = $data['golfnet_networkid'];
		}		
		//echo "excellent Comment:".$comments;
		if ($file->isUploadedFile()) {
			$filename = $file->_value['name'];
			preg_match('/.+\.(.+)/',$filename,$matches);
			$extention = $matches[1];
			$time = time().'.';

			$newfilename = $shortname."-photo".$time.$extention;
			
			if ( $email != "" ) {
			$sql = "UPDATE players set firstname = E'$firstname',comments = E'$comments', lastname = E'$lastname', initialhandicap = '$initialhandicap', email = '$email',
						preferred_tee = '$preferred_tee', inactive = '$inactive', ringerboard ='$ringerboard', deucepot = '$deucepot', skins = '$skins', archived = '$archived', profilephoto = '$newfilename', golfnet_networkid = {$golfnet_networkid} WHERE id = '$id';";
			}
			else {
				$sql = "UPDATE players set firstname = E'$firstname',comments = E'$comments', lastname = E'$lastname', initialhandicap = '$initialhandicap', inactive = '$inactive',
							preferred_tee = '$preferred_tee', archived = '$archived', ringerboard ='$ringerboard', deucepot = '$deucepot', skins = '$skins', profilephoto = '$newfilename', golfnet_networkid = {$golfnet_networkid} WHERE id = '$id';";
			}
		}
		else{
			if ( $email != "" ) {
				$sql = "UPDATE players set firstname = E'$firstname',comments = E'$comments', lastname = E'$lastname', initialhandicap = '$initialhandicap', email = '$email',
						preferred_tee = '$preferred_tee', archived = '$archived', ringerboard ='$ringerboard', deucepot = '$deucepot', skins = '$skins', inactive = '$inactive', golfnet_networkid = {$golfnet_networkid} WHERE id = '$id';";
			}
			else {
				$sql = "UPDATE players set firstname = E'$firstname',comments = E'$comments', lastname = E'$lastname', initialhandicap = '$initialhandicap',
							inactive = '$inactive', archived = '$archived', ringerboard ='$ringerboard', deucepot = '$deucepot', skins = '$skins', preferred_tee = '$preferred_tee', golfnet_networkid = {$golfnet_networkid} WHERE id = '$id';";
			}
		}
		//echo $sql; exit;
		$result = $db->query($sql);

		if (DB::isError($result)) {
			//die($result->getMessage());
			$smarty->assign('status', 'Error: Unable to update player');
		}
		else {
			// Reset data in the form fields
			//
			$form->setConstants($elements);
			$smarty->assign('status','Player successfully updated');

      /**
       *  Remove player from stats for the current season
       */
      if ( $archived == "t" ) {
        $qry = "DELETE FROM players_cache WHERE seasons_id = '{$seasons_id}' AND players_id = '{$id}';";
		    $result = $db->query($qry);
      }
			
			/**
			 *	For player profile photos
			 */
			$path = $_SERVER['DOCUMENT_ROOT'] . "/public/images/players/".$shortname."/";
			
			if (!is_dir($path)) {
			    mkdir( $path , 0777 );
			}

			if ($file->isUploadedFile()) {
				$file->moveUploadedFile($path);
				rename($path . $filename , $path . $newfilename);

				/**
				 *	Scale the logo down
				 */
				$width = shell_exec("/usr/local/bin/identify -format %w " . $path . $newfilename);
				$height = shell_exec("/usr/local/bin/identify -format %h " . $path . $newfilename);
				if ($width > $height) {
					$scale = "200x";
				}
				else {
					$scale = "x185";
				}
				$command = "/usr/local/bin/convert -scale " . $scale . " -quality 90 -colorspace RGB +profile \"*\" \"" . $path . $newfilename . "\" \"" .  $path . $newfilename . "\"";
				$result = shell_exec($command);
			}
			
			$cacheDB->get(NSPACE . "_" . $shortname . "_players");
			
			//$sendemail = $data['sendemail'] ? 't' : 'f';
			//if ( $sendemail == 't' ) {
			//	emailPasswordReset( $email );
			//}
			
			// Checking if the league is tour mode and auto populate player to team is true or not
			$sql2 = "SELECT shortname, auto_playertoteam FROM leagues WHERE id = '$leagues_id' AND clubs_id = '$clubs_id';";
			$result2 = $db->getRow($sql2);
			
			//if($result2['shortname'] == 'tour' && $result2['auto_playertoteam'] == 't'){
			if( preg_match('/tour/', $result2['shortname']) && $result2['auto_playertoteam'] == 't'){

				$teamname = $firstname.' '.$lastname;
				
				// IF team name is not same then edit the team name
				if($teamname != $oldteamname ){
					$sqlteam = "SELECT id as teams_id FROM teams WHERE leagues_id = '$leagues_id' AND LOWER(name) = LOWER('$oldteamname');";
					$teams_id = $db->getOne($sqlteam);
					
					$sqlname = "UPDATE teams set name = E'$teamname' WHERE id = '$teams_id';";
					$result = $db->query($sqlname);
					
				}
			}
			header('Location: /admin/players.php?type=view');
		}
	}

	$control = @$form->toArray();

	$smarty->assign("control", $control);
	$smarty->display( 'admin/addupdateplayer.tpl' );

}
elseif ( $_GET['type'] == 'delete' ) {

	// Start the form object
	//
	$form = new HTML_QuickForm('leagues','post','/admin/players.php?type=delete');
	$form->setDefaults('');
	$form->setJsWarnings ("Please correct the following problem(s):", "");

	// All Players
	//
	unset($players);
	$sql = "SELECT players.id AS id,firstname,lastname
			FROM players
			WHERE leagues_id = '$leagues_id'
			ORDER BY lastname ASC, firstname ASC;";

	$result = $db->query($sql);
	while ($row = $result->fetchRow()) {
		$players[$row['id']] = $row['lastname'] . ", " . $row['firstname'];
	}

	
	$form->addElement('header', 'delete', 'Delete a Player');
	$form->addElement('select', 'player', 'Player:', $players);
	$form->addElement('submit', 'submit', 'Delete');

	// Rules
	//
	$form->addRule('player', 'Player is required', 'required', null, 'client');

	if ($form->validate()) {

		$teamname = $row['firstname']." ".$row['lastname'];
		
		$data = $form->exportValues();
		$id = $data['player'];

		$leagues_id = $_SESSION['leagues_id'];
		$clubs_id = $_SESSION['clubs_id'];
		$seasons_id = $_SESSION['seasons_id'];
		
		// Checking if the league is tour mode and auto populate player to team is true or not
		$sql2 = "SELECT shortname, auto_playertoteam, auto_teamtopool FROM leagues WHERE id = '$leagues_id' AND clubs_id = '$clubs_id';";
		$result2 = $db->getRow($sql2);
		
		//if($result2['shortname'] == 'tour' && $result2['auto_playertoteam'] == 't'){
		if( preg_match('/tour/', $result2['shortname']) && $result2['auto_playertoteam'] == 't'){

			$sql = "SELECT firstname,lastname FROM players WHERE leagues_id = '$leagues_id' AND id = '$id';";
			$player = $db->getRow($sql);
			
			$teamname = $player['firstname'].' '.$player['lastname'];
			
			$sqlid = "SELECT id as teams_id FROM teams WHERE leagues_id = '$leagues_id' AND LOWER(name) = LOWER('$teamname');";
			$teams_id = $db->getOne($sqlid);

  		$sql = "begin;";
  		$result = $db->query($sql);

			/**
			 *  Delete Player/Team from Pool
			 */
		  if ( $result2['auto_teamtopool'] == "t" ) {
			    $qry = "DELETE FROM medal_poolsteams WHERE teams_id = '$teams_id';";
			    $result = $db->query($qry);
			}
			
			// DELETION OF Player from Team
			$sqltp = "DELETE FROM teamsplayers WHERE teams_id = '$teams_id' AND players_id = '$id' AND seasons_id = '$seasons_id';";
			$result = $db->query($sqltp);
			
			// DELETION of team itself
			$sqlteam = "DELETE FROM teams WHERE id = '$teams_id';";
			$res = $db->query($sqlteam);

  		if (DB::isError($res)) {
  			$sql = "rollback;";
  			$result = $db->query($sql);
  			//die($result->getMessage());
  			//$smarty->assign('status', 'Error: Unable to delete player');
  		}
  		else {
  			$sql = "commit;";
  			$result = $db->query($sql);
  		}

		}
	
		
		$sql = "begin;";
		$result = $db->query($sql);

		$sql = "DELETE FROM players_cache WHERE players_id = '$id';";
		$result = $db->query($sql);

		$sql = "DELETE FROM players WHERE id = '$id' AND leagues_id = '$leagues_id';";
		$result2 = $db->query($sql);

		if (DB::isError($result) || DB::isError($result2)) {
			$sql = "rollback;";
			$result = $db->query($sql);
			//die($result->getMessage());
			$smarty->assign('status', 'Error: Unable to delete player');
		}
		else {
			$sql = "commit;";
			$result = $db->query($sql);
			
			unset($players[$id]);
			//$form->setConstants(array('player' => $players));

			// I have to set the options variable to null manually
			// because I can not get setConstants to work with a select array
			// http://pear.php.net/bugs/bug.php?id=5251
			//
			$myElement =& $form->getElement('player');
			$myElement->_options = null;
			$myElement->loadArray($players);

			$smarty->assign('status','Player successfully deleted');
		}
	}

	$control = @$form->toArray();

	$smarty->assign("control", $control);
	$smarty->display( 'admin/generic.tpl' );

}
elseif ( $_GET['type'] == 'view' ) {

	// Checking for previous page
	$url = $_SERVER['HTTP_REFERER'];
	preg_match('/\/[a-z0-9]+.php/', $url, $match);
	$previouspage = array_shift($match);
	//echo $_REQUEST['previouspage']; //exit;
	if($previouspage== '/draftsetup.php' || $_REQUEST['previouspage']=='t'){
		//$form->addElement('hidden', 'previouspage', $previouspage);
		$smarty->assign( 'previouspage', $previouspage);
		
	}
	
	// All Players
	//
	unset($players);
	$sql = "SELECT players.id AS id,firstname,lastname,initialhandicap, email, noemail, skins, inactive, archived, ringerboard, deucepot, preferred_tee, profilephoto
			FROM players
			WHERE leagues_id = '$leagues_id' and archived = 'f'
			ORDER BY lastname ASC, firstname ASC;";

	$result = $db->query($sql);
	$activePlayerCount = 0;
	while ($row = $result->fetchRow()) {
		
		if($row['inactive'] == 'f'){
			$activePlayerCount++;	
		}
			
			$players[$row['id']] = array( 'name' => $row['lastname'] . ", " . $row['firstname'],
											'init' => $row['initialhandicap'],
											'email' => $row['email'],
											'preferred_tee' => $row['preferred_tee'],
											'inactive' => $row['inactive'],
											'noemail' => $row['noemail'],
											'skins' => $row['skins'],
											'ringerboard' => $row['ringerboard'],
											'deucepot' => $row['deucepot'],
											'archived' => $row['archived'],
											'profilephoto' => $row['profilephoto']
											
										);
	}
	$players['activeCount'] = $activePlayerCount;
	$smarty->assign( 'players', $players);
	
	
	// Archived Players
	unset($archivedplayers);
	$sql = "SELECT players.id AS id,firstname,lastname,initialhandicap, email, noemail, skins, deucepot, ringerboard, inactive, preferred_tee, profilephoto
			FROM players
			WHERE leagues_id = '$leagues_id' and archived = 't'
			ORDER BY lastname ASC, firstname ASC;";

	$result = $db->query($sql);
	while ($row = $result->fetchRow()) {
			$archivedplayers[$row['id']] = array( 'name' => $row['lastname'] . ", " . $row['firstname'],
											'init' => $row['initialhandicap'],
											'email' => $row['email'],
											'preferred_tee' => $row['preferred_tee'],
											'inactive' => $row['inactive'],
											'archived' => $row['archived'],
											'noemail' => $row['noemail'],
											'skins' => $row['skins'],
											'ringerboard' => $row['ringerboard'],
											'deucepot' => $row['deucepot'],
											'profilephoto' => $row['profilephoto']
										);
	}

	$smarty->assign( 'archivedplayers', $archivedplayers);
	
	$sql = "SELECT showarchive FROM leagues WHERE id = '$leagues_id';";
	$showhidearchived = $db->getOne($sql);
	$smarty->assign( 'showhidearchived', $showhidearchived);
	
	$smarty->display( 'admin/viewplayers.tpl' );

}
elseif ( $_GET['type'] == 'changearchive' ) {
	
	/**
	 *	Quick update players status
	 */
	 
	//$data = $form->exportValues();

	$showarchive = prepData(trim($_POST['showarchive']));
		
	$sql = "UPDATE leagues SET showarchive = '$showarchive' WHERE id = '$leagues_id';";
	$result = $db->query($sql);

	header('Location: /admin/players.php?type=view');
}
elseif ( $_GET['type'] == 'update_players' ) {

	/**
	 *	Quick update players status
	 */
	$sql = "UPDATE players set inactive = 'f', noemail='f', skins='f', ringerboard = 'f', deucepot = 'f' WHERE leagues_id = '$leagues_id' AND archived = 'f';";
	$result = $db->query($sql);

	unset($_POST['submit']);
	//echo "<pre>";
	//print_r($_POST);
	foreach( $_POST as $key => $value ) {
		if ( preg_match('/inactive_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set inactive = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}
		
		if ( preg_match('/archive_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set archived = 't', inactive = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}
		
		
		if ( preg_match('/noemail_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set noemail = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}
		if ( preg_match('/skins_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set skins = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}
		
		if ( preg_match('/ringerboard_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set ringerboard = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}

		if ( preg_match('/deucepot_(\d+)/', $key, $matches) ) {
			$sql = "UPDATE players set deucepot = 't' WHERE id = '{$matches[1]}';";
			$result = $db->query($sql);
		}
		
	}
	if($_REQUEST['previouspage']=='t'){
		//$form->addElement('hidden', 'previouspage', $previouspage);
		header('Location: /admin/players.php?type=view&previouspage=t');		
	}
	else{
		header('Location: /admin/players.php?type=view');
	}
}
else {
	header('Location: /admin/players.php?type=add');
}


function emailPasswordReset( $email ) {

	global $db, $leagues_id;

	$sql = "SELECT id FROM players WHERE leagues_id = '$leagues_id' AND email = '$email';";
	$row = $db->getRow($sql);

	if ( !isset($row['id']) ) {
		return PEAR::raiseError("Unable to find that email address.  Contact your league administrator to add your email address.");
	}

	//$db->query("begin");

	$verification = md5(microtime());

	$sql = "UPDATE players SET verification = '$verification' WHERE email = '$email' AND leagues_id = '$leagues_id';";
	$result = $db->query($sql);

	if (DB::isError($result)) {
		//$db->query("rollback");
		return PEAR::raiseError( "Unable to verify player" );
	}

	//$db->query("commit");

	/**
	 *	Send verification email to player
	 */
	$mail = new PHPMailer();
	$mail->From = "reset@golfscoring.net";
	$mail->FromName = "Golf Scoring";
	
	$mail->IsSendmail();
	//$mail->Host = "tap.net";
	//$mail->Mailer = "smtp";

	$mail->AddAddress( $email );
	$mail->Subject = "golfscoring.net password reset";
	$mail->Body = "Please click on the following link to reset your password.\n\nhttp://www.golfscoring.net/" . $_SESSION['clubshortname'] . "/" . $_SESSION['leaguenameshort'] . "/" . $_SESSION['seasonname'] . "/reset/$verification\n";

	$mail->Send();

	return PEAR::raiseError("An email has been sent to that address.  Click on the link in the email to reset your password.");
}

?>
