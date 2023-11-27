<?php
/*
Plugin Name: Maintenance Mode
Plugin URI: https://mateuszturek.pl/
Description: Wtyczka umożliwiająca włączenie trybu konserwacji, blokując dostęp do witryny dla niezalogowanych użytkowników.
Version: 1.4
Author: Selectors
Author URI: https://mateuszturek.pl/
License: GPLv2 or later
Text Domain: maintenance-mode
*/

// Sprawdź, czy użytkownik jest zalogowany i ma uprawnienia do zarządzania opcjami.
function maintenance_mode_user_has_access() {
    return current_user_can('manage_options');
}

// Włącz tryb konserwacji, jeśli użytkownik nie jest zalogowany i nie ma uprawnień do zarządzania opcjami.
function maintenance_mode() {
    if (!maintenance_mode_user_has_access() && get_option('maintenance_mode_enabled')) {
        $default_message = 'Przepraszamy, nasza strona jest obecnie w trakcie konserwacji. Proszę spróbować ponownie później.';
        $content = get_option('maintenance_mode_content', $default_message);
        if (empty(trim($content))) {
            $content = $default_message;
        }
        $background_image_url = get_option('maintenance_mode_background_image', '');

        // Dodajemy tę linię, aby pobrać tytuł strony z opcji
        $title = get_option('maintenance_mode_title', 'Tryb konserwacji');

        $style = !empty($background_image_url) ? ' style="background-image: url(' . esc_url($background_image_url) . '); background-size: cover; background-position: center center; background-repeat: no-repeat; width: 100%; height: 100vh; display: flex; justify-content: center; align-items: center; position: absolute; top: 0; left: 0;"' : '';
        $message = '<div' . $style . '><div style="padding: 20px; background: rgba(255, 255, 255, 0.8);">' . $content . '</div></div>';

        // Zmieniamy funkcję wp_die, aby dodać niestandardowy tytuł strony w elemencie <title> w sekcji <head>
        wp_die(
            $message,
            $title,
            [
                'response' => 503,
                'title' => $title,
            ]
        );
    }
}
add_action('template_redirect', 'maintenance_mode');


// Dodaj opcję włączania / wyłączania trybu konserwacji, edytor WYSIWYG i opcję przesyłania obrazka tła w panelu administracyjnym WordPress.
function maintenance_mode_settings() {
    register_setting('general', 'maintenance_mode_enabled', 'bool');
    register_setting('general', 'maintenance_mode_content', 'string');
    register_setting('general', 'maintenance_mode_background_image', 'string');
    register_setting('general', 'maintenance_mode_title', 'string'); // Dodaj tę linię

    add_settings_section('maintenance_mode_section', 'Ustawienia trybu konserwacji', '__return_false', 'general');
    add_settings_field('maintenance_mode_enabled', 'Włącz tryb konserwacji', 'maintenance_mode_enabled_callback', 'general', 'maintenance_mode_section');
    add_settings_field('maintenance_mode_title', 'Tytuł strony', 'maintenance_mode_title_callback', 'general', 'maintenance_mode_section'); // Dodaj tę linię
    add_settings_field('maintenance_mode_content', 'Treść strony konserwacji', 'maintenance_mode_content_callback', 'general', 'maintenance_mode_section');
    add_settings_field('maintenance_mode_background_image', 'Obraz tła strony konseracji', 'maintenance_mode_background_image_callback', 'general', 'maintenance_mode_section');
}
add_action('admin_init', 'maintenance_mode_settings');

function maintenance_mode_enabled_callback() {
    $enabled = get_option('maintenance_mode_enabled');
    echo '<input type="checkbox" name="maintenance_mode_enabled" value="1" ' . checked(1, $enabled, false) . '/>';
}

function maintenance_mode_title_callback() {
    $default_title = 'Tryb konserwacji';
    $title = get_option('maintenance_mode_title', $default_title);
    echo '<input type="text" name="maintenance_mode_title" value="' . esc_attr($title) . '" style="width: 100%;" />';
}

function maintenance_mode_content_callback() {
    $default_message = 'Przepraszamy, nasza strona jest obecnie w trakcie konserwacji. Proszę spróbować ponownie później.';
    $content = get_option('maintenance_mode_content', $default_message);
    wp_editor($content, 'maintenance_mode_content', [
        'textarea_name' => 'maintenance_mode_content',
        'media_buttons' => true,
        'textarea_rows' => 10,
        'tinymce' => true
    ]);
}

function maintenance_mode_background_image_callback() {
    $background_image_url = get_option('maintenance_mode_background_image', '');
    echo '<input type="text" name="maintenance_mode_background_image" id="maintenance_mode_background_image" value="' . esc_attr($background_image_url) . '" style="width: 100%;" />';
    echo '<input type="button" id="maintenance_mode_background_image_button" class="button" value="Wybierz obraz" />';
    echo '<script>
        jQuery(document).ready(function($) {
            var _custom_media = true;
            var _orig_send_attachment = wp.media.editor.send.attachment;
            $("#maintenance_mode_background_image_button").click(function(e) {
                var send_attachment_bkp = wp.media.editor.send.attachment;
                _custom_media = true;
                wp.media.editor.send.attachment = function(props, attachment){
                    if (_custom_media) {
                        $("#maintenance_mode_background_image").val(attachment.url);
                    } else {
                        return _orig_send_attachment.apply(this, [props, attachment]);
                    };
                }
                wp.media.editor.open(this);
                return false;
            });
        });
    </script>';
}

// Wyświetl powiadomienie o aktywnym trybie konserwacji w kokpicie WordPress
function maintenance_mode_admin_notice() {
    if (get_option('maintenance_mode_enabled')) {
        $settings_url = admin_url('options-general.php');
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Tryb konserwacji jest aktywny.</strong> Pamiętaj, aby go <a href="' . $settings_url . '">wyłączyć</a>, kiedy skończysz.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'maintenance_mode_admin_notice');

// Dodaj łącze "Ustawienia" na stronie wtyczek
function maintenance_mode_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php') . '">' . __('Ustawienia', 'maintenance-mode') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'maintenance_mode_plugin_action_links');
