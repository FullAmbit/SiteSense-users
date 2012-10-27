<?php
/*
* SiteSense
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@sitesense.org so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade SiteSense to newer
* versions in the future. If you wish to customize SiteSense for your
* needs please refer to http://www.sitesense.org for more information.
*
* @author     Full Ambit Media, LLC <pr@fullambit.com>
* @copyright  Copyright (c) 2011 Full Ambit Media, LLC (http://www.fullambit.com)
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

function users_beforeForm($data,$db){
	if(isset($data->user['id'])&&$data->user['id']!==0){
		common_redirect_local($data,'');
	}
}

function users_validateDynamicFormField($data,$db,$field,$fieldValue){	
	$fieldRef =& $data->output['customForm']->fields[$field['id']];
	$formError =& $data->output['customForm']->error;
}

function users_validateusername($data,$db,$field,$fieldValue){
	$fieldRef =& $data->output['customForm']->fields[$field['id']];
	$formError =& $data->output['customForm']->error;
	
	// Check If UserName Exists...
	if($data->getUserIdByName($fieldValue)) {
		$formError = true;
    	$fieldRef['error']=true;
    	$fieldRef['errorList'][]='That username already exists.';
    }
}

// Save The UserName To The Database
function users_saveusername($data,$db,$field,$fieldName,$fieldValue){
	// Initial Save...Create User Row
	$statement = $db->prepare('createUserRow','users');
	$r = $statement->execute(array(
		':name' => $fieldValue
	));
	// Get User Id Now.
	$statement=$db->prepare('checkUserName','users');
	$statement->execute(array(
		':name' => $fieldValue
	));
	list($data->user['id']) = $statement->fetch();
	$data->user['name'] = $fieldValue;
	return $r;
}

// Save Password To Database
function users_savepassword($data,$db,$field,$fieldName,$fieldValue){
	$fieldValue=hash('sha256',$fieldValue);
	$statement = $db->prepare('updateUserField','users',array('!column1!' => 'password'));
	$r=$statement->execute(array(
		':name' => $data->user['name'],
		':fieldValue' => $fieldValue
	));
}

// Add Seperate Field Data (Catch-All Function)
function users_saveDynamicFormField($data,$db,$field,$fieldName,$fieldValue){	
	$userColumns = array(
		'username'=>'username',
		'password'=>'password',
		'first_name'=>'firstName',
		'last_name'=>'lastName',
		'contact_email'=>'contactEMail',
		'public_email'=>'publicEMail',
		'time_zone'=>'timeZone'
	);
	if(isset($userColumns[$fieldName])){
		// In Users Table
		$statement = $db->prepare('updateUserField','users',array('!column1!' => $userColumns[$fieldName]));
		$r=$statement->execute(array(
			':name' => $data->user['name'],
			':fieldValue' => $fieldValue
		));
	}elseif(strtolower($field['type'])!=='password'&&!$field['compareTo']){
		// Not Part of Users Table, not a password field or a "retype X" field
		$statement = $db->prepare('addDynamicUserField','users');
		$statement->execute(array(
			':userId' => $data->user['id'],
			':name' => $fieldName,
			':value' => $fieldValue
		));
	}
}

function users_afterForm($data,$db){
	// Get Inserted User Item
	$statement=$db->prepare('getByName','users');
	$statement->execute(array(
		':name' => $data->user['name']
	));
	$userItem = $statement->fetch(PDO::FETCH_ASSOC);
	// Do We Require E-Mail Verification??
	if($data->settings['verifyEmail'] == 1) {
		common_include('modules/users/users.module.php'); // for sendActivationEMail()
        $hash=md5(common_randomPassword(32,32));
		$statement=$db->prepare('insertActivationHash','users');
		$statement->execute(array(
		    ':userId' => $userItem['id'],
		    ':hash' => $hash,
		    ':expires' => date('Y-m-d H:i:s',(time()+(14*24*360)))
		));
		sendActivationEMail($data,$db,$userItem['id'],$hash,$userItem['contactEMail']);
	}
	if(!empty($data->settings['users']['sendConfirmation'])){
		common_loadPhrases($data,$db,'users');
		if(isset($data->phrases['users']['thanksForRegisterSubject'])&&isset($data->phrases['users']['thanksForRegisterContent'])){
			common_sendMail($data,$db,$userItem['contactEMail'],
				$data->phrases['users']['thanksForRegisterSubject'],
				$data->phrases['users']['thanksForRegisterContent']);
		}
	}else{
		$user=$data->user=$userItem;
		if (!empty($user['timeZone']) && $user['timeZone']!==0) {
			date_default_timezone_set($user['timeZone']);
			ini_set('date.timezone', $user['timeZone']);
		}
		getUserPermissions($db, $user);
		$userCookieValue = hash('sha256',$user['id'].'|'.time().'|'.common_randomPassword(32, 64));
		$statement=$db->query('userSessionTimeOut');
		$data->settings['userSessionTimeOut'] = $statement->fetchColumn();
		$expires=time()+$data->settings['userSessionTimeOut'];
		setcookie($db->sessionPrefix.'SESSID',$userCookieValue,$expires,$data->linkHome,'','',true);
		$expires=gmdate("Y-m-d H:i:s", $expires);
		$statement=$db->prepare('updateUserSession');
		$statement->execute(array(
			':sessionId' => $userCookieValue,
			':userId'    => $user['id'],
			':expires'   => $expires,
			':ipAddress' => $_SERVER['REMOTE_ADDR'],
			':userAgent' => $_SERVER['HTTP_USER_AGENT']
		));
	}
	// Insert into group
	if($data->settings['defaultGroup']!==0) {
		$statement=$db->prepare('addUserToPermissionGroupNoExpires');
		$statement->execute(array(
			':userID'          => $userItem['id'],
			':groupName'       => $data->settings['defaultGroup']
		));
	}
	
	// Update Registered IP, Registered Date And Last Access
	$statement=$db->prepare('updateIPDateAndAccess','users');
	$r = $statement->execute(array(
		':userID' => $userItem['id'],
		':registeredIP' => $_SERVER['REMOTE_ADDR'],
	));
}

// Send Activation EMail
function sendActivationEMail($data,$db,$userId,$hash,$sendToEmail) {
    $statement=$db->prepare('getRegistrationEMail','users');
    $statement->execute();
    if ($mailBody=$statement->fetchColumn()) {
        $mailBody = html_entity_decode($mailBody,ENT_QUOTES,'UTF-8');
        $activationLink='http://'.$_SERVER['SERVER_NAME'].$data->linkRoot.'users/register/activate/'.$userId.'/'.$hash;
        $mailBody=str_replace(
            array(
                '$siteName',
                '$registerLink'
            ),
            array(
                $data->settings['siteTitle'],
                '<a href="'.$activationLink.'">'.$activationLink.'</a>'
            ),
            $mailBody
        );
        $subject=$data->settings['siteTitle'].' Activation Link';
        $header='From: Account Activation - '.$data->settings['siteTitle'].'<'.$data->settings['register']['sender'].">\r\n".
            'Reply-To: '.$data->settings['register']['sender']."\r\n".
            'X-Mailer: PHP/'.phpversion()."\r\n".
            'Content-Type: text/html';
        $content='<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html><head>
<title>'.$data->phrases['users']['activateAccountPageTitle'].'</title>
</head><body>
'.$mailBody.'
</body></html>';
        $data->output['messages'][]='
	  	<p>
	  		'.$data->phrases['users']['activationLink1'].$sendToEmail.$data->phrases['users']['activationLink2'].'
	  	</p>
	  ';
        if (mail(
            $sendToEmail,
            $subject,
            $content,
            $header
        )) {
            return true;
        } else die('A Fatal error occurred in the mail subsystem');
    } else {
        $data->output['messages'][]='
			<p>
				Activation e-mail has been deleted! Please contact the administrator.
			</p>
		';
    }
    return false;
}