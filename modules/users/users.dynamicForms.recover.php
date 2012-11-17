<?php
function users_beforeForm($data,$db){
	if(isset($data->user['id'])&&$data->user['id']!==0){
		$data->output['responseMessage']='You are logged in, you may not recover a password.';
		return FALSE;
	}
}
function users_validateDynamicFormField($data,$db,$fieldItem,$fieldValue){
	$data->output['recover'][$fieldItem['apiFieldToMapTo']]=$fieldValue;
}
function users_saveDynamicFormField($data,$db,$fieldName,$fieldValue){
}
function users_afterForm($data,$db){
	if(isset($data->output['recover']['username'])){
		$statement=$db->prepare('getByName','users');
		$statement->execute(array(
			':name' => $data->output['recover']['username'],
		));
	}elseif(isset($data->output['recover']['email'])){
		$statement=$db->prepare('getByEmail','users');
		$statement->execute(array(
			':email' => $data->output['recover']['email'],
		));
	}
	$user=$statement->fetch(PDO::FETCH_ASSOC);
	if($user&&(!empty($user['contactEMail'])||!empty($user['publicEMail']))){
		if(isset($user['contactEMail'])){
			$email=$user['contactEMail'];
		}else{
			$email=$user['publicEMail'];
		}
		$statement=$db->prepare('getDynamicUserField','users');
		$statement->execute(array(
			':userId' => $user['id'],
			':name'   => 'recoveryHash',
		));
		$hash=openssl_random_pseudo_bytes(mt_rand(16,32));
		$hash=bin2hex($hash); // this will be 2x the length of the openssl call b/c it is hexified
		if($statement->fetchColumn(0)){
			$statement=$db->prepare('updateDynamicUserField','users');
		}else{
			$statement=$db->prepare('addDynamicUserField','users');
		}
		$statement->execute(array(
			':userId' => $user['id'],
			':name'   => 'recoveryHash',
			':value'  => $hash,
		));
		common_loadPhrases($data,$db,'users');
		common_sendMail($data,$db,$email,'Password Recovery',
			$data->phrases['users']['recoverPassword'].PHP_EOL.
			$data->domainName.$data->linkRoot.'users/recover/'.$hash);
	}
}