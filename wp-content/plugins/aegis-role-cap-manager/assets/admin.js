(function ($) {
	const state = {
		currentUserId: 0,
		catalog: [],
	};

	function setReadonly(isReadonly, isSuperAdmin) {
		const $form = $('.aegis-rcm-form');
		$form.attr('aria-readonly', isReadonly ? 'true' : 'false');
		$form.find('input[type="checkbox"], button').prop('disabled', isReadonly);
		$form.find('.aegis-rcm-readonly').toggleClass('hidden', !isReadonly);
		const note = isSuperAdmin
			? 'Super Admin has all capabilities (read-only).' 
			: '';
		$form.find('.aegis-rcm-effective-note').text(note);
	}

	function renderCheckboxList($container, items, selected, fieldName) {
		$container.empty();
		items.forEach((item) => {
			const value = typeof item === 'string' ? item : item.key;
			const label = typeof item === 'string' ? item : item.label;
			const isChecked = selected.includes(value);
			const $label = $('<label />');
			const $checkbox = $('<input />', {
				type: 'checkbox',
				name: fieldName,
				value: value,
				checked: isChecked,
			});
			$label.append($checkbox).append(document.createTextNode(label));
			$container.append($label);
		});
	}

	function renderEffective($container, items, selected) {
		$container.empty();
		items.forEach((item) => {
			const $label = $('<label />');
			const $checkbox = $('<input />', {
				type: 'checkbox',
				checked: selected.includes(item),
				disabled: true,
			});
			$label.append($checkbox).append(document.createTextNode(item));
			$container.append($label);
		});
	}

	function loadUserDetail(userId) {
		if (!userId) {
			return;
		}
		state.currentUserId = userId;
		$('.aegis-rcm-user').removeClass('is-selected');
		$('.aegis-rcm-user[data-user-id="' + userId + '"]').addClass('is-selected');
		$('.aegis-rcm-form input[name="target_user_id"]').val(userId);
		$('.aegis-rcm-save').prop('disabled', true);

		$.get(
			aegisRcmData.ajaxUrl,
			{
				action: 'aegis_rcm_get_user_detail',
				nonce: aegisRcmData.nonce,
				user_id: userId,
			},
			function (response) {
				if (!response || !response.success) {
					return;
				}
				const data = response.data;
				state.catalog = data.catalog || [];
				const rolesSelected = data.roles || [];
				const overridesSelected = data.overrides || [];
				const effectiveSelected = data.effective || [];
				const roleCatalog = aegisRcmData.roles || [];

				renderCheckboxList(
					$('.aegis-rcm-checkboxes[data-section="roles"]'),
					roleCatalog,
					rolesSelected,
					'roles[]'
				);
				renderCheckboxList(
					$('.aegis-rcm-checkboxes[data-section="overrides"]'),
					state.catalog,
					overridesSelected,
					'caps_overrides['
				);

				$('.aegis-rcm-checkboxes[data-section="overrides"]').find('input').each(function () {
					const cap = $(this).val();
					$(this).attr('name', 'caps_overrides[' + cap + ']');
				});

				renderEffective(
					$('.aegis-rcm-checkboxes[data-section="effective"]'),
					state.catalog,
					effectiveSelected
				);

				setReadonly(data.isProtected, data.isSuperAdmin);
				if (!data.isProtected) {
					$('.aegis-rcm-save').prop('disabled', false);
				}
			}
		);
	}

	function filterOverrides(query) {
		const search = query.toLowerCase();
		const overridesSelected = [];
		$('.aegis-rcm-checkboxes[data-section="overrides"] input:checked').each(function () {
			overridesSelected.push($(this).val());
		});
		const filtered = state.catalog.filter((cap) => cap.toLowerCase().includes(search));
		renderCheckboxList(
			$('.aegis-rcm-checkboxes[data-section="overrides"]'),
			filtered,
			overridesSelected,
			'caps_overrides['
		);
		$('.aegis-rcm-checkboxes[data-section="overrides"]').find('input').each(function () {
			const cap = $(this).val();
			$(this).attr('name', 'caps_overrides[' + cap + ']');
		});
		if ($('.aegis-rcm-form').attr('aria-readonly') === 'true') {
			$('.aegis-rcm-checkboxes[data-section="overrides"] input').prop('disabled', true);
		}
	}

	$(document).on('click', '.aegis-rcm-user', function () {
		const userId = $(this).data('user-id');
		loadUserDetail(userId);
	});

	$(document).on('input', '.aegis-rcm-cap-search', function () {
		filterOverrides($(this).val());
	});

	$(document).ready(function () {
		const selectedUser = $('.aegis-rcm-details').data('selected-user');
		if (selectedUser) {
			loadUserDetail(selectedUser);
		}
	});
})(jQuery);
