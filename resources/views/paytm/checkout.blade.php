<script src="https://secure.paytmpayments.com/merchantpgpui/checkoutjs/merchants/{{ env('PAYTM_MID') }}.js"></script>

<script>
window.addEventListener("load", function () {

    const config = {
        root: "",
        flow: "DEFAULT",
        data: {
            orderId: "{{ $order->order_no }}",
            token: "{{ $order->txn_token }}",
            tokenType: "TXN_TOKEN",
            amount: "{{ $order->bill_amount }}"
        },
        handler: {
            notifyMerchant: function (eventName, data) {
                console.log("Paytm event:", eventName, data);
            }
        }
    };

    window.Paytm.CheckoutJS.load(config).then(function (checkoutJsInstance) {
        checkoutJsInstance.invoke();
    }).catch(function (err) {
        console.error("Paytm load failed:", err);
    });

});
</script>