<?php

if(!class_exists('WP_List_Table')){
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

class Lingotek_Custom_Fields_Table extends WP_List_Table {
  protected $profiles, $content_types;

  /*
   * constructor
   *
   * @since 0.2
   */
  function __construct() {
    parent::__construct(array(
      'plural'   => 'lingotek-custom-fields', // do not translate (used for css class)
      'ajax'   => false
    ));
  }

  /*
   * displays the item's meta_key
   *
   * @since 0.2
   *
   * @param array $item
   * @return string
   */
  function column_meta_key($item) {
    return isset($item['meta_key']) ? esc_html($item['meta_key']) : '';
  }

  /*
   * displays the item setting
   *
   * @since 0.2
   *
   * @param array $item
   * @param string $column_name
   * @return string
   */
  function column_setting($item) {
    $settings = array('translate', 'copy', 'ignore');
    $custom_field_choices = get_option('lingotek_custom_fields', array());

    printf('<select class="custom-field-setting" name="%1$s" id="%1$s">', 'settings' . '[' . $item['meta_key'] . ']');
    
    // select the option from the lingotek_custom_fields option
    foreach ($settings as $setting) {
      if ($setting === $custom_field_choices[$item['meta_key']]) {
        $selected = 'selected="selected"';
      }
      else {
        $selected = '';
      }
      echo "\n\t<option value='" . esc_attr($setting) . "' $selected>" . esc_attr(ucwords($setting)) . '</option>';
    }
  echo '</select>';
  }

  /*
   * gets the list of columns
   *
   * @since 0.2
   *
   * @return array the list of column titles
   */
  function get_columns() {
    return array(
      'meta_key' => __('Custom Field Key', 'wp-lingotek'),
      'setting' => __('Action', 'wp-lingotek'),
    );
  }

  /*
   * gets the list of sortable columns
   *
   * @since 0.2
   *
   * @return array
   */
  function get_sortable_columns() {
    return array(
      'meta_key' => array('meta_key', false),
    );
  }

  /*
   * prepares the list of items for displaying
   *
   * @since 0.2
   *
   * @param array $data
   */
  function prepare_items($data = array()) {
    $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

    function usort_reorder($a, $b){
      $result = strcmp($a[$_REQUEST['orderby']], $b[$_REQUEST['orderby']]); // determine sort order
      return (empty($_REQUEST['order']) || $_REQUEST['order'] == 'asc') ? $result : -$result; // send final sort direction to usort
    };

    if (!empty($_REQUEST['orderby'])) // no sort by default
      usort($data, 'usort_reorder');

    $total_items = count($data);
    $this->items = $data;

    $this->set_pagination_args(array(
      'total_items' => $total_items,
      'per_page'  => count($data),
    ));
  }
} 