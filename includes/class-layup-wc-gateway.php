<?php

class WC_Layup_Gateway extends WC_Payment_Gateway
{
    public $version;
    public $available_countries;
    public $available_currencies;
    public $lu_max_end_date;
    public $lu_min_end_date;
    public $testmode;
    public $payplan_disp;
    public $payplan_disp_cart;
    public $layup_dep;
    public $layup_dep_type;
    public $dynamic_deposit;
    public $popup_warning;
    public $learn_more_style;
    public $product_type;
    public $api_key_error;
    public $api_url;
    public $payment_plan_template;
    public $api_key;
    public $complete_payment_on_deposit;
    
    /**
     * Class constructor
     */

    public function __construct()
    {
        $this->errors = [];

        $this->version = WC_GATEWAY_LAYUP_VERSION;

        $this->id = 'layup'; // payment gateway plugin ID
        

        $this->icon = plugin_dir_url(dirname(__FILE__)) . 'img/logo-color.168d4abe.png'; // URL of the icon that will be displayed on checkout page near your gateway name
        

        $this->has_fields = false; // in case of custom credit card form
        

        $this->method_title = __('LayUp', 'layup-gateway');

        $this->method_description = 'Activate your payment plan with a small deposit and break down the total cost into more affordable monthly payments.'; // will be displayed on the options page
        

        $this->available_countries = array(
            'ZA'
        );

        $this->available_currencies = (array)apply_filters('layup_gateway_available_currencies', array(
            'ZAR'
        ));

        // gateways can support products, subscriptions, refunds, saved payment methods,
        

        $this->supports = array(

            'products'

        );

        // Method with all the options fields
        

        $this->init_form_fields();

        // Load the settings.
        

        $this->init_settings();

        $this->title = $this->get_option('title');

        $this->description = $this->get_option('description');

        $this->enabled = $this->get_option('enabled');

        $this->lu_max_end_date = $this->get_option('lu_max_end_date');

        $this->lu_min_end_date = $this->get_option('lu_min_end_date');

        $this->testmode = 'yes' === $this->get_option('lu_testmode');

        $this->payplan_disp = 'yes' === $this->get_option('payplan_disp');
        $this->payplan_disp_cart = 'yes' === $this->get_option('payplan_disp_cart');

        $this->layup_dep = (int)$this->get_option('layup_dep');
        $this->layup_dep_type = $this->get_option('layup_dep_type');
        $this->dynamic_deposit = 'yes' === $this->get_option('dynamic_deposit');
        $this->popup_warning = 'yes' === $this->get_option('popup_warning');
        $this->learn_more_style = $this->get_option('learn_more_style');
        $this->product_type = $this->get_option('product_type');

        $this->api_key_error = $this->get_option('api_key_error');
        $this->payment_plan_template = $this->get_option('payment_plan_template');

        if ($this->get_option('lu_api_key') != '')
        {
            $this->api_key = $this->get_option('lu_api_key');
        }
        else
        {
            $this->api_key = "myApiKey";
        }

        $this->api_url = $this->testmode ? "https://sandbox-api.layup.co.za/v1/orders" : "https://api.layup.co.za/v1/orders";

        $this->complete_payment_on_deposit = 'yes' === $this->get_option('complete_payment_on_deposit');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ));

        add_action('woocommerce_api_wc_layup_gateway', array(
            $this,
            'layup_callback'
        ));

        add_action('admin_notices', array(
            $this,
            'admin_notices'
        ));

    }

    /**
     * Plugin admin options
     */

    public function init_form_fields()
    {

        $this->form_fields = array(

            'enabled' => array(

                'title' => 'Enable/Disable',

                'label' => 'Enable LayUp Gateway',

                'type' => 'checkbox',

                'description' => '',

                'default' => 'no'

            ) ,

            'lu_api_key' => array(

                'title' => 'Live API Key',

                'type' => 'password',

                'description' => 'The API Key for your Merchant Account provided by LayUp.'

            ) ,

            'title' => array(

                'title' => 'Title',

                'type' => 'text',

                'description' => 'This controls the title which the user sees during checkout.',

                'default' => 'LayUp'

            ) ,

            'description' => array(

                'title' => 'Description',

                'type' => 'text',

                'description' => 'This controls the description which the user sees during checkout.',

                'default' => 'Interest Free Lay-By | Safe & Easy Instalments | No Credit Checks | Instant Sign Up',

            ) ,

            'lu_testmode' => array(

                'title' => 'Test mode',

                'label' => 'Enable Test Mode',

                'type' => 'checkbox',

                'description' => 'Place the payment gateway in test mode using test API keys.',

                'default' => 'yes'

            ) ,

            'dynamic_deposit' => array(

                'title' => 'Allow dynamic deposits',

                'type' => 'checkbox',

                'description' => 'Enabling this will combine all individual product deposit types. This is meant to be used if you have multiple different deposits set on your products',

                'default' => 'no'

            ) ,

            'popup_warning' => array(

                'title' => 'Allow popup warning on product page',

                'type' => 'checkbox',

                'description' => 'Enabling this will create a popup warning for the customer if they attemped to add a product to their cart which already has products with different deposit configurations.',

                'default' => 'no'

            ) ,

            'layup_dep_type' => array(

                'title' => 'Deposit Type',

                'type' => 'select',

                'description' => 'Select one of the following deposit types, required to initiate a payment plan and activate an order, applicable to all payment plans created by this Merchant Account.<br>
                Percentage: Define a percentage of the total order value e.g. 10%.<br>
                First Instalment: Deposit equal to the instalment value determined by the customer according to the payment plan duration e.g. R5,000 order paid over 5 months = R1,000 deposit.<br>
                Flat Fee: Define a specific amount (lower than the max order value) e.g. R150.',

                'options' => array(
                    'PERCENTAGE' => 'Percentage',
                    'INSTALMENT' => 'First instalment',
                    'FLAT' => 'Flat fee'
                ) ,

                'default' => 'PERCENTAGE'

            ) ,

            'layup_dep' => array(

                'title' => 'Deposit Amount',

                'type' => 'number',

                'description' => 'The deposit amount based on what was chosen as the deposit type.<br>(only applicable if pecentage or flat fee is chosen for the deposit type.)',

                'default' => '20'

            ) ,

            'lu_min_end_date' => array(

                'title' => 'Min months',

                'type' => 'number',

                'description' => 'The minimum number of months a customer can choose to pay off an order',

                'default' => '1',

                'custom_attributes' => array(

                    'min' => '1'

                )

            ) ,

            'lu_max_end_date' => array(

                'title' => 'Max months',

                'type' => 'number',

                'description' => 'The maximum number of months a customer can choose to pay off an order',

                'default' => '6',

                'custom_attributes' => array(

                    'min' => '1'

                )

            ) ,

            'payplan_disp' => array(

                'title' => 'Show payment plan example',

                'type' => 'checkbox',

                'description' => 'Show payment plan example under each product and on single product page',

                'default' => 'yes'

            ) ,

            'payplan_disp_cart' => array(

                'title' => 'Show payment plan on cart page',

                'type' => 'checkbox',

                'description' => 'Show payment plan example under the checkout button on the cart page',

                'default' => 'no'

            ) ,

            'payment_plan_template' => array(

                'title' => 'Payment plan preview',

                'type' => 'text',

                'description' => 'Configure your own payment plan preview. You can make use the following variables: "{amount}", "{months}", "{deposit}". ie "Pay only {deposit} and {amount}/pm for {months} months". For default leave blank.',

                'default' => '',

            ) ,

            'learn_more_style' => array(

                'title' => 'Learn more popup style',

                'type' => 'select',

                'description' => 'Change between a Layby style or a Subscription style depending on product offering',

                'options' => array(
                    'layby' => 'Layby',
                    'subscription' => 'Subscription'
                ) ,

                'default' => 'layby'

            ) , 

            'product_type' => array(

                'title' => 'Global product type',

                'type' => 'select',

                'description' => 'Change between a Layby, Subscription or Pre-order depending on your product offering',

                'options' => array(
                    'layby' => 'Layby',
                    'subscription' => 'Subscription',
                    'pre-order' => 'Pre-order'
                ) ,

                'default' => 'layby'

            ) ,

            'complete_payment_on_deposit' => array(

                'title' => 'Complete payment on deposit payment',

                'label' => 'Enable complete payment on deposit',

                'type' => 'checkbox',

                'description' => 'Enabling this will move an order to processing when the initial deposit is paid.',

                'default' => 'no'

            ) ,

            'api_key_error' => array(

                'type' => 'hidden',

                'default' => 0

            )

        );

    }

    function process_admin_options()
 {
    $_POST['woocommerce_layup_api_key_error'] = "0";
    parent::process_admin_options();
 }

    /**
     * Check if this gateway is enabled and available in the base currency being traded with.
     */

    public function is_valid_for_use()
    {

        $is_available = false;

        $is_available_currency = in_array(get_woocommerce_currency() , $this->available_currencies);

        if ($is_available_currency && $this->merchant_id && $this->merchant_key)
        {

            $is_available = true;

        }

        return $is_available;

    }

    public function admin_options()
    {

        if (in_array(get_woocommerce_currency() , $this->available_currencies))
        {

            parent::admin_options();

        }
        else
        {

?>



            <h3><?php _e('LayUp', 'layup-gateway'); ?></h3>



            <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'layup-gateway'); ?></strong> <?php /* translators: 1: a href link 2: closing href */
            echo sprintf(__('Choose South African Rands as your store currency in %1$sGeneral Settings%2$s to enable the LayUp Gateway.', 'layup-gateway') , '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=general')) . '">', '</a>'); ?></p></div>



            <?php

        }

    }

    public function validate_lu_api_key_field( $key, $value){
                $status = $this->validate_merchant_api_key($value);

                if( $status["status"] ){
                    if( $status["env"] == "Production" ){
                    array_push($this->errors, '<div class="notice notice-warning"><p>'. __('Your API Key is for our production environment please make sure you have disabled test mode.', 'layup-gateway'). '</p></div>');
                    return $value;
                    } elseif( $status["env"] == "Sandbox" ){
                        array_push($this->errors, '<div class="notice notice-warning"><p>'. __('Your API Key is for our sandbox environment please make sure you have enabled test mode.', 'layup-gateway'). '</p></div>');
                        return $value;
                    }
        
                } else{
                    array_push($this->errors, '<div class="notice notice-error"><p>'. __('Your API key seems to be incorrect, please check that you have entered the correct API key and try again.', 'layup-gateway'). '</p></div>');
                    return $value;
                }
        
            }

            
            private function validate_merchant_api_key($value) {
                $status["status"] = false;
                $status["env"] = "";
                $headers = array(

                    'Content-Type' => 'application/json',
    
                    'apikey' => $value,
    
                );
    
    
                $args = array(
    
                    'headers' => $headers,
    
                );

                $api_url_sandbox = "https://sandbox-api.layup.co.za/v1/auth/me";
                $api_url_prod = "https://api.layup.co.za/v1/auth/me";
    
                $response_prod = wp_remote_get($api_url_prod, $args);
                $response_sandbox = wp_remote_get($api_url_sandbox, $args);
    
                if (!is_wp_error($response_prod) && !is_wp_error($response_sandbox)) {

                    if ($response_prod['body'] != "Unauthorized") {
                        $status["status"] = true;
                        $status["env"] = "Production";
                    } elseif ($response_sandbox['body'] != "Unauthorized") {
                        $status["status"] = true;
                        $status["env"] = "Sandbox";
                    }
                    
                } else {
                    array_push($this->errors, '<div class="notice notice-error"><p>'. __('There seems to be a problem contacting the LayUp Servers, Please try again.', 'layup-gateway'). '</p></div>');
                }
                return $status;
            }

    /*
    
     * Processing the order and redirecting to layup
    
    */

    public function process_payment($order_id)
    {

        global $woocommerce;

        $cart_inarray = false;
        $product_names = '';
        $cart_products = $woocommerce
            ->cart->cart_contents;

        foreach ($cart_products as $cart_product)
        { //enumerate over all cart contents
            $layup_disable_meta = get_post_meta($cart_product['data']->get_id() , 'layup_disable', true);

            if (!empty($layup_disable_meta))
            {

                $cart_inarray = true; //set inarray to true
                $product_names .= $cart_product['data']->get_title() . ', ';

            }

        }

        if ($cart_inarray)
        { //product is in the cart
            wc_add_notice('You currently have the following items in your cart that do not allow you to use LayUp as a payment method: ' . $product_names . 'please remove them if you wish to use the LayUp payment method.', 'error');

            return;
        }

        // we need it to get any order detailes
        $order = wc_get_order($order_id);

        $unid = md5(uniqid($order_id, true));

        $ref = substr($unid, 0, 10);

        $blog_title = get_bloginfo();

        $order_items = $order->get_items(array(
            'line_item'
        ));

        // Build product array
        $custom_dep_inarray = false;

        $check_dep_type = [];
        $check_dep_amount = [];
        $check_dep_months_min = [];
        $check_dep_months_max = [];

        foreach ($order_items as $cd_item_id => $cd_order_item)
        {

            $cd_product = $cd_order_item->get_product();

            if ($cd_product->is_type('variation'))
            {
                $cd_product = wc_get_product($cd_product->get_parent_id());

            }

            $layup_custom_deposit = get_post_meta($cd_product->get_id() , 'layup_custom_deposit', true);
            $layup_custom_deposit_type = get_post_meta($cd_product->get_id() , 'layup_custom_deposit_type', true);
            $layup_custom_deposit_amount = get_post_meta($cd_product->get_id() , 'layup_custom_deposit_amount', true);
            $layup_custom_months = get_post_meta($cd_product->get_id() , 'layup_custom_months', true);
            $layup_custom_months_min = get_post_meta($cd_product->get_id() , 'layup_custom_months_min', true);
            $layup_custom_months_max = get_post_meta($cd_product->get_id() , 'layup_custom_months_max', true);

            if ($layup_custom_deposit == "yes")
            {
                array_push($check_dep_type, $layup_custom_deposit_type);
                array_push($check_dep_amount, $layup_custom_deposit_amount);
            }
            else
            {
                array_push($check_dep_type, $this->layup_dep_type);
                array_push($check_dep_amount, $this->layup_dep);
            }
            
            if ($layup_custom_months == "yes")
            {
                array_push($check_dep_months_min, $layup_custom_months_min);
                array_push($check_dep_months_max, $layup_custom_months_max);
            }
            else
            {
                array_push($check_dep_months_min, $this->lu_min_end_date);
                array_push($check_dep_months_max, $this->lu_max_end_date);
            }
            

        }


        if ($this->dynamic_deposit == "yes") {
            if (count(array_flip($check_dep_type)) > 1 || count(array_flip($check_dep_amount)) > 1) {
                $combine_amount = [];
                foreach($order_items as $combine_item_id => $combine_order_item) {
                    $combine_product = $combine_order_item->get_product();
                    if ($combine_product->is_type('variation'))
                    {
                        $combine_product = wc_get_product($combine_product->get_parent_id());
                    }
                    $combine_product_price = $combine_order_item->get_total() + $combine_order_item->get_total_tax();
                    $combine_product_id = $combine_product->get_id();
                    $layup_custom_deposit_combine = get_post_meta($combine_product_id , 'layup_custom_deposit', true);
                    $layup_custom_deposit_type_combine = get_post_meta($combine_product_id , 'layup_custom_deposit_type', true);
                    $layup_custom_deposit_amount_combine = get_post_meta($combine_product_id, 'layup_custom_deposit_amount', true);
                    $layup_custom_months_max_combine = get_post_meta($combine_product_id , 'layup_custom_months_max', true);
                    if ($layup_custom_deposit_combine == "yes")
                    {
                        if ($layup_custom_deposit_type_combine == "FLAT") {
                            array_push($combine_amount, $layup_custom_deposit_amount_combine);
                        } elseif ($layup_custom_deposit_type_combine == "PERCENTAGE") {
                            $perc_flat_amount = $layup_custom_deposit_amount_combine/100 * $combine_product_price;
                            array_push($combine_amount, $perc_flat_amount);
                        } elseif ($layup_custom_deposit_type_combine == "INSTALMENT") {
                            $instal_flat_amount = $combine_product_price / ($layup_custom_months_max_combine + 1);
                            array_push($combine_amount, $instal_flat_amount);
                        }
                    } else {
                        if ($this->layup_dep_type == "FLAT") {
                            array_push($combine_amount, $this->layup_dep);
                        } elseif ($this->layup_dep_type == "PERCENTAGE") {
                            $perc_flat_amount = $this->layup_dep/100 * $combine_product_price;
                            array_push($combine_amount, $perc_flat_amount);
                        } elseif ($this->layup_dep_type == "INSTALMENT") {
                            $instal_flat_amount = $combine_product_price / ($this->lu_max_end_date + 1);
                            array_push($combine_amount, $instal_flat_amount);
                        }
                    }
                }
                $check_dep_amount = array(array_sum($combine_amount));
                $check_dep_type = array("FLAT");
            }

            if (count(array_flip($check_dep_months_min)) > 1 || count(array_flip($check_dep_months_max)) > 1) {
                $check_dep_months_min = array(max($check_dep_months_min));
                $check_dep_months_max = array(min($check_dep_months_max));
            }
        }

        if (count(array_unique($check_dep_type)) <= 1 && count(array_unique($check_dep_amount)) <= 1 && count(array_unique($check_dep_months_min)) <= 1 && count(array_unique($check_dep_months_max)) <= 1)
        {

            if (!empty($check_dep_amount[0]))
            {
                $this->layup_dep = $check_dep_amount[0];
                settype($this->layup_dep, 'float');
            }

            if (!empty($check_dep_type[0]))
            {
                $this->layup_dep_type = $check_dep_type[0];
            }

            if (!empty($check_dep_months_min[0]))
            {
                $this->lu_min_end_date = $check_dep_months_min[0];
            }

            if (!empty($check_dep_months_max[0]))
            {
                $this->lu_max_end_date = $check_dep_months_max[0] + 1;
            }

            $woo_thank_you = $order->get_checkout_order_received_url();

            // Build product array
            $products = array();
            $i = 0;

            foreach ($order_items as $item_id => $order_item)
            {

                $product = $order_item->get_product();
                $price = round(($order_item->get_total() * 100),0);

                if ($product->get_sku() != '')
                {
                    $product_sku = $product->get_sku();
                }
                else
                {
                    $product_sku = $this->generate_layup_sku($product->get_title());
                }

                $products[$i] = array(

                    'amount' => (int)$price,

                    'link' => get_permalink($product->get_id()) ,

                    'sku' => $product_sku

                );

                // Format and add min and max dates
                

                $date_sel = get_post_meta($order_id, 'layup_date_sel', true);

                if (!empty($date_sel))
                {

                    $curr_date = date('c');

                    $min_date = date('c', strtotime("+" . $this->lu_min_end_date . " months", strtotime($curr_date)));

                    $max_date = date('c', strtotime($date_sel));

                }
                else
                {

                    $curr_date = date('c');

                    $min_date = date('c', strtotime("+" . $this->lu_min_end_date . " months", strtotime($curr_date)));

                    $max_date = date('c', strtotime("+" . $this->lu_max_end_date . " months", strtotime($curr_date)));

                }

                if ($i == 0)
                {

                    if ($product->is_type('variation'))
                    {
                        $parent_product = wc_get_product($product->get_parent_id());
                        $product_id = $parent_product->get_id();

                    }
                    else
                    {
                        $product_id = $product->get_id();
                    }

                    $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id));

                    if ($featured_image)
                    {

                        $order_image = $featured_image;

                    }
                    else
                    {

                        $order_image[0] = wc_placeholder_img_src();

                    }

                }

                $i++;

            }

            // Check for fees

            foreach ( $order->get_items('fee') as $item_id => $item_fee ){

                $products[$i] = array(

                    'amount' => (int)round(($item_fee->get_total() * 100),0),

                    'link' => get_site_url(),

                    'sku' => $item_fee->get_name()

                );

                $i++;
         
            }

            // Check for shipping total
            
            
            $order_shipping_total = round(($order->get_total_shipping() * 100),0);

            if ($order_shipping_total != '')
            {

                $products[$i] = array(

                    'amount' => (int)$order_shipping_total,

                    'link' => get_site_url() ,

                    'sku' => 'Shipping'

                );

                $i++;

            }

            // Check for tax total
            

            $order_tax_total = round(($order->get_total_tax() * 100),0);

            if ($order_tax_total != '')
            {

                $products[$i] = array(

                    'amount' => (int)$order_tax_total,

                    'link' => get_site_url() ,

                    'sku' => 'VAT'

                );

            }

            // Build and send LayUp order request
            
            
            $order_details = array(

                'depositAmount' => round(($this->layup_dep * 100),0),

                'products' => $products,

                'endDateMax' => $max_date,

                'endDateMin' => $min_date,

                'depositPerc' => (int)$this->layup_dep,

                'reference' => $ref,

                'name' => $blog_title . ' #' . $order->get_order_number() ,

                'imageUrl' => $order_image[0],

                'depositType' => $this->layup_dep_type,

            );

            $headers = array(

                'Content-Type' => 'application/json',

                'apikey' => $this->api_key,

            );

            $order_details_json = json_encode($order_details, JSON_UNESCAPED_SLASHES);

            $args = array(

                'headers' => $headers,

                'body' => $order_details_json

            );

            $response = wp_remote_post($this->api_url, $args);

            if (!is_wp_error($response))
            {

                $body = json_decode($response['body'], true);

                // Check if order was created successfully
                

                if ($body['state'] == 'PARTIAL')
                {

                    // Link LayUp Order to Woocommerce order
                    

                    $order->update_meta_data('layup_order_id', $body['_id']);

                    $order->update_meta_data('layup_order_ref', $body['reference']);

                    // some notes added to Woocommerce order on admin dashboard
                    $order->add_order_note(__('Order created with LayUp', 'layup-gateway'));

                    $order->save();

                    //empty cart
                    
                    $woocommerce
                        ->cart
                        ->empty_cart();

                    // Redirect to LayUp
                    

                    return array(

                        'result' => 'success',

                        'redirect' => ($this->testmode) ? 'https://sandbox.layup.co.za/order/' . $body['_id'] . '?notifyUrl=' . $woo_thank_you : 'https://shopper.layup.co.za/order/' . $body['_id'] . '?notifyUrl=' . $woo_thank_you

                    );

                }
                else
                {

                    wc_add_notice($response['body'], 'error');

                    return;

                }

            }
            else
            {

                wc_add_notice('LayUp service is unreachable. Please try again', 'error');

                return;

            }

        }
        else
        {

            wc_add_notice('Some products are using a custom deposit for LayUp checkout. Please make sure that all products in your cart have the same deposit type and months before checking out with LayUp.', 'error');

            return;
        }

    }

    function generate_layup_sku($str)
    {
        $acronym = '';
        $word = '';
        $words = preg_split("/(\s|\-|\.)/", $str);
        $i = 0;
        foreach ($words as $w)
        {
            $acronym .= substr($w, 0, 1);
            if ($i++ == 3) break;
        }
        $word = $word . $acronym;
        $digits = 3;
        $rand_num = str_pad(rand(0, pow(10, $digits) - 1) , $digits, '0', STR_PAD_LEFT);
        $word = $word . $rand_num;
        return $word;
    }

    // Handles the callbacks received from the payment backend. give this url to your payment processing comapny as the ipn response URL:
    // USAGE:  http://myurl.com/?wc-api=WC_Layup_Gateway
    function layup_callback()
    {
        $inputJSON = file_get_contents('php://input');
        $_POST = json_decode($inputJSON, true);
        $layup_order_id = $_POST['body']['orderId'];

        $query_args = array(
            'status' => array( 'pending', 'on-hold', 'cancelled', 'processing' ),
            'meta_key' => 'layup_order_id',
            'meta_value' => $layup_order_id,
            'meta_compare' => '='
        );

        $orders = wc_get_orders( $query_args );

        if ($orders)
        {

            $first_order = $orders[0];
            $order = wc_get_order($first_order->get_id());

            if ($_POST['type'] == 'ORDERPLACED')
            {
                $headers = array(
                    'accept' => 'application/json',
                    'apikey' => $this->api_key,
                );

                $order_args = array(
                    'headers' => $headers,
                );

                $order_response = wp_remote_get($this->api_url . '/' . $layup_order_id . '?populate=plans,plans.payments', $order_args);

                if (!is_wp_error($order_response))
                {

                    $body = json_decode($order_response['body'], true);
                    
                    $pp = 0;

                    // Save LayUp payment plans to Woocommerce order

                    foreach ($body['plans'] as $plans)
                {

                    $order->update_meta_data('layup_pp_id_' . $pp, $plans['_id']);

                    $order->update_meta_data('layup_pp_freq_' . $pp, strtolower($plans['frequency']));

                    $order->update_meta_data('layup_pp_quant_' . $pp, $plans['quantity']);

                    //get monthly amount
                    $due = '';

                    foreach ($plans['payments'] as $payment)
                    {

                        if ($payment['paid'] == false)
                        {

                            $due = $payment['due'];
                            $monthly = $payment['amount'];

                            break;
                        }
                    }

                    $paid = 0;

                    foreach ($plans['payments'] as $payment)
                    {

                        if ($payment['paid'] == true)
                        {

                            $paid += $payment['amount'];
                        }
                    }

                    //convert cents to rands
                    

                    $monthly_rands = $monthly / 100;

                    $outstanding = $plans['amountDue'] + $plans['depositDue'] - $paid;

                    $outstanding_rands = $outstanding / 100;

                    
                    //formate numbers to work with WC
                    $due_date = date("Y/m/d", strtotime($due));

                    $outstanding_foramted = number_format($outstanding_rands, 2, '.', '');

                    $monthly_payment = number_format($monthly_rands, 2, '.', '');

                    $order->update_meta_data('layup_pp_due_date_' . $pp, $due_date);

                    $order->update_meta_data('layup_pp_outstanding_' . $pp, $outstanding_foramted);

                    $order->update_meta_data('layup_pp_monthly_' . $pp, $monthly_payment);

                    $pp++;
                }

                    if ($order->get_status() == "pending")
                    {

                        if ($this->complete_payment_on_deposit)
                        {
                            $order->payment_complete();
                            $order->add_order_note(__('Deposit paid to LayUp.', 'layup-gateway'));
                            $order->save();
                        }
                        else
                        {
                            $order->update_status('wc-on-hold', __('Deposit paid to LayUp.', 'layup-gateway'));
                        }

                    }
                    
                }

            }
            elseif ($_POST['type'] == 'ORDERCOMPLETED')
            {
                if ($this->complete_payment_on_deposit)
                {
                    $order->add_order_note(__('LayUp order paid in full.', 'layup-gateway'));
                    $order->save();
                }
                else
                {
                    $order->payment_complete();
                    $order->add_order_note(__('LayUp order paid in full.', 'layup-gateway'));
                    $order->update_meta_data('layup_pp_outstanding_0', '0');
                    $order->save();
                }

            }
            elseif ($_POST['type'] == 'ORDERCANCELLED')
            {

                $order->update_status('wc-cancelled', __('Order cancelled by LayUp.', 'layup-gateway'));

            }
            elseif ($_POST['type'] == 'ORDEREXPIRED')
            {

                $order->update_status('wc-cancelled', __('Order expired by LayUp.', 'layup-gateway'));

            } elseif ($_POST['type'] == 'PAYMENTSUCCESSFUL')
            {
                $headers = array(
                    'accept' => 'application/json',
                    'apikey' => $this->api_key,
                );

                $order_args = array(
                    'headers' => $headers,
                );

                $order_response = wp_remote_get($this->api_url . '/' . $layup_order_id . '?populate=plans,plans.payments', $order_args);

                if (!is_wp_error($order_response))
                {

                    $body = json_decode($order_response['body'], true);
                    error_log(print_r($body, true));
                    $pp = 0;

                    // Save LayUp payment plans to Woocommerce order

                    foreach ($body['plans'] as $plans)
                {

                    $order->update_meta_data('layup_pp_id_' . $pp, $plans['_id']);

                    $order->update_meta_data('layup_pp_freq_' . $pp, strtolower($plans['frequency']));

                    $order->update_meta_data('layup_pp_quant_' . $pp, $plans['quantity']);

                    //get monthly amount
                    $due = '';

                    foreach ($plans['payments'] as $payment)
                    {

                        if ($payment['paid'] == false && $payment['amount'] > 0)
                        {

                            $due = $payment['due'];
                            $monthly = $payment['amount'];

                            break;
                        }
                    }

                    $paid = 0;

                    foreach ($plans['payments'] as $payment)
                    {

                        if ($payment['paid'] == true)
                        {

                            $paid += $payment['amount'];
                        }
                    }

                    //convert cents to rands
                    

                    $monthly_rands = $monthly / 100;

                    $outstanding = $plans['amountDue'] + $plans['depositDue'] - $paid;

                    $outstanding_rands = $outstanding / 100;

                    //formate numbers to work with WC
                    $due_date = date("Y/m/d", strtotime($due));

                    $outstanding_foramted = number_format($outstanding_rands, 2, '.', '');

                    $monthly_payment = number_format($monthly_rands, 2, '.', '');

                    $order->update_meta_data('layup_pp_due_date_' . $pp, $due_date);

                    $order->update_meta_data('layup_pp_outstanding_' . $pp, $outstanding_foramted);

                    $order->update_meta_data('layup_pp_monthly_' . $pp, $monthly_payment);

                    $pp++;
                }

                $order->save();

                }

            } 
            else
            {
                return;
            }
        }

    }

    /**
     *  Show possible admin notices
     */

    public function admin_notices()
    {

        if(count($this->errors) > 0){
            foreach($this->errors as $err){
                echo $err;
            }
        }


        if ('yes' !== $this->get_option('enabled')
 || 'yes' !== $this->get_option('lu_testmode'))
        {

            return;

        }

        $settings_url = add_query_arg(

        array(

            'page' => 'wc-settings',

            'tab' => 'checkout',

            'section' => 'layup',

        ) ,

        admin_url('admin.php')
);

    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){ 
         $url = "https://";   
    }else {
         $url = "http://";   
    }
    // Append the host(domain name, ip) to the URL.   
    $url.= $_SERVER['HTTP_HOST'];   
    
    // Append the requested resource location to the URL   
    $url.= $_SERVER['REQUEST_URI'];    
       

    if ($url != $settings_url) {

        echo '<div class="notice notice-warning"><p>'
 . __('LayUp is currently in test mode and requires additional configuration to function correctly. Complete setup ', 'layup-gateway')
 . '<a href="' . esc_url($settings_url) . '">' . __('here.', 'layup-gateway') . '</a>



			</p></div>';
    }

    }

}

