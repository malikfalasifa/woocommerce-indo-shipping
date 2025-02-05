<?php
require_once WCIS_DIR . '/helper/rajaongkir.php';

/**
 * Global setting for Indo Shipping
 */
class WCIS_Method extends WC_Shipping_Method {
  private $api;

  public function __construct($instance_id = 0) {
		$this->id = 'wcis';
    $this->title = __('Indo Shipping');
		$this->method_title = __('Indo Shipping');
		$this->method_description = __('Indonesian domestic shipping with JNE, TIKI, or POS');

    $this->enabled = $this->get_option('enabled');
    $this->init_form_fields();

    // allow save setting
    add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_transients']);
	}

  /**
   * Initiate global setting page for WCIS
   */
  function init_form_fields() {
    $enabled_field = array(
      'title' => __('Enable/Disable'),
      'type' => 'checkbox',
      'label' => __('Enable Indo Shipping'),
      'description' => __( 'Tick this then go to Shipping Zone > Create Indonesia Zone > Add Shipping Method > Choose "Indo Shipping"' ),
      'default' => 'yes'
    );

    $key_field = array(
      'title' => __('API Key'),
      'type' => 'password',
      'description' => __('Signup at <a href="http://rajaongkir.com/akun/daftar" target="_blank">rajaongkir.com</a> and choose Pro license (Paid). Paste the API Key here'),
    );

    $city_field = array(
      'title' => __('City Origin'),
      'type' => 'select',
      // 'class'    => 'wc-enhanced-select', // bugged!! doesn't save the value
      'description' => __('Ship from where? <br> Change your province at General > Store Address'),
      'options' => array()
    );

    $this->form_fields = array(
      'key' => $key_field
    );

    // if key is valid, show the other setting fields
    if( $this->check_key_valid() ) {
      $city_field['options'] = $this->get_cities_origin();

      $this->form_fields['enabled'] = $enabled_field;
      $this->form_fields['city'] = $city_field;

      // set service fields by each courier
      $couriers = wcis_get_couriers();
      foreach( $couriers as $id => $name ) {
        $this->form_fields[$id . '_services'] = array(
          'title' => $name,
          'type' => 'multiselect',
          'class' => 'wc-enhanced-select',
          'description' => __("Choose allowed services by $name."),
          'options' => wcis_get_services($id, true)
        );
      }

    } // if valid
  }


  /**
   * Add API Key to Transient so it's cached
   */
  function process_admin_transients() {
    $t_license = get_transient('wcis_license');

    $post_data = $this->get_post_data();
    $key = $post_data['woocommerce_wcis_key'];

    // check license
    $license_valid = isset($t_license['valid']) && $t_license['valid'];
    $license_different = isset($t_license['key']) && $t_license['key'] === $key;

    // if not valid OR different from before, update transient
    if(!$license_valid || $license_different) {
      $rj = new RajaOngkir($key);
      $t_license = [
        'key' => $key,
        'valid' => $rj->is_valid()
      ];

      set_transient('wcis_license', $t_license, 60*60*24*30);
    }

    return $t_license;
  }


  /////


  /**
   * Validate API Key by doing a sample AJAX call
   * @return bool
   */
  private function check_key_valid() {
    $license = get_transient('wcis_license');

    // if key doesn't exist, abort
    if(!isset($license['key'])) { return false; }

    // if valid, return success
    if(isset($license['valid']) && $license['valid']) {
      $msg = __('API Connected!');
      $this->form_fields['key']['description'] = '<span style="color: #4caf50;">' . $msg . '</span>';
    }
    else {
      $msg = __('Invalid API Key. Are you using non-Pro license?');
      $this->form_fields['key']['description'] = '<span style="color:#f44336;">' . $msg . '</span>';
    }

    return $license['valid'];
  }

  /**
   * Get cities list from cache. Cached when the setting is saved.
   * @return array - List of cities in base province
   */
  private function get_cities_origin() {
    $country = wc_get_base_location();
    $prov_id = wcis_get_province_id( $country['state'] );

    // get cities data
    $cities_raw = wcis_get_cities( $prov_id );

    // parse raw data
    $cities = array();
    foreach( $cities_raw as $id => $value ) {
      $cities[$id] = $value['city_name'];
    }

    return $cities;
  }


  /**
   * Set API key to cache
   * 
   * @return array - in the format of `{ key, valid }`
   */
  private function _set_license_cache() {
    
  }


}
