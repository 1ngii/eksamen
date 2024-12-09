jQuery(document).ready(function($) {
    // Handle changes in subscription plan selection
    $('#subscription_options').change(function() {
        // Get the selected plan's name (text) and value (price)
        var selectedPlanPeriod = $(this).find('option:selected').text();
        var selectedPlanValue = $(this).val();
        var newPrice = parseFloat(selectedPlanValue);

        // Update the displayed price based on the selected plan
        updatePriceDisplay(newPrice);

        // Send an AJAX request to check the subscription selection on the server
        $.ajax({
            url: ajax_object.ajax_url, // Server endpoint for the request
            method: 'POST',
            data: {
                action: 'check_subscription_selection', // Custom action for server processing
                selected_subscription_price: selectedPlanValue, // Selected price
                selected_subscription_period: selectedPlanPeriod, // Selected period name
                product_id: $('#product_id').val(), // Product ID for reference
                selected_price: newPrice // Selected price for the product
            },
            success: function(response) {
                // Handle successful response from the server
                if (response.success) {
                    $('#selected_price').val(response.data.new_price); // Update the price input field
                } else {
                    alert(response.data.message); // Show an error message
                }
            },
            error: function() {
                // Handle any errors in the AJAX request
                alert('An error occurred while processing your request.');
            }
        });
    }).change(); // Trigger the change event on page load to initialize

    var regularPrice = parseFloat(productData.regular_price); // Regular product price
    var customPriceSub = parseFloat($('#subscription_options').val()); // Custom subscription price

    // Handle changes in the billing method selection
    $('#billing_method').change(function() {
        var selectedValue = $(this).val(); // Selected billing method
        var newPrice;

        if (selectedValue === '0') {
            // If "one-time" billing method is selected
            newPrice = regularPrice; // Set price to regular price
            $('#subscription_options_container').hide(); // Hide subscription options
            $('#selected_price').val(newPrice); // Update the hidden price input field
        } else {
            // If "subscription" billing method is selected
            newPrice = customPriceSub; // Set price to subscription price
            $('#subscription_options_container').show(); // Show subscription options
            $('#selected_price').val(newPrice); // Update the hidden price input field
        }

        // Update the displayed price based on the selected billing method
        updatePriceDisplay(newPrice);

        // Send an AJAX request to save the selected billing method
        $.ajax({
            url: ajax_object2.ajax_url, // Server endpoint for saving billing method
            type: 'POST',
            data: {
                action: 'save_billing_method', // Custom action for saving billing method
                billing_method: selectedValue // Selected billing method value
            },
            success: function(response) {
                // Handle a successful save
                // console.log('Billing method saved: ' + response.data);
            },
            error: function() {
                // Handle errors while saving the billing method
                console.error('There was an error saving the billing method.');
            }
        });
    }).change(); // Trigger the change event on page load to initialize

    // Function to update the displayed price
    function updatePriceDisplay(price) {
        if (!isNaN(price)) {
            // Display the price formatted to two decimal places
            $('#price_display').text(price.toFixed(2) + ' kr');
        }
    }
});
