<?php
/*
Plugin Name: Custom Button Admin
Description: Adds a custom button to the WordPress admin dashboard to preview and execute custom code.
Version: 1.0
Author:
*/

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Custom_Button_Admin
{

  public function __construct()
  {
    // Hook into the admin menu
    add_action('admin_menu', [$this, 'cba_add_admin_menu']);
  }

  public function cba_add_admin_menu()
  {
    add_menu_page(
      'Custom Button',          // Page title
      'Custom Button',          // Menu title
      'manage_options',         // Capability
      'custom-button',          // Menu slug
      [$this, 'cba_render_admin_page'] // Function to display the page content
    );
  }

  public function cba_render_admin_page()
  {
?>
    <div class="wrap">
      <h1>Custom Button</h1>
      <form method="post" action="">
        <input type="hidden" name="cba_custom_action" value="preview_code">
        <?php submit_button('Preview Posts to Delete'); ?>
      </form>
      <br>
      <form method="post" action="">
        <input type="hidden" name="cba_custom_action" value="delete_all_posts_w_image_v1">
        <?php submit_button('delete_all_posts_w_image_v1'); ?>
      </form>
      <form method="post" action="">
        <input type="hidden" name="delete_attachment" value="delete_all_media">
        <?php submit_button('delete_all_attachments'); ?>
      </form>
    </div>
<?php

    // Handle the actions based on the button clicked
    if (isset($_POST['cba_custom_action'])) {
      if ($_POST['cba_custom_action'] === 'preview_code') {
        $this->cba_preview_posts();
      } elseif ($_POST['cba_custom_action'] === 'delete_all_posts_w_image_v1') {
        $this->delete_all_posts_w_image_v1();
      } elseif ($_POST['cba_custom_action'] === 'delete_all_media') {
        $this->cba_delete_attachments_by_post_type();
      }
    }
  }

  public function cba_preview_posts()
  {

    global $wpdb;
    $target_posttype = 'product';
    // Fetch the posts that will be deleted
    $posts = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT ID, post_title 
                 FROM wp_posts 
                 WHERE post_type =%s;
        ",
        $target_posttype
      )
    );

    if (! empty($posts)) {
      echo '<h2>Posts that will be deleted:</h2>';
      echo '<ul>';
      foreach ($posts as $post) {
        echo '<li><strong>ID:</strong> ' . esc_html($post->ID) . ' - <strong>Title:</strong> ' . esc_html($post->post_title) . '</li>';
      }
      echo '</ul>';
    } else {
      echo '<div class="notice notice-info"><p>No posts found with the post type "' . esc_html($target_posttype) . '".</p></div>';
    }
  }

  public function delete_all_posts_w_image_v1()
  {
    global $wpdb;

    // delete all attachment first 
    // check all the products if there is attachments -> then delete all
    $target_posttype = 'product';
    // Fetch the posts that will be deleted
    $products = $wpdb->get_results(
      $wpdb->prepare(
        "
          SELECT ID
          FROM wp_posts 
          WHERE post_type =%s;
        ",
        $target_posttype
      )
    );

    $count_deleted = 0;
    if (! empty($products)) {
      $count_deleted = 0;
      foreach ($products as $post_id) {
        $attachments = get_attached_media('', $post_id);
        foreach ($attachments as $attachment) {
          // Delete the attachment metadata and file
          wp_delete_attachment($attachment->ID, true);
          ++$count_deleted;
        }
      }
    }

    $result_posts = $wpdb->query(
      "
            DELETE a,b,c
            FROM {$wpdb->posts} a
            LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id)
            LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id)
            WHERE a.post_type = 'product';
      "
    );
    // $wpdb->show_errors();
    // $wpdb->print_error();

    $result_wp = $wpdb->query(
      "
            DELETE a,b
            FROM wp_posts a
            LEFT JOIN wp_wc_product_meta_lookup b ON (a.ID = b.product_id)
            WHERE a.post_type = 'product';
      "
    );

    $wpdb->show_errors();
    $wpdb->print_error();

    // Check if the query was successful
    if ($result_posts !== false) {
      echo '<div class="notice notice-success"><p>Custom code executed successfully! Rows affected: ' . ($result_posts) . '</p></div>';
      echo var_dump($wpdb->last_error);
    } else {
      echo '<div class="notice notice-error"><p>An error occurred while executing the custom code.</p></div>';
    }
  }



  public function cba_delete_attachments_by_post_type()
  {

    global $wpdb;

    // SQL Query to delete orphaned attachments and their metadata
    $result = $wpdb->query(
      "
        DELETE wp, pm
        FROM  wp_posts wp
        LEFT JOIN wp_postmeta pm ON wp.ID = pm.post_id
        WHERE wp.post_type = 'attachment';
        "
    );
    $result = $wpdb->query(
      "
            DELETE pm
            FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key = '_wp_attached_file'
            OR pm.meta_key = '_wp_attachment_metadata';
            "
    );

    // Check if the query was successful
    if ($result !== false) {
      echo '<div class="notice notice-success"><p>Custom code executed successfully! Rows affected: ' . ($result + $result_posts) . '</p></div>';
    } else {
      echo '<div class="notice notice-error"><p>An error occurred while executing the custom code.</p></div>';
    }
  }
}

// Initialize the plugin
new Custom_Button_Admin();
