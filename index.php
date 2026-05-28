<?php
/**
 * Plugin Name: Zanphar's Secure Download Gateway (GPLv2)
 * Plugin URI:  https://www.chware.org
 * Description: Forces a license agreement and countdown timer before granting access to multiple files via a shortcode.
 * Version:     1.0.0
 * Author:      Charles McDonald
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Create the Administration Settings Menu
add_action( 'admin_menu', 'dwg_add_admin_menu' );
add_action( 'admin_init', 'dwg_settings_init' );

function dwg_add_admin_menu() {
    add_options_page(
        'Download Gateway Settings',
        'Download Gateway',
        'manage_options',
        'download_gateway',
        'dwg_options_page'
    );
}

function dwg_settings_init() {
    register_setting( 'dwg_plugin_page', 'dwg_settings' );

    add_settings_section(
        'dwg_plugin_page_section',
        __( 'Configure Gateway Settings', 'text_domain' ),
        'dwg_settings_section_callback',
        'dwg_plugin_page'
    );

    add_settings_field(
        'dwg_copyright_text',
        __( 'Copyright Footer Text', 'text_domain' ),
        'dwg_copyright_render',
        'dwg_plugin_page',
        'dwg_plugin_page_section'
    );

    add_settings_field(
        'dwg_files_list',
        __( 'Manage Downloadable Files', 'text_domain' ),
        'dwg_files_render',
        'dwg_plugin_page',
        'dwg_plugin_page_section'
    );
}

function dwg_copyright_render() {
    $options = get_option( 'dwg_settings' );
    $default_copyright = 'Copyright &copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.';
    $value = isset( $options['dwg_copyright_text'] ) ? $options['dwg_copyright_text'] : $default_copyright;
    echo "<input type='text' name='dwg_settings[dwg_copyright_text]' class='large-text' value='" . esc_attr( $value ) . "'>";
}

function dwg_files_render() {
    $options = get_option( 'dwg_settings' );
    $files = isset( $options['dwg_files_list'] ) ? $options['dwg_files_list'] : array();
    
    echo '<div id="dwg-files-container">';
    if ( ! empty( $files ) && is_array( $files ) ) {
        foreach ( $files as $index => $file ) {
            dwg_render_file_row( $index, $file );
        }
    } else {
        dwg_render_file_row( 0, array( 'id' => '', 'name' => '', 'url' => '' ) );
    }
    echo '</div>';
    echo '<p><button type="button" class="button" id="dwg-add-file">Add Another File</button></p>';
    echo '<p class="description">Assign a unique <strong>File ID</strong> (e.g., <code>app-v1</code>) to call specific files in the shortcode.</p>';

    // Simple script to handle dynamic row additions in admin panel
    ?>
    <script>
        document.getElementById('dwg-add-file').addEventListener('click', function() {
            var container = document.getElementById('dwg-files-container');
            var rowCount = container.getElementsByClassName('dwg-file-row').length;
            var div = document.createElement('div');
            div.className = 'dwg-file-row';
            div.style.marginBottom = '10px';
            div.innerHTML = `
                <input type="text" name="dwg_settings[dwg_files_list][${rowCount}][id]" placeholder="File ID (e.g., plugin-zip)" style="width:15%">
                <input type="text" name="dwg_settings[dwg_files_list][${rowCount}][name]" placeholder="Display Name (e.g., MyPlugin.zip)" style="width:25%">
                <input type="text" name="dwg_settings[dwg_files_list][${rowCount}][url]" placeholder="File URL (https://...)" style="width:45%">
                <button type="button" class="button dwg-remove-row" style="color:red;">Remove</button>
            `;
            container.appendChild(div);
        });
        document.addEventListener('click', function(e) {
            if(e.target && e.target.classList.contains('dwg-remove-row')) {
                e.target.closest('.dwg-file-row').remove();
            }
        });
    </script>
    <?php
}

function dwg_render_file_row( $index, $file ) {
    ?>
    <div class="dwg-file-row" style="margin-bottom: 10px;">
        <input type="text" name="dwg_settings[dwg_files_list][<?php echo $index; ?>][id]" value="<?php echo esc_attr($file['id']); ?>" placeholder="File ID" style="width:15%">
        <input type="text" name="dwg_settings[dwg_files_list][<?php echo $index; ?>][name]" value="<?php echo esc_attr($file['name']); ?>" placeholder="Display Name" style="width:25%">
        <input type="text" name="dwg_settings[dwg_files_list][<?php echo $index; ?>][url]" value="<?php echo esc_attr($file['url']); ?>" placeholder="File URL" style="width:45%">
        <button type="button" class="button dwg-remove-row" style="color:red;">Remove</button>
    </div>
    <?php
}

function dwg_settings_section_callback() {
    echo __( 'Set up your system copyrights and paste URLs for files you intend to provide via shortcodes.', 'text_domain' );
}

function dwg_options_page() {
    ?>
    <div class="wrap">
        <form action='options.php' method='post'>
            <h2>Secure Download Gateway Settings</h2>
            <?php
            settings_fields( 'dwg_plugin_page' );
            do_settings_sections( 'dwg_plugin_page' );
            submit_button();
            ?>
        </form>
        <hr>
        <h3>How to use:</h3>
        <p>Use the shortcode <code>[download_gateway file_id="YOUR_FILE_ID" timer="5"]</code> inside any page or post.</p>
    </div>
    <?php
}


// 2. Shortcode Engine to Display Frontend Gateway Layout
add_shortcode( 'download_gateway', 'dwg_render_gateway_shortcode' );

function dwg_render_gateway_shortcode( $atts ) {
    $attributes = shortcode_atts( array(
        'file_id' => '',
        'timer'   => '5'
    ), $atts );

    $options = get_option( 'dwg_settings' );
    $files = isset( $options['dwg_files_list'] ) ? $options['dwg_files_list'] : array();
    
    $target_file = null;
    foreach ( $files as $file ) {
        if ( $file['id'] === $attributes['file_id'] ) {
            $target_file = $file;
            break;
        }
    }

    if ( ! $target_file ) {
        return '<p style="color:red;">Gateway Error: Specified File ID not found.</p>';
    }

    $default_copyright = '&copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.';
    $copyright_text = isset( $options['dwg_copyright_text'] ) ? $options['dwg_copyright_text'] : $default_copyright;

    // Output buffering to elegantly package layout code
    ob_start();
    ?>
    <div class="dwg-wrapper">
        <div class="dwg-container">
            <h3>Download: <?php echo esc_html( $target_file['name'] ); ?></h3>
            <p>Please review and accept the GNU GPLv2 License Agreement to proceed with this software download.</p>
            
            <div class="dwg-license-box">
                <strong>GNU GENERAL PUBLIC LICENSE</strong><br>
                Version 2, June 1991<br><br>
                Copyright (C) 1989, 1991 Free Software Foundation, Inc.<br>
                51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA<br><br>
                Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed.<br><br>
                <strong>Preamble</strong><br>
                The licenses for most software are designed to take away your freedom to share and change it. By contrast, the GNU General Public License is intended to guarantee your freedom to share and change free software--to make sure the software is free for all its users...
            </div>

            <div class="dwg-checkbox-container">
                <input type="checkbox" id="dwg-agree-<?php echo esc_attr($attributes['file_id']); ?>" onchange="dwgToggleBtn('<?php echo esc_js($attributes['file_id']); ?>')">
                <label for="dwg-agree-<?php echo esc_attr($attributes['file_id']); ?>">I have read and accept the GPLv2 license terms.</label>
            </div>

            <button id="dwg-btn-<?php echo esc_attr($attributes['file_id']); ?>" class="dwg-btn" disabled onclick="dwgStartSequence('<?php echo esc_js($attributes['file_id']); ?>', '<?php echo esc_url($target_file['url']); ?>', '<?php echo esc_js($target_file['name']); ?>', <?php echo intval($attributes['timer']); ?>)">
                Download File
            </button>

            <div id="dwg-timer-<?php echo esc_attr($attributes['file_id']); ?>" class="dwg-timer-msg">
                Your file download begins in <span id="dwg-count-<?php echo esc_attr($attributes['file_id']); ?>"><?php echo intval($attributes['timer']); ?></span> seconds...
            </div>
        </div>

        <footer class="dwg-footer">
            <?php echo wp_kses_post( $copyright_text ); ?>
        </footer>
    </div>

    <style>
        .dwg-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #f4f6f9;
            padding: 40px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .dwg-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            max-width: 550px;
            width: 100%;
        }
        .dwg-license-box {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            padding: 15px;
            height: 160px;
            overflow-y: scroll;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #475569;
        }
        .dwg-checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .dwg-checkbox-container input {
            margin-right: 10px !important;
            transform: scale(1.1);
            cursor: pointer;
        }
        .dwg-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #2271b1;
            color: white !important;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }
        .dwg-btn:hover { background-color: #135e96; }
        .dwg-btn:disabled {
            background-color: #cbd5e1;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }
        .dwg-timer-msg {
            margin-top: 15px;
            text-align: center;
            font-weight: bold;
            color: #d97706;
            display: none;
            font-size: 14px;
        }
        .dwg-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
    </style>

    <script>
        function dwgToggleBtn(id) {
            var cb = document.getElementById('dwg-agree-' + id);
            var btn = document.getElementById('dwg-btn-' + id);
            btn.disabled = !cb.checked;
        }

        function dwgStartSequence(id, fileUrl, fileName, duration) {
            var cb = document.getElementById('dwg-agree-' + id);
            var btn = document.getElementById('dwg-btn-' + id);
            var msg = document.getElementById('dwg-timer-' + id);
            var countSpan = document.getElementById('dwg-count-' + id);

            btn.disabled = true;
            cb.disabled = true;
            msg.style.display = 'block';
            btn.textContent = 'Processing Download Request...';

            var timeLeft = duration;
            var interval = setInterval(function() {
                timeLeft--;
                countSpan.textContent = timeLeft;

                if (timeLeft <= 0) {
                    clearInterval(interval);
                    msg.innerHTML = '✨ Your file download has triggered successfully.';
                    btn.textContent = 'Download Initialization Completed';
                    
                    var link = document.createElement('a');
                    link.href = fileUrl;
                    link.setAttribute('download', fileName);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }, 1000);
        }
    </script>
    <?php
    return ob_get_clean();
}

