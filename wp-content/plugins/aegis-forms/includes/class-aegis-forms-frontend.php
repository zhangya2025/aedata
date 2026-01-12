<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aegis_Forms_Frontend {
    public static function init() {
        add_shortcode( 'aegis_repair_form', array( __CLASS__, 'render_repair_form' ) );
        add_shortcode( 'aegis_dealer_form', array( __CLASS__, 'render_dealer_form' ) );
    }

    public static function render_repair_form() {
        return self::render_form( 'repair' );
    }

    public static function render_dealer_form() {
        return self::render_form( 'dealer' );
    }

    private static function render_form( $form_type ) {
        $message = self::get_message();
        ob_start();
        ?>
        <div class="aegis-forms aegis-forms-<?php echo esc_attr( $form_type ); ?>">
            <?php if ( $message ) : ?>
                <div class="aegis-forms__notice">
                    <?php echo esc_html( $message ); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'aegis_forms_submit' ); ?>
                <input type="hidden" name="action" value="aegis_forms_submit" />
                <input type="hidden" name="form_type" value="<?php echo esc_attr( $form_type ); ?>" />

                <div style="display:none;">
                    <label for="aegis-forms-website">Website</label>
                    <input type="text" name="website" id="aegis-forms-website" autocomplete="off" />
                </div>

                <?php if ( 'repair' === $form_type ) : ?>
                    <p>
                        <label for="aegis-forms-name"><?php esc_html_e( 'Name *', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-name" name="name" required />
                    </p>
                    <p>
                        <label for="aegis-forms-email"><?php esc_html_e( 'Email *', 'aegis-forms' ); ?></label>
                        <input type="email" id="aegis-forms-email" name="email" required />
                    </p>
                    <p>
                        <label for="aegis-forms-phone"><?php esc_html_e( 'Phone', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-phone" name="phone" />
                    </p>
                    <p>
                        <label for="aegis-forms-country"><?php esc_html_e( 'Country', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-country" name="country" />
                    </p>
                    <p>
                        <label for="aegis-forms-order"><?php esc_html_e( 'Order Number', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-order" name="order_number" />
                    </p>
                    <p>
                        <label for="aegis-forms-sku"><?php esc_html_e( 'Product SKU', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-sku" name="product_sku" />
                    </p>
                    <p>
                        <label for="aegis-forms-issue"><?php esc_html_e( 'Issue Description *', 'aegis-forms' ); ?></label>
                        <textarea id="aegis-forms-issue" name="issue_description" rows="5" required></textarea>
                    </p>
                <?php else : ?>
                    <p>
                        <label for="aegis-forms-company"><?php esc_html_e( 'Company Name *', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-company" name="company_name" required />
                    </p>
                    <p>
                        <label for="aegis-forms-contact"><?php esc_html_e( 'Contact Name *', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-contact" name="contact_name" required />
                    </p>
                    <p>
                        <label for="aegis-forms-email"><?php esc_html_e( 'Email *', 'aegis-forms' ); ?></label>
                        <input type="email" id="aegis-forms-email" name="email" required />
                    </p>
                    <p>
                        <label for="aegis-forms-phone"><?php esc_html_e( 'Phone *', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-phone" name="phone" required />
                    </p>
                    <p>
                        <label for="aegis-forms-country"><?php esc_html_e( 'Country', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-country" name="country" />
                    </p>
                    <p>
                        <label for="aegis-forms-website-social"><?php esc_html_e( 'Website / Social', 'aegis-forms' ); ?></label>
                        <input type="text" id="aegis-forms-website-social" name="website_social" />
                    </p>
                    <p>
                        <label for="aegis-forms-message"><?php esc_html_e( 'Message', 'aegis-forms' ); ?></label>
                        <textarea id="aegis-forms-message" name="message" rows="5"></textarea>
                    </p>
                <?php endif; ?>

                <p>
                    <label for="aegis-forms-attachments"><?php esc_html_e( 'Attachments (jpg, png, pdf, up to 3 files)', 'aegis-forms' ); ?></label>
                    <input type="file" id="aegis-forms-attachments" name="attachments[]" multiple="multiple" accept=".jpg,.jpeg,.png,.pdf" />
                </p>

                <p>
                    <button type="submit"><?php esc_html_e( 'Submit', 'aegis-forms' ); ?></button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_message() {
        if ( empty( $_GET['aegis_forms'] ) ) {
            return '';
        }

        $state = sanitize_text_field( wp_unslash( $_GET['aegis_forms'] ) );
        $ticket = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
        $msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';

        if ( 'submitted' === $state && $ticket ) {
            return sprintf( __( 'Submission received. Your ticket number is %s.', 'aegis-forms' ), $ticket );
        }

        if ( 'error' === $state ) {
            $messages = array(
                'nonce' => __( 'Security check failed. Please try again.', 'aegis-forms' ),
                'invalid' => __( 'Invalid submission. Please try again.', 'aegis-forms' ),
                'rate' => __( 'Too many submissions. Please try again later.', 'aegis-forms' ),
                'required' => __( 'Please fill in the required fields.', 'aegis-forms' ),
                'upload' => __( 'Attachment upload failed. Please try again.', 'aegis-forms' ),
                'server' => __( 'Server error. Please try again.', 'aegis-forms' ),
            );

            if ( isset( $messages[ $msg ] ) ) {
                return $messages[ $msg ];
            }

            return __( 'Submission failed. Please try again.', 'aegis-forms' );
        }

        return '';
    }
}
