<?php
function admin_users_updater_10_101($data,$db){
	$statement=$db->prepare('getFormByShortName','admin_dynamicForms',array('!lang!'=>'_en_us'));
	$statement->execute(array(
		':shortName' => 'register',
	));
	$oldForm=$statement->fetch(PDO::FETCH_ASSOC);
	if($oldForm){
		$statement=$db->prepare('deleteForm','admin_dynamicForms',array('!lang!'=>'_en_us'));
		$statement->execute(array(
			':id' => $oldForm['id'],
		));
		$statement=$db->prepare('deleteFieldsByForm','admin_dynamicForms',array('!lang!'=>'_en_us'));
		$statement->execute(array(
			':id' => $oldForm['id'],
		));
	}
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
		)
	);
	$fieldIds=array();
	$statement = $db->prepare('newField','admin_dynamicForms',array('!lang!'=>'_en_us'));
	foreach($fields as $fieldShortName => $fieldVars){
		$statement->execute($fieldVars);
		$fieldIds[$fieldShortName]=$db->lastInsertId();
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
	}
	return TRUE;
}