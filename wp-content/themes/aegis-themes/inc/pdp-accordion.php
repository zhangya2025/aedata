<?php
/**
 * PDP accordion shortcode for block templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render PDP details accordion via shortcode.
 *
 * @return string
 */
function aegis_pdp_details_shortcode() {
    $post = get_post();

    if ( ! ( $post instanceof WP_Post ) ) {
        return '';
    }

    $post_id = $post->ID;

    $description_raw = get_post_field( 'post_content', $post_id );
    $description     = apply_filters( 'the_content', $description_raw );
    $has_description = ! empty( trim( wp_strip_all_tags( $description_raw ) ) );

    $features      = aegis_pdp_get_meta_value( $post_id, '_aegis_pdp_features', array( 'aegis_pdp_features' ) );
    $feature_lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $features ) ) );
    $has_features  = ! empty( $feature_lines );

    $specs              = aegis_pdp_get_meta_value( $post_id, '_aegis_pdp_specs', array( 'aegis_pdp_specs' ) );
    $spec_lines         = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $specs ) ) );
    $has_specifications = ! empty( $spec_lines );

    if ( ! $has_description && ! $has_features && ! $has_specifications ) {
        return '';
    }

    ob_start();

    if ( current_user_can( 'manage_options' ) ) {
        printf(
            "\n<!-- AEGIS PDP META features_len=%d specs_len=%d -->\n",
            strlen( trim( (string) $features ) ),
            strlen( trim( (string) $specs ) )
        );
    }
    ?>
    <div class="aegis-wc-pdp-accordion">
        <?php if ( $has_description ) : ?>
            <details class="aegis-pdp-acc" open>
                <summary><?php esc_html_e( 'Description', 'aegis-themes' ); ?></summary>
                <div class="aegis-pdp-acc__body">
                    <?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </details>
        <?php endif; ?>

        <?php if ( $has_features ) : ?>
            <details class="aegis-pdp-acc">
                <summary><?php esc_html_e( 'Features', 'aegis-themes' ); ?></summary>
                <div class="aegis-pdp-acc__body">
                    <ul>
                        <?php foreach ( $feature_lines as $line ) : ?>
                            <li><?php echo esc_html( $line ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        <?php endif; ?>

        <?php if ( $has_specifications ) : ?>
            <details class="aegis-pdp-acc">
                <summary><?php esc_html_e( 'Specifications', 'aegis-themes' ); ?></summary>
                <div class="aegis-pdp-acc__body">
                    <table>
                        <tbody>
                        <?php
                        foreach ( $spec_lines as $line ) {
                            $parts = array_map( 'trim', explode( ':', $line, 2 ) );

                            if ( empty( $parts[0] ) ) {
                                continue;
                            }

                            $value = isset( $parts[1] ) ? $parts[1] : '';
                            ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( $parts[0] ); ?></th>
                                <td><?php echo esc_html( $value ); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}
