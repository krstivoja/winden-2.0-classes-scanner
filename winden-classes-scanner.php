<?php

/**
 * Plugin Name:       Winden's 2.0 Classes Scanner
 * Description:       Scan files to create safe list of classes
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dplugins-winden-classes-scanner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Winden_Classes_Scanner
{
    /**
     * Class constructor to add hooks.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('f!winden/core/worker:compile_content_payload', array($this, 'compile_content_payload'), 10);
    }

    /**
     * Add submenu page to the Tools menu.
     */
    public function add_admin_menu()
    {
        add_submenu_page('tools.php', 'Winden Class Extractor', 'Winden Class Extractor', 'manage_options', 'class-extractor', array($this, 'render_admin_page'));
    }

    /**
     * Render admin page and handle form submissions.
     */
    public function render_admin_page()
    {
        $option_name = 'class_extractor_path';
        $default_path = 'plugins/' . basename(dirname(__FILE__)) . '/src/*/';

        $saved_path = get_option($option_name, $default_path);
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : $saved_path;

        if (isset($_POST['save_path'])) {
            update_option($option_name, $path);
            echo '<div class="notice notice-success is-dismissible"><p>Path saved!</p></div>';
        }

        if (isset($_POST['scan_directory'])) {
            $wp_content_dir = WP_CONTENT_DIR . '/';
            $src_directories = glob($wp_content_dir . $path, GLOB_ONLYDIR);

            $classes = array();
            foreach ($src_directories as $dir) {
                $classes = array_merge($classes, $this->scan_directory_for_classes($dir));
            }

            file_put_contents(plugin_dir_path(__FILE__) . 'extracted_classes.txt', implode("\n", $classes));
            echo '<div class="notice notice-success is-dismissible"><p>Classes extracted and saved to extracted_classes.txt!</p></div>';
        }

?>
        <style>
            p.submit {
                margin: 0;
                padding: 0;
            }
        </style>
        <div class="wrap">
            <h2>Winden's Class Extractor</h2>
            <hr>
            <form method="post">
                <h2>Scanning path</h2>

                <div style="display: flex; align-items: center;">
                    <label for="path">wp-content/</label>
                    <input style="margin-right:5px;" type="text" id="path" name="path" value="<?php echo esc_attr($path); ?>" class="regular-text">
                    <?php submit_button('Save Path', 'button', 'save_path'); ?>
                </div>

                <br><br>
                <?php submit_button('Scan and Extract Classes', 'primary', 'scan_directory'); ?>
            </form>
        </div>
        <hr style="margin-top: 30px;">
        <p>After you hit the "Scan and Extract Classes" go to the Winden settings page and compile classes as usually.</p>
<?php
    }

    /**
     * Scan directory for classes.
     *
     * @param string $dir Directory path.
     * @return array Unique array of classes.
     */
    public function scan_directory_for_classes($dir)
    {
        $classes = array();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $content = file_get_contents($file->getRealPath());
                preg_match_all('/class=["\']([^"\']+)["\']/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $split_classes = explode(' ', $match);
                        $classes = array_merge($classes, $split_classes);
                    }
                }
            }
        }

        return array_unique($classes);
    }

    /**
     * Add classes to payload.
     *
     * @param string $content Content.
     * @return string Content with added classes.
     */
    public function compile_content_payload($content)
    {
        $classes_file_path = plugin_dir_path(__FILE__) . 'extracted_classes.txt';

        if (file_exists($classes_file_path)) {
            $classes = file_get_contents($classes_file_path);
            $classes = str_replace("\n", ' ', $classes);
            $classes = preg_replace('/\s+/', ' ', $classes);
            $classes = trim($classes);

            if (!empty($classes)) {
                $content .= "<div class=\"$classes\"></div>";
            }
        }

        return $content;
    }

    /**
     * Add settings link on plugins page.
     *
     * @param array $links Array of default plugin action links.
     * @return array Array of plugin action links.
     */
    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('tools.php?page=class-extractor') . '">' . __('Settings', 'dplugins-winden-classes-scanner') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


}

$winden_classes_scanner = new Winden_Classes_Scanner();
add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($winden_classes_scanner, 'add_settings_link'));
