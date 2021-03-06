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
function users_settings() {
	return array(
		'name'      => 'users',
		'shortName' => 'users',
		'version'   => '1.0.1'
	);
}
function users_install($db, $drop=false, $firstInstall = FALSE, $lang = "en_us") {
	if($firstInstall){
		$structures = array(
			'activations' => array(
				'userId'              => SQR_IDKey,
				'hash'                => 'VARCHAR(255)',
				'expires'             => SQR_time
			),
			'banned' => array(
				'id'                  => SQR_IDKey,
				'userId'              => SQR_ID,
				'email'               => SQR_email,
				'ipAddress'           => SQR_IP,
				'timestamp'           => SQR_added,
				'expiration'          => SQR_time,
				'UNIQUE KEY `userId` (`userId`)'
			),
			'sessions' => array(
				'sessionId'           => 'VARCHAR(255) NOT NULL PRIMARY KEY',
				'userId'              => SQR_ID,
				'expires'             => SQR_time,
				'ipAddress'           => SQR_IP,
				'userAgent'           => 'VARCHAR(255)',
	            //'language'            => 'VARCHAR(64) NOT NULL DEFAULT""',
				'KEY `userId` (`userId`,`expires`)'
			),
			'users' => array(
				'id'                  => SQR_IDKey,
				'name'                => SQR_username,
				'password'            => SQR_password,
				'firstName'           => SQR_firstName,
				'lastName'            => SQR_lastName,
				'registeredDate'      => SQR_added,
				'registeredIP'        => SQR_IP,
				'lastAccess'          => SQR_time,
				'contactEMail'        => SQR_email,
				'publicEMail'         => SQR_email,
				'emailVerified'       => SQR_boolean.' DEFAULT \'0\'',
				'activated'           => SQR_boolean.' DEFAULT \'0\'',
				'timeZone'     => 'VARCHAR(63) DEFAULT NULL',
				'defaultLanguage'     => 'VARCHAR(64) NOT NULL DEFAULT ""'
			),
			'users_dynamic_fields' => array(
				'id'                  => SQR_IDKey,
				'userId'              => SQR_ID,
				'name'                => 'VARCHAR(255) NOT NULL',
				'value'               => 'VARCHAR(255) NOT NULL',
				'UNIQUE KEY (`name`,`userId`)'
			),
			'user_groups' => array(
				'userID'              => SQR_ID,
				'groupName'           => SQR_name,
				'expires'             => SQR_time
			),
			'user_group_permissions' => array(
				'groupName'           => SQR_name,
				'permissionName'      => SQR_name,
				'value'               => 'TINYINT(1) NOT NULL'
			),
			'user_permissions' => array(
				'userId'              => SQR_ID,
				'permissionName'      => SQR_name,
				'value'               => 'TINYINT(1) NOT NULL'
			)
		);
		if ($drop)
			users_uninstall($db);
	
		$db->createTable('activations', $structures['activations'], false);
		$db->createTable('banned', $structures['banned'], false);
		$db->createTable('sessions', $structures['sessions'], false);
		$db->createTable('users', $structures['users'], false);
		$db->createTable('users_dynamic_fields',$structures['users_dynamic_fields'],false);
		$db->createTable('user_groups', $structures['user_groups'], false);
		$db->createTable('user_group_permissions', $structures['user_group_permissions'], false);
		$db->createTable('user_permissions', $structures['user_permissions'], false);
	
		// Set up default permission groups
		$defaultPermissionGroups=array(
			'Moderator' => array(
				'users_access',
				'users_accessOthers',
				'users_activate',
				'users_add',
				'users_ban',
				'users_edit',
				'users_delete',
				'users_groups'
			),
			'Writer' => array(
				'users_access',
				'users_edit'
			),
			'Blogger' => array(
				'users_access',
				'users_edit'
			),
			'User' => array(
				'users_access'
			),
		);
		$statement=$db->prepare('addPermissionByGroupName');
		foreach ($defaultPermissionGroups as $groupName => $permissions) {
			foreach ($permissions as $permissionName) {	
				$statement->execute(array(
						':groupName' => $groupName,
						':permissionName' => $permissionName,
						':value' => '1'
				));
			}
		}
		// This code loads all the permissions from the config files to create the Admin and Banned usergroups.
		class psuedoData{
			var $permissions=array('core'=>array('access','permissions')),$admin=array();
		}
		$psuedo=new psuedoData();
		$adminConfigs=glob('modules/*/admin/*.config.php');
		foreach($adminConfigs as $configFile){
			common_include($configFile);
			$configFile=explode('/',$configFile);
			@call_user_func_array($configFile[1].'_admin_config',array($psuedo,$db));
		}
		$psuedo->permissions['core']=array(
			'permissions',
			'access'
		);
		foreach($psuedo->permissions as $groupName=>$permissions){
			foreach($permissions as $permissionName => $value){
				$statement->execute(array(
						':groupName' => 'Admin',
						':permissionName' => $groupName.'_'.$permissionName,
						':value' => '1',
				));
				$statement->execute(array(
						':groupName' => 'Banned',
						':permissionName' => $groupName.'_'.$permissionName,
						':value' => '-1',
				));
			}
		}
		// Create Dynamic-Registration Form
		$statement = $db->prepare('newForm','admin_dynamicForms',array('!lang!'=>'_en_us'));
		$statement->execute(array(
			':enabled' => 1,
			':shortName' => 'register',
			':name' => 'Register',
			':title' => 'User Registration',
			':rawContentBefore' => '',
			':parsedContentBefore' => '',
			':rawContentAfter' => '',
			':parsedContentAfter' => '',
			':rawSuccessMessage' => 'Thank you for signing up!',
			':parsedSuccessMessage' => 'Thank you for signing up!',
			':requireLogin' => 0,
			':topLevel' => 1,
			':eMail' => '',
			':submitTitle' => 'Register Now',
			':api' => 0
		));
		$registerFormId = $db->lastInsertId();
		$statement->execute(array(
			':enabled' => 1,
			':shortName' => 'login',
			':name' => 'Login',
			':title' => 'Log in',
			':rawContentBefore' => '',
			':parsedContentBefore' => '',
			':rawContentAfter' => '',
			':parsedContentAfter' => '',
			':rawSuccessMessage' => 'You are now logged in.',
			':parsedSuccessMessage' => 'You are now logged in.',
			':requireLogin' => 0,
			':topLevel' => 1,
			':eMail' => '',
			':submitTitle' => 'Log In',
			':api' => 0
		));
		$loginFormId = $db->lastInsertId();
		$statement->execute(array(
			':enabled' => 1,
			':shortName' => 'edit-profile',
			':name' => 'Edit Profile',
			':title' => 'Update your Profile',
			':rawContentBefore' => '',
			':parsedContentBefore' => '',
			':rawContentAfter' => '',
			':parsedContentAfter' => '',
			':rawSuccessMessage' => 'Your profile has been updated.',
			':parsedSuccessMessage' => 'Your profile has been updated.',
			':requireLogin' => 1,
			':topLevel' => 1,
			':eMail' => '',
			':submitTitle' => 'Save Changes',
			':api' => 0
		));
		$profileFormId = $db->lastInsertId();
		$statement->execute(array(
			':enabled' => 1,
			':shortName' => 'recover-password',
			':name' => 'Recover Password',
			':title' => 'Recover Your Password',
			':rawContentBefore' => '',
			':parsedContentBefore' => '',
			':rawContentAfter' => '',
			':parsedContentAfter' => '',
			':rawSuccessMessage' => 'An email has been sent to the contact address on file for that account with further instructions.',
			':parsedSuccessMessage' => 'An email has been sent to the contact address on file for that account with further instructions.',
			':requireLogin' => 0,
			':topLevel' => 1,
			':eMail' => '',
			':submitTitle' => 'Proceed',
			':api' => 0
		));
		$recoveryFormId = $db->lastInsertId();
		// Create Fields
		$fields = array(
			'firstName' => array(
				':form' => $registerFormId,
				':name' => 'First Name',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 2,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'lastName' => array(
				':form' => $registerFormId,
				':name' => 'Last Name',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 3,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'username' => array(
				':form' => $registerFormId,
				':name' => 'Username',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 1,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'password' => array(
				':form' => $registerFormId,
				':name' => 'Password',
				':type' => 'password',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 4,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'contactEMail' => array(
				':form' => $registerFormId,
				':name' => 'Contact EMail',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 6,
				':isEmail' => 1,
				':compareTo' => 0
			),
			'timeZone' => array(
				':form' => $registerFormId,
				':name' => 'Time Zone',
				':type' => 'timezone',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 8,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'loginUsername' => array(
				':form' => $loginFormId,
				':name' => 'Username',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.login',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 1,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'loginPassword' => array(
				':form' => $loginFormId,
				':name' => 'Password',
				':type' => 'password',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.login',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 2,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'loginKeep' => array(
				':form' => $loginFormId,
				':name' => 'Keep me logged in',
				':type' => 'checkbox',
				':description' => '',
				':enabled' => 1,
				':required' => 0,
				':moduleHook' => 'users.login',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 3,
				':isEmail' => 0,
				':compareTo' => 0
			),
			'recoveryUsername' => array(
				':form' => $recoveryFormId,
				':name' => 'Email Address',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.recover',
				':apiFieldToMapTo' => 'email',
				':sortOrder' => 1,
				':isEmail' => 1,
				':compareTo' => 0
			),
		);
		$fieldIds=array();
		$statement = $db->prepare('newField','admin_dynamicForms',array('!lang!'=>'_en_us'));
		foreach($fields as $fieldShortName => $fieldVars){
			$statement->execute($fieldVars);
			$fieldIds[$fieldShortName]=$db->lastInsertId();
			if($fieldVars[':moduleHook']==='users.register'){
				$statement->execute(
					array(
						':form'      => $profileFormId,
						':moduleHook'=> 'users.profile',
						':required'  => 0,
						':isEmail'   => 0,
					)+$fieldVars
				);
				$fieldIds['profile-'.$fieldShortName]=$db->lastInsertId();
			}
		}
		$fields=array(
			'retypeContactEMail' => array(
				':form' => $registerFormId,
				':name' => 'Retype EMail',
				':type' => 'textbox',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 7,
				':isEmail' => 1,
				':compareTo' => $fieldIds['contactEMail'],
			),
			'retypePassword' => array(
				':form' => $registerFormId,
				':name' => 'Retype Password',
				':type' => 'password',
				':description' => '',
				':enabled' => 1,
				':required' => 1,
				':moduleHook' => 'users.register',
				':apiFieldToMapTo' => NULL,
				':sortOrder' => 5,
				':isEmail' => 0,
				':compareTo' => $fieldIds['password'],
			),
		);
		foreach($fields as $fieldShortName => $fieldVars){
			$statement->execute($fieldVars);
			if($fieldShortName==='retypePassword'){
				$statement->execute(
					array(
						':form'      => $profileFormId,
						':compareTo' => $fieldIds['profile-password'],
						':moduleHook'=> 'users.profile',
						':required'  => 0,
					)+$fieldVars
				);
			}
		}
		// Insert URL Remap For Registration
		common_include('libraries/admin.common.php');
		$statement = $db->prepare('insertUrlRemap','admin_urls');
		$statement->execute(array(
			':match' => '^register(/.*)?$',
			':replace' => 'dynamic-forms/register/\1',
			':hostname' => '',
			':isRedirect' => 0,
			':regex' => 0,
			':sortOrder' => $db->countRows('urls')+1
		));
		$statement->execute(array(
			':match' => '^login(/.*)?$',
			':replace' => 'dynamic-forms/login/\1',
			':hostname' => '',
			':isRedirect' => 1,
			':regex' => 0,
			':sortOrder' => $db->countRows('urls')+1
		));
		$statement->execute(array(
			':match' => '^users/login(/.*)?$',
			':replace' => 'dynamic-forms/login/\1',
			':hostname' => '',
			':isRedirect' => 0,
			':regex' => 0,
			':sortOrder' => $db->countRows('urls')+1
		));
		// Generate an admin account if this is a fresh installation
		if ($db->countRows('users')==0) {
			try {
				$newPassword=common_randomPassword();
				echo '
					<h3>Attempting to add admin user</h3>';
				$statement=$db->prepare('addUser', 'installer');
				$statement->execute(array(
						':name' => 'admin',
						':passphrase' => hash('sha256', $newPassword),
						':registeredIP' => $_SERVER['REMOTE_ADDR']
					));
				echo '
					<p>Administrator account automatically generated!</p>';
				return $newPassword;
			} catch(PDOException $e) {
				$db->installErrors++;
				echo '
					<h3 class="error">Failed to create administrator account!</h3>
					<pre>', $e->getMessage(), '</pre><br />';
			}
		} else echo '<p class="exists">"users database" already contains records</p>';
	}
	$statement=$db->prepare('addSetting','installer',array('!lang!'=>'_en_us'));
	$statement->execute(array(
		':name'     => 'sendConfirmation',
		':category' => 'users',
		':value'    => '0',
	));
}
function users_uninstall($db) {
	$db->dropTable('activations');
	$db->dropTable('banned');
	$db->dropTable('sessions');
	$db->dropTable('users');
	$db->dropTable('user_groups');
	$db->dropTable('user_group_permissions');
	$db->dropTable('user_permissions');
}