<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Mail {
	const OPTION_KEY      = 'aegis_mail_settings';
	const TEST_RESULT_KEY = 'aegis_mail_last_test';

	public function register() {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 999 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 999 );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 999 );
	}

	public function get_settings() {
		$defaults = array(
			'enabled'       => 0,
			'smtp_host'     => '',
			'smtp_port'     => 587,
			'smtp_encryption' => 'tls',
			'smtp_autotls'  => 1,
			'smtp_username' => '',
			'smtp_password' => '',
			'from_email'    => '',
			'from_name'     => '',
			'force_from'    => 1,
		);

		$stored = $this->get_option();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, $defaults );

		return $this->apply_constant_overrides( $settings );
	}

	public function configure_phpmailer( $phpmailer ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( empty( $settings['smtp_host'] ) || empty( $settings['smtp_username'] ) || empty( $settings['smtp_password'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['smtp_host'];
		$phpmailer->Port       = (int) $settings['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $settings['smtp_username'];
		$phpmailer->Password   = $settings['smtp_password'];
		$phpmailer->CharSet    = 'UTF-8';
		$phpmailer->Timeout    = 10;

		$encryption = $settings['smtp_encryption'];
		if ( 'ssl' === $encryption ) {
			$phpmailer->SMTPSecure  = 'ssl';
			$phpmailer->SMTPAutoTLS = false;
		} elseif ( 'tls' === $encryption ) {
			$phpmailer->SMTPSecure  = 'tls';
			$phpmailer->SMTPAutoTLS = ! empty( $settings['smtp_autotls'] );
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = ! empty( $settings['smtp_autotls'] );
		}

		if ( ! empty( $settings['force_from'] ) && ! empty( $settings['from_email'] ) ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'], false );
		}
	}

	public function filter_from_email( $email ) {
		$settings = $this->get_settings();
		if ( empty( $settings['force_from'] ) || empty( $settings['from_email'] ) ) {
			return $email;
		}

		return $settings['from_email'];
	}

	public function filter_from_name( $name ) {
		$settings = $this->get_settings();
		if ( empty( $settings['force_from'] ) || empty( $settings['from_name'] ) ) {
			return $name;
		}

		return $settings['from_name'];
	}

	public function get_option() {
		if ( is_multisite() ) {
			return get_site_option( self::OPTION_KEY, array() );
		}

		return get_option( self::OPTION_KEY, array() );
	}

	public function update_option( $settings ) {
		if ( is_multisite() ) {
			return update_site_option( self::OPTION_KEY, $settings );
		}

		return update_option( self::OPTION_KEY, $settings );
	}

	public function delete_option() {
		if ( is_multisite() ) {
			return delete_site_option( self::OPTION_KEY );
		}

		return delete_option( self::OPTION_KEY );
	}

	public function set_last_test_result( $result ) {
		if ( is_multisite() ) {
			return set_site_transient( self::TEST_RESULT_KEY, $result, DAY_IN_SECONDS );
		}

		return set_transient( self::TEST_RESULT_KEY, $result, DAY_IN_SECONDS );
	}

	public function get_last_test_result() {
		if ( is_multisite() ) {
			return get_site_transient( self::TEST_RESULT_KEY );
		}

		return get_transient( self::TEST_RESULT_KEY );
	}

	public function delete_last_test_result() {
		if ( is_multisite() ) {
			return delete_site_transient( self::TEST_RESULT_KEY );
		}

		return delete_transient( self::TEST_RESULT_KEY );
	}

	private function apply_constant_overrides( $settings ) {
		$map = array(
			'enabled'        => 'AEGIS_MAIL_ENABLED',
			'smtp_host'      => 'AEGIS_MAIL_SMTP_HOST',
			'smtp_port'      => 'AEGIS_MAIL_SMTP_PORT',
			'smtp_encryption' => 'AEGIS_MAIL_SMTP_ENCRYPTION',
			'smtp_autotls'   => 'AEGIS_MAIL_SMTP_AUTOTLS',
			'smtp_username'  => 'AEGIS_MAIL_SMTP_USERNAME',
			'smtp_password'  => 'AEGIS_MAIL_SMTP_PASSWORD',
			'from_email'     => 'AEGIS_MAIL_FROM_EMAIL',
			'from_name'      => 'AEGIS_MAIL_FROM_NAME',
			'force_from'     => 'AEGIS_MAIL_FORCE_FROM',
		);

		foreach ( $map as $key => $constant ) {
			if ( defined( $constant ) ) {
				$settings[ $key ] = constant( $constant );
			}
		}

		$settings['smtp_port'] = (int) $settings['smtp_port'];
		$settings['smtp_autotls'] = ! empty( $settings['smtp_autotls'] ) ? 1 : 0;
		$settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;
		$settings['force_from'] = ! empty( $settings['force_from'] ) ? 1 : 0;

		$allowed_encryption = array( 'ssl', 'tls', 'none' );
		if ( empty( $settings['smtp_encryption'] ) || ! in_array( $settings['smtp_encryption'], $allowed_encryption, true ) ) {
			$settings['smtp_encryption'] = 'none';
		}

		return $settings;
	}
}
