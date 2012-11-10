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
common_include('libraries/forms.php');
function populateTimeZones($data) {
    $currentTime=time();
    $times=array();
    $start=$currentTime-date('G',$currentTime)*3600;
    for($i=0;$i<24*60;$i+=15) {
        $times[date('g:i A',$start+$i*60)]=array();
    }
    $timezones=DateTimeZone::listIdentifiers();
    foreach($timezones AS $timezone) {
        $dt=new DateTime('@'.$currentTime);
        $dt->setTimeZone(new DateTimeZone($timezone));
        $time=$dt->format('g:i A');
        $times[$time][]=$timezone;
    }
    $timeZones=array_filter($times);
    foreach($timeZones as $time => $timeZoneList) {
        foreach($timeZoneList as $timeZone) {
            $data->output['timeZones'][]=array(
                'text'  => $time.' - '.$timeZone,
                'value' => $timeZone
            );
        }
    }
}
function checkUserName($name,$db) {
	$statement=$db->prepare('checkUserName','users');
	$statement->execute(array(':name' => $name));
	return $statement->fetchColumn();
}
function users_buildContent($data,$db) {
    populateTimeZones($data);
    switch($data->action[1]){
		case 'recover':
			if($data->action[2]){
				$statement=$db->prepare('getRecoveryUser','users');
				$statement->execute(array(
					':hash' => $data->action[2]
				));
				$user=$statement->fetch(PDO::FETCH_ASSOC);
				if($user){
					// generate pass
					$len=mt_rand(8,12);
					$i=0;
					$pass='';
					while($i<$len){
						$r=rand(0,2);
						if($r===0) {
							$inty=mt_rand(65,90);
						}elseif($r===1) {
							$inty=mt_rand(48,57);
						} else {
							$inty=mt_rand(97,122);
						}
						$i++;
						$pass.=chr($inty);
					}
					common_sendMail($data,$db,(empty($user['contactEMail'])?$user['publicEMail']:$user['contactEMail']),'Your New Password',
						$data->phrases['users']['newPassword'].PHP_EOL.
						$data->phrases['users']['username'].' '.$user['name'].PHP_EOL.
						$data->phrases['users']['password'].' '.$pass);
					$statement=$db->prepare('updateUserField','users',array('!column1!'=>'password'));
					$statement->execute(array(
						':fieldValue' => hash('sha256',$pass),
						':name'       => $user['name'],
					));
					$statement=$db->prepare('deleteDynamicUserField','users');
					$statement->execute(array(
						':userId' => $user['id'],
						':name'   => 'recoveryHash',
					));
				}
			}else{
				common_redirect_local($data,'dynamic-forms/recover-password');
			}
			break;
		case 'edit':
			common_redirect_local($data,'dynamic-forms/edit-profile');
		case 'register':
            if(isset($data->user['id'])){
                common_redirect_local($data,'');
            }elseif(empty($data->action[2])){
				common_redirect_local($data,'dynamic-forms/register');
            } else {
                switch ($data->action[2]) {
                    case 'activate':
                        $data->output['showForm']=false;
                        $userId=$data->action[3];
                        $hash=$data->action[4];
                        /*
                            We have to use a var for time so as to not have accounts
                            'slip through the cracks' waiting for the queries to execute
                        */
                        $expireTime=time();
                        $statement=$db->prepare('getExpiredActivations','users');
                        $statement->execute(array(':expireTime' => $expireTime));
                        $delStatement=$db->prepare('deleteUserById','users');
                        while($user=$statement->fetch()) {
                            $delStatement->execute(array(':userId' => $user['userId']));
                        }
                        $statement=$db->prepare('expireActivationHashes','users');
                        $statement->execute(array(':expireTime' => $expireTime));
                        $statement=$db->prepare('checkActivationHash','users');
                        $statement->execute(array(
                            ':userId' => $userId,
                            ':hash' => $hash
                        ));
                        if($attemptExpires=$statement->fetchColumn()) {
                            // Set Email Verified To True
                            $statement = $db->prepare('updateEmailVerification','users');
                            $statement->execute(array(
                                ':userId' => $userId
                            ));
                            // If Email Verification Is Enough, Then Activate The User.
                            if($data->settings['requireActivation'] == 0) {
                                $statement=$db->prepare('activateUser','users');
                                $statement->execute(array(
                                    ':userId' => $userId
                                ));
                            }
                            $statement=$db->prepare('deleteActivation','users');
                            $statement->execute(array(
                                ':userId' => $userId,
                                ':hash' => $hash
                            ));
                            if($data->settings['requireActivation']==0) {
                                $data->output['messages'][]='
                                        <p>
                                            '.$data->phrases['users']['accountActivated'].'
                                            <a href="'.$data->linkRoot.'login">'.$data->phrases['users']['clickLogin'].'</a>
                                        </p>
                                    ';
                            } else {
                                $data->output['messages'][]='
                                        <p>
                                            '.$data->phrases['users']['emailVerified'].'
                                        </p>
                                    ';
                            }
                        } else {
                            $data->output['messages'][]='
                                    <p>
                                        '.$data->phrases['users']['userIdOrCodeDoesNotExist'].'
                                    </p>
                                ';
                        }
                    break; // case 'activate'
                }
            }
			break; // case 'register'
		case 'logout':
			setcookie($db->sessionPrefix.'SESSID', '', 0, $data->linkHome);
			$statement=$db->prepare('logoutSession');
			$statement->execute(array(
				':sessionID' => $_COOKIE[$db->sessionPrefix.'SESSID']
		    ));
			break;
	}
}
function users_content($data){
	switch($data->action[1]){
		/*case 'edit':
			if(isset($data->output['savedOkMessage'])) {
				echo $data->output['savedOkMessage'];
			} else {
				theme_contentBoxHeader($data->phrases['users']['editingUser']);
				//theme_EditSettings($data);
				theme_buildForm($data->output['userForm']);
				theme_contentBoxFooter();
			}
		break;*/
        case 'logout':
			theme_contentBoxHeader($data->phrases['users']['logout']);
			echo '<p>'.$data->phrases['users']['loggedOut'].'</p>';
			theme_contentBoxFooter();
			break;
        case 'register':
			theme_contentBoxHeader($data->phrases['users']['accountRegistrationActivation']);
			foreach ($data->output['messages'] as $message) {
				echo '<p>',$message,'</p>';
			}
			theme_contentBoxFooter();
			break;
		case 'recover':
			echo '<p>',$data->phrases['users']['newPasswordSent'],'</p>';
			break;
	}
}