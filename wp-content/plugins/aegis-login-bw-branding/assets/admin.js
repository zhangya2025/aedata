jQuery(function ($) {
	var frame;
	var $container = $('.aegis-login-bw-branding');

	$container.on('click', '.aegis-login-bw-branding__upload', function (event) {
		event.preventDefault();

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: aegisLoginBwBranding.title,
			button: {
				text: aegisLoginBwBranding.button,
			},
			library: {
				type: 'image',
			},
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$container.find('input[name="aegis_login_bw_branding_options[logo_id]"]').val(attachment.id);
			$container.find('.aegis-login-bw-branding__logo-preview').attr('src', attachment.url);
		});

		frame.open();
	});

	$container.on('click', '.aegis-login-bw-branding__remove', function (event) {
		event.preventDefault();
		$container.find('input[name="aegis_login_bw_branding_options[logo_id]"]').val('0');
		$container.find('.aegis-login-bw-branding__logo-preview').attr('src', aegisLoginBwBranding.placeholder);
	});
});
