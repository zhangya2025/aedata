<?php

if (!defined('ABSPATH')) {
    exit;
}

class Aegis_Maintenance_Guard
{
    public function init()
    {
        add_action('template_redirect', [$this, 'maybe_block'], 100);
    }

    public function maybe_block()
    {
        $options = Aegis_Maintenance::get_options();
        if (empty($options['enabled'])) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        $path = parse_url(add_query_arg([]), PHP_URL_PATH);
        $path = '/' . ltrim($path, '/');

        if ($path === '/aegislogin.php') {
            return;
        }
        if (!empty($GLOBALS['__aegis_safe_login_whitelist'])) {
            return;
        }
        if ($path === '/wp-login.php') {
            return;
        }

        if ($path === '/wp-admin/admin-ajax.php' || $path === '/wp-cron.php') {
            return;
        }

        if (is_admin()) {
            return;
        }

        if (is_user_logged_in() && $this->current_user_allowed($options['allow_roles'])) {
            return;
        }

        if (!empty($options['allow_paths']) && $this->path_matches_any($path, $options['allow_paths'])) {
            return;
        }

        $this->send_headers($options);

        $title = $options['title_text'];
        $title_size = intval($options['title_size_px']);
        $title_color = $options['title_color'];
        $reason = $options['reason_text'];
        $reason_size = intval($options['reason_size_px']);
        $reason_color = $options['reason_color'];
        $noindex = !empty($options['noindex']);

        include AEGIS_MAINTENANCE_PLUGIN_DIR . 'public/maintenance-template.php';
        exit;
    }

    private function current_user_allowed($allowed_roles)
    {
        if (!is_array($allowed_roles)) {
            return false;
        }

        $user = wp_get_current_user();
        if (empty($user->roles)) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function path_matches_any($path, $patterns_string)
    {
        $lines = preg_split('/\r?\n/', $patterns_string);
        foreach ($lines as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            if ($this->match_pattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function match_pattern($path, $pattern)
    {
        if (strpos($pattern, '*') === false) {
            return strpos($path, $pattern) === 0;
        }

        $escaped = preg_quote($pattern, '#');
        $regex = '#^' . str_replace('\\*', '[^/]+', $escaped) . '#';
        return (bool) preg_match($regex, $path);
    }

    private function send_headers($options)
    {
        if (!headers_sent()) {
            if (!empty($options['send_503'])) {
                status_header(503);
            }
            if (!empty($options['retry_after_minutes'])) {
                $seconds = intval($options['retry_after_minutes']) * 60;
                header('Retry-After: ' . $seconds);
            }
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }
}
