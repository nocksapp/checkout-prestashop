<section>
    <p>{l s='Select your bank' mod='nockscheckout'}
    <select class="form-control form-control-select" id="nocks_ideal_issuer" onchange="onIssuerChange()">
        {foreach $issuers as $key => $value}
            <option value="{$key}">
                {$value}
            </option>
        {/foreach}
    </select>
    </p>
</section>

<script type="text/javascript">
    function onIssuerChange() {
        document.getElementsByName('nocks_ideal_issuer')[0].value = document.getElementById('nocks_ideal_issuer').value;
    }
</script>