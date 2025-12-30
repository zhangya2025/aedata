<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($noindex)) : ?>
        <meta name="robots" content="noindex,nofollow">
    <?php endif; ?>
    <style>
        :root {
            --aegis-title-color: <?php echo esc_html($title_color); ?>;
            --aegis-title-size: <?php echo esc_html($title_size); ?>px;
            --aegis-reason-color: <?php echo esc_html($reason_color); ?>;
            --aegis-reason-size: <?php echo esc_html($reason_size); ?>px;
        }
        body {
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
        }
        .aegis-maintenance-wrapper {
            background: #fff;
            padding: 40px 48px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            text-align: center;
            max-width: 680px;
            width: 90%;
        }
        .aegis-maintenance-wrapper h1 {
            margin: 0 0 16px;
            color: var(--aegis-title-color);
            font-size: var(--aegis-title-size);
            font-weight: 700;
        }
        .aegis-maintenance-wrapper p {
            margin: 0;
            color: var(--aegis-reason-color);
            font-size: var(--aegis-reason-size);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="aegis-maintenance-wrapper">
        <h1><?php echo esc_html($title); ?></h1>
        <p><?php echo nl2br(esc_html($reason)); ?></p>
    </div>
</body>
</html>
