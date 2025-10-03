<?php
/*
Plugin Name: WebCut - URL Shortener
Description: A simple URL shortener plugin for WordPress.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

const WEBCUT_DEFAULT_PREFIX = 'webcut';
const WEBCUT_DB_VERSION = '2.0.0';

function webcut_get_prefix() {
    $prefix = get_option('webcut_prefix', WEBCUT_DEFAULT_PREFIX);
    if (!is_string($prefix) || $prefix === '') {
        return WEBCUT_DEFAULT_PREFIX;
    }

    return sanitize_title($prefix);
}

function webcut_register_settings() {
    register_setting(
        'webcut_settings',
        'webcut_prefix',
        [
            'type'              => 'string',
            'sanitize_callback' => 'webcut_sanitize_prefix',
            'default'           => WEBCUT_DEFAULT_PREFIX,
        ]
    );
}
add_action('admin_init', 'webcut_register_settings');

function webcut_sanitize_prefix($value) {
    $value = sanitize_title($value);

    return $value === '' ? WEBCUT_DEFAULT_PREFIX : $value;
}

function webcut_register_admin_page() {
    add_options_page(
        __('WebCut Settings', 'webcut'),
        __('WebCut', 'webcut'),
        'manage_options',
        'webcut-settings',
        'webcut_render_settings_page'
    );
}
add_action('admin_menu', 'webcut_register_admin_page');

function webcut_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'webcut'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'webcut';
    $entries = $wpdb->get_results("SELECT id, long_url, custom_url, prefix, created_at FROM $table_name ORDER BY created_at DESC");
    $notice = isset($_GET['webcut_notice']) ? sanitize_key($_GET['webcut_notice']) : '';

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('WebCut Settings', 'webcut'); ?></h1>
        <?php if ($notice === 'deleted') : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('WebCut entry deleted.', 'webcut'); ?></p>
            </div>
        <?php elseif ($notice === 'error') : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Unable to delete the WebCut entry.', 'webcut'); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields('webcut_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="webcut_prefix"><?php esc_html_e('URL Prefix', 'webcut'); ?></label>
                        </th>
                        <td>
                            <input name="webcut_prefix" id="webcut_prefix" type="text" value="<?php echo esc_attr(webcut_get_prefix()); ?>" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('This prefix is applied to every shortened link. Default is "webcut".', 'webcut'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2><?php esc_html_e('Existing WebCuts', 'webcut'); ?></h2>
        <?php if (empty($entries)) : ?>
            <p><?php esc_html_e('No shortened URLs found yet.', 'webcut'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Short URL', 'webcut'); ?></th>
                        <th><?php esc_html_e('Destination', 'webcut'); ?></th>
                        <th><?php esc_html_e('Prefix', 'webcut'); ?></th>
                        <th><?php esc_html_e('Slug', 'webcut'); ?></th>
                        <th><?php esc_html_e('Created', 'webcut'); ?></th>
                        <th><?php esc_html_e('Actions', 'webcut'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) :
                        $short_url = home_url('/' . $entry->prefix . '/' . $entry->custom_url);
                        $created = $entry->created_at ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->created_at) : __('Not recorded', 'webcut');
                        $delete_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action' => 'webcut_delete',
                                    'id'     => $entry->id,
                                ],
                                admin_url('admin-post.php')
                            ),
                            'webcut_delete_' . $entry->id
                        );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url($short_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($short_url); ?></a></td>
                            <td><a href="<?php echo esc_url($entry->long_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($entry->long_url); ?></a></td>
                            <td><?php echo esc_html($entry->prefix); ?></td>
                            <td><?php echo esc_html($entry->custom_url); ?></td>
                            <td><?php echo esc_html($created); ?></td>
                            <td><a class="button button-small button-secondary" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this WebCut?', 'webcut')); ?>');"><?php esc_html_e('Delete', 'webcut'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Register shortcode
function webcut_shortcode() {
    $prefix = webcut_get_prefix();
    $base   = trailingslashit(home_url('/' . $prefix));
    $can_create = is_user_logged_in() && current_user_can(webcut_required_capability());
    ob_start();
    ?>
    <div class="webcut-wrapper container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="card-title text-center mb-4"><?php esc_html_e('WebCut - URL Shortener', 'webcut'); ?></h2>
                        <?php if (!$can_create) : ?>
                            <div class="alert alert-warning mb-0"><?php esc_html_e('You need the appropriate permissions to create WebCuts. Please log in with an account that can manage options.', 'webcut'); ?></div>
                        <?php else : ?>
                            <form id="webcutForm" class="webcut-form needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="longUrl" class="form-label"><?php esc_html_e('URL to shorten', 'webcut'); ?></label>
                                    <input type="url" class="form-control form-control-lg" id="longUrl" name="longUrl" placeholder="<?php esc_attr_e('https://example.com/page', 'webcut'); ?>" required>
                                    <div class="form-text"><?php esc_html_e('Enter the full destination URL you want to shorten.', 'webcut'); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="customUrl" class="form-label"><?php esc_html_e('Custom path', 'webcut'); ?></label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text"><?php echo esc_html($base); ?></span>
                                        <input type="text" class="form-control" id="customUrl" name="customUrl" placeholder="<?php esc_attr_e('my-custom-slug', 'webcut'); ?>" required>
                                    </div>
                                    <div class="form-text"><?php esc_html_e('Use lowercase letters, numbers, or hyphens.', 'webcut'); ?></div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><?php esc_html_e('Make WebCut', 'webcut'); ?></button>
                            </form>
                        <?php endif; ?>
                        <div id="result" class="mt-4" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('webcut', 'webcut_shortcode');

function webcut_enqueue_assets() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    if (!is_singular()) {
        return;
    }

    $post = get_post();
    if (!$post || !has_shortcode((string) $post->post_content, 'webcut')) {
        return;
    }

    $bootstrap_version = '5.3.3';

    wp_enqueue_style(
        'webcut-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        $bootstrap_version
    );

    wp_enqueue_style(
        'webcut-frontend',
        plugins_url('assets/css/webcut.css', __FILE__),
        ['webcut-bootstrap'],
        WEBCUT_DB_VERSION
    );

    wp_enqueue_script(
        'webcut-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        [],
        $bootstrap_version,
        true
    );

    wp_register_script(
        'webcut-frontend',
        plugins_url('assets/js/webcut.js', __FILE__),
        [],
        WEBCUT_DB_VERSION,
        true
    );

    wp_localize_script(
        'webcut-frontend',
        'webcutData',
        [
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('webcut_create'),
            'defaultError'      => __('Unable to create WebCut. Please try again.', 'webcut'),
            'missingFields'     => __('Both fields are required.', 'webcut'),
            'successHeading'    => __('Your WebCut URL:', 'webcut'),
            'processingMessage' => __('Processing your WebCut...', 'webcut'),
            'creatingLabel'     => __('Creating...', 'webcut'),
        ]
    );

    wp_enqueue_script('webcut-frontend');
}
add_action('wp_enqueue_scripts', 'webcut_enqueue_assets');

// Handle form submission
function webcut_required_capability() {
    return apply_filters('webcut_required_capability', 'manage_options');
}

function webcut_rate_limit_key() {
    if (is_user_logged_in()) {
        return 'user_' . get_current_user_id();
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    return 'ip_' . md5($ip);
}

function webcut_check_rate_limit() {
    $limit = (int) apply_filters('webcut_rate_limit_total', 5);
    $window = (int) apply_filters('webcut_rate_limit_window', 5 * MINUTE_IN_SECONDS);
    $key = 'webcut_rate_' . webcut_rate_limit_key();
    $count = (int) get_transient($key);

    if ($count >= $limit) {
        return true;
    }

    set_transient($key, $count + 1, $window);

    return false;
}

function create_webcut() {
    if (!check_ajax_referer('webcut_create', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh and try again.', 'webcut')], 400);
    }

    if (!is_user_logged_in() || !current_user_can(webcut_required_capability())) {
        wp_send_json_error(['message' => __('You do not have permission to create WebCuts.', 'webcut')], 403);
    }

    if (webcut_check_rate_limit()) {
        wp_send_json_error(['message' => __('Rate limit reached. Please wait before creating another WebCut.', 'webcut')], 429);
    }

    global $wpdb;
    $long_url = isset($_POST['long_url']) ? wp_unslash($_POST['long_url']) : '';
    $custom_url = isset($_POST['custom_url']) ? wp_unslash($_POST['custom_url']) : '';

    $long_url = wp_http_validate_url($long_url);
    $custom_url = sanitize_title($custom_url);

    if (!$long_url || empty($custom_url)) {
        wp_send_json_error(['message' => __('Both fields are required and must be valid.', 'webcut')], 400);
    }

    $table_name = $wpdb->prefix . 'webcut';
    $prefix = webcut_get_prefix();

    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE prefix = %s AND custom_url = %s",
            $prefix,
            $custom_url
        )
    );

    if ($exists) {
        wp_send_json_error(['message' => __('Custom URL already exists for this prefix.', 'webcut')], 409);
    }

    $inserted = $wpdb->insert(
        $table_name,
        [
            'long_url'   => esc_url_raw($long_url),
            'custom_url' => $custom_url,
            'prefix'     => $prefix,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s']
    );

    if (!$inserted) {
        wp_send_json_error(['message' => __('Unable to create WebCut. Please try again.', 'webcut')], 500);
    }

    wp_send_json_success([
        'short_url' => home_url('/' . $prefix . '/' . $custom_url),
    ]);
}
add_action('wp_ajax_create_webcut', 'create_webcut');
add_action('wp_ajax_nopriv_create_webcut', 'create_webcut');

function webcut_install_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webcut';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        long_url text NOT NULL,
        custom_url varchar(100) NOT NULL,
        prefix varchar(100) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY webcut_prefix_custom (prefix, custom_url)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('webcut_db_version', WEBCUT_DB_VERSION);
}

function webcut_maybe_upgrade() {
    $stored_version = get_option('webcut_db_version');
    if ($stored_version !== WEBCUT_DB_VERSION) {
        webcut_install_table();
    }
}

register_activation_hook(__FILE__, 'webcut_install_table');
add_action('plugins_loaded', 'webcut_maybe_upgrade');

function webcut_handle_delete() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'webcut'));
    }

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

    if (!$id || !wp_verify_nonce($nonce, 'webcut_delete_' . $id)) {
        wp_die(__('Invalid delete request.', 'webcut'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'webcut';
    $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'          => 'webcut-settings',
            'webcut_notice' => $deleted ? 'deleted' : 'error',
        ],
        admin_url('options-general.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_webcut_delete', 'webcut_handle_delete');

// Handle the redirection
function webcut_redirect() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');

    if ($path === '') {
        return;
    }

    $segments = explode('/', $path, 3);
    if (count($segments) < 2) {
        return;
    }

    $prefix = sanitize_title($segments[0]);
    $slug = sanitize_title($segments[1]);

    if ($prefix === '' || $slug === '') {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'webcut';

    $long_url = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT long_url FROM $table_name WHERE prefix = %s AND custom_url = %s",
            $prefix,
            $slug
        )
    );

    if ($long_url) {
        wp_redirect($long_url, 301);
        exit;
    }
}
add_action('template_redirect', 'webcut_redirect');
?>
