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
    var successModal = document.getElementById('purchaseSuccessModal');
    var confirmModal = document.getElementById('godosPurchaseConfirmModal');
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

    var pendingPurchaseItemId = null;
    var pendingPurchaseBtn = null;

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

    function hexToRgb(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            var r = parseInt(hex.substring(0, 1) + hex.substring(0, 1), 16);
            var g = parseInt(hex.substring(1, 2) + hex.substring(1, 2), 16);
            var b = parseInt(hex.substring(2, 3) + hex.substring(2, 3), 16);
            return [r, g, b];
        } else if (hex.length === 6) {
            var r = parseInt(hex.substring(0, 2), 16);
            var g = parseInt(hex.substring(2, 4), 16);
            var b = parseInt(hex.substring(4, 6), 16);
            return [r, g, b];
        }
        return null;
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

        // Custom Godos Item Purchase handler
        var buyItemBtn = target.closest('[data-shop-buy-item]');
        if (buyItemBtn && confirmModal && successModal) {
            event.preventDefault();
            event.stopPropagation();
            var itemId = buyItemBtn.getAttribute('data-shop-buy-item');
            var price = parseInt(buyItemBtn.getAttribute('data-item-price') || '0', 10);
            var itemName = buyItemBtn.getAttribute('data-item-name') || '';

            if (price > userGodos) {
                showToast(lang === 'en' ? 'Insufficient Godos points!' : 'Punti Godos insufficienti!', true);
                return;
            }

            pendingPurchaseItemId = itemId;
            pendingPurchaseBtn = buyItemBtn;

            // Populate Confirm Modal
            var confirmNameNode = document.getElementById('confirm-reveal-badge-name');
            var confirmDescNode = document.getElementById('confirm-reveal-badge-desc');
            var confirmPriceNode = document.getElementById('confirm-reveal-badge-price');
            var confirmBoxNode = document.getElementById('confirm-reveal-badge-box');

            if (confirmNameNode) confirmNameNode.textContent = itemName;
            
            // Find card description
            var cardElement = buyItemBtn.closest('.shop-card');
            var cardDescText = '';
            var cardImgSrc = '';
            if (cardElement) {
                var descElement = cardElement.querySelector('.card-amount-bonus-note');
                if (descElement) cardDescText = descElement.textContent;
                var imgElement = cardElement.querySelector('.shop-badge-preview img');
                if (imgElement) cardImgSrc = imgElement.src;
            }
            if (confirmDescNode) confirmDescNode.textContent = cardDescText;
            if (confirmPriceNode) confirmPriceNode.textContent = formatNumber(price);
            if (confirmBoxNode) {
                confirmBoxNode.innerHTML = '';
                if (cardImgSrc) {
                    var img = document.createElement('img');
                    img.src = cardImgSrc;
                    img.alt = itemName;
                    confirmBoxNode.appendChild(img);
                }
            }

            openModal(confirmModal, buyItemBtn);
            return;
        }

        // Click on Confirm button inside the confirmation modal
        var btnConfirmPurchase = target.closest('#btn-confirm-godos-purchase');
        if (btnConfirmPurchase && pendingPurchaseItemId && pendingPurchaseBtn) {
            event.preventDefault();
            event.stopPropagation();

            var originalText = btnConfirmPurchase.textContent;
            btnConfirmPurchase.disabled = true;
            btnConfirmPurchase.textContent = lang === 'en' ? 'Processing...' : 'Elaborazione...';

            fetchJson('/api/purchase_godos_item.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ item_id: parseInt(pendingPurchaseItemId, 10) })
            }).then(function (data) {
                if (data.status !== 'success') throw new Error(data.message);

                // Update balances
                updateBalances({ soldi_rimasti: data.soldi_rimasti, shards_rimaste: userShards });

                // Set owned state on the shop list button
                pendingPurchaseBtn.disabled = true;
                pendingPurchaseBtn.textContent = lang === 'en' ? 'Owned' : 'Posseduto';
                pendingPurchaseBtn.style.background = 'rgba(255, 255, 255, 0.08)';
                pendingPurchaseBtn.style.color = 'rgba(255, 255, 255, 0.3)';
                pendingPurchaseBtn.style.border = '1px solid rgba(255, 255, 255, 0.05)';

                // Update availability
                if (data.availability_left !== null) {
                    var availSpan = document.querySelector('[data-item-availability="' + pendingPurchaseItemId + '"]');
                    if (availSpan) {
                        availSpan.textContent = lang === 'en' 
                            ? 'Only ' + data.availability_left + ' left!' 
                            : 'Solo ' + data.availability_left + ' rimasti!';
                    }
                }

                // Render success reveal modal details
                if (data.item_type === 'badge' && data.badge) {
                    var nameNode = document.getElementById('success-reveal-badge-name');
                    var descNode = document.getElementById('success-reveal-badge-desc');
                    var boxNode = document.getElementById('success-reveal-badge-box');
                    var titleNode = document.getElementById('success-modal-title');
                    var subtitleNode = document.getElementById('success-modal-subtitle');
                    
                    if (titleNode) titleNode.textContent = lang === 'en' ? 'Unlocked!' : 'Sbloccato!';
                    if (subtitleNode) subtitleNode.textContent = lang === 'en' ? 'You have successfully purchased this badge.' : 'Hai acquistato correttamente il badge.';

                    if (nameNode) nameNode.textContent = lang === 'en' ? (data.badge.name_en || data.badge.name) : data.badge.name;
                    var description = lang === 'en' ? (data.badge.description_en || data.badge.descrizione) : data.badge.descrizione;
                    if (descNode) descNode.textContent = description;

                    if (boxNode) {
                        boxNode.innerHTML = '';
                        var img = document.createElement('img');
                        img.src = data.badge.image_url;
                        img.alt = data.badge.name;
                        boxNode.appendChild(img);
                        
                        boxNode.style.borderColor = data.badge.color || '#fbbf24';
                        if (data.badge.color) {
                            var rgb = hexToRgb(data.badge.color);
                            if (rgb) {
                                boxNode.style.boxShadow = '0 0 25px rgba(' + rgb.join(',') + ', 0.35)';
                            }
                        }
                    }
                }

                closeModal(confirmModal, false);
                openModal(successModal, pendingPurchaseBtn);
                showToast(lang === 'en' ? 'Purchase completed!' : 'Acquisto completato!', false);
                
            }).catch(function (error) {
                showToast(error.message || (lang === 'en' ? 'An error occurred.' : 'Si è verificato un errore.'), true);
                closeModal(confirmModal, true);
            }).finally(function () {
                btnConfirmPurchase.disabled = false;
                btnConfirmPurchase.textContent = originalText;
                pendingPurchaseItemId = null;
                pendingPurchaseBtn = null;
            });
            return;
        }

        var close = target.closest('[data-shop-close]');
        if (close) {
            event.preventDefault();
            event.stopPropagation();
            closeModal(close.closest('.shop-modal') || close.closest('.shop-action-modal'));
            return;
        }

        var tabButton = target.closest('.shop-tab-btn[data-tab]');
        if (tabButton) {
            event.preventDefault();
            var currentActiveBtn = document.querySelector('.shop-tab-btn.active');
            if (currentActiveBtn === tabButton) return;

            document.querySelectorAll('.shop-tab-btn').forEach(function (button) {
                button.classList.toggle('active', button === tabButton);
            });

            var targetTabId = tabButton.getAttribute('data-tab');
            var newContent = document.getElementById(targetTabId);
            var activeContent = document.querySelector('.shop-tab-content.show');

            if (activeContent) {
                activeContent.classList.remove('show');
                window.setTimeout(function () {
                    activeContent.classList.remove('active');
                    if (newContent) {
                        newContent.classList.add('active');
                        newContent.offsetHeight;
                        newContent.classList.add('show');
                    }
                }, 300);
            } else {
                document.querySelectorAll('.shop-tab-content').forEach(function (content) {
                    content.classList.remove('active', 'show');
                });
                if (newContent) {
                    newContent.classList.add('active');
                    newContent.offsetHeight;
                    newContent.classList.add('show');
                }
            }
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

    // Initialize active tab animation on load
    var initialActive = document.querySelector('.shop-tab-content.active');
    if (initialActive) {
        window.requestAnimationFrame(function () {
            initialActive.offsetHeight;
            initialActive.classList.add('show');
        });
    }

    if (window.location.hash === '#godosConversionModal' && prepareConversion()) {
        openModal(conversionModal, document.querySelector('[data-shop-convert]'));
    }
}());
