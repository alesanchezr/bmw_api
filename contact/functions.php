<?php


class Functions{
    static function addLead($userEmail){
        
        $i = new Insightly($GLOBALS['INSIGLY_KEYS'][Insightly::getSalesUser()]);
        
        $contact = \AC\ACAPI::getContactByEmail($userEmail);
        if(empty($contact)) throw new Exception('The contact was not found in Active Campaign');
        
        $contactInfo = [];
        $contactToSave = [
			'FIRST_NAME' => $contact->first_name,
			'LAST_NAME' => (!empty($contact->last_name)) ? $contact->last_name : 'undefined'
		];
        if(!empty($contact->email)) $contactToSave['CONTACTINFOS'][] = (object) array('TYPE' => 'EMAIL','LABEL' => 'WORK','DETAIL' => $contact->email);
        if(!empty($contact->phone)) $contactToSave['CONTACTINFOS'][] = (object) array('TYPE' => 'PHONE','LABEL' => 'WORK','DETAIL' => $contact->phone);

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
        
        $resp = $i->addContact((object) $contactToSave);
        $resp = $i->addLead((object) $leadToSave);
        
        return $resp;
    }
}