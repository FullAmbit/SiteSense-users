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
	if(empty($data->user['id'])){
		common_loadPhrases($data,$db,'users');
		$data->output['responseMessage']=
			sprintf($data->phrases['users']['requiresLogin'],$data->phrases['users']['updateProfile']);
		return FALSE;
	}
	$data->output['editingField']=TRUE;
}
function users_loadDynamicFormFieldValue($data,$db,$field){
	$userColumns = array(
		'username'=>'name',
		'first_name'=>'firstName',
		'last_name'=>'lastName',
		'contact_email'=>'contactEMail',
		'public_email'=>'publicEMail',
		'time_zone'=>'timeZone'
	);
	if(isset($userColumns[common_generateShortName($field['name'],TRUE)])){
		return $data->user[$userColumns[common_generateShortName($field['name'],TRUE)]];
	}else{
		$statement=$db->prepare('getDynamicUserField','users');
		$statement->execute(array(
			':userId' => $data->user['id'],
			':name'   => common_generateShortName($field['name'],TRUE),
		));
		if($out=$statement->fetch(PDO::FETCH_ASSOC)){
			return $out['value'];
		}else{
			return '';
		}
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
	if($data->getUserIdByName($fieldValue)&&$fieldValue!==$data->user['name']) {
		$formError = true;
    	$fieldRef['error']=true;
    	$fieldRef['errorList'][]='That username already exists.';
    }
	if(empty($fieldValue)) {
		$formError = true;
    	$fieldRef['error']=true;
    	$fieldRef['errorList'][]='You may not have an empty username.';
    }
}
function users_savepassword($data,$db,$field,$fieldName,$fieldValue){
	if(!empty($fieldValue)){
		$fieldValue=hash('sha256',$fieldValue);
		$statement = $db->prepare('updateUserField','users',array('!column1!' => 'password'));
		$r=$statement->execute(array(
			':name' => $data->user['name'],
			':fieldValue' => $fieldValue
		));
	}
}
function users_saveDynamicFormField($data,$db,$field,$fieldName,$fieldValue){
	$userColumns = array(
		'username'=>'name',
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
			':fieldValue' => htmlentities($fieldValue,ENT_QUOTES,'UTF-8'),
		));
	}elseif(strtolower($field['type'])!=='password'&&!$field['compareTo']){
		$statement=$db->prepare('getDynamicUserField','users');
		$statement->execute(array(
			':userId' => $data->user['id'],
			':name'   => common_generateShortName($field['name'],TRUE),
		));
		if($out=$statement->fetch(PDO::FETCH_ASSOC)){ //update
			$statement = $db->prepare('updateDynamicUserField','users');
		}else{ //add
			$statement = $db->prepare('addDynamicUserField','users');
		}
		$statement->execute(array(
			':userId' => $data->user['id'],
			':name' => $fieldName,
			':value' => htmlentities($fieldValue,ENT_QUOTES,'UTF-8'),
		));
	}
}
function users_afterForm($data,$db){
}