console.log('ZENKIPAY');
var element = document.getElementById('woocommerce_zenkipay_sync_code');
var maskOptions = {
    mask: 'a\\-******',
    lazy: false, // make placeholder always visible
    placeholderChar: 'X', // defaults to '_'
    prepare: function (str) {
        return str.toUpperCase();
    },
};
var mask = IMask(element, maskOptions);
