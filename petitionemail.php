<?php

require_once 'petitionemail.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function petitionemail_civicrm_config(&$config) {
  _petitionemail_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function petitionemail_civicrm_xmlMenu(&$files) {
  _petitionemail_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function petitionemail_civicrm_install() {
  return _petitionemail_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function petitionemail_civicrm_uninstall() {
  return _petitionemail_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function petitionemail_civicrm_enable() {
  return _petitionemail_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function petitionemail_civicrm_disable() {
  return _petitionemail_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function petitionemail_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _petitionemail_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function petitionemail_civicrm_managed(&$entities) {
  return _petitionemail_civix_civicrm_managed($entities);
}

function petitionemail_civicrm_buildForm( $formName, &$form ) {
  if ($formName == 'CRM_Campaign_Form_Petition_Signature') {  
    $survey_id = $form->getVar('_surveyId');
    if ($survey_id) {
      $petitionemailval_sql = "SELECT petition_id, 
                                      recipient_email, 
                                      recipient_name, 
                                      default_message, 
                                      message_field, 
                                      subject 
                                 FROM civicrm_petition_email 
                                WHERE petition_id = %1";
      $petitionemailval_params = array( 1 => array( $survey_id, 'Integer' ) );
      $petitionemailval = CRM_Core_DAO::executeQuery( $petitionemailval_sql, $petitionemailval_params );
      while ($petitionemailval->fetch()) {
 
        $defaults = $form->getVar('_defaults');
        $messagefield = 'custom_' . $petitionemailval->message_field;
        foreach ($form->_elements as $element) {
          if ($element->_attributes['name'] == $messagefield) { 
            $element->_value = $petitionemailval->default_message; 
          }
        }
        $defaults[$messagefield] = $form->_defaultValues[$messagefield] = $petitionemailval->default_message;
        $form->setVar('_defaults',$defaults);
      }
    }
  }

  if ($formName != 'CRM_Campaign_Form_Petition') { return; }
  $survey_id = $form->getVar('_surveyId');
  if ($survey_id) {
    $petitionemailval_sql = "SELECT petition_id, 
                                    recipient_email, 
                                    recipient_name, 
                                    default_message, 
                                    message_field, 
                                    subject 
                               FROM civicrm_petition_email 
                              WHERE petition_id = %1";
    $petitionemailval_params = array( 1 => array( $survey_id, 'Integer' ) );
    $petitionemailval = CRM_Core_DAO::executeQuery( $petitionemailval_sql, $petitionemailval_params );
    while ($petitionemailval->fetch()) {

      $form->_defaultValues['email_petition'] = 1;
      $form->_defaultValues['recipient_name'] = $petitionemailval->recipient_name;
      $form->_defaultValues['recipient'] = $petitionemailval->recipient_email;
      $form->_defaultValues['default_message'] = $petitionemailval->default_message;
      $form->_defaultValues['user_message'] = $petitionemailval->message_field;
      $form->_defaultValues['subjectline'] = $petitionemailval->subject;
    }
  }
  $form->add('checkbox', 'email_petition', ts('Send an email to a target'));
  $form->add('text', 'recipient_name', ts('Recipient\'s Name'));
  $form->add('text', 'recipient', ts('Recipient\'s Email'));
  $validcustomgroups = array();

  // Get the Profiles in use by this petition so we can find out
  // if there are any potential fields for an extra message to the
  // petition target.
  $params = array('version' => '3', 
                  'module' => 'CiviCampaign', 
                  'entity_table' => 'civicrm_survey', 
                  'entity_id' => $survey_id );
  $join_results = civicrm_api('UFJoin','get', $params);
  $custom_fields = array();
  if ($join_results['is_error'] == 0) {
    foreach ($join_results['values'] as $join_value) {
      $uf_group_id = $join_value['uf_group_id'];

      // Now get all fields in this profile
      $params = array('version' => 3, 'uf_group_id' => $uf_group_id);
      $field_results = civicrm_api('UFField', 'get', $params);
      if ($field_results['is_error'] == 0) {
        foreach ($field_results['values'] as $field_value) {
          $field_name = $field_value['field_name'];
          if(!preg_match('/^custom_[0-9]+/', $field_name)) {
            // We only know how to lookup field types for custom
            // fields. Skip core fields.
            continue;
          }

          $id = substr(strrchr($field_name, '_'), 1);
          // Finally, see if this is a text or textarea field.
          $params = array('version' => 3, 'id' => $id);
          $custom_results = civicrm_api('CustomField', 'get', $params);
          if ($custom_results['is_error'] == 0) {
            $field_value = array_pop($custom_results['values']);
            $html_type = $field_value['html_type'];
            $label = $field_value['label'];
            $id = $field_value['id'];
            if($html_type == 'Text' || $html_type == 'TextArea') {
              $custom_fields[$id] = $label;
            }
          }
        }
      }
    }
  }
  $custom_message_field_options = array();
  if(count($custom_fields) == 0) {
    $custom_message_field_options = array('' => t('- No Text or TextArea fields defined in your profiles -'));
  }
  else {
    $custom_message_field_options = array('' => t('- Select -'));
    $custom_message_field_options = $custom_message_field_options + $custom_fields;
  }
  $form->add('select', 'user_message', ts('Custom Message Field'), $custom_message_field_options);
  $form->add('textarea', 'default_message', ts('Default Message'));
  $form->add('text', 'subjectline', ts('Email Subject Line'));
}

function petitionemail_civicrm_alterContent(  &$content, $context, $tplName, &$object ) {
  if ($tplName == 'CRM/Campaign/Form/Petition.tpl') {
    $rendererval = $object->getVar('_renderer');
    $action = $object->getVar('_action');
    if ($action == 8) { return; }

    //insert the field before is_active
    $insertpoint = strpos($content, '<tr class="crm-campaign-survey-form-block-is_active">');

    $help_code = "<a class=\"helpicon\" onclick=\"CRM.help('Petition Email', {'id':'id-email-petition','file':'CRM\/Campaign\/Form\/Petition'}); return false;\" href=\"#\" title=\"Petition Email Help\"></a>";
    $content1 = substr($content, 0, $insertpoint);
    $content3 = substr($content, $insertpoint);
    $content2 = '
  <tr class="crm-campaign-survey-form-block-email_petition">
    <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['email_petition']['label'] . $help_code . '</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['email_petition']['html'].'
      <div class="description">'.ts('Should signatures generate an email to the petition\'s  target?') .'</div>
    </td>
  </tr>
  <tr class="crm-campaign-survey-form-block-recipient_name">
  <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['recipient_name']['label'] .  '</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['recipient_name']['html'].'
      <div class="description">'.ts('Enter the target\'s name (as he or she should see it) here.').'</div>
    </td>
  </tr>
  <tr class="crm-campaign-survey-form-block-recipient">
    <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['recipient']['label'] .'</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['recipient']['html'].'
      <div class="description">'.ts('Enter the target\'s email address here.').'</div>
    </td>
  </tr>
  <tr class="crm-campaign-survey-form-block-user_message">
    <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['user_message']['label'] .'</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['user_message']['html'].'
      <div class="description">'.ts('Select a field that will have the signer\'s custom message.  Make sure it is included in the Activity Profile you selected.').'</div>
    </td>
  </tr>
  <tr class="crm-campaign-survey-form-block-default_message">
    <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['default_message']['label'] .'</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['default_message']['html'].'
      <div class="description">'.ts('Enter the default message to be included in the email.').'</div>
    </td>
  </tr>
  <tr class="crm-campaign-survey-form-block-subjectline">
    <td class="label">'. $rendererval->_tpl->_tpl_vars['form']['subjectline']['label'] .'</td>
    <td>'.$rendererval->_tpl->_tpl_vars['form']['subjectline']['html'].'
      <div class="description">'.ts('Enter the subject line to be included in the email.').'</div>
    </td>
  </tr>
';
       
    // jQuery to show/hide the email fields   
    $content4 = '
    <script type="text/javascript">
      cj(document).ready( function() {
        showHideEmailPetition();
        checkProfileIncludesMessage();
        cj("input#email_petition").click( function() { showHideEmailPetition(); });
        cj("#profile_id").change( function() { checkProfileIncludesMessage(); });
        cj("#user_message").change( function() { checkProfileIncludesMessage(); });
      });
      
      function checkProfileIncludesMessage() {
        cj("#profileMissingMessage").remove();
        var actProfile = cj("#profile_id").val();
        cj().crmAPI("UFField","get",{ "sequential" :"1", "uf_group_id" : actProfile },{ success:function (data){    
          var msgField = cj("#user_message").val();
          if (msgField) {
            var fieldinfo = cj.inArray(msgField, data["values"])
            var matchfield = false;
            cj.each(data["values"], function(key, value) {
              if (value["field_name"] == "custom_"+msgField) {
                matchfield = true;
                return true;
              }
            });
            if (!matchfield) {
              cj("#user_message").after("<div id=\'profileMissingMessage\' style=\'background-color: #FF9999; border: 1px solid #CC3333; display: inline-block; font-size: 85%; margin-left: 1ex; padding: 0.5ex; vertical-align: top;\'>'.ts('This field is not in the activity profile you selected').'</div>");
            }
          }
        }
      });
      }
      function showHideEmailPetition() {
        if( cj("input#email_petition").attr("checked") ) {
          cj("tr.crm-campaign-survey-form-block-recipient").show("fast");
          cj("tr.crm-campaign-survey-form-block-recipient_name").show("fast");
          cj("tr.crm-campaign-survey-form-block-user_message").show("fast");
          cj("tr.crm-campaign-survey-form-block-default_message").show("fast");
          cj("tr.crm-campaign-survey-form-block-subjectline").show("fast");
        } else {
          cj("tr.crm-campaign-survey-form-block-recipient").hide("fast");
          cj("tr.crm-campaign-survey-form-block-recipient_name").hide("fast");
          cj("tr.crm-campaign-survey-form-block-user_message").hide("fast");
          cj("tr.crm-campaign-survey-form-block-default_message").hide("fast");
          cj("tr.crm-campaign-survey-form-block-subjectline").hide("fast");
        }
      }
    </script>';
    
    $content = $content1.$content2.$content3.$content4;
  }
}

function petitionemail_civicrm_postProcess( $formName, &$form ) {
  if ($formName != 'CRM_Campaign_Form_Petition') { return; }
  if ( $form->_submitValues['email_petition'] == 1 ) {
    require_once 'api/api.php';
    $survey_id = $form->getVar('_surveyId');
    $lastmoddate = 0;
    if (!$survey_id) {  // Ugly hack because the form doesn't return the id
      $surveys = civicrm_api("Survey",
                             "get", 
                             array('version' => '3', 
                                   'sequential' =>'1', 
                                   'title' =>$form->_submitValues['title']
                                   )
                            );
      if (is_array($surveys['values'])) {
        foreach($surveys['values'] as $survey) {
          if ($lastmoddate > strtotime($survey['last_modified_date'])) { continue; }
          $lastmoddate = strtotime($survey['last_modified_date']);
          $survey_id = $survey['id'];
        }
      }
    }
    if (!$survey_id) {
      CRM_Core_Session::setStatus( ts('Cannot find the petition for saving email delivery fields.') );
      return;
    }

    $recipient = $form->_submitValues['recipient']; 
    $recipient_name = $form->_submitValues['recipient_name'];
    $default_message =  $form->_submitValues['default_message'];
    $user_message = intval($form->_submitValues['user_message']);
    $subjectline = $form->_submitValues['subjectline'];

    $checkexisting_sql ="SELECT COUNT(*) AS count 
                           FROM civicrm_petition_email
                          WHERE petition_id = %1";
    $checkexisting_params = array( 1 => array( $survey_id, 'Integer' ) );

    $checkexisting = CRM_Core_DAO::singleValueQuery( $checkexisting_sql, $checkexisting_params );
    if ( $checkexisting == 0 ) {
      $petitionemail_data_sql = "INSERT INTO civicrm_petition_email (
                                             petition_id, 
                                             recipient_email, 
                                             recipient_name, 
                                             default_message, 
                                             message_field, 
                                             subject
                                    ) VALUES ( 
                                             %1, 
                                             %2, 
                                             %3, 
                                             %4, 
                                             %5, 
                                             %6 
                                    )";

      $petitionemail_data_params = array( 1 => array( $survey_id, 'Integer' ),
                                          2 => array( $recipient, 'String' ),
                                          3 => array( $recipient_name, 'String' ),
                                          4 => array( $default_message, 'String' ),
                                          5 => array( $user_message, 'String' ),
                                          6 => array( $subjectline, 'String' ),
                                         );
    } else {
      $petitionemail_data_sql = "UPDATE civicrm_petition_email
                                    SET recipient_email = %2,
                                        recipient_name = %3,
                                        default_message = %4,
                                        message_field = %5,
                                        subject = %6
                                  WHERE petition_id = %1";

      $petitionemail_data_params = array( 1 => array( $survey_id, 'Integer' ),
                                          2 => array( $recipient, 'String' ),
                                          3 => array( $recipient_name, 'String' ),
                                          4 => array( $default_message, 'String' ),
                                          5 => array( $user_message, 'String' ),
                                          6 => array( $subjectline, 'String' ),
                                         );
    }
    $petitionemail = CRM_Core_DAO::executeQuery( $petitionemail_data_sql, $petitionemail_data_params );

    //FIXME Add failed database write check
    //if (!$insert) {
    //  CRM_Core_Session::setStatus( ts('Could not save petition delivery information.') ); 
    //}
  }
}

function petitionemail_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  static $profile_fields = NULL;
  if($objectName == 'Profile' && is_array($objectRef)) {
    // This seems like broad criteria to be hanging on to a static array, however,
    // not sure how else to capture the input to be used in case this is a petition
    // being signed that has a target. If you are anonymous, you have a source field in the
    // array, but that is not there if you are logged in. Sigh.
      $profile_fields = $objectRef;
  }

  if ($op == 'create' && $objectName == 'Activity') {
    require_once 'api/api.php';

    //Check what the Petition Activity id is
    $petitiontype = petitionemail_get_petition_type();

    //Only proceed if the Petition Activity is being created
    if ($objectRef->activity_type_id == $petitiontype) {
      $survey_id = $objectRef->source_record_id;
      $activity_id = $objectRef->id;
      global $language;  
      $petitionemail_get_sql = "SELECT petition_id, 
                                       recipient_email, 
                                       recipient_name, 
                                       default_message, 
                                       message_field, 
                                       subject 
                                  FROM civicrm_petition_email 
                                 WHERE petition_id = %1";
      $petitionemail_get_params = array( 1 => array( $survey_id, 'Integer' ) );
      $petitionemail_get = CRM_Core_DAO::executeQuery( $petitionemail_get_sql, $petitionemail_get_params );
      while ($petitionemail_get->fetch() ) {
        if($petitionemail_get->petition_id == NULL) {
          // Must not be a petition with a target.
          return;
        }

        // Set up variables for the email message
        // Figure out whether to use the user-supplied message or the default message
        $petition_message = NULL;
        // If the petition has specified a message field, and we've encountered the profile post action....
        if(!empty($petitionemail_get->message_field) && !is_null($profile_fields)) {
          if(is_numeric($petitionemail_get->message_field)) {
            $message_field = 'custom_' . $petitionemail_get->message_field;
          }
          else {
            $message_field = $petitionemail_get->message_field;
          }
          // If the field is in the profile
          if(array_key_exists($message_field, $profile_fields)) {
            // If it's not empty...
            if(!empty($profile_fields[$message_field])) {
              $petition_message = $profile_fields[$message_field];
            }
          }
        } 
        // No user supplied message, use the default
        if(is_null($petition_message)) {
          $petition_message = $petitionemail_get->default_message;
        }
        $to = $petitionemail_get->recipient_name . ' <' . $petitionemail_get->recipient_email . '>';
        $activity = civicrm_api("Activity",
                                "getsingle", 
                                array ('version' => '3',
                                       'sequential' =>'1', 
                                       'id' =>$objectId)
                               );
        $from = civicrm_api("Contact",
                            "getsingle", 
                            array ('version' => '3',
                                   'sequential' =>'1', 
                                   'id' =>$activity['source_contact_id'])
                           );
        if (array_key_exists('email', $from) && !empty($from['email'])) {
          $from = $from['display_name'] . ' <' . $from['email'] . '>';
        } else {
          $domain = civicrm_api("Domain",
                                "get", 
                                array ('version' => '3',
                                       'sequential' =>'1')
                                );
          if ($domain['is_error'] != 0 || !is_array($domain['values'])) { 
            // Can't send email without a from address.
            return; 
          }
          $from = '"' . $from['display_name'] . '"' . ' <' . $domain['values']['from_email'] . '>';
        }

        // Setup email message
        $email_params = array( 
          'from'    => $from,
          'toName'  => $petitionemail_get->recipient_name,
          'toEmail' => $petitionemail_get->recipient_email,
          'subject' => $petitionemail_get->subject,
          'text'    => $petition_message, 
          'html'    => $petition_message
        );
        $success = CRM_Utils_Mail::send($email_params);

        if($success == 1) {
          CRM_Core_Session::setStatus( ts('Message sent successfully to') . " $to" );
        } else {
          CRM_Core_Session::setStatus( ts('Error sending message to') . " $to" );
        }
      }
    }
  }
}
 
function petitionemail_get_petition_type() {
  require_once 'api/api.php';
  $acttypegroup = civicrm_api("OptionGroup",
                              "getsingle", 
                              array('version' => '3',
                                    'sequential' =>'1', 
                                    'name' =>'activity_type')
                             );
  if ( $acttypegroup['id'] && !isset($acttypegroup['is_error']) ) {
    $acttype = civicrm_api("OptionValue",
                           "getsingle", 
                           array ('version' => '3',
                                  'sequential' => '1', 
                                  'option_group_id' => $acttypegroup['id'], 
                                  'name' =>'Petition')
                          );
    $petitiontype = $acttype['value'];
  }
    
  return $petitiontype;
}
