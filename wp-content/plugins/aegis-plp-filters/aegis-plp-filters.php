<?php
/**
 * Plugin Name: AEGIS PLP Filters (Phase 1)
 * Description: Marmot-style filter bar + drawer for WooCommerce archives. Phase 1 uses GET submit (no AJAX).
 * Version: 0.1.0
 * Author: AEDATA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class Aegis_PLP_Filters_Phase1 {

  const VERSION = '0.1.0';

  public static function boot(): void {
    // Only if Woo is available.
    if ( ! function_exists( 'is_woocommerce' ) ) {
      add_action( 'admin_notices', [ __CLASS__, 'admin_notice_missing_woo' ] );
      return;
    }

    add_action( 'wp', [ __CLASS__, 'maybe_hook_toolbar' ] );
    add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

    // Hook to modify the main product query on archives.
    add_action( 'woocommerce_product_query', [ __CLASS__, 'apply_filters_to_query' ], 10, 1 );
  }

  public static function admin_notice_missing_woo(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }
    echo '<div class="notice notice-warning"><p><strong>AEGIS PLP Filters:</strong> WooCommerce is not active.</p></div>';
  }

  private static function is_plp_context(): bool {
    // Shop, product category, product tag, product attribute archives.
    return ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
  }

  public static function maybe_hook_toolbar(): void {
    if ( ! self::is_plp_context() ) {
      return;
    }

    // Remove default count + ordering; we will render them inside our toolbar.
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

    add_action( 'woocommerce_before_shop_loop', [ __CLASS__, 'render_plp_toolbar' ], 15 );
  }

  public static function enqueue_assets(): void {
    if ( ! self::is_plp_context() ) {
      return;
    }

    $url = plugin_dir_url( __FILE__ );

    wp_enqueue_style(
      'aegis-plp-filters',
      $url . 'assets/aegis-plp.css',
      [],
      self::VERSION
    );

    wp_enqueue_script(
      'aegis-plp-filters',
      $url . 'assets/aegis-plp.js',
      [],
      self::VERSION,
      true
    );
  }

  /**
   * =========================
   * Filter configuration
   * =========================
   * You can adjust labels, order, and attribute slugs here.
   */
  private static function filter_bar_buttons(): array {
    return [
      [ 'label' => 'Color',              'target' => 'aegis-plp-group-color' ],
      [ 'label' => 'Temperature (°C)',   'target' => 'aegis-plp-group-temp' ],
      [ 'label' => 'Price',              'target' => 'aegis-plp-group-price' ],
      [ 'label' => 'Fill Type',          'target' => 'aegis-plp-group-fill' ],
      [ 'label' => 'Best Use',           'target' => 'aegis-plp-group-use' ],
      [ 'label' => 'More Filters',        'target' => 'aegis-plp-group-more' ],
    ];
  }

  private static function temp_buckets(): array {
    // Bucket key => [min, max, label]
    // Range uses: min <= x < max (half-open), to avoid overlap on boundaries.
    return [
      'lte_-15'  => [ 'min' => null, 'max' => -15, 'label' => '≤ -15°C' ],
      '-15_-10'  => [ 'min' => -15,  'max' => -10, 'label' => '-15°C to -10°C' ],
      '-10_-5'   => [ 'min' => -10,  'max' => -5,  'label' => '-10°C to -5°C' ],
      '-5_0'     => [ 'min' => -5,   'max' => 0,   'label' => '-5°C to 0°C' ],
      '0_5'      => [ 'min' => 0,    'max' => 5,   'label' => '0°C to 5°C' ],
      'gte_5'    => [ 'min' => 5,    'max' => null,'label' => '≥ 5°C' ],
    ];
  }

  private static function attribute_taxonomy( string $attr_slug ): string {
    // attr_slug is like: sleepingbag_fill_type, sleepingbag-color, sleepingbag-size ...
    if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
      return wc_attribute_taxonomy_name( $attr_slug ); // returns pa_{slug}
    }
    return 'pa_' . $attr_slug;
  }

  private static function request_csv_values( string $key ): array {
    if ( ! isset( $_GET[ $key ] ) ) {
      return [];
    }

    $raw = wp_unslash( $_GET[ $key ] );

    if ( is_array( $raw ) ) {
      $vals = $raw;
    } else {
      $vals = explode( ',', (string) $raw );
    }

    $vals = array_map( 'trim', $vals );
    $vals = array_filter( $vals, static function( $v ) { return $v !== ''; } );
    $vals = array_map( 'sanitize_title', $vals );

    return array_values( array_unique( $vals ) );
  }

  private static function preserved_query_inputs(): void {
    // Keep orderby/search when applying filters; do not preserve paged.
    $preserve = [ 'orderby', 's', 'post_type' ];
    foreach ( $preserve as $key ) {
      if ( ! isset( $_GET[ $key ] ) ) {
        continue;
      }
      $val = wp_unslash( $_GET[ $key ] );
      if ( is_array( $val ) ) {
        continue;
      }
      printf(
        '<input type="hidden" name="%s" value="%s" />',
        esc_attr( $key ),
        esc_attr( (string) $val )
      );
    }
  }

  private static function get_clear_url(): string {
    $remove = [ 'temp_limit', 'min_price', 'max_price' ];

    foreach ( array_keys( $_GET ) as $k ) {
      if ( strpos( $k, 'filter_' ) === 0 ) {
        $remove[] = $k;
      }
    }

    return remove_query_arg( $remove );
  }

  private static function remove_one_value_url( string $param, string $value ): string {
    $current = self::request_csv_values( $param );
    $next = array_values( array_filter( $current, static function( $v ) use ( $value ) {
      return $v !== $value;
    } ) );

    if ( empty( $next ) ) {
      return remove_query_arg( [ $param ] );
    }

    return add_query_arg( [ $param => implode( ',', $next ) ] );
  }

  private static function render_tax_group( string $attr_slug, string $title, string $details_id, bool $open = false ): void {
    $taxonomy = self::attribute_taxonomy( $attr_slug );

    if ( ! taxonomy_exists( $taxonomy ) ) {
      return;
    }

    $terms = get_terms( [
      'taxonomy'   => $taxonomy,
      'hide_empty' => true,
    ] );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
      return;
    }

    $param = 'filter_' . $attr_slug;
    $selected = self::request_csv_values( $param );

    printf(
      '<details class="aegis-plp__group" id="%s"%s>',
      esc_attr( $details_id ),
      $open ? ' open' : ''
    );

    printf(
      '<summary class="aegis-plp__group-title">%s</summary>',
      esc_html( $title )
    );

    // Hidden input (clean URL; JS keeps it synced from checkboxes).
    printf(
      '<input type="hidden" name="%s" value="%s" data-aegis-hidden="%s" />',
      esc_attr( $param ),
      esc_attr( implode( ',', $selected ) ),
      esc_attr( $param )
    );

    echo '<div class="aegis-plp__group-body">';

    foreach ( $terms as $term ) {
      $checked = in_array( $term->slug, $selected, true );
      echo '<label class="aegis-plp__check">';
      printf(
        '<input type="checkbox" value="%s" %s data-aegis-filter="%s" />',
        esc_attr( $term->slug ),
        $checked ? 'checked' : '',
        esc_attr( $param )
      );
      printf( '<span>%s</span>', esc_html( $term->name ) );
      echo '</label>';
    }

    echo '</div></details>';
  }

  private static function render_temp_group(): void {
    $selected = self::request_csv_values( 'temp_limit' );
    $buckets = self::temp_buckets();

    echo '<details class="aegis-plp__group" id="aegis-plp-group-temp" open>';
    echo '<summary class="aegis-plp__group-title">Temperature Rating (Limit, °C)</summary>';

    printf(
      '<input type="hidden" name="temp_limit" value="%s" data-aegis-hidden="temp_limit" />',
      esc_attr( implode( ',', $selected ) )
    );

    echo '<div class="aegis-plp__group-body">';
    foreach ( $buckets as $key => $cfg ) {
      $checked = in_array( $key, $selected, true );
      echo '<label class="aegis-plp__check">';
      printf(
        '<input type="checkbox" value="%s" %s data-aegis-filter="temp_limit" />',
        esc_attr( $key ),
        $checked ? 'checked' : ''
      );
      printf( '<span>%s</span>', esc_html( $cfg['label'] ) );
      echo '</label>';
    }
    echo '</div></details>';
  }

  private static function render_price_group(): void {
    $min = isset( $_GET['min_price'] ) ? (string) wp_unslash( $_GET['min_price'] ) : '';
    $max = isset( $_GET['max_price'] ) ? (string) wp_unslash( $_GET['max_price'] ) : '';

    echo '<details class="aegis-plp__group" id="aegis-plp-group-price">';
    echo '<summary class="aegis-plp__group-title">Price</summary>';
    echo '<div class="aegis-plp__group-body aegis-plp__price">';
    echo '<label class="aegis-plp__field"><span>Min</span>';
    printf(
      '<input type="number" step="1" min="0" name="min_price" value="%s" placeholder="0" />',
      esc_attr( $min )
    );
    echo '</label>';
    echo '<label class="aegis-plp__field"><span>Max</span>';
    printf(
      '<input type="number" step="1" min="0" name="max_price" value="%s" placeholder="-" />',
      esc_attr( $max )
    );
    echo '</label>';
    echo '</div></details>';
  }

  private static function render_chips(): void {
    $chips = [];

    // Temperature chips
    $temp_selected = self::request_csv_values( 'temp_limit' );
    $buckets = self::temp_buckets();
    foreach ( $temp_selected as $bk ) {
      if ( ! isset( $buckets[ $bk ] ) ) {
        continue;
      }
      $chips[] = [
        'label' => 'Temp: ' . $buckets[ $bk ]['label'],
        'url'   => self::remove_one_value_url( 'temp_limit', $bk ),
      ];
    }

    // Price chip
    $min = isset( $_GET['min_price'] ) ? trim( (string) wp_unslash( $_GET['min_price'] ) ) : '';
    $max = isset( $_GET['max_price'] ) ? trim( (string) wp_unslash( $_GET['max_price'] ) ) : '';
    if ( $min !== '' || $max !== '' ) {
      $label = 'Price: ' . ( $min !== '' ? $min : '0' ) . ' - ' . ( $max !== '' ? $max : '∞' );
      $chips[] = [
        'label' => $label,
        'url'   => remove_query_arg( [ 'min_price', 'max_price' ] ),
      ];
    }

    // Attribute chips: any filter_{slug}
    foreach ( array_keys( $_GET ) as $k ) {
      if ( strpos( $k, 'filter_' ) !== 0 ) {
        continue;
      }
      $attr_slug = substr( $k, 7 );
      if ( $attr_slug === '' ) {
        continue;
      }
      $taxonomy = self::attribute_taxonomy( $attr_slug );
      if ( ! taxonomy_exists( $taxonomy ) ) {
        continue;
      }

      $vals = self::request_csv_values( $k );
      foreach ( $vals as $slug ) {
        $term = get_term_by( 'slug', $slug, $taxonomy );
        $name = $term && ! is_wp_error( $term ) ? $term->name : $slug;

        $chips[] = [
          'label' => $name,
          'url'   => self::remove_one_value_url( $k, $slug ),
        ];
      }
    }

    if ( empty( $chips ) ) {
      return;
    }

    echo '<div class="aegis-plp__chips" aria-label="Active filters">';
    foreach ( $chips as $chip ) {
      printf(
        '<a class="aegis-plp__chip" href="%s">%s <span aria-hidden="true">×</span></a>',
        esc_url( $chip['url'] ),
        esc_html( $chip['label'] )
      );
    }

    printf(
      '<a class="aegis-plp__chip aegis-plp__chip--clear" href="%s">Clear All</a>',
      esc_url( self::get_clear_url() )
    );

    echo '</div>';
  }

  public static function render_plp_toolbar(): void {
    if ( ! self::is_plp_context() ) {
      return;
    }

    echo '<div class="aegis-plp" data-aegis-plp>';

    echo '<div class="aegis-plp__toolbar">';

    echo '<div class="aegis-plp__toolbar-left">';
    foreach ( self::filter_bar_buttons() as $btn ) {
      printf(
        '<button type="button" class="aegis-plp__filter-btn" data-aegis-open="%s">%s</button>',
        esc_attr( $btn['target'] ),
        esc_html( $btn['label'] )
      );
    }
    echo '</div>';

    echo '<div class="aegis-plp__toolbar-right">';
    echo '<div class="aegis-plp__count">';
    if ( function_exists( 'woocommerce_result_count' ) ) {
      woocommerce_result_count();
    }
    echo '</div>';
    echo '<div class="aegis-plp__ordering">';
    if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
      woocommerce_catalog_ordering();
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // toolbar

    self::render_chips();

    // Overlay + Drawer
    echo '<div class="aegis-plp__overlay" hidden data-aegis-overlay></div>';

    echo '<aside class="aegis-plp__drawer" id="aegis-plp-drawer" hidden aria-hidden="true">';
    echo '<form class="aegis-plp__form" method="get" action="">';
    self::preserved_query_inputs();

    echo '<div class="aegis-plp__drawer-header">';
    echo '<div class="aegis-plp__drawer-title">Filters</div>';
    echo '<button type="button" class="aegis-plp__drawer-close" data-aegis-close aria-label="Close">×</button>';
    echo '</div>';

    echo '<div class="aegis-plp__drawer-body">';

    // Core groups
    self::render_tax_group( 'sleepingbag-color', 'Color', 'aegis-plp-group-color', true );
    self::render_temp_group();
    self::render_price_group();
    self::render_tax_group( 'sleepingbag_fill_type', 'Insulation / Fill Type', 'aegis-plp-group-fill' );
    self::render_tax_group( 'sleepingbag_activity', 'Best Use', 'aegis-plp-group-use' );

    // More filters (container details, to allow "More Filters" scroll target)
    echo '<details class="aegis-plp__group" id="aegis-plp-group-more">';
    echo '<summary class="aegis-plp__group-title">More Filters</summary>';
    echo '<div class="aegis-plp__group-body">';

    // Nested groups inside "More" as simple sections.
    echo '<div class="aegis-plp__subgroups">';
    self::render_tax_group( 'sleepingbag_fp', 'Fill Power', 'aegis-plp-sub-fp' );
    self::render_tax_group( 'sleepingbag_shape', 'Shape', 'aegis-plp-sub-shape' );
    self::render_tax_group( 'sleepingbag_fit', 'Fit', 'aegis-plp-sub-fit' );
    self::render_tax_group( 'sleepingbag_fabric_denier', 'Fabric Denier', 'aegis-plp-sub-denier' );
    self::render_tax_group( 'sleepingbag_zip_side', 'Zipper Side', 'aegis-plp-sub-zip-side' );
    self::render_tax_group( 'sleepingbag_zipper_count', 'Zipper Count', 'aegis-plp-sub-zip-count' );
    self::render_tax_group( 'sleepingbag-size', 'Size', 'aegis-plp-sub-size' );
    self::render_tax_group( 'sleepingbag_model', 'Model', 'aegis-plp-sub-model' );
    self::render_tax_group( 'sleeping-bag-type', 'Sleeping Bag Type', 'aegis-plp-sub-type' );
    echo '</div>';

    echo '</div></details>'; // more

    echo '</div>'; // drawer body

    echo '<div class="aegis-plp__drawer-footer">';
    printf(
      '<a class="aegis-plp__clear" href="%s">Clear All</a>',
      esc_url( self::get_clear_url() )
    );
    echo '<button type="submit" class="aegis-plp__apply">View Results</button>';
    echo '</div>';

    echo '</form></aside>'; // drawer

    echo '</div>'; // aegis-plp root
  }

  /**
   * Apply filters to the main archive query.
   * - Tax filters: any GET param starting with filter_{attr_slug}
   * - Temperature: temp_limit buckets applied to meta key sleepingbag_limit_c
   */
  public static function apply_filters_to_query( $q ): void {
    if ( ! self::is_plp_context() ) {
      return;
    }
    if ( ! $q instanceof WP_Query ) {
      return;
    }
    if ( ! $q->is_main_query() ) {
      return;
    }

    // --- Tax filters (attributes) ---
    $tax_query = $q->get( 'tax_query' );
    if ( ! is_array( $tax_query ) ) {
      $tax_query = [];
    }

    $added_any_tax = false;

    foreach ( array_keys( $_GET ) as $k ) {
      if ( strpos( $k, 'filter_' ) !== 0 ) {
        continue;
      }
      $attr_slug = substr( $k, 7 );
      if ( $attr_slug === '' ) {
        continue;
      }

      $taxonomy = self::attribute_taxonomy( $attr_slug );
      if ( ! taxonomy_exists( $taxonomy ) ) {
        continue;
      }

      $terms = self::request_csv_values( $k );
      if ( empty( $terms ) ) {
        continue;
      }

      $tax_query[] = [
        'taxonomy' => $taxonomy,
        'field'    => 'slug',
        'terms'    => $terms,
        'operator' => 'IN',
      ];
      $added_any_tax = true;
    }

    if ( $added_any_tax ) {
      $tax_query['relation'] = 'AND';
      $q->set( 'tax_query', $tax_query );
    }

    // --- Temperature buckets (meta_query) ---
    $temp_selected = self::request_csv_values( 'temp_limit' );
    if ( ! empty( $temp_selected ) ) {
      $buckets = self::temp_buckets();
      $or = [ 'relation' => 'OR' ];
      $meta_key = 'sleepingbag_limit_c';

      foreach ( $temp_selected as $bk ) {
        if ( ! isset( $buckets[ $bk ] ) ) {
          continue;
        }
        $min = $buckets[ $bk ]['min'];
        $max = $buckets[ $bk ]['max'];

        // min <= x < max
        if ( $min !== null && $max !== null ) {
          $or[] = [
            'relation' => 'AND',
            [
              'key'     => $meta_key,
              'value'   => $min,
              'compare' => '>=',
              'type'    => 'NUMERIC',
            ],
            [
              'key'     => $meta_key,
              'value'   => $max,
              'compare' => '<',
              'type'    => 'NUMERIC',
            ],
          ];
        } elseif ( $min === null && $max !== null ) {
          $or[] = [
            'key'     => $meta_key,
            'value'   => $max,
            'compare' => '<=',
            'type'    => 'NUMERIC',
          ];
        } elseif ( $min !== null && $max === null ) {
          $or[] = [
            'key'     => $meta_key,
            'value'   => $min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
          ];
        }
      }

      if ( count( $or ) > 1 ) {
        $meta_query = $q->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
          $meta_query = [];
        }
        if ( ! isset( $meta_query['relation'] ) ) {
          $meta_query['relation'] = 'AND';
        }
        $meta_query[] = $or;
        $q->set( 'meta_query', $meta_query );
      }
    }
  }
}

Aegis_PLP_Filters_Phase1::boot();
