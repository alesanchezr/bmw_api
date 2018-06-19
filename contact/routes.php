<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Carbon\Carbon;
use \BreatheCode\BCWrapper;

require('../vendor-static/ActiveCampaign/ACAPI.php');
require('../vendor-static/insightly/insightly.php');

\AC\ACAPI::start(AC_API_KEY);
\AC\ACAPI::setupEventTracking('798615081', AC_EVENT_KEY);

function addAPIRoutes($api){

	$api->get('/all', function(Request $request, Response $response, array $args) use ($api) {
        $contacts = $api->db['mysql']->contact()->orderBy( 'subscribe_date', 'DESC' )->fetchAll();
		return $response->withJson($contacts);	
	});
	
	$api->get('/{contact_id}', function(Request $request, Response $response, array $args) use ($api) {
		
		$api->validate($args['contact_id'])->int();
		
        $row = $api->db['mysql']->contact()
			->where('id',$args['contact_id'])->fetch();
		return $response->withJson($row);	
	});
	
	$api->post('/insightly/sync', function (Request $request, Response $response, array $args) use ($api) {
	    
        $parsedBody = $request->getParsedBody();
        $userEmail = null;
        if(!empty($parsedBody['email'])) $userEmail = $parsedBody['email'];
        else if(isset($parsedBody['contact']['email'])) $userEmail = $parsedBody['contact']['email'];
        else throw new Exception('Please specify the user email', 404);
        
        $contact = \AC\ACAPI::getContactByEmail($userEmail);
        if(empty($contact)) return $response->withJson(['The contact was not found']);
        
        $contactInfo = [];
        $contactToSave = [
			'FIRST_NAME' => $contact->first_name,
			'LAST_NAME' => (!empty($contact->last_name)) ? $contact->last_name : 'undefined'
		];
        $contactToSave['CONTACTINFOS'][] = (object) array('TYPE' => 'EMAIL','LABEL' => 'WORK','DETAIL' => $contact->email);
        $contactToSave['CONTACTINFOS'][] = (object) array('TYPE' => 'PHONE','LABEL' => 'WORK','DETAIL' => $contact->phone);

		$leadToSave = [
			'FIRST_NAME' => $contact->first_name,
			'LAST_NAME' => (!empty($contact->last_name)) ? $contact->last_name : 'undefined',
 			'TITLE' =>  $contact->first_name.' '.$contact->last_name,
            'EMAIL_ADDRESS' => $contact->email,
            'MOBILE_PHONE_NUMBER' => $contact->phone,
            'PHONE_NUMBER' => $contact->phone,
            'DATE_CREATED_UTC' => $contact->sdate
		];
		
		$leadToSave['CUSTOMFIELDS'] = [];
        foreach($contact->fields as $id => $field){
            if($field->perstag == 'CLIENT_COMMENTS') $leadToSave['CUSTOMFIELDS'][] =  (object) [ 'CUSTOM_FIELD_ID'=>'client_comments__c', 'FIELD_VALUE'=>$field->val];
            else if($field->perstag == 'UTMFORM') $leadToSave['CUSTOMFIELDS'][] = (object) [ 'CUSTOM_FIELD_ID'=>'utm_form__c', 'FIELD_VALUE'=>$field->val];
            else if($field->perstag == 'EVENT_DATE') $leadToSave['CUSTOMFIELDS'][] = (object) [ 'CUSTOM_FIELD_ID'=>'event_date__c', 'FIELD_VALUE'=>$field->val];
            else if($field->perstag == 'BUDGET') $leadToSave['CUSTOMFIELDS'][] = (object) [ 'CUSTOM_FIELD_ID'=>'budget__c', 'FIELD_VALUE'=>$field->val];
            else if($field->perstag == 'UTMCAMPAIGN') $leadToSave['CUSTOMFIELDS'][] = (object) [ 'CUSTOM_FIELD_ID'=>'utm_campaign__c', 'FIELD_VALUE'=>$field->val];
        }

        $i = new Insightly(INSIGLY_KEY);
        $resp = $i->addContact((object) $contactToSave);
        $resp = $i->addLead((object) $leadToSave);
        
        return $response->withJson($resp);
	});
	
	$api->post('/sync', function (Request $request, Response $response, array $args) use ($api) {
        
        $log = [];
        $parsedBody = $request->getParsedBody();
        
        $userEmail = null;
        if(!empty($parsedBody['email'])) $userEmail = $parsedBody['email'];
        else if(isset($parsedBody['contact']['email'])) $userEmail = $parsedBody['contact']['email'];
        else throw new Exception('Please specify the user email', 404);
        
        try{
            $contact = \AC\ACAPI::getContactByEmail($userEmail);
        }catch(Exception $e){ return $response->withJson(['The contact was not found']); }
        if(empty($contact)) return $response->withJson(['The contact was not found']);
        
        $contactToSave = [
			'ac_id' => $contact->ID,
			'first_name' => $contact->first_name,
			'last_name' => $contact->last_name,
			'email' => $contact->email,
			'phone' => $contact->phone,
			'subscribe_date' => $contact->sdate,
			'ac_id' => $contact->name
		];
        foreach($contact->fields as $id => $field){
            if($field->perstag == 'UTM_LANGUAGE') $contactToSave['utm_language'] = $field->val;
            if($field->perstag == 'EVENT_DATE') $contactToSave['event_date'] = $field->val;
            if($field->perstag == 'COMMENTS') $contactToSave['comments'] = $field->val;
            if($field->perstag == 'PREFERRED_WEDDING_PLANNER') $contactToSave['preferred_wedding_planner'] = $field->val;
            if($field->perstag == 'BUDGET') $contactToSave['budget'] = $field->val;
            if($field->perstag == 'CLIENT_COMMENTS') $contactToSave['client_comments'] = $field->val;
            if($field->perstag == 'UTMCAMPAIGN') $contactToSave['utm_campaign'] = $field->val;
            if($field->perstag == 'UTMFORM') $contactToSave['utm_form'] = $field->val;
        }
        
        try{
			$row = $api->db['mysql']->createRow( 'contact', $contactToSave);
			$row->save();
        }catch(Exception $e){ return $response->withJson(['The contact was not saved']); }
        
        return $response->withJson($row);
	});

	// $api->get('/{email}', function(Request $request, Response $response, array $args) use ($api) {
        
	// 	if(empty($args['email'])) throw new Exception('Invalid param email', 400);
		
 //       $contact = \AC\ACAPI::getContactByEmail($args['email']);
 //       if(empty($contact)) $response->withJson("The user was not found in active campaign",400);

	// 	return $response->withJson($contact);	
	// });
	
	return $api;
}