<?php
/**
 * Plugin Name: WW Resources
 * Description: Resource library and tech news for resources.williamwu.info
 * Version: 0.1.0
 * Author: William Wu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ww_register_resource_meta() {
    register_post_meta( 'resource', '_ww_url', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
    ] );

    register_post_meta( 'resource', '_ww_difficulty', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
    ] );
}
add_action( 'init', 'ww_register_resource_meta' );

function ww_register_resource_cpt() {
    $labels = [
        'name'          => 'Resources',
        'singular_name' => 'Resource',
        'menu_name'     => 'Resources',
        'add_new_item'  => 'Add New Resource',
        'edit_item'     => 'Edit Resource',
        'all_items'     => 'All Resources',
    ];

    $args = [
        'labels'       => $labels,
        'public'       => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-archive',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
        'rewrite'      => [ 'slug' => 'library' ],
    ];

    register_post_type( 'resource', $args );
}
add_action('init', 'ww_register_resource_cpt');

function ww_register_resource_taxonomy() {
    $labels = [
            'name'          => 'Resource Types',
            'singular_name' => 'Resource Type',
            'menu_name'     => 'Types',
            'add_new_item'  => 'Add New Type',
            'edit_item'     => 'Edit Type',
            'all_items'     => 'All Types',
        ];

    $args = [
        'labels'        => $labels,
        'hierarchical'  => true,
        'show_in_rest'  => true,
        'rewrite'       => ['slug' => 'type']
    ];

    register_taxonomy( 'resource_type', 'resource', $args );
}
add_action( 'init', 'ww_register_resource_taxonomy' );

function ww_add_resource_meta_box(){
    add_meta_box(
        'ww_resource_details',       // id
        'Resource Details',          // title
        'ww_render_resource_meta_box', // render callback
        'resource',                  // screen (post type)
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'ww_add_resource_meta_box');

function ww_render_resource_meta_box( $post ){
        wp_nonce_field( 'ww_save_resource_meta',
        'ww_resource_meta_nonce' );

        $url        = get_post_meta( $post->ID, '_ww_url', true );
        $difficulty = get_post_meta( $post->ID, '_ww_difficulty', true
        );
        ?>
            <p>
                <label for="ww_url">Resource URL</label><br>
                <input type="url" id="ww_url" name="ww_url" class="widefat"
                        value="<?php echo esc_attr( $url ); ?>">
            </p>
            <p>
                <label for="ww_difficulty">Difficulty</label><br>
                <select id="ww_difficulty" name="ww_difficulty">
                    <?php foreach ( [ 'beginner', 'intermediate','advanced' ] as $level ) : ?>
                        <option value="<?php echo esc_attr( $level ); ?>"
                            <?php selected( $difficulty, $level ); ?>>
                            <?php echo esc_html( ucfirst( $level ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        <?php
}

function ww_save_resource_meta( $post_id ) {
        // Autosave bail
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Wrong post type bail
        if ( get_post_type( $post_id ) !== 'resource' ) {
            return;
        }
        // Nonce check
        if ( ! isset( $_POST['ww_resource_meta_nonce'] ) ||
            ! wp_verify_nonce( $_POST['ww_resource_meta_nonce'],
            'ww_save_resource_meta' ) ) {
            return;
        }
        // Capability check
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['ww_url'] ) ) {
            update_post_meta( $post_id, '_ww_url', sanitize_url(
            wp_unslash( $_POST['ww_url'] ) ) );
        }
        if ( isset( $_POST['ww_difficulty'] ) ) {
            update_post_meta( $post_id, '_ww_difficulty',
            sanitize_text_field( wp_unslash( $_POST['ww_difficulty'] ) ) );
        }
    }
add_action( 'save_post', 'ww_save_resource_meta' );

//add the menu item
function ww_add_setting_page() {
    add_options_page(
        'WW Resources Setting', //title of page
        'WW Resources', // menu label under Settings
        'manage_options', // capability required to see it
        'ww-resources',  // page slug (used in the URL and below)
        'ww_render_setting_page' // function that draws the page
        );
}
add_action('admin_menu', 'ww_add_setting_page');

//the page-render function
function ww_render_setting_page() {
    if( !current_user_can('manage_options')){
        return;
    }
        ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'ww_settings_group' );
            do_settings_sections( 'ww-resources' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

//registering and setting users 
function ww_settings_defaults(){
    return [
        'news_count' => 5,
        'news_source' => 'devto'
    ];
}

function ww_register_settings() {
    register_setting('ww_settings_group', 'ww_settings',[
        'type'              =>'array',
        'sanitize_callback' =>'ww_sanitize_settings',
        'default'           => ww_settings_defaults(),
    ]);
    // Section: groups fields under a "Tech News" heading on the ww-resources page                   
    add_settings_section( 'ww_news_section', 'Tech News', null, 'ww-resources');
    // Field: one input row inside ww_news_section; its HTML comes from thecallback    
    add_settings_field('ww_news_count', 'Number of news items','ww_render_news_count_field', 'ww-resources', 'ww_news_section');
    //
    add_settings_field( 'ww_news_source', 'News source','ww_render_news_source_field', 'ww-resources', 'ww_news_section' );
}
add_action( 'admin_init', 'ww_register_settings' );

// Saved settings merged over defaults (reused by the Tech News block later)
function ww_get_settings() {
    return wp_parse_args( get_option( 'ww_settings', [] ),
ww_settings_defaults() );
}

function ww_render_news_count_field() {
    $settings = ww_get_settings();
    ?>
    <input type="number" name="ww_settings[news_count]" min="1" max="20"
            value="<?php echo esc_attr( $settings['news_count'] ); ?>">
    <?php
}

function ww_render_news_source_field() {
    $settings = ww_get_settings();
    $sources  = [ 'devto' => 'dev.to', 'hackernews' => 'Hacker News' ];
    ?>
    <select name="ww_settings[news_source]">
        <?php foreach ( $sources as $value => $label ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>"
                <?php selected( $settings['news_source'], $value ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}