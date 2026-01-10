jQuery(function ($) {
  function updatePreview() {
    const $badge = $('.aegis-badges-preview-badge .aegis-badge');
    if (!$badge.length) {
      return;
    }

    const template = $('#aegis_badges_template').val();
    $badge.removeClass('aegis-badge--pill aegis-badge--ribbon aegis-badge--corner');
    $badge.addClass('aegis-badge--' + template);

    const text = $('#aegis_badges_text').val();
    const fallbackText = $badge.data('default-text') || '';
    $badge.text(text || fallbackText);

    const vars = {
      '--bg': $('#aegis_badges_bg').val(),
      '--fg': $('#aegis_badges_fg').val(),
      '--px': $('#aegis_badges_px').val() + 'px',
      '--py': $('#aegis_badges_py').val() + 'px',
      '--r': $('#aegis_badges_radius').val() + 'px',
      '--fs': $('#aegis_badges_font_size').val() + 'px',
      '--fw': $('#aegis_badges_font_weight').val(),
      '--top': $('#aegis_badges_top').val() + 'px',
      '--right': $('#aegis_badges_right').val() + 'px'
    };

    Object.keys(vars).forEach(function (key) {
      $badge.css(key, vars[key]);
    });
  }

  function filterAttributeTerms() {
    const selectedTaxonomy = $('#aegis_badges_rule_attribute_taxonomy').val();
    const $termsSelect = $('#aegis_badges_rule_attribute_terms');

    $termsSelect.find('option').each(function () {
      const $option = $(this);
      const taxonomy = $option.data('taxonomy');
      const shouldEnable = !selectedTaxonomy || taxonomy === selectedTaxonomy;

      $option.prop('disabled', !shouldEnable);
      if (!shouldEnable) {
        $option.prop('selected', false);
      }
    });

    if ($termsSelect.data('select2')) {
      $termsSelect.trigger('change.select2');
    }
  }

  $('.aegis-color-field').wpColorPicker({
    change: updatePreview,
    clear: updatePreview
  });

  $('#aegis_badges_template, #aegis_badges_text, #aegis_badges_px, #aegis_badges_py, #aegis_badges_radius, #aegis_badges_font_size, #aegis_badges_font_weight, #aegis_badges_top, #aegis_badges_right').on('input change', updatePreview);

  $('#aegis_badges_preset_selector').on('change', function () {
    const preset = $(this).val();
    const url = new URL(window.location.href);
    url.searchParams.set('preset', preset);
    url.searchParams.set('section', 'presets');
    window.location.href = url.toString();
  });

  $('#aegis_badges_rule_attribute_taxonomy').on('change', filterAttributeTerms);

  if ($.fn.selectWoo) {
    $('select.wc-product-search').selectWoo();
    $('select.wc-enhanced-select').selectWoo();
  }

  updatePreview();
  filterAttributeTerms();
});
