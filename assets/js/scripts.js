jQuery(document).ready(function ($) {
    const headingHtml = `
        <div class="shirt-size-header">
            <h3>Shirt Sizes</h3>
            <p class="shirt-size-description">
                Please select the correct shirt size for each item in your order.
            </p>
        </div>
    `;

    $('#order-fields .wc-block-components-checkout-step__content')
        .first()
        .before(headingHtml);
});

jQuery(document).ready(function($) {
    $(document).on('change', '#order-fields select', function() {
        var selectedSize = $(this).val();
        const $orderBtn = $('.wc-block-components-checkout-place-order-button');

        $orderBtn.prop('disabled', true).css('opacity', '0.5');

        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'add_shirt_size_to_order',
                label: $(this).prev('label').text(),
                uid: $(this).attr('id'),
                size: selectedSize,
            },
            success: function(response) {
                $( document.body ).trigger('wc_fragment_refresh');
                $( document.body ).trigger('update_checkout');
                console.log(response);
            },
            complete: function() {
                $orderBtn.prop('disabled', false).css('opacity', '1');
                console.log('complete');
            }
        });
    });
});