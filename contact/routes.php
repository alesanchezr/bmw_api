<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Carbon\Carbon;
use \BreatheCode\BCWrapper;

require('../vendor-static/ActiveCampaign/ACAPI.php');
require('../vendor-static/insightly/insightly.php');
require('./functions.php');

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

        $failed = $api->db['failed']->getJsonByName('leads');
        try{
            $resp = Functions::addLead($userEmail);
            $failed = array_filter($failed, function($attempt) use ($userEmail){ 
                return ($attempt->email != $userEmail);
            });
            
            return $response->withJson($resp);
        }catch(Exception $e){
            
            $isPresent = (count(array_filter($failed, function($attempt) use ($userEmail){ 
                return ($attempt->email == $userEmail);
            })) > 0);
            if($isPresent){
                $failed = array_map(function($attempt) use ($userEmail){
                    if($attempt->email == $userEmail){
                        $found = true;
                        $attempt->attemps++;
                        $attempt->last_attempt = time();
                    }
                    return $attempt;
                }, $failed);
            }
            else{
                array_push($failed, ['email' => $userEmail, "last_attempt" => time(), "attemps" => 1, "error" => $e->getMessage()]);
            }
            $api->db['failed']->toFile('leads')->save($failed);

            throw $e;
        }
        
	});
	
	$api->post('/pending/sync', function (Request $request, Response $response, array $args) use ($api) {
	    
	    $failed = $api->db['failed']->getJsonByName('leads');
	    $resp = [];
	    if(count($failed)==0) return $response->withJson("Nothing to process"); 
	    foreach($failed as $contact){
            try{
                Functions::addLead($contact->email);
                $resp[] = "SUCCESS: The contact $contact->email was added into insiglty";
                $failed = array_filter($failed, function($attempt) use ($contact){ 
                    return ($attempt->email != $contact->email);
                });
                $api->db['failed']->toFile('leads')->save($failed);
            }catch(Exception $e){
                
                $resp[] = "FAIL: The contact $contact->email was NOT added into insiglty: ".$e->getMessage();
                $failed = array_map(function($attempt) use ($contact){
                    if($attempt->email == $contact->email){
                        $found = true;
                        $attempt->attemps++;
                        $attempt->last_attempt = time();
                    }
                    return $attempt;
                }, $failed);
                $api->db['failed']->toFile('leads')->save($failed);
            }
	    }
	    
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