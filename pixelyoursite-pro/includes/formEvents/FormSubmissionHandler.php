<?php

namespace PixelYourSite;

class FormSubmissionHandler {

    private $key;
    private $form_track;
	public function __construct() {
		add_filter( 'gform_confirmation', array( $this, 'my_gform_after_submission' ), 50, 4 );
		add_action( 'wpforms_process_complete', array( $this, 'my_wpforms_after_submission'), 10, 4 );
		add_action( 'fluentform/submission_inserted', array( $this, 'my_fluentform_after_submission'), 10, 3);
        add_action( 'elementor_pro/forms/new_record', array( $this, 'my_elementor_after_submission'), 10, 2 );
        add_action( 'forminator_form_after_handle_submit', array( $this, 'my_forminator_after_submission'), 10, 2 );
        $user_id = get_current_user_id();
        $this->key = 'form_track_' . ( $user_id ? $user_id : md5( PYS()->get_user_ip() ) );
	}

    function my_elementor_after_submission($record, $handler)
    {
        $form_id = $record->get_form_settings( 'id' );
        $this->form_track = array('formType' => 'elementor_form', 'formId' => $form_id);
        set_transient($this->key, $this->form_track, 60 * 5);
    }
	function my_gform_after_submission($confirmation, $form, $entry, $ajax) {
		if(!$ajax || (!empty($confirmation) && is_array($confirmation) && array_key_exists('redirect', $confirmation))) {
			$this->form_track = array('formType' => 'gravity', 'formId' => $form['id']);
			set_transient($this->key, $this->form_track, 60 * 5);
		}

		return $confirmation;
	}
	function my_wpforms_after_submission($fields, $entry, $form_data, $entry_id) {
		if ( !(isset( $form_data['settings']['ajax_submit'] ) && $form_data['settings']['ajax_submit'] == '1') ) {
			$this->form_track = array( 'formType' => 'wpforms', 'formId' => $form_data['id'] );
			set_transient( $this->key, $this->form_track, 60 * 5 );
		}
	}

	function my_fluentform_after_submission($entryId, $formData, $form) {
		if($form->settings['confirmation']['redirectTo'] !== 'samePage'){
			$this->form_track = array( 'formType' => 'fluentform', 'formId' => $form->id );
			set_transient( $this->key, $this->form_track, 60 * 5 );
		}
	}

    function my_forminator_after_submission($form_id, $response) {
        if (class_exists('\Forminator_API')) {
            $form = \Forminator_API::get_form( $form_id );
            // Check if AJAX is used
            if ( (isset($form->behaviors) && is_array($form->behaviors) && array_filter($form->behaviors, fn($behavior) => !empty($behavior['redirect-url']))) || (! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) !== 'xmlhttprequest')) {
                $this->form_track = array( 'formType' => 'forminator', 'formId' => $form_id );
                set_transient( $this->key, $this->form_track, 60 * 5 );
            }
        } else {
            error_log('Forminator_API class not found.');
        }
    }
}