(function () {
    'use strict';

    var root = document.querySelector('[data-shards-shop]');
    if (!root || root.getAttribute('data-shop-controller-ready') === '1') return;
    root.setAttribute('data-shop-controller-ready', '1');

    var lang = root.getAttribute('data-lang') === 'en' ? 'en' : 'it';
    var copy = lang === 'en' ? {
        paypalLoading: 'Loading PayPal…',
        paypalUnavailable: 'PayPal is temporarily unavailable. Card payment is still available.',
        paypalCreateError: 'Unable to create the PayPal order.',
        paypalCaptureError: 'Unable to complete the PayPal payment.',
        insufficient: 'You need at least 100 Godos to convert them into Shards.',
        conversionError: 'Conversion failed. Please try again.',
        conversionSuccess: function (shards, godos) { return 'Converted ' + godos + ' Godos into ' + shards + ' Godo Shards.'; },
        processing: 'Converting…',
        confirm: 'Confirm conversion'
    } : {
        paypalLoading: 'Caricamento PayPal…',
        paypalUnavailable: 'PayPal non è disponibile al momento. Il pagamento con carta resta attivo.',
        paypalCreateError: 'Impossibile creare l’ordine PayPal.',
        paypalCaptureError: 'Impossibile completare il pagamento PayPal.',
        insufficient: 'Servono almeno 100 Godos per convertirli in Shards.',
        conversionError: 'Conversione non riuscita. Riprova.',
        conversionSuccess: function (shards, godos) { return 'Convertiti ' + godos + ' Godos in ' + shards + ' Godo Shards.'; },
        processing: 'Conversione…',
        confirm: 'Conferma conversione'
    };

    var paymentModal = document.getElementById('paymentModal');
    var conversionModal = document.getElementById('godosConversionModal');
    var conversionForm = document.getElementById('godos-conversion-form');
    var slider = document.getElementById('godos-slider');
    var sliderValue = document.getElementById('slider-shards-val');
    var sliderCost = document.getElementById('slider-godos-cost');
    var sliderMax = document.getElementById('slider-max-label');
    var formError = document.querySelector('[data-shop-form-error]');
    var currentPackageId = '';
    var activeModal = null;
    var lastTrigger = null;
    var userGodos = parseInt(root.getAttribute('data-user-godos') || '0', 10) || 0;
    var userShards = parseInt(root.getAttribute('data-user-shards') || '0', 10) || 0;

    function formatNumber(value) {
        return Number(value || 0).toLocaleString(lang === 'en' ? 'en-US' : 'it-IT');
    }

    function formatPrice(value) {
        var amount = Number(value || 0);
        return amount.toLocaleString(lang === 'en' ? 'en-IE' : 'it-IT', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2
        });
    }

    function clearHash() {
        if (!window.location.hash) return;
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    function openModal(modal, trigger) {
        if (!modal) return false;
        if (activeModal && activeModal !== modal) closeModal(activeModal, false);
        lastTrigger = trigger || document.activeElement;
        activeModal = modal;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('shop-modal-open');
        var panel = modal.querySelector('.shop-action-modal__panel');
        if (panel) {
            window.requestAnimationFrame(function () {
                try { panel.focus({ preventScroll: true }); } catch (error) { panel.focus(); }
            });
        }
        return true;
    }

    function closeModal(modal, restoreFocus) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (activeModal === modal) activeModal = null;
        if (!document.querySelector('.shop-action-modal.is-open')) {
            document.body.classList.remove('shop-modal-open');
        }
        clearHash();
        if (restoreFocus !== false && lastTrigger && typeof lastTrigger.focus === 'function') {
            try { lastTrigger.focus({ preventScroll: true }); } catch (error) { lastTrigger.focus(); }
        }
    }

    function showToast(message, isError) {
        var oldToast = document.querySelector('.shop-toast.is-runtime');
        if (oldToast) oldToast.remove();
        var toast = document.createElement('div');
        toast.className = 'shop-toast is-runtime' + (isError ? ' is-error' : '');
        toast.innerHTML = '<i class="fa-solid ' + (isError ? 'fa-circle-xmark' : 'fa-circle-check') + '"></i><span></span>';
        toast.querySelector('span').textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function () {
            toast.classList.add('is-leaving');
            window.setTimeout(function () { toast.remove(); }, 350);
        }, 4200);
    }

    function setPaymentStatus(message) {
        var container = document.getElementById('paypal-button-container');
        if (!container) return;
        container.innerHTML = '';
        var status = document.createElement('p');
        status.className = 'shop-payment-status';
        status.textContent = message;
        container.appendChild(status);
    }

    function fetchJson(url, options) {
        return window.fetch(url, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (payload) {
                if (!response.ok) throw new Error(payload.message || copy.conversionError);
                return payload;
            });
        });
    }

    function renderPayPal() {
        var container = document.getElementById('paypal-button-container');
        if (!container) return;
        setPaymentStatus(copy.paypalLoading);

        if (!window.paypal || typeof window.paypal.Buttons !== 'function') {
            setPaymentStatus(copy.paypalUnavailable);
            return;
        }

        try {
            container.innerHTML = '';
            var buttons = window.paypal.Buttons({
                style: { layout: 'vertical', shape: 'rect', label: 'paypal', height: 46 },
                createOrder: function () {
                    return fetchJson('/api/create_paypal_shard_order.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ package_id: currentPackageId })
                    }).then(function (data) {
                        if (!data.ok || !data.id) throw new Error(data.message || copy.paypalCreateError);
                        return data.id;
                    });
                },
                onApprove: function (data) {
                    return fetchJson('/api/capture_paypal_shard_order.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ orderID: data.orderID, package_id: currentPackageId })
                    }).then(function (details) {
                        if (!details.ok) throw new Error(details.message || copy.paypalCaptureError);
                        window.location.assign('/' + lang + '/shop.php?payment=success&package_id=' + encodeURIComponent(currentPackageId));
                    });
                },
                onError: function () {
                    setPaymentStatus(copy.paypalUnavailable);
                }
            });
            var rendered = buttons.render(container);
            if (rendered && typeof rendered.catch === 'function') {
                rendered.catch(function () { setPaymentStatus(copy.paypalUnavailable); });
            }
        } catch (error) {
            setPaymentStatus(copy.paypalUnavailable);
        }
    }

    function preparePayment(link) {
        currentPackageId = link.getAttribute('data-package-id') || '';
        var name = link.getAttribute('data-package-name') || 'Godo Shards';
        var price = link.getAttribute('data-package-price') || '0';
        var nameNode = document.getElementById('modal-pkg-name');
        var priceNode = document.getElementById('modal-pkg-price');
        var stripeLink = document.getElementById('stripe-checkout-btn');
        if (nameNode) nameNode.textContent = name;
        if (priceNode) priceNode.textContent = formatPrice(price);
        if (stripeLink) stripeLink.href = link.href;
    }

    function updateSlider() {
        if (!slider) return;
        var quantity = Math.max(1, parseInt(slider.value || '1', 10) || 1);
        if (sliderValue) sliderValue.textContent = formatNumber(quantity);
        if (sliderCost) sliderCost.textContent = formatNumber(quantity * 100);
    }

    function prepareConversion() {
        var maxBuyable = Math.floor(userGodos / 100);
        if (maxBuyable < 1) {
            showToast(copy.insufficient, true);
            return false;
        }
        if (slider) {
            slider.max = String(maxBuyable);
            slider.value = String(Math.min(10, maxBuyable));
        }
        if (sliderMax) sliderMax.textContent = 'Max: ' + formatNumber(maxBuyable);
        if (formError) {
            formError.hidden = true;
            formError.textContent = '';
        }
        updateSlider();
        return true;
    }

    function updateBalances(data) {
        userGodos = Number(data.soldi_rimasti || 0);
        userShards = Number(data.shards_rimaste || 0);
        root.setAttribute('data-user-godos', String(userGodos));
        root.setAttribute('data-user-shards', String(userShards));
        var godosNode = document.querySelector('[data-shop-balance="godos"]');
        var shardsNode = document.querySelector('[data-shop-balance="shards"]');
        if (godosNode) godosNode.textContent = formatNumber(userGodos);
        if (shardsNode) shardsNode.textContent = formatNumber(userShards);
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        var buyLink = target.closest('[data-shop-buy]');
        if (buyLink && paymentModal) {
            event.preventDefault();
            event.stopPropagation();
            preparePayment(buyLink);
            openModal(paymentModal, buyLink);
            renderPayPal();
            return;
        }

        var convertLink = target.closest('[data-shop-convert]');
        if (convertLink && conversionModal) {
            event.preventDefault();
            event.stopPropagation();
            if (prepareConversion()) openModal(conversionModal, convertLink);
            return;
        }

        var close = target.closest('[data-shop-close]');
        if (close) {
            event.preventDefault();
            event.stopPropagation();
            closeModal(close.closest('.shop-action-modal'));
            return;
        }

        var tabButton = target.closest('.shop-tab-btn[data-tab]');
        if (tabButton) {
            event.preventDefault();
            document.querySelectorAll('.shop-tab-btn').forEach(function (button) {
                button.classList.toggle('active', button === tabButton);
            });
            document.querySelectorAll('.shop-tab-content').forEach(function (content) {
                content.classList.toggle('active', content.id === tabButton.getAttribute('data-tab'));
            });
        }
    }, true);

    document.addEventListener('input', function (event) {
        if (event.target === slider) updateSlider();
    }, true);

    if (conversionForm && window.fetch) {
        conversionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var quantity = Math.max(1, parseInt(slider ? slider.value : '1', 10) || 1);
            var button = document.getElementById('btn-confirm-godos-buy');
            if (quantity * 100 > userGodos) {
                if (formError) { formError.textContent = copy.insufficient; formError.hidden = false; }
                return;
            }
            if (button) { button.disabled = true; button.textContent = copy.processing; }
            if (formError) formError.hidden = true;

            fetchJson(conversionForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ shards: quantity })
            }).then(function (data) {
                if (data.status !== 'success') throw new Error(data.message || copy.conversionError);
                updateBalances(data);
                closeModal(conversionModal);
                showToast(copy.conversionSuccess(quantity, data.costo_punti), false);
            }).catch(function (error) {
                if (formError) { formError.textContent = error.message || copy.conversionError; formError.hidden = false; }
            }).finally(function () {
                if (button) { button.disabled = false; button.textContent = copy.confirm; }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeModal) closeModal(activeModal);
    });

    document.querySelectorAll('#payment-toast, #conversion-toast').forEach(function (toast) {
        window.setTimeout(function () {
            toast.classList.add('is-leaving');
            window.setTimeout(function () { toast.remove(); }, 350);
        }, 5000);
    });

    if (window.location.hash === '#godosConversionModal' && prepareConversion()) {
        openModal(conversionModal, document.querySelector('[data-shop-convert]'));
    }
}());
