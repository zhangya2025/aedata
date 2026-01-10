<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Aegis_Badges_Admin_Presets' ) ) {
	class Aegis_Badges_Admin_Presets {
		public static function render_settings() {
			$presets   = Aegis_Badges::get_presets();
			$defaults  = Aegis_Badges::get_default_presets();
			$settings  = Aegis_Badges::get_settings();
			$preset_id = self::get_current_preset_id();
			$preset    = isset( $presets[ $preset_id ] ) ? $presets[ $preset_id ] : $defaults['preset_a'];
			$preset    = wp_parse_args( $preset, $defaults[ $preset_id ] );
			$vars      = isset( $preset['vars'] ) && is_array( $preset['vars'] ) ? $preset['vars'] : array();
			$vars      = wp_parse_args( $vars, $defaults[ $preset_id ]['vars'] );
			$style     = aegis_badges_build_inline_style( $vars );
			$text      = $preset['text'] !== '' ? $preset['text'] : $settings['default_text'];
			$rule      = self::get_rule_for_preset( $preset_id );
			?>
			<form method="post">
				<?php wp_nonce_field( 'aegis_badges_presets_save', 'aegis_badges_presets_nonce' ); ?>
				<?php wp_nonce_field( 'aegis_badges_rules_save', 'aegis_badges_rules_nonce' ); ?>
				<input type="hidden" name="aegis_badges_preset_id" value="<?php echo esc_attr( $preset_id ); ?>" />

				<div class="aegis-badges-presets-toolbar">
					<label for="aegis_badges_preset_selector"><?php esc_html_e( 'Select preset', 'aegis-badges' ); ?></label>
					<select id="aegis_badges_preset_selector" name="aegis_badges_preset_selector">
						<?php foreach ( $presets as $key => $value ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $preset_id, $key ); ?>><?php echo esc_html( $value['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="aegis-badges-presets-layout">
					<div class="aegis-badges-presets-form">
						<h3><?php esc_html_e( 'Preset settings', 'aegis-badges' ); ?></h3>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="aegis_badges_template"><?php esc_html_e( 'Template', 'aegis-badges' ); ?></label></th>
								<td>
									<select id="aegis_badges_template" name="aegis_badges_preset[template]">
										<option value="pill" <?php selected( $preset['template'], 'pill' ); ?>><?php esc_html_e( 'Pill', 'aegis-badges' ); ?></option>
										<option value="ribbon" <?php selected( $preset['template'], 'ribbon' ); ?>><?php esc_html_e( 'Ribbon', 'aegis-badges' ); ?></option>
										<option value="corner" <?php selected( $preset['template'], 'corner' ); ?>><?php esc_html_e( 'Corner', 'aegis-badges' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_text"><?php esc_html_e( 'Text', 'aegis-badges' ); ?></label></th>
								<td><input type="text" id="aegis_badges_text" name="aegis_badges_preset[text]" value="<?php echo esc_attr( $preset['text'] ); ?>" class="regular-text" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_bg"><?php esc_html_e( 'BG color', 'aegis-badges' ); ?></label></th>
								<td><input type="text" id="aegis_badges_bg" name="aegis_badges_preset[vars][bg]" value="<?php echo esc_attr( $vars['bg'] ); ?>" class="aegis-color-field" data-default-color="<?php echo esc_attr( $defaults[ $preset_id ]['vars']['bg'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_fg"><?php esc_html_e( 'FG color', 'aegis-badges' ); ?></label></th>
								<td><input type="text" id="aegis_badges_fg" name="aegis_badges_preset[vars][fg]" value="<?php echo esc_attr( $vars['fg'] ); ?>" class="aegis-color-field" data-default-color="<?php echo esc_attr( $defaults[ $preset_id ]['vars']['fg'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_px"><?php esc_html_e( 'Padding X', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_px" name="aegis_badges_preset[vars][px]" value="<?php echo esc_attr( $vars['px'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_py"><?php esc_html_e( 'Padding Y', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_py" name="aegis_badges_preset[vars][py]" value="<?php echo esc_attr( $vars['py'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_radius"><?php esc_html_e( 'Radius', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_radius" name="aegis_badges_preset[vars][radius]" value="<?php echo esc_attr( $vars['radius'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_font_size"><?php esc_html_e( 'Font size', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_font_size" name="aegis_badges_preset[vars][font_size]" value="<?php echo esc_attr( $vars['font_size'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_font_weight"><?php esc_html_e( 'Font weight', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_font_weight" name="aegis_badges_preset[vars][font_weight]" value="<?php echo esc_attr( $vars['font_weight'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_top"><?php esc_html_e( 'Offset top', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_top" name="aegis_badges_preset[vars][top]" value="<?php echo esc_attr( $vars['top'] ); ?>" step="1" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="aegis_badges_right"><?php esc_html_e( 'Offset right', 'aegis-badges' ); ?></label></th>
								<td><input type="number" id="aegis_badges_right" name="aegis_badges_preset[vars][right]" value="<?php echo esc_attr( $vars['right'] ); ?>" step="1" /></td>
							</tr>
						</table>
						<p>
							<button type="submit" name="aegis_badges_preset_save" class="button button-primary"><?php esc_html_e( 'Save', 'aegis-badges' ); ?></button>
							<button type="submit" name="aegis_badges_preset_reset" class="button"><?php esc_html_e( 'Reset', 'aegis-badges' ); ?></button>
						</p>
					</div>
					<div class="aegis-badges-presets-preview">
						<h3><?php esc_html_e( 'Live preview', 'aegis-badges' ); ?></h3>
						<div class="aegis-badges-preview-card">
							<div class="aegis-badges-preview-badge">
								<span class="aegis-badge aegis-badge--<?php echo esc_attr( $preset['template'] ); ?>" style="<?php echo esc_attr( $style ); ?>" data-preset="<?php echo esc_attr( $preset_id ); ?>" data-default-text="<?php echo esc_attr( $settings['default_text'] ); ?>">
									<?php echo esc_html( $text ); ?>
								</span>
							</div>
							<div class="aegis-badges-preview-content">
								<div class="aegis-badges-preview-thumb"></div>
								<div class="aegis-badges-preview-lines">
									<span></span>
									<span></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<h3><?php esc_html_e( 'Apply rules', 'aegis-badges' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="aegis_badges_rule_priority"><?php esc_html_e( 'Priority', 'aegis-badges' ); ?></label></th>
						<td><input type="number" id="aegis_badges_rule_priority" name="aegis_badges_rule_priority" value="<?php echo esc_attr( $rule['priority'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis_badges_rule_categories"><?php esc_html_e( 'Categories', 'aegis-badges' ); ?></label></th>
						<td>
							<select id="aegis_badges_rule_categories" name="aegis_badges_rule_product_cat_ids[]" multiple="multiple" class="wc-enhanced-select" style="min-width:240px;">
								<?php foreach ( self::get_terms_for_select( 'product_cat' ) as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $rule['product_cat_ids'], true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis_badges_rule_tags"><?php esc_html_e( 'Tags', 'aegis-badges' ); ?></label></th>
						<td>
							<select id="aegis_badges_rule_tags" name="aegis_badges_rule_product_tag_ids[]" multiple="multiple" class="wc-enhanced-select" style="min-width:240px;">
								<?php foreach ( self::get_terms_for_select( 'product_tag' ) as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $rule['product_tag_ids'], true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis_badges_rule_attribute_taxonomy"><?php esc_html_e( 'Attribute taxonomy', 'aegis-badges' ); ?></label></th>
						<td>
							<select id="aegis_badges_rule_attribute_taxonomy" name="aegis_badges_rule_attribute_taxonomy" class="wc-enhanced-select" style="min-width:240px;">
								<option value=""><?php esc_html_e( 'Select attribute', 'aegis-badges' ); ?></option>
								<?php foreach ( self::get_attribute_taxonomies() as $taxonomy => $label ) : ?>
									<option value="<?php echo esc_attr( $taxonomy ); ?>" <?php selected( $rule['attribute_taxonomy'], $taxonomy ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis_badges_rule_attribute_terms"><?php esc_html_e( 'Attribute terms', 'aegis-badges' ); ?></label></th>
						<td>
							<select id="aegis_badges_rule_attribute_terms" name="aegis_badges_rule_attribute_term_ids[]" multiple="multiple" class="wc-enhanced-select" style="min-width:240px;">
								<?php foreach ( self::get_attribute_terms_for_select() as $term ) : ?>
									<option value="<?php echo esc_attr( $term['id'] ); ?>" data-taxonomy="<?php echo esc_attr( $term['taxonomy'] ); ?>" <?php selected( in_array( $term['id'], $rule['attribute_term_ids'], true ) ); ?>><?php echo esc_html( $term['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis_badges_rule_products"><?php esc_html_e( 'Products', 'aegis-badges' ); ?></label></th>
						<td>
							<select id="aegis_badges_rule_products" class="wc-product-search" name="aegis_badges_rule_product_ids[]" multiple="multiple" data-action="woocommerce_json_search_products_and_variations" data-placeholder="<?php esc_attr_e( 'Search for products', 'aegis-badges' ); ?>" style="min-width:240px;">
								<?php foreach ( $rule['product_ids'] as $product_id ) : ?>
									<?php $product_title = get_the_title( $product_id ); ?>
									<?php if ( $product_title ) : ?>
										<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo esc_html( $product_title ); ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" name="aegis_badges_rules_save" class="button button-primary"><?php esc_html_e( 'Save rules', 'aegis-badges' ); ?></button>
				</p>
			</form>
			<?php
		}

		public static function save_settings() {
			if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$preset_id = isset( $_POST['aegis_badges_preset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_badges_preset_id'] ) ) : 'preset_a';
			$preset_id = Aegis_Badges::normalize_preset_id( $preset_id );

			if ( isset( $_POST['aegis_badges_preset_save'] ) && isset( $_POST['aegis_badges_presets_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aegis_badges_presets_nonce'] ) ), 'aegis_badges_presets_save' ) ) {
				$raw_preset = isset( $_POST['aegis_badges_preset'] ) ? (array) wp_unslash( $_POST['aegis_badges_preset'] ) : array();
				$presets    = Aegis_Badges::get_presets();
				$defaults   = Aegis_Badges::get_default_presets();

				$template = isset( $raw_preset['template'] ) ? sanitize_text_field( $raw_preset['template'] ) : $defaults[ $preset_id ]['template'];
				if ( ! in_array( $template, array( 'pill', 'ribbon', 'corner' ), true ) ) {
					$template = $defaults[ $preset_id ]['template'];
				}

				$text = isset( $raw_preset['text'] ) ? sanitize_text_field( $raw_preset['text'] ) : $defaults[ $preset_id ]['text'];

				$vars     = isset( $raw_preset['vars'] ) ? (array) $raw_preset['vars'] : array();
				$bg_color = isset( $vars['bg'] ) ? sanitize_hex_color( $vars['bg'] ) : '';
				$fg_color = isset( $vars['fg'] ) ? sanitize_hex_color( $vars['fg'] ) : '';
				$vars = array(
					'bg'          => $bg_color ? $bg_color : $defaults[ $preset_id ]['vars']['bg'],
					'fg'          => $fg_color ? $fg_color : $defaults[ $preset_id ]['vars']['fg'],
					'px'          => isset( $vars['px'] ) ? floatval( $vars['px'] ) : $defaults[ $preset_id ]['vars']['px'],
					'py'          => isset( $vars['py'] ) ? floatval( $vars['py'] ) : $defaults[ $preset_id ]['vars']['py'],
					'radius'      => isset( $vars['radius'] ) ? floatval( $vars['radius'] ) : $defaults[ $preset_id ]['vars']['radius'],
					'font_size'   => isset( $vars['font_size'] ) ? floatval( $vars['font_size'] ) : $defaults[ $preset_id ]['vars']['font_size'],
					'font_weight' => isset( $vars['font_weight'] ) ? floatval( $vars['font_weight'] ) : $defaults[ $preset_id ]['vars']['font_weight'],
					'top'         => isset( $vars['top'] ) ? floatval( $vars['top'] ) : $defaults[ $preset_id ]['vars']['top'],
					'right'       => isset( $vars['right'] ) ? floatval( $vars['right'] ) : $defaults[ $preset_id ]['vars']['right'],
				);

				$presets[ $preset_id ] = array(
					'label'    => $defaults[ $preset_id ]['label'],
					'template' => $template,
					'text'     => $text,
					'vars'     => $vars,
				);

				update_option( Aegis_Badges::PRESETS_OPTION_KEY, $presets );
			}

			if ( isset( $_POST['aegis_badges_preset_reset'] ) && isset( $_POST['aegis_badges_presets_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aegis_badges_presets_nonce'] ) ), 'aegis_badges_presets_save' ) ) {
				$presets  = Aegis_Badges::get_presets();
				$defaults = Aegis_Badges::get_default_presets();

				if ( isset( $defaults[ $preset_id ] ) ) {
					$presets[ $preset_id ] = $defaults[ $preset_id ];
					update_option( Aegis_Badges::PRESETS_OPTION_KEY, $presets );
				}
			}

			if ( isset( $_POST['aegis_badges_rules_save'] ) && isset( $_POST['aegis_badges_rules_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aegis_badges_rules_nonce'] ) ), 'aegis_badges_rules_save' ) ) {
				$rules   = Aegis_Badges::get_rules();
				$rules   = array_values(
					array_filter(
						$rules,
						static function ( $rule ) use ( $preset_id ) {
							return isset( $rule['preset_id'] ) && $rule['preset_id'] !== $preset_id;
						}
					)
				);

				$priority = isset( $_POST['aegis_badges_rule_priority'] ) ? intval( wp_unslash( $_POST['aegis_badges_rule_priority'] ) ) : 0;

				$cat_ids = isset( $_POST['aegis_badges_rule_product_cat_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['aegis_badges_rule_product_cat_ids'] ) ) : array();
				$cat_ids = array_values( array_filter( $cat_ids ) );

				$tag_ids = isset( $_POST['aegis_badges_rule_product_tag_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['aegis_badges_rule_product_tag_ids'] ) ) : array();
				$tag_ids = array_values( array_filter( $tag_ids ) );

				$attribute_taxonomy = isset( $_POST['aegis_badges_rule_attribute_taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_badges_rule_attribute_taxonomy'] ) ) : '';
				$attribute_term_ids = isset( $_POST['aegis_badges_rule_attribute_term_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['aegis_badges_rule_attribute_term_ids'] ) ) : array();
				$attribute_term_ids = array_values( array_filter( $attribute_term_ids ) );

				$product_ids = isset( $_POST['aegis_badges_rule_product_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['aegis_badges_rule_product_ids'] ) ) : array();
				$product_ids = array_values( array_filter( $product_ids ) );

				$attribute_terms = array();
				if ( $attribute_taxonomy && ! empty( $attribute_term_ids ) ) {
					$attribute_terms[] = array(
						'taxonomy' => $attribute_taxonomy,
						'term_ids' => $attribute_term_ids,
					);
				}

				$has_criteria = ! empty( $cat_ids ) || ! empty( $tag_ids ) || ! empty( $attribute_terms ) || ! empty( $product_ids );

				if ( $has_criteria ) {
					$rules[] = array(
						'id'              => uniqid( 'aegis_rule_', true ),
						'preset_id'       => $preset_id,
						'priority'        => $priority,
						'product_cat_ids' => $cat_ids,
						'product_tag_ids' => $tag_ids,
						'attribute_terms' => $attribute_terms,
						'product_ids'     => $product_ids,
					);
				}

				update_option( Aegis_Badges::RULES_OPTION_KEY, $rules );
			}
		}

		private static function get_current_preset_id() {
			$preset_id = isset( $_GET['preset'] ) ? sanitize_text_field( wp_unslash( $_GET['preset'] ) ) : 'preset_a';

			return Aegis_Badges::normalize_preset_id( $preset_id );
		}

		private static function get_rule_for_preset( $preset_id ) {
			$rules = Aegis_Badges::get_rules();
			foreach ( $rules as $rule ) {
				if ( isset( $rule['preset_id'] ) && $rule['preset_id'] === $preset_id ) {
					return array(
						'priority'           => isset( $rule['priority'] ) ? intval( $rule['priority'] ) : 0,
						'product_cat_ids'    => isset( $rule['product_cat_ids'] ) ? array_map( 'intval', (array) $rule['product_cat_ids'] ) : array(),
						'product_tag_ids'    => isset( $rule['product_tag_ids'] ) ? array_map( 'intval', (array) $rule['product_tag_ids'] ) : array(),
						'attribute_taxonomy' => isset( $rule['attribute_terms'][0]['taxonomy'] ) ? sanitize_text_field( $rule['attribute_terms'][0]['taxonomy'] ) : '',
						'attribute_term_ids' => isset( $rule['attribute_terms'][0]['term_ids'] ) ? array_map( 'intval', (array) $rule['attribute_terms'][0]['term_ids'] ) : array(),
						'product_ids'        => isset( $rule['product_ids'] ) ? array_map( 'intval', (array) $rule['product_ids'] ) : array(),
					);
				}
			}

			return array(
				'priority'           => 0,
				'product_cat_ids'    => array(),
				'product_tag_ids'    => array(),
				'attribute_taxonomy' => '',
				'attribute_term_ids' => array(),
				'product_ids'        => array(),
			);
		}

		private static function get_terms_for_select( $taxonomy ) {
			if ( ! $taxonomy ) {
				return array();
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			return is_array( $terms ) ? $terms : array();
		}

		private static function get_attribute_terms_for_select() {
			$terms      = array();
			$taxonomies = self::get_attribute_taxonomies();

			foreach ( array_keys( $taxonomies ) as $taxonomy ) {
				$taxonomy_terms = self::get_terms_for_select( $taxonomy );
				foreach ( $taxonomy_terms as $term ) {
					$terms[] = array(
						'id'       => $term->term_id,
						'taxonomy' => $taxonomy,
						'label'    => $term->name,
					);
				}
			}

			return $terms;
		}

		private static function get_attribute_taxonomies() {
			$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
			$output     = array();

			if ( empty( $attributes ) ) {
				return $output;
			}

			foreach ( $attributes as $attribute ) {
				if ( ! isset( $attribute->attribute_name ) ) {
					continue;
				}

				$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
				if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
					continue;
				}

				$output[ $taxonomy ] = $attribute->attribute_label;
			}

			return $output;
		}
	}
}
