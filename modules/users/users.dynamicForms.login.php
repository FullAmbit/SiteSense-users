<?php
function users_beforeForm($data,$db){
	if(isset($data->user['id'])&&$data->user['id']!==0){
		common_loadPhrases($data,$db,'users');
		$data->output['responseMessage']=$data->phrases['users']['cantLoggedIn'];
		return FALSE;
	}
	if(!empty($data->action[3])){
		setcookie($db->sessionPrefix.'from',$data->action[3],time()+3600*72,$data->linkHome);
	}
}
function users_validateDynamicFormField($data,$db,$fieldItem,$fieldValue){
	if($fieldItem['name']=='Username'){
		$fieldValues=array();
		foreach($data->output['customForm']->fields as $field){
			$fieldValues[strtolower($field['label'])]=$field;
		}
		$statement=$db->prepare('checkPassword','users');
		$statement->execute(array(
			':name' => $fieldValues['username']['value'],
			':passphrase' => hash('sha256',$fieldValues['password']['value'])
		));
		if ($user=$statement->fetch(PDO::FETCH_ASSOC)) {
			$data->user=$user;
			return TRUE;
		}else{
			$data->output['customForm']->error=TRUE;
			$data->output['customForm']->fields[$fieldValues['username']['name']]['error']=TRUE;
			$data->output['customForm']->fields[$fieldValues['username']['name']]['errorList'][]='Invalid username or password.';
			$data->output['customForm']->fields[$fieldValues['password']['name']]['error']=TRUE;
			$data->output['customForm']->fields[$fieldValues['password']['name']]['errorList'][]='Invalid username or password.';
			return FALSE;
		}
	}
}
function users_saveDynamicFormField($data,$db,$fieldName,$fieldValue){
}
function users_afterForm($data,$db){
	if(!empty($data->user)){
		$user=$data->user;
		// Set User TimeZone
		if (!empty($user['timeZone']) && $user['timeZone']!==0) {
			date_default_timezone_set($user['timeZone']);
			ini_set('date.timezone', $user['timeZone']);
		}
		// Load permissions
		getUserPermissions($db, $user);
		// Purge existing sessions containing user ID
		$statement=$db->prepare('purgeSessionByUserId');
		$statement->execute(array(
			'userId' => $user['id']
		));
		// Create new session
		$userCookieValue = hash('sha256',
			$user['id'].'|'.time().'|'.common_randomPassword(32, 64)
		);
		// Push expiration ahead
		$statement=$db->query('userSessionTimeOut');
		$data->settings['userSessionTimeOut'] = $statement->fetchColumn();
		$expires=time()+$data->settings['userSessionTimeOut'];
		if(isset($fieldValues['keep me logged in'])&&$fieldValues['keep me logged in']=='on'){
			$expires = time()+604800; // 1 week
		}
		// Update and sync cookie to server values
		setcookie($db->sessionPrefix.'SESSID', $userCookieValue, $expires, $data->linkHome, '', '', true);
		setcookie($db->sessionPrefix.'from','',time()-3600,$data->linkHome,'','',true);
		$expires=gmdate("Y-m-d H:i:s", $expires);
		$statement=$db->prepare('updateUserSession');
		$statement->execute(array(
			':sessionId' => $userCookieValue,
			':userId'    => $user['id'],
			':expires'   => $expires,
			':ipAddress' => $_SERVER['REMOTE_ADDR'],
			':userAgent' => $_SERVER['HTTP_USER_AGENT']
		));
		if(!empty($_COOKIE[$db->sessionPrefix.'from'])){
			common_redirect_local($data,$_COOKIE[$db->sessionPrefix.'from']);
		}
		return TRUE;
	}
}