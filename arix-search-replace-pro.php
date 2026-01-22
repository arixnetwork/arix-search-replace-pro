<?php
/**
 * Plugin Name: ARIX Search & Replace Pro
 * Plugin URI: https://arixnetwork.com/plugins/search-replace-pro
 * Description: Advanced search and replace tool for your entire WordPress site. Safely update text, URLs, and content across all posts, pages, products, and custom post types.
 * Version: 1.0.0
 * Author: ARIXNETWORK
 * Author URI: https://arixnetwork.com
 * Text Domain: arix-search-replace
 * Domain Path: /languages
 * License: GPL-3.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ARIX_Search_Replace_Pro {
    
    private $version = '1.0.0';
    private $plugin_name = 'arix-search-replace';
    
    public function __construct() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_arix_search_replace', array($this, 'handle_search_replace'));
        add_action('wp_ajax_arix_preview_results', array($this, 'preview_results'));
        add_action('wp_ajax_arix_generate_dummy_content', array($this, 'generate_dummy_content'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain($this->plugin_name, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('ARIX Search & Replace Pro', $this->plugin_name),
            __('Search & Replace', $this->plugin_name),
            'manage_options',
            'arix-search-replace',
            array($this, 'admin_page'),
            'dashicons-search',
            60
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_arix-search-replace') {
            return;
        }
        
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), $this->version);
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), $this->version, true);
        
        wp_localize_script($this->plugin_name, 'arix_srp', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arix_srp_nonce'),
            'confirm_message' => __('Are you sure? This action cannot be undone!', $this->plugin_name),
            'processing_message' => __('Processing... Please wait.', $this->plugin_name),
            'success_message' => __('Operation completed successfully!', $this->plugin_name),
            'error_message' => __('An error occurred. Please try again.', $this->plugin_name)
        ));
    }
    
    public function admin_page() {
        ?>
        <div class="wrap arix-srp-container">
            <h1 class="wp-heading-inline"><?php _e('ARIX Search & Replace Pro', $this->plugin_name); ?></h1>
            <hr class="wp-header-end">
            
            <div class="arix-srp-notices">
                <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
                    <div class="notice notice-success"><p><?php _e('Content updated successfully!', $this->plugin_name); ?></p></div>
                <?php endif; ?>
            </div>
            
            <div class="arix-srp-main">
                <div class="arix-srp-card">
                    <h2><?php _e('Search & Replace Settings', $this->plugin_name); ?></h2>
                    
                    <form id="arix-srp-form">
                        <div class="arix-srp-form-group">
                            <label for="search_text"><?php _e('Search for:', $this->plugin_name); ?></label>
                            <input type="text" id="search_text" name="search_text" required>
                            <p class="description"><?php _e('Text or URL to find in your content', $this->plugin_name); ?></p>
                        </div>
                        
                        <div class="arix-srp-form-group">
                            <label for="replace_text"><?php _e('Replace with:', $this->plugin_name); ?></label>
                            <input type="text" id="replace_text" name="replace_text">
                            <p class="description"><?php _e('Leave empty to delete the found text', $this->plugin_name); ?></p>
                        </div>
                        
                        <div class="arix-srp-form-group">
                            <label><?php _e('Content Types:', $this->plugin_name); ?></label>
                            <div class="arix-srp-checkbox-group">
                                <?php
                                $post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($post_types as $post_type) {
                                    printf(
                                        '<label><input type="checkbox" name="post_types[]" value="%s" checked> %s</label>',
                                        esc_attr($post_type->name),
                                        esc_html($post_type->label)
                                    );
                                }
                                ?>
                            </div>
                            <p class="description"><?php _e('Select which content types to include in the search', $this->plugin_name); ?></p>
                        </div>
                        
                        <div class="arix-srp-form-group">
                            <label>
                                <input type="checkbox" name="case_sensitive" value="1"> 
                                <?php _e('Case sensitive search', $this->plugin_name); ?>
                            </label>
                        </div>
                        
                        <div class="arix-srp-form-group">
                            <label>
                                <input type="checkbox" name="whole_words" value="1"> 
                                <?php _e('Match whole words only', $this->plugin_name); ?>
                            </label>
                        </div>
                        
                        <div class="arix-srp-actions">
                            <button type="button" id="preview-btn" class="button button-primary">
                                <?php _e('Preview Changes', $this->plugin_name); ?>
                            </button>
                            <button type="submit" id="replace-btn" class="button button-secondary" disabled>
                                <?php _e('Apply Changes', $this->plugin_name); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="arix-srp-preview" id="preview-results">
                    <h2><?php _e('Preview Results', $this->plugin_name); ?></h2>
                    <div class="arix-srp-preview-content">
                        <p><?php _e('Enter your search terms and click "Preview Changes" to see what will be modified.', $this->plugin_name); ?></p>
                    </div>
                </div>
                
                <div class="arix-srp-card">
                    <h2><?php _e('Generate Dummy Content', $this->plugin_name); ?></h2>
                    <p><?php _e('Create sample pages, posts, and products for testing purposes.', $this->plugin_name); ?></p>
                    <button id="generate-dummy" class="button button-secondary">
                        <?php _e('Generate Sample Content', $this->plugin_name); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_search_replace() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'arix_srp_nonce')) {
            wp_die(__('Security check failed', $this->plugin_name));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', $this->plugin_name));
        }
        
        $search_text = sanitize_text_field($_POST['search_text']);
        $replace_text = sanitize_text_field($_POST['replace_text']);
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array();
        $case_sensitive = isset($_POST['case_sensitive']) ? true : false;
        $whole_words = isset($_POST['whole_words']) ? true : false;
        
        if (empty($search_text) || empty($post_types)) {
            wp_send_json_error(__('Invalid parameters', $this->plugin_name));
        }
        
        // Build query arguments
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        $updated_count = 0;
        
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            $original_content = $post->post_content;
            $original_title = $post->post_title;
            $original_excerpt = $post->post_excerpt;
            
            $new_content = $this->perform_replacement($original_content, $search_text, $replace_text, $case_sensitive, $whole_words);
            $new_title = $this->perform_replacement($original_title, $search_text, $replace_text, $case_sensitive, $whole_words);
            $new_excerpt = $this->perform_replacement($original_excerpt, $search_text, $replace_text, $case_sensitive, $whole_words);
            
            // Update post if changes were made
            if ($new_content !== $original_content || $new_title !== $original_title || $new_excerpt !== $original_excerpt) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $new_content,
                    'post_title' => $new_title,
                    'post_excerpt' => $new_excerpt
                ));
                $updated_count++;
            }
        }
        
        // Also update options table (for site URLs, etc.)
        if (strpos($search_text, 'http') === 0) {
            $this->update_options_table($search_text, $replace_text);
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully updated %d items', $this->plugin_name), $updated_count),
            'count' => $updated_count
        ));
    }
    
    private function perform_replacement($content, $search, $replace, $case_sensitive, $whole_words) {
        if (empty($content)) {
            return $content;
        }
        
        $flags = $case_sensitive ? '' : 'i';
        $pattern = preg_quote($search, '/');
        
        if ($whole_words) {
            $pattern = '\b' . $pattern . '\b';
        }
        
        $pattern = '/' . $pattern . '/' . $flags;
        return preg_replace($pattern, $replace, $content);
    }
    
    private function update_options_table($search, $replace) {
        global $wpdb;
        
        // Get all options that might contain URLs
        $options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE '%{$search}%'");
        
        foreach ($options as $option) {
            $new_value = str_replace($search, $replace, $option->option_value);
            if ($new_value !== $option->option_value) {
                update_option($option->option_name, $new_value);
            }
        }
    }
    
    public function preview_results() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'arix_srp_nonce')) {
            wp_die(__('Security check failed', $this->plugin_name));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', $this->plugin_name));
        }
        
        $search_text = sanitize_text_field($_POST['search_text']);
        $replace_text = sanitize_text_field($_POST['replace_text']);
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array();
        $case_sensitive = isset($_POST['case_sensitive']) ? true : false;
        $whole_words = isset($_POST['whole_words']) ? true : false;
        
        if (empty($search_text) || empty($post_types)) {
            wp_send_json_error(__('Invalid parameters', $this->plugin_name));
        }
        
        // Build query arguments
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'any',
            'posts_per_page' => 10 // Limit preview to 10 results
        );
        
        $query = new WP_Query($args);
        $results = array();
        
        foreach ($query->posts as $post) {
            $content = $post->post_content;
            $title = $post->post_title;
            $excerpt = $post->post_excerpt;
            
            // Check if search term exists in any field
            $found_in = array();
            if (strpos($content, $search_text) !== false) $found_in[] = 'content';
            if (strpos($title, $search_text) !== false) $found_in[] = 'title';
            if (strpos($excerpt, $search_text) !== false) $found_in[] = 'excerpt';
            
            if (!empty($found_in)) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'found_in' => $found_in,
                    'original_content' => wp_trim_words($content, 20),
                    'replaced_content' => wp_trim_words($this->perform_replacement($content, $search_text, $replace_text, $case_sensitive, $whole_words), 20)
                );
            }
        }
        
        wp_send_json_success($results);
    }
    
    public function generate_dummy_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'arix_srp_nonce')) {
            wp_die(__('Security check failed', $this->plugin_name));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', $this->plugin_name));
        }
        
        // Generate dummy pages
        $pages = array(
            'About Us' => 'This is a sample about page with some dummy content for testing.',
            'Services' => 'Our services include web development, design, and SEO optimization.',
            'Contact' => 'Contact us at contact@example.com or call (555) 123-4567.'
        );
        
        foreach ($pages as $title => $content) {
            if (!get_page_by_title($title, OBJECT, 'page')) {
                wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ));
            }
        }
        
        // Generate dummy posts
        $posts = array(
            'Welcome to Our Blog' => 'This is the first post on our new blog. We will be sharing industry insights and company news here.',
            'Top 10 WordPress Tips' => 'Discover the best practices for optimizing your WordPress site for performance and security.',
            'How to Use Search & Replace' => 'Learn how to effectively use the ARIX Search & Replace Pro plugin to manage your content.'
        );
        
        foreach ($posts as $title => $content) {
            if (!get_page_by_title($title, OBJECT, 'post')) {
                wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'post'
                ));
            }
        }
        
        // Generate dummy products (if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            $products = array(
                'Premium WordPress Theme' => 'A responsive, multipurpose WordPress theme with advanced customization options.',
                'SEO Optimization Plugin' => 'Boost your site\'s visibility with our powerful SEO toolkit.',
                'E-commerce Starter Kit' => 'Everything you need to launch your online store with WordPress and WooCommerce.'
            );
            
            foreach ($products as $title => $content) {
                if (!get_page_by_title($title, OBJECT, 'product')) {
                    wp_insert_post(array(
                        'post_title' => $title,
                        'post_content' => $content,
                        'post_status' => 'publish',
                        'post_type' => 'product'
                    ));
                }
            }
        }
        
        wp_send_json_success(__('Dummy content generated successfully!', $this->plugin_name));
    }
}

// Initialize the plugin
new ARIX_Search_Replace_Pro();

// Create assets directory structure
if (!file_exists(plugin_dir_path(__FILE__) . 'assets')) {
    wp_mkdir_p(plugin_dir_path(__FILE__) . 'assets/css');
    wp_mkdir_p(plugin_dir_path(__FILE__) . 'assets/js');
}

// Create CSS file
$css_content = '
.arix-srp-container {
    max-width: 1200px;
    margin: 0 auto;
}

.arix-srp-main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 992px) {
    .arix-srp-main {
        grid-template-columns: 1fr;
    }
}

.arix-srp-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.arix-srp-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.arix-srp-form-group {
    margin-bottom: 20px;
}

.arix-srp-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.arix-srp-form-group input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.arix-srp-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.arix-srp-checkbox-group label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: normal;
    margin: 0;
    padding: 5px 10px;
    background: #f8f9f9;
    border-radius: 4px;
    cursor: pointer;
}

.arix-srp-checkbox-group input[type="checkbox"] {
    margin: 0;
}

.arix-srp-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.arix-srp-preview {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 20px;
}

.arix-srp-preview h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.arix-srp-preview-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.arix-srp-preview-item:last-child {
    border-bottom: none;
}

.arix-srp-preview-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.arix-srp-preview-content pre {
    background: #f8f9f9;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    white-space: pre-wrap;
}

.arix-srp-loading {
    text-align: center;
    padding: 20px;
}

.arix-srp-loading .spinner {
    float: none;
    margin: 0 auto;
}

/* Responsive adjustments */
@media (max-width: 782px) {
    .arix-srp-actions {
        flex-direction: column;
    }
    
    .arix-srp-actions button {
        width: 100%;
    }
}
';

file_put_contents(plugin_dir_path(__FILE__) . 'assets/css/admin.css', $css_content);

// Create JS file
$js_content = '
jQuery(document).ready(function($) {
    let previewData = null;
    
    // Preview button handler
    $("#preview-btn").on("click", function() {
        const formData = $("#arix-srp-form").serialize();
        
        $.ajax({
            url: arix_srp.ajax_url,
            type: "POST",
            data: {
                action: "arix_preview_results",
                nonce: arix_srp.nonce,
                ...$("#arix-srp-form").serializeArray().reduce((obj, item) => {
                    obj[item.name] = item.value;
                    return obj;
                }, {})
            },
            beforeSend: function() {
                $("#preview-results .arix-srp-preview-content").html(
                    \'<div class="arix-srp-loading"><span class="spinner is-active"></span><p>\' + arix_srp.processing_message + \'</p></div>\'
                );
                $("#replace-btn").prop("disabled", true);
            },
            success: function(response) {
                if (response.success) {
                    previewData = response.data;
                    let html = \'<div class="arix-srp-preview-items">\';
                    
                    if (response.data.length === 0) {
                        html += \'<p>No matches found for your search criteria.</p>\';
                    } else {
                        response.data.forEach(function(item) {
                            html += \'<div class="arix-srp-preview-item">\';
                            html += \'<h3>\' + item.title + \' <small>(\' + item.type + \')</small></h3>\';
                            html += \'<p><strong>Original:</strong> \' + item.original_content + \'</p>\';
                            html += \'<p><strong>Replaced:</strong> \' + item.replaced_content + \'</p>\';
                            html += \'</div>\';
                        });
                        html += \'<p>Total matches found: \' + response.data.length + \'</p>\';
                    }
                    
                    html += \'</div>\';
                    $("#preview-results .arix-srp-preview-content").html(html);
                    $("#replace-btn").prop("disabled", false);
                } else {
                    $("#preview-results .arix-srp-preview-content").html(\'<p class="error">\' + arix_srp.error_message + \'</p>\');
                }
            },
            error: function() {
                $("#preview-results .arix-srp-preview-content").html(\'<p class="error">\' + arix_srp.error_message + \'</p>\');
            }
        });
    });
    
    // Replace button handler
    $("#arix-srp-form").on("submit", function(e) {
        e.preventDefault();
        
        if (!confirm(arix_srp.confirm_message)) {
            return;
        }
        
        $.ajax({
            url: arix_srp.ajax_url,
            type: "POST",
            data: {
                action: "arix_search_replace",
                nonce: arix_srp.nonce,
                ...$(this).serializeArray().reduce((obj, item) => {
                    obj[item.name] = item.value;
                    return obj;
                }, {})
            },
            beforeSend: function() {
                $("#replace-btn").prop("disabled", true).text(arix_srp.processing_message);
            },
            success: function(response) {
                if (response.success) {
                    alert(arix_srp.success_message + " " + response.data.message);
                    location.reload();
                } else {
                    alert(arix_srp.error_message);
                    $("#replace-btn").prop("disabled", false).text("Apply Changes");
                }
            },
            error: function() {
                alert(arix_srp.error_message);
                $("#replace-btn").prop("disabled", false).text("Apply Changes");
            }
        });
    });
    
    // Generate dummy content
    $("#generate-dummy").on("click", function() {
        if (!confirm("This will create sample pages, posts, and products. Continue?")) {
            return;
        }
        
        $.ajax({
            url: arix_srp.ajax_url,
            type: "POST",
            data: {
                action: "arix_generate_dummy_content",
                nonce: arix_srp.nonce
            },
            beforeSend: function() {
                $(this).prop("disabled", true).text("Generating...");
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(arix_srp.error_message);
                    $("#generate-dummy").prop("disabled", false).text("Generate Sample Content");
                }
            },
            error: function() {
                alert(arix_srp.error_message);
                $("#generate-dummy").prop("disabled", false).text("Generate Sample Content");
            }
        });
    });
});
';

file_put_contents(plugin_dir_path(__FILE__) . 'assets/js/admin.js', $js_content);