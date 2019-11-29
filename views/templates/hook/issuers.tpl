<section>
    <p>
        <select class="form-control form-control-select" id="nocks_ideal_issuer" onchange="onIssuerChange()">
            <option value="">{l s='Select your bank' mod='nockscheckout'}</option>
            {foreach $issuers as $key => $value}
                <option value="{$key}">
                    {$value}
                </option>
            {/foreach}
        </select>
    </p>
</section>

<script type="text/javascript">
    var paymentConfirmation;
    var paymentConfirmationClone;
    var isIdeal = false;

    function docReady(fn) {
        // see if DOM is already available
        if (document.readyState === "complete" || document.readyState === "interactive") {
            // call on next available tick
            setTimeout(fn, 1);
        } else {
            document.addEventListener("DOMContentLoaded", fn);
        }
    }

    docReady(function() {
        var listener = function(e) {
            var form = document.getElementById('pay-with-' + e.target.id + '-form');
            if (form) {
                var method = form.querySelector('input[name="method"]');
                isIdeal = method && method.value === 'IDEAL'
            }

            handleIdealButton();
        };

        document.querySelectorAll('input[name="payment-option"]').forEach((paymentOptionInput) => {
            paymentOptionInput.onchange = listener;
        });

        // Add a payment confirmation clone so we can disable it when there is no ideal issuer selected
        paymentConfirmation = document.getElementById('payment-confirmation');
        paymentConfirmationClone = paymentConfirmation.cloneNode(true);
        paymentConfirmationClone.id = 'payment-confirmation-clone';
        paymentConfirmationClone.style.display = 'none';
        paymentConfirmation.parentNode.appendChild(paymentConfirmationClone);
    });

    function onIssuerChange() {
        document.getElementsByName('nocks_ideal_issuer')[0].value = document.getElementById('nocks_ideal_issuer').value;
        handleIdealButton();
    }

    function handleIdealButton() {
        var issuer = document.getElementsByName('nocks_ideal_issuer')[0].value;

        if (isIdeal && issuer === '') {
            // Hide original button and show clone
            paymentConfirmation.setAttribute('style', 'visibility: hidden; height: 0;');
            paymentConfirmationClone.style.display = 'block';
        } else {
            // Hide clone and show original
            paymentConfirmation.setAttribute('style', 'visibility: visible; height: auto;');
            paymentConfirmationClone.style.display = 'none';
        }
    }
</script>