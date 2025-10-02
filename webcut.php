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

// Register shortcode
function webcut_shortcode() {
    ob_start();
    ?>
    <div class="container">
        <h2 class="text-center">WebCut - URL Shortener</h2>
        <form id="webcutForm">
            <div class="form-group">
                <label for="longUrl">URL to shorten:</label>
                <input type="url" class="form-control" id="longUrl" name="longUrl" placeholder="Enter the long URL" required>
            </div>
            <div class="form-group">
                <label for="customUrl">Custom URL: <?php echo esc_url(home_url('/')); ?></label>
                <input type="text" class="form-control" id="customUrl" name="customUrl" placeholder="Enter custom URL" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Make WebCut</button>
        </form>
        <div id="result" class="mt-3"></div>
    </div>
    <script>
        document.getElementById('webcutForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const longUrl = document.getElementById('longUrl').value;
            const customUrl = document.getElementById('customUrl').value;
            const resultDiv = document.getElementById('result');
            if (!longUrl || !customUrl) {
                resultDiv.innerHTML = '<div class="alert alert-danger">Both fields are required.</div>';
                return;
            }
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'action': 'create_webcut',
                    'long_url': longUrl,
                    'custom_url': customUrl
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success">Your WebCut URL: <a href="' + data.short_url + '" target="_blank">' + data.short_url + '</a></div>';
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    }
                });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('webcut', 'webcut_shortcode');

// Handle form submission
function create_webcut() {
    global $wpdb;
    $long_url = sanitize_text_field($_POST['long_url']);
    $custom_url = sanitize_text_field($_POST['custom_url']);

    if (empty($long_url) || empty($custom_url)) {
        wp_send_json_error(['message' => 'Both fields are required.']);
    }

    // Check if the custom URL already exists
    $table_name = $wpdb->prefix . 'webcut';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE custom_url = %s", $custom_url));

    if ($exists) {
        wp_send_json_error(['message' => 'Custom URL already exists.']);
    }

    // Insert into the database
    $wpdb->insert($table_name, [
        'long_url' => $long_url,
        'custom_url' => $custom_url
    ]);

    wp_send_json_success(['short_url' => home_url('/' . $custom_url)]);
}
add_action('wp_ajax_create_webcut', 'create_webcut');
add_action('wp_ajax_nopriv_create_webcut', 'create_webcut');

// Create the database table
function webcut_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webcut';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        long_url text NOT NULL,
        custom_url varchar(100) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY custom_url (custom_url)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'webcut_activate');

// Handle the redirection
function webcut_redirect() {
    global $wpdb;
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $table_name = $wpdb->prefix . 'webcut';

    $long_url = $wpdb->get_var($wpdb->prepare("SELECT long_url FROM $table_name WHERE custom_url = %s", $request_uri));

    if ($long_url) {
        wp_redirect($long_url, 301);
        exit();
    }
}
add_action('template_redirect', 'webcut_redirect');
?>
