<?php
function users_beforeForm($data,$db){
	if(isset($data->user['id'])&&$data->user['id']!==0){
		common_redirect_local($data,'');
	}
}
function users_validateDynamicFormField($data,$db,$fieldItem,$fieldValue){
	if($fieldItem['apiFieldToMapTo']==='username'){
		$fieldValues=array();
		$data->output['username']=$fieldValue; // don't tell user if username exists - disclosure of user information, however, insignificant, is a no-no
	}
}
function users_saveDynamicFormField($data,$db,$fieldName,$fieldValue){
}
function users_afterForm($data,$db){
	$statement=$db->prepare('getByName','users');
	$statement->execute(array(
		':name' => $data->output['username'],
	));
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
		common_sendMail($data,$db,$email,'Password Recovery',
			$data->phrases['users']['recoverPassword'].PHP_EOL.
			$data->domainName.$data->linkRoot.'users/recover/'.$hash);
	}
}