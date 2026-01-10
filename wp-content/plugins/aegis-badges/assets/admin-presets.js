jQuery(function ($) {
  function updatePreview() {
    const $badge = $('#aegis-badge-preview');
    if (!$badge.length) {
      return;
    }

    const template = $('#aegis_badges_template').val();
    const text = $('#aegis_badges_text').val();
    const bg = $('#aegis_badges_bg').val();
    const fg = $('#aegis_badges_fg').val();
    const px = $('#aegis_badges_px').val();
    const py = $('#aegis_badges_py').val();
    const radius = $('#aegis_badges_radius').val();
    const fontSize = $('#aegis_badges_font_size').val();
    const fontWeight = $('#aegis_badges_font_weight').val();
    const top = $('#aegis_badges_top').val();
    const right = $('#aegis_badges_right').val();

    $badge.removeClass('aegis-badge--pill aegis-badge--ribbon aegis-badge--corner');
    $badge.addClass('aegis-badge--' + template);

    $badge.text(text);

    $badge[0].style.setProperty('--bg', bg);
    $badge[0].style.setProperty('--fg', fg);
    $badge[0].style.setProperty('--px', px + 'px');
    $badge[0].style.setProperty('--py', py + 'px');
    $badge[0].style.setProperty('--r', radius + 'px');
    $badge[0].style.setProperty('--fs', fontSize + 'px');
    $badge[0].style.setProperty('--fw', fontWeight);
    $badge[0].style.setProperty('--top', top + 'px');
    $badge[0].style.setProperty('--right', right + 'px');
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

  $('#aegis_badges_template, #aegis_badges_text, #aegis_badges_bg, #aegis_badges_fg, #aegis_badges_px, #aegis_badges_py, #aegis_badges_radius, #aegis_badges_font_size, #aegis_badges_font_weight, #aegis_badges_top, #aegis_badges_right').on('input change', updatePreview);

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
