<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Settings {
	const OPTION_NAME = 'aegis_forms_settings';

	public static function get_default_settings() {
		return array(
			'guest_allowed' => array(
				'repair' => false,
				'dealer' => false,
				'contact' => false,
				'sponsorship' => false,
				'customization' => false,
			),
			'messages' => array(
				'login_required' => 'This form is available to registered users. Please log in to continue.',
				'submit_denied' => 'Submission failed: please log in and try again.',
			),
		);
	}

	public static function get_form_types() {
		return array(
			'repair' => 'Repair',
			'dealer' => 'Dealer',
			'contact' => 'Contact',
			'sponsorship' => 'Sponsorship',
			'customization' => 'Customization',
		);
	}

	public static function get_settings() {
		$defaults = self::get_default_settings();
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $defaults );

		if ( empty( $settings['guest_allowed'] ) || ! is_array( $settings['guest_allowed'] ) ) {
			$settings['guest_allowed'] = array();
		}

		foreach ( $defaults['guest_allowed'] as $type => $default_value ) {
			$settings['guest_allowed'][ $type ] = ! empty( $settings['guest_allowed'][ $type ] );
		}

		if ( empty( $settings['messages'] ) || ! is_array( $settings['messages'] ) ) {
			$settings['messages'] = array();
		}

		foreach ( $defaults['messages'] as $key => $message ) {
			if ( empty( $settings['messages'][ $key ] ) ) {
				$settings['messages'][ $key ] = $message;
			}
		}

		return $settings;
	}

	public static function is_guest_allowed( $form_type ) {
		$settings = self::get_settings();
		return ! empty( $settings['guest_allowed'][ $form_type ] );
	}

	public static function get_message( $key ) {
		$settings = self::get_settings();
		if ( isset( $settings['messages'][ $key ] ) ) {
			return $settings['messages'][ $key ];
		}

		$defaults = self::get_default_settings();
		return isset( $defaults['messages'][ $key ] ) ? $defaults['messages'][ $key ] : '';
	}

	public static function update_settings( $payload ) {
		$defaults = self::get_default_settings();
		$form_types = self::get_form_types();

		$guest_allowed = array();
		$raw_guest = isset( $payload['guest_allowed'] ) && is_array( $payload['guest_allowed'] ) ? $payload['guest_allowed'] : array();
		foreach ( $form_types as $type => $label ) {
			$guest_allowed[ $type ] = ! empty( $raw_guest[ $type ] );
		}

		$messages = array();
		$raw_messages = isset( $payload['messages'] ) && is_array( $payload['messages'] ) ? $payload['messages'] : array();
		foreach ( $defaults['messages'] as $key => $default_message ) {
			$raw_value = isset( $raw_messages[ $key ] ) ? $raw_messages[ $key ] : '';
			$sanitized = sanitize_textarea_field( wp_unslash( $raw_value ) );
			$messages[ $key ] = $sanitized ? $sanitized : $default_message;
		}

		update_option(
			self::OPTION_NAME,
			array(
				'guest_allowed' => $guest_allowed,
				'messages' => $messages,
			)
		);
	}
}
