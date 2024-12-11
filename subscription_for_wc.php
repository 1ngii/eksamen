<?php
/*
Plugin Name: Subscription for WooCommerce
Author: Helgi Bjarnason
Version: 1.0
Description: Adds subscription data tabs to all products, and implements a recurring payment system for subscription products.
*/

// Start a session if not already started
add_action('init', 'start_session', 1);
function start_session() {
    if (!session_id()) {
        session_start(); // Ensure session is initialized for storing temporary data
    }
}

// Remove the default price HTML on product pages
add_filter('woocommerce_get_price_html', '__return_empty_string', 10, 2);

// Add a "Subscription" tab to the WooCommerce product data section in the admin
add_filter('woocommerce_product_data_tabs', 'ingii_product_settings_tabs');
function ingii_product_settings_tabs($tabs) {
    $tabs['subscription'] = [
        'label'  =>  'Subscription',
        'target'  =>  'subscription_product_data',
        'priority'  =>  '21', // Determines order in the tab list
    ];
    return $tabs;
}

// Display the subscription settings panel in the product data section
add_action('woocommerce_product_data_panels', 'subscription_product_panels');
function subscription_product_panels() {
    // Main container for subscription settings
    echo '<div id="subscription_product_data" class="panel woocommerce_options_panel">';

    // Checkbox to indicate if the product is a subscription product
    woocommerce_wp_checkbox([
        'id'    => 'sub',
        'value' => get_post_meta(get_the_ID(), 'subscription_product', true),
        'label' => 'Subscription product',
        'desc_tip' => true,
        'description' => 'This is a subscription type product with recurring payments'
    ]);

    // Retrieve and display existing subscription plans
    $existing_subscription_plans = get_post_meta(get_the_ID(), 'subscription_plans', true) ?: [];
    $subscription_value = get_post_meta(get_the_ID(), 'subscription_product_data', true);
    $is_subscription_enabled = get_post_meta(get_the_ID(), 'subscription_product', true) === 'yes';

    // Toggle visibility of subscription options based on checkbox state
    echo '<div id="subscription_product_data_extend" style="display: ' . ($is_subscription_enabled ? 'block' : 'none') . ';">';

    // Dropdown for existing subscription options
    $options = [];
    foreach ($existing_subscription_plans as $index => $plan) {
        $options[$index] = $plan['period'] . ': ' . $plan['value'] . ' kr';
    }

    woocommerce_wp_select([
        'id' => 'subscription_options',
        'label' => __('List of possible subscription options', 'New Subscription WooCommerce'),
        'options' => $options,
        'desc' => __('Choose one option from the dropdown', 'New Subscription WooCommerce'),
        'value' => $subscription_value,
    ]);

    // Form for adding a new subscription option
    echo '<form method="POST">';
    echo '<h3>New Subscription option</h3>';
    echo '<div id="new_subscription_plan">';
    woocommerce_wp_select([
        'id'  =>  'new_subscription_period',
        'label'  =>  'Period',
        'value'  => get_post_meta(get_the_ID(), 'new_subscription_period', true),
        'options'   =>  array(
            'minutely'  =>  'minutely',
            'monthly' =>  'monthly',
            'yearly'  =>  'yearly',
        ),
    ]);
    woocommerce_wp_text_input([
        'id'  =>  'new_subscription_value',
        'label'  =>  'Value (kr)',
        'value'  => get_post_meta(get_the_ID(), 'new_subscription_value', true),
        'type'  =>  'number',
    ]);
    echo '</div>';

    // Button to submit the new subscription plan
    echo '<button type="submit" name="add_subscription_plan">Submit new plan</button>';
    echo '</form>';

    echo '</div>'; // End of subscription options container
    echo '</div>'; // End of main subscription panel

    // Enqueue JavaScript to toggle the subscription options visibility
    wc_enqueue_js("
        jQuery('#sub').change(function(){
            if(jQuery(this).is(':checked')) {
                $('#subscription_product_data_extend').show();
            } else {
                $('#subscription_product_data_extend').hide();
            }
        }).change(); // Trigger change to set initial state
    ");
}

// Hooking the function to the single product summary section in WooCommerce.
add_action('woocommerce_single_product_summary', 'display_and_change_billing_option', 25);

function display_and_change_billing_option()
{
    global $product; // Accessing the global WooCommerce product object.

    // Check if the product is a subscription product by fetching metadata.
    $is_subscription_product = get_post_meta($product->get_id(), 'subscription_product', true);

    // Get the regular price of the product.
    $regular_price = $product->get_regular_price();

    $custom_price = 0; // Placeholder for the subscription price.
    $current_price = $regular_price; // Default to the regular price.

    // Fetch subscription plans metadata or set an empty array if none exist.
    $subscription_plans = get_post_meta($product->get_id(), 'subscription_plans', true) ?: [];

    // Iterate through subscription plans to get the first custom price.
    foreach ($subscription_plans as $plan) {
        if (isset($plan['value'])) {
            $custom_price = floatval($plan['value']); // Convert to float and store.
            break; // Stop after getting the first plan's value.
        }
    }

    // If the product is a subscription product, display billing options.
    if ($is_subscription_product === 'yes') {
        // Create a dropdown for choosing the billing method.
        echo '<div class="billing-option">';
        echo '<label for="billing_method">Choose your preferred billing method:</label>';
        echo '<select id="billing_method" name="billing_method">';
        echo '<option value="0"' . selected($current_price, $regular_price, false) . '>Regular price</option>';
        echo '<option value="1"' . selected($current_price, $custom_price, false) . '>Subscription</option>';
        echo '</select>';
        echo '</div>';

        // If there are subscription plans, display additional options.
        if (!empty($subscription_plans)) {
            // Container for subscription options dropdown.
            echo '<div id="subscription_options_container" style="display: none;">';
            echo '<form method="POST" id="sub_plans"> ';
            echo '<label for="subscription_options">Select Subscription Plan:</label>';
            echo '<select id="subscription_options" name="subscription_options">';
            // Populate dropdown with subscription plan options.
            foreach ($subscription_plans as $plan) {
                echo '<option value="' . esc_attr($plan['value']) . '">' . esc_html($plan['period']) . '</option>';
            }
            echo '</select>';
            echo '</form>';
            echo '</div>';
?>
            <!-- Hidden field to store the product ID, used for processing on the server side. -->
            <input type="hidden" id="product_id" value="<?php echo get_the_ID(); ?>">
<?php
        }
    }

    // Display the current price of the product.
    echo '<div id="price_display">' . wc_price($current_price) . '</div>';
}

// Hook to save billing method via AJAX for logged-in users.
add_action('wp_ajax_save_billing_method', 'save_billing_method');
// Hook to save billing method via AJAX for non-logged-in users.
add_action('wp_ajax_nopriv_save_billing_method', 'save_billing_method');

// Function to handle saving the selected billing method.
function save_billing_method()
{
    // Check if 'billing_method' is set in the POST data.
    if (isset($_POST['billing_method'])) {
        // Sanitize the input 'billing_method' to ensure it's safe.
        $billing_method = sanitize_text_field($_POST['billing_method']);

        // Store the selected billing method in the WooCommerce session.
        WC()->session->set('chosen_billing_method', $billing_method);

        // Respond with a success message and the selected billing method.
        wp_send_json_success('Billing method saved successfully.' . $billing_method);
    } else {
        // If no billing method is selected, respond with an error message.
        wp_send_json_error('Billing method not selected.');
    }

    // End the request and ensure no further code is executed.
    wp_die();
}

// Add a hidden input field before the add-to-cart quantity field in WooCommerce product page.
add_filter('woocommerce_before_add_to_cart_quantity', function ($selected_price) {
    // Add a hidden input field to store the selected price (which can be set later).
    echo '<input type="hidden" name="selected_price" id="selected_price" value="" />';

    // Return the original value of the quantity field (or modify if needed).
    return $selected_price;
});

// Hook to enqueue product price script on product pages
add_action('wp_enqueue_scripts', 'enqueue_product_price_script');

function enqueue_product_price_script()
{
    // Only load the script on product pages
    if (is_product()) {
        global $product;
        // Get the regular price of the product
        $regular_price = $product->get_regular_price();

        // Enqueue a custom script for handling the product price on the product page
        wp_enqueue_script('product_price', get_template_directory_uri() . 'script.js', array('jquery'), null, true);

        // Localize the script to pass the product's regular price to JavaScript
        wp_localize_script('product_price', 'productData', array(
            'regular_price' => $regular_price,
        ));
    }
}

// Hook to enqueue the AJAX script for handling AJAX requests
add_action('wp_enqueue_scripts', 'enqueue_ajax_script');

function enqueue_ajax_script()
{
    // Enqueue the AJAX script
    wp_enqueue_script('ajax-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);

    // Localize the script to make the AJAX URL accessible in JavaScript
    wp_localize_script('ajax-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    
    // Another instance of localizing the AJAX URL, possibly redundant
    wp_localize_script('ajax-script', 'ajax_object2', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Hook for handling the AJAX request when checking subscription selection
add_action('wp_ajax_check_subscription_selection', 'check_subscription_selection');
add_action('wp_ajax_nopriv_check_subscription_selection', 'check_subscription_selection');

function check_subscription_selection()
{
    // Ensure that WooCommerce session is loaded, if not load it
    if (! WC()->session) {
        wc_load_cart(); // Load the cart session if not already available
    }

    // Get the product ID from the POST request
    $product_id = intval($_POST['product_id']);

    // Sanitize and get the selected subscription price and period
    $selected_subscription_price = sanitize_text_field($_POST['selected_subscription_price']);
    $selected_subscription_period = sanitize_text_field($_POST['selected_subscription_period']);

    // Save the selected subscription data in the session
    WC()->session->set('saved_subscription_price', $selected_subscription_price);
    WC()->session->set('saved_subscription_period', $selected_subscription_period);

    // Check if the product is a subscription product (using custom field)
    $is_subscription_product = get_post_meta($product_id, 'subscription_product', true);

    // If it's a subscription product and no price is selected, return an error
    if ($is_subscription_product === 'yes' && empty($selected_subscription_price)) {
        wp_send_json_error(array('message' => 'Please select a subscription option.'));
    } else {
        // Convert the selected subscription price to a float
        $price = floatval($selected_subscription_price);

        // Send a success response with the new price
        wp_send_json_success(array(
            'message' => 'Valid selection.',
            'new_price' => $price
        ));

        // Save the selected price in the session for further use
        WC()->session->set('selected_subscription_price', $price);
    }

    // End the AJAX request
    wp_die();
}

// Hook to adjust the product price before WooCommerce calculates the cart totals
add_action('woocommerce_before_calculate_totals', 'adjust_product_price_before_totals', 10, 1);

function adjust_product_price_before_totals($cart)
{
    // Skip if the request is made from the admin dashboard or via AJAX
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Loop through each item in the cart
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Check if the item is a subscription (custom flag 'is_subscription' is true)
        if ($cart_item['is_subscription']) {
            // Check if the item has a selected price and the updated price
            if (isset($cart_item['updated_price']) && $cart_item['updated_price'] && isset($cart_item['selected_price'])) {
                // Get the selected subscription price
                $selected_price = floatval($cart_item['selected_price']);
                // Set the new price for the product in the cart
                $cart_item['data']->set_price($selected_price); // Set the new price for the subscription product
            }
        }
    }
}

// Hook to add custom cart item data (such as subscription price and method) when an item is added to the cart
add_filter('woocommerce_add_cart_item_data', 'h_add_cart_items', 10, 1);

function h_add_cart_items($cart_item_data)
{
    // Check if a selected price has been passed in the POST request
    if (isset($_POST['selected_price'])) {
        // Sanitize and assign the selected subscription price to the cart item
        $selected_price = floatval($_POST['selected_price']);
        $cart_item_data['selected_price'] = $selected_price;
        $cart_item_data['updated_price'] = true; // Mark that the price has been updated
        // Custom data key to track that this is a subscription price
    }

    // Check if the chosen billing method is subscription
    $chosen_billing_method = WC()->session->get('chosen_billing_method');
    if ($chosen_billing_method === '1') {
        $cart_item_data['is_subscription'] = true; // Mark the cart item as a subscription
    }

    // If the item is a subscription, add subscription-related data to the cart item
    if (isset($cart_item_data['is_subscription']) && $cart_item_data['is_subscription']) {
        // Get the saved subscription period and price from the session
        $saved_period = WC()->session->get('saved_subscription_period');
        $saved_price = WC()->session->get('saved_subscription_price');

        // Store the subscription period and price in an array
        $subscription_data = array(
            'period' => $saved_period,
            'price'  => $saved_price,
        );

        // Attach the subscription data to the cart item
        $cart_item_data['subscription_data'] = $subscription_data;
        // Store the subscription data in the session for later use
        WC()->session->set('subscription_data', $subscription_data);
    }

    // Add a custom message for the subscription product to show in the cart
    if ($cart_item_data['is_subscription'] === true) {
        $cart_item_data['subscription_msg'] =
            'This is a subscription product and you will be billed ' . $subscription_data['price'] . 'kr on a ' . $subscription_data['period'] . ' basis.';
    }

    return $cart_item_data; // Return the updated cart item data
}

// Hook to display custom subscription data (like subscription message) in the cart item data
add_filter('woocommerce_get_item_data', 'h_get_item_data', 10, 2);

function h_get_item_data($item_data, $cart_item_data)
{
    // Check if the cart item has a custom subscription message
    if (isset($cart_item_data['subscription_msg'])) {
        // Add the subscription message to the cart item data displayed to the user
        $item_data[] = array(
            'key'   =>  __('Subscription ', 'Helgi'), // Label for the subscription data
            'value' =>  wc_clean($cart_item_data['subscription_msg']), // Clean and display the message
        );
    }

    return $item_data; // Return the modified item data with the subscription message
}

// Hook to add custom data (subscription period, price) to each order item
add_action('woocommerce_checkout_create_order_line_item', 'add_custom_data_to_order_item', 10, 4);

function add_custom_data_to_order_item($item, $cart_item_key, $values, $order)
{
    // Retrieve the subscription period and price stored in the cart item
    $saved_subscription_period = $values['subscription_data']['period'];
    $saved_subscription_price = $values['subscription_data']['price'];
    $_h_is_subscription_order = true; // Mark this as a subscription order

    // Check if both subscription period and price are available
    if (isset($saved_subscription_period) && isset($saved_subscription_price)) {
        // Add the subscription data as meta data to the order item
        $item->add_meta_data('Subscription Period', $saved_subscription_period);
        $item->add_meta_data('Subscription Price', $saved_subscription_price);
        $item->add_meta_data('_h_is_subscription_order', $_h_is_subscription_order);
    }
}

// Hook that fires when the order status changes to 'completed'
add_action('woocommerce_order_status_completed', 'setup_recurring_billing', 10, 1);

function setup_recurring_billing($order_id)
{   
    // Get the order object using the order ID
    $order = wc_get_order($order_id);

    // Loop through all items in the order
    foreach ($order->get_items() as $item_id => $item) {
        // Retrieve the subscription data from the order item meta
        $subscription_period = $item->get_meta('Subscription Period');
        $subscription_price = $item->get_meta('Subscription Price');
        $is_subscription_order = $item->get_meta('_h_is_subscription_order');

        // If subscription data exists, call the recurring billing setup function
        if ($subscription_period && $subscription_price) {
            handle_recurring_billing($order_id, $subscription_period, $subscription_price, $is_subscription_order);
        }
    }
}

// Function that handles the recurring billing setup for the order
function handle_recurring_billing($order_id, $subscription_period, $subscription_price, $is_subscription_order)
{   
    // Calculate the next payment date based on the subscription period
    $next_payment_date = calculate_next_payment_date($subscription_period);

    // Save subscription details (period, price, and next payment date) as post meta for the order
    update_post_meta($order_id, '_subscription_period', $subscription_period);
    update_post_meta($order_id, '_subscription_price', $subscription_price);
    update_post_meta($order_id, '_h_is_subscription_order', $is_subscription_order);
    update_post_meta($order_id, '_next_payment_date', $next_payment_date);
}

// Function to calculate the next payment date based on the subscription period
function calculate_next_payment_date($subscription_period,)
{
    // Get the current time in timestamp format
    $current_time = current_time('timestamp');

    // Calculate the next payment date depending on the selected subscription period
    if ($subscription_period == 'minutely') {
        return strtotime('+1 minute', $current_time); // Add 1 minute
    } elseif ($subscription_period == 'monthly') {
        return strtotime('+1 month', $current_time); // Add 1 month
    } elseif ($subscription_period == 'yearly') {
        return strtotime('+1 year', $current_time); // Add 1 year
    } else {
        // Default case if no period is set (return current time)
        return $current_time;
    }
}

// Hook to schedule recurring payment task when an order status is set to 'completed'
add_action('woocommerce_order_status_completed', 'schedule_recurring_payment_task', 10, 1);

function schedule_recurring_payment_task($order_id)
{
    // Retrieve the next payment date for the order from the order's metadata
    $next_payment_date = get_post_meta($order_id, '_next_payment_date', true);

    if ($next_payment_date) {
        // Check if there's already a scheduled event for recurring payment
        $timestamp = wp_next_scheduled('process_recurring_payment', array($order_id));

        // If no recurring payment is scheduled, schedule the next recurring payment event
        if (!$timestamp) {
            wp_schedule_single_event($next_payment_date, 'process_recurring_payment', array($order_id));
            error_log("Scheduled next payment date for order #$order_id at: " . date('Y-m-d H:i:s', $next_payment_date));
        }
    }
}

// Hook to display subscription details in the admin order view
add_action('woocommerce_admin_order_data_after_order_details', 'display_subscription_meta_in_admin_order', 10, 1);

function display_subscription_meta_in_admin_order($order)
{
    // Loop through each order item
    foreach ($order->get_items() as $item_id => $item) {
        // Retrieve subscription period and price from the order item meta
        $subscription_period = $item->get_meta('Subscription Period');
        $subscription_price = $item->get_meta('Subscription Price');

        // If both period and price are set, display them in the order details
        if ($subscription_period && $subscription_price) {
            echo '<p><strong>Subscription Period:</strong> ' . esc_html($subscription_period) . '</p>';
            echo '<p><strong>Subscription Price:</strong> ' . wc_price($subscription_price) . '</p>';
        }
    }
}

// Hook to save custom subscription fields when editing product details in admin
add_action('woocommerce_process_product_meta', 'ingii_save_field');

function ingii_save_field($id)
{
    // Check if the subscription checkbox is checked to enable subscription for the product
    $subscription = isset($_POST['sub']) && 'yes' === $_POST['sub'] ? 'yes' : 'no';
    update_post_meta($id, 'subscription_product', $subscription);

    // If subscription is enabled, handle the subscription plans (period and value)
    if ($subscription === 'yes') {
        // Retrieve the new subscription period and price from the form inputs
        $new_subscription_period = isset($_POST['new_subscription_period']) ? sanitize_text_field($_POST['new_subscription_period']) : '';
        $new_subscription_value = isset($_POST['new_subscription_value']) ? sanitize_text_field($_POST['new_subscription_value']) : '';

        // Proceed if both the subscription period and price are provided
        if (!empty($new_subscription_period) && !empty($new_subscription_value)) {
            // Retrieve the existing subscription plans for this product
            $existing_plans = get_post_meta($id, 'subscription_plans', true) ?: [];

            // Check if the new subscription period already exists in the plans
            $period_exists = false;
            foreach ($existing_plans as $plan) {
                if (isset($plan['period']) && $plan['period'] === $new_subscription_period) {
                    $period_exists = true;
                    error_log('Period status ' . $period_exists);
                    break;
                }
            }

            // If the period doesn't exist, add the new subscription plan
            if (!$period_exists) {
                $existing_plans[] = [
                    'period' => $new_subscription_period,
                    'value'  => $new_subscription_value,
                ];
                update_post_meta($id, 'subscription_plans', $existing_plans);
            } else {
                // Log the error if the period already exists
                error_log(print_r($new_subscription_period, true));
                wc_add_notice('The selected period is already in use. Please choose a different period.', 'error');
            }
        }
    } else {
        // If subscription is disabled, remove any existing subscription plans
        delete_post_meta($id, 'subscription_plans');
    }
}
?>
