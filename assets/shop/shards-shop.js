(function () {
    'use strict';

    var root = document.querySelector('[data-shards-shop]');
    if (!root || root.getAttribute('data-shop-controller-ready') === '1') return;
    root.setAttribute('data-shop-controller-ready', '1');

    var lang = root.getAttribute('data-lang') === 'en' ? 'en' : 'it';
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // Il cambio Godos -> Shards lo decide il pannello admin (100 di base).
    var godosPerShard = Math.max(1, parseInt(root.getAttribute('data-godos-per-shard') || '100', 10) || 100);

    var copy = lang === 'en' ? {
        paypalLoading: 'Loading PayPal…',
        paypalUnavailable: 'PayPal is temporarily unavailable. Card payment is still available.',
        paypalCreateError: 'Unable to create the PayPal order.',
        paypalCaptureError: 'Unable to complete the PayPal payment.',
        insufficient: function (rate) { return 'You need at least ' + rate + ' Godos to convert them into Shards.'; },
        conversionError: 'Conversion failed. Please try again.',
        conversionSuccess: function (shards, godos) { return 'Converted ' + godos + ' Godos into ' + shards + ' Godo Shards.'; },
        processing: 'Converting…',
        confirm: 'Confirm conversion',
        purchaseProcessing: 'Processing...',
        insufficientGodos: 'Insufficient Godos points!',
        owned: 'Owned',
        left: function (n) { return 'Only ' + n + ' left!'; },
        soldOut: 'Sold out',
        missing: function (n) { return 'You need ' + n + ' more Godos'; },
        buy: 'Buy',
        ended: 'No longer on sale',
        unlocked: 'Unlocked!',
        boughtBadge: 'You have successfully purchased this badge.',
        purchaseDone: 'Purchase completed!',
        genericError: 'An error occurred.',
        pulls: function (n) {
            var multi = Math.floor(n / 10);
            var single = n % 10;
            if (multi > 0 && single > 0) return n + ' Pulls (' + multi + ' Multi + ' + single + ' Pulls)';
            if (multi > 0) return n + ' Pulls (' + multi + ' Multi)';
            return n + ' Pulls';
        },
        credited: function (n) { return 'Payment completed: +' + n + ' Godo Shards credited!'; },
        creditedGeneric: 'Payment completed! Your Shards have been credited.',
        creditSlow: 'Payment received. Your Shards will arrive within a few minutes: reload the page shortly. If they do not, <a href="/en/supporto">open a ticket</a>.',
        creditError: 'The payment was received but crediting failed. <a href="/en/supporto">Open a ticket</a> and we will fix it.'
    } : {
        paypalLoading: 'Caricamento PayPal…',
        paypalUnavailable: 'PayPal non è disponibile al momento. Il pagamento con carta resta attivo.',
        paypalCreateError: 'Impossibile creare l’ordine PayPal.',
        paypalCaptureError: 'Impossibile completare il pagamento PayPal.',
        insufficient: function (rate) { return 'Servono almeno ' + rate + ' Godos per convertirli in Shards.'; },
        conversionError: 'Conversione non riuscita. Riprova.',
        conversionSuccess: function (shards, godos) { return 'Convertiti ' + godos + ' Godos in ' + shards + ' Godo Shards.'; },
        processing: 'Conversione…',
        confirm: 'Conferma conversione',
        purchaseProcessing: 'Elaborazione...',
        insufficientGodos: 'Punti Godos insufficienti!',
        owned: 'Posseduto',
        left: function (n) { return 'Solo ' + n + ' rimasti!'; },
        soldOut: 'Esaurito',
        missing: function (n) { return 'Ti mancano ' + n + ' Godos'; },
        buy: 'Acquista',
        ended: 'Non più in vendita',
        unlocked: 'Sbloccato!',
        boughtBadge: 'Hai acquistato correttamente il badge.',
        purchaseDone: 'Acquisto completato!',
        genericError: 'Si è verificato un errore.',
        pulls: function (n) {
            var multi = Math.floor(n / 10);
            var single = n % 10;
            if (multi > 0 && single > 0) return n + ' Pull (' + multi + ' Multi + ' + single + ' Pull)';
            if (multi > 0) return n + ' Pull (' + multi + ' Multi)';
            return n + ' Pull';
        },
        credited: function (n) { return 'Pagamento completato: +' + n + ' Godo Shards accreditate!'; },
        creditedGeneric: 'Pagamento completato! Le tue Shards sono state accreditate.',
        creditSlow: 'Pagamento ricevuto. Le Shards arrivano entro pochi minuti: ricarica la pagina tra poco. Se non arrivano, <a href="/it/supporto">apri un ticket</a>.',
        creditError: 'Il pagamento è arrivato ma l’accredito non è riuscito. <a href="/it/supporto">Apri un ticket</a> e lo sistemiamo.'
    };

    var paymentModal = document.getElementById('paymentModal');
    var conversionModal = document.getElementById('godosConversionModal');
    var successModal = document.getElementById('purchaseSuccessModal');
    var confirmModal = document.getElementById('godosPurchaseConfirmModal');
    var conversionForm = document.getElementById('godos-conversion-form');
    var slider = document.getElementById('godos-slider');
    var qtyInput = document.getElementById('godos-qty');
    var sliderValue = document.getElementById('slider-shards-val');
    var sliderCost = document.getElementById('slider-godos-cost');
    var sliderPulls = document.getElementById('slider-pulls-val');
    var sliderMax = document.getElementById('slider-max-label');
    var formError = document.querySelector('[data-shop-form-error]');
    var currentPackageId = '';
    var activeModal = null;
    var lastTrigger = null;
    var userGodos = parseInt(root.getAttribute('data-user-godos') || '0', 10) || 0;
    var userShards = parseInt(root.getAttribute('data-user-shards') || '0', 10) || 0;

    var pendingPurchaseItemId = null;
    var pendingPurchaseBtn = null;

    // Come number_format() del PHP: il browser in italiano scriverebbe
    // "2500" senza punto, mentre la pagina appena caricata mostra "2.500".
    function formatNumber(value) {
        var rounded = Math.round(Number(value || 0));
        var sign = rounded < 0 ? '-' : '';
        return sign + String(Math.abs(rounded)).replace(/\B(?=(\d{3})+(?!\d))/g, lang === 'en' ? ',' : '.');
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
        var r, g, b;
        if (hex.length === 3) {
            r = parseInt(hex.substring(0, 1) + hex.substring(0, 1), 16);
            g = parseInt(hex.substring(1, 2) + hex.substring(1, 2), 16);
            b = parseInt(hex.substring(2, 3) + hex.substring(2, 3), 16);
            return [r, g, b];
        } else if (hex.length === 6) {
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
            return [r, g, b];
        }
        return null;
    }

    /* ── Indirizzo della pagina ─────────────────────────────────────── */

    // L'hash dice quale scheda e' aperta (#shards, #godos) o apre il
    // convertitore (#converti). I modali lo puliscono quando si chiudono.
    function setHash(hash) {
        if (!window.history || !window.history.replaceState) return;
        var url = window.location.pathname + window.location.search + (hash ? '#' + hash : '');
        window.history.replaceState(null, '', url);
    }

    function clearHash() {
        if (!window.location.hash) return;
        var hash = window.location.hash.replace('#', '');
        // Chiudendo un modale si resta sulla scheda in cui si era.
        if (hash === 'converti' || hash === 'godosConversionModal') {
            setHash('godos');
        } else if (hash !== 'shards' && hash !== 'godos') {
            setHash('');
        }
    }

    // I parametri del ritorno da un pagamento servono una volta sola: se
    // restassero, ricaricando la pagina riapparirebbe "pagamento completato".
    function cleanReturnParams() {
        if (!window.history || !window.history.replaceState) return;
        var params = new URLSearchParams(window.location.search);
        var changed = false;
        ['payment', 'package_id', 'session_id', 'conversion', 'shards', 'error'].forEach(function (key) {
            if (params.has(key)) {
                params.delete(key);
                changed = true;
            }
        });
        if (!changed) return;
        var query = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    }

    /* ── Modali ─────────────────────────────────────────────────────── */

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

    function hideToast(toast, delay) {
        window.setTimeout(function () {
            toast.classList.add('is-leaving');
            window.setTimeout(function () { toast.remove(); }, 350);
        }, delay);
    }

    function showToast(message, isError, options) {
        options = options || {};
        var oldToast = document.querySelector('.shop-toast.is-runtime');
        if (oldToast) oldToast.remove();
        var toast = document.createElement('div');
        toast.className = 'shop-toast is-runtime' + (isError ? ' is-error' : '');
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<i class="fa-solid ' + (isError ? 'fa-circle-xmark' : 'fa-circle-check') + '"></i><span></span>';
        // Solo i messaggi scritti qui dentro contengono link: quelli del
        // server passano sempre come testo.
        if (options.html) {
            toast.querySelector('span').innerHTML = message;
        } else {
            toast.querySelector('span').textContent = message;
        }
        document.body.appendChild(toast);
        hideToast(toast, options.duration || 4200);
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

    /* ── Pagamento (Stripe e PayPal): invariato ─────────────────────── */

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
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ package_id: currentPackageId, csrf_token: csrfToken })
                    }).then(function (data) {
                        if (!data.ok || !data.id) throw new Error(data.message || copy.paypalCreateError);
                        return data.id;
                    });
                },
                onApprove: function (data) {
                    return fetchJson('/api/capture_paypal_shard_order.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ orderID: data.orderID, package_id: currentPackageId, csrf_token: csrfToken })
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

    /* ── Convertitore Godos -> Shards ───────────────────────────────── */

    function maxConvertible() {
        return Math.floor(userGodos / godosPerShard);
    }

    function setQuantity(value) {
        if (!slider) return;
        var max = Math.max(1, parseInt(slider.max || '1', 10) || 1);
        var quantity = Math.min(max, Math.max(1, parseInt(value, 10) || 1));
        slider.value = String(quantity);
        if (qtyInput && qtyInput !== document.activeElement) qtyInput.value = String(quantity);
        if (sliderValue) sliderValue.textContent = formatNumber(quantity);
        if (sliderCost) sliderCost.textContent = formatNumber(quantity * godosPerShard);
        if (sliderPulls) sliderPulls.textContent = '= ' + copy.pulls(quantity);
        return quantity;
    }

    function updateSlider() {
        if (!slider) return;
        setQuantity(slider.value);
        if (qtyInput) qtyInput.value = slider.value;
    }

    function prepareConversion() {
        var maxBuyable = maxConvertible();
        if (maxBuyable < 1) {
            showToast(copy.insufficient(formatNumber(godosPerShard)), true);
            return false;
        }
        if (slider) {
            slider.max = String(maxBuyable);
            slider.value = String(Math.min(10, maxBuyable));
        }
        if (qtyInput) qtyInput.max = String(maxBuyable);
        if (sliderMax) sliderMax.textContent = 'Max: ' + formatNumber(maxBuyable);
        if (formError) {
            formError.hidden = true;
            formError.textContent = '';
        }
        updateSlider();
        return true;
    }

    /* ── Saldo e oggetti Godos ──────────────────────────────────────── */

    // Dopo ogni cambio di saldo i bottoni degli oggetti dicono subito se
    // l'oggetto e' alla portata ("Ti mancano X Godos") invece di scoprirlo
    // cliccando.
    function refreshAffordability() {
        document.querySelectorAll('[data-shop-buy-item]').forEach(function (button) {
            var state = button.getAttribute('data-item-state');
            if (state !== 'buy' && state !== 'short') return;
            var price = parseInt(button.getAttribute('data-item-price') || '0', 10);
            var short = price > userGodos;
            button.setAttribute('data-item-state', short ? 'short' : 'buy');
            button.classList.toggle('is-short', short);
            button.disabled = short;
            button.textContent = short ? copy.missing(formatNumber(price - userGodos)) : copy.buy;
        });
    }

    function updateBalances(data) {
        if (data.soldi_rimasti !== undefined && data.soldi_rimasti !== null) userGodos = Number(data.soldi_rimasti);
        if (data.shards_rimaste !== undefined && data.shards_rimaste !== null) userShards = Number(data.shards_rimaste);
        root.setAttribute('data-user-godos', String(userGodos));
        root.setAttribute('data-user-shards', String(userShards));
        var godosNode = document.querySelector('[data-shop-balance="godos"]');
        var shardsNode = document.querySelector('[data-shop-balance="shards"]');
        if (godosNode) godosNode.textContent = formatNumber(userGodos);
        if (shardsNode) shardsNode.textContent = formatNumber(userShards);
        refreshAffordability();
    }

    function markItem(button, state, label) {
        button.setAttribute('data-item-state', state);
        button.classList.remove('is-short', 'is-owned', 'is-soldout', 'is-soon');
        if (state !== 'buy') button.classList.add('is-' + state);
        button.disabled = state !== 'buy';
        button.textContent = label;
    }

    /* ── Schede ─────────────────────────────────────────────────────── */

    function showTab(tabButton, updateHash) {
        if (!tabButton) return;
        var currentActiveBtn = document.querySelector('.shop-tab-btn.active');
        if (updateHash !== false) setHash(tabButton.getAttribute('data-tab-hash') || '');
        if (currentActiveBtn === tabButton) return;

        document.querySelectorAll('.shop-tab-btn').forEach(function (button) {
            var active = button === tabButton;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
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

    /* ── Click ──────────────────────────────────────────────────────── */

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
            if (prepareConversion()) {
                openModal(conversionModal, convertLink);
                setHash('converti');
            }
            return;
        }

        var step = target.closest('[data-conv-step]');
        if (step && slider) {
            event.preventDefault();
            setQuantity((parseInt(slider.value, 10) || 1) + parseInt(step.getAttribute('data-conv-step'), 10));
            if (qtyInput) qtyInput.value = slider.value;
            return;
        }

        if (target.closest('[data-conv-max]') && slider) {
            event.preventDefault();
            setQuantity(slider.max);
            if (qtyInput) qtyInput.value = slider.value;
            return;
        }

        // Acquisto di un oggetto con i Godos
        var buyItemBtn = target.closest('[data-shop-buy-item]');
        if (buyItemBtn && confirmModal && successModal) {
            event.preventDefault();
            event.stopPropagation();
            if (buyItemBtn.disabled || buyItemBtn.getAttribute('data-item-state') !== 'buy') return;

            var itemId = buyItemBtn.getAttribute('data-shop-buy-item');
            var price = parseInt(buyItemBtn.getAttribute('data-item-price') || '0', 10);
            var itemName = buyItemBtn.getAttribute('data-item-name') || '';

            if (price > userGodos) {
                showToast(copy.insufficientGodos, true);
                refreshAffordability();
                return;
            }

            pendingPurchaseItemId = itemId;
            pendingPurchaseBtn = buyItemBtn;

            var confirmNameNode = document.getElementById('confirm-reveal-badge-name');
            var confirmDescNode = document.getElementById('confirm-reveal-badge-desc');
            var confirmPriceNode = document.getElementById('confirm-reveal-badge-price');
            var confirmBoxNode = document.getElementById('confirm-reveal-badge-box');

            if (confirmNameNode) confirmNameNode.textContent = itemName;

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

        var btnConfirmPurchase = target.closest('#btn-confirm-godos-purchase');
        if (btnConfirmPurchase && pendingPurchaseItemId && pendingPurchaseBtn) {
            event.preventDefault();
            event.stopPropagation();

            var originalText = btnConfirmPurchase.textContent;
            var purchasedBtn = pendingPurchaseBtn;
            var purchasedId = pendingPurchaseItemId;
            btnConfirmPurchase.disabled = true;
            btnConfirmPurchase.textContent = copy.purchaseProcessing;

            fetchJson('/api/purchase_godos_item.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ item_id: parseInt(purchasedId, 10), csrf_token: csrfToken })
            }).then(function (data) {
                if (data.status !== 'success') throw new Error(data.message);

                updateBalances({ soldi_rimasti: data.soldi_rimasti });
                markItem(purchasedBtn, 'owned', copy.owned);

                if (data.availability_left !== null && data.availability_left !== undefined) {
                    var left = Math.max(0, Number(data.availability_left));
                    var availSpan = document.querySelector('[data-item-availability="' + purchasedId + '"]');
                    if (availSpan) availSpan.textContent = left > 0 ? copy.left(left) : copy.soldOut;
                    var bar = document.querySelector('[data-item-stock="' + purchasedId + '"]');
                    if (bar) {
                        var total = Math.max(1, parseInt(bar.getAttribute('data-stock-total') || '1', 10));
                        bar.style.width = Math.round(left / total * 1000) / 10 + '%';
                    }
                }

                if (data.item_type === 'badge' && data.badge) {
                    var nameNode = document.getElementById('success-reveal-badge-name');
                    var descNode = document.getElementById('success-reveal-badge-desc');
                    var boxNode = document.getElementById('success-reveal-badge-box');
                    var titleNode = document.getElementById('success-modal-title');
                    var subtitleNode = document.getElementById('success-modal-subtitle');

                    if (titleNode) titleNode.textContent = copy.unlocked;
                    if (subtitleNode) subtitleNode.textContent = copy.boughtBadge;
                    if (nameNode) nameNode.textContent = lang === 'en' ? (data.badge.name_en || data.badge.name) : data.badge.name;
                    // L'API non manda la descrizione del badge: si usa quella
                    // dell'oggetto, gia' mostrata nella conferma (prima qui
                    // compariva "undefined").
                    var confirmDesc = document.getElementById('confirm-reveal-badge-desc');
                    if (descNode) descNode.textContent = confirmDesc ? confirmDesc.textContent : '';

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
                openModal(successModal, purchasedBtn);
                showToast(copy.purchaseDone, false);
            }).catch(function (error) {
                showToast(error.message || copy.genericError, true);
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
            showTab(tabButton, true);
        }
    }, true);

    document.addEventListener('input', function (event) {
        if (event.target === slider) updateSlider();
        if (event.target === qtyInput && qtyInput.value !== '') setQuantity(qtyInput.value);
    }, true);

    if (qtyInput) {
        qtyInput.addEventListener('change', function () {
            qtyInput.value = String(setQuantity(qtyInput.value));
        });
    }

    if (conversionForm && window.fetch) {
        conversionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var quantity = Math.max(1, parseInt(slider ? slider.value : '1', 10) || 1);
            var button = document.getElementById('btn-confirm-godos-buy');
            if (quantity * godosPerShard > userGodos) {
                if (formError) { formError.textContent = copy.insufficient(formatNumber(godosPerShard)); formError.hidden = false; }
                return;
            }
            if (button) { button.disabled = true; button.textContent = copy.processing; }
            if (formError) formError.hidden = true;

            fetchJson(conversionForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ shards: quantity, csrf_token: csrfToken })
            }).then(function (data) {
                if (data.status !== 'success') throw new Error(data.message || copy.conversionError);
                updateBalances(data);
                closeModal(conversionModal);
                showToast(copy.conversionSuccess(quantity, formatNumber(data.costo_punti)), false);
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

    /* ── Conti alla rovescia degli oggetti a tempo ──────────────────── */

    var countdowns = Array.prototype.slice.call(document.querySelectorAll('[data-countdown]'));
    if (countdowns.length) {
        var units = lang === 'en' ? ['d', 'h', 'm', 's'] : ['g', 'h', 'm', 's'];
        var formatLeft = function (ms) {
            var total = Math.max(0, Math.floor(ms / 1000));
            var parts = [Math.floor(total / 86400), Math.floor(total / 3600) % 24, Math.floor(total / 60) % 60, total % 60];
            var start = 0;
            while (start < 2 && parts[start] === 0) start++;
            return parts.slice(start, start + 2).map(function (value, index) {
                return (index > 0 ? String(value).padStart(2, '0') : value) + units[start + index];
            }).join(' ');
        };
        var tick = function () {
            countdowns.forEach(function (node) {
                var target = Date.parse(node.getAttribute('data-countdown') || '');
                var output = node.querySelector('[data-countdown-output]');
                if (!output || isNaN(target)) return;
                var left = target - Date.now();
                output.textContent = formatLeft(left);
                // Finito il tempo, l'oggetto non si compra piu' (il server
                // lo rifiuterebbe comunque).
                if (left <= 0 && !node.getAttribute('data-ended')) {
                    node.setAttribute('data-ended', '1');
                    var card = node.closest('.shop-card');
                    var button = card ? card.querySelector('[data-shop-buy-item]') : null;
                    if (button && button.getAttribute('data-item-state') !== 'owned') {
                        markItem(button, 'soldout', copy.ended);
                    }
                }
            });
        };
        tick();
        window.setInterval(tick, 1000);
    }

    /* ── Ritorno da Stripe: attesa dell'accredito ───────────────────── */

    // Stripe rimanda qui appena si paga, ma le Shards le accredita il webhook,
    // che puo' arrivare qualche secondo dopo. La pagina chiede lo stato
    // dell'ordine finche' non risulta pagato, poi aggiorna il saldo.
    var pendingSession = root.getAttribute('data-pending-session') || '';
    if (pendingSession && window.fetch) {
        var pendingToast = document.getElementById('payment-toast');
        var attempts = 0;
        var finish = function (message, isError) {
            if (pendingToast) pendingToast.remove();
            showToast(message, isError, { html: true, duration: isError ? 12000 : 6000 });
        };
        var poll = function () {
            attempts++;
            fetchJson('/api/shop/order_status.php?session_id=' + encodeURIComponent(pendingSession), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (data) {
                if (data.stato === 'pagato') {
                    updateBalances({ soldi_rimasti: data.soldi, shards_rimaste: data.shards });
                    finish(data.shards_accreditate ? copy.credited(formatNumber(data.shards_accreditate)) : copy.creditedGeneric, false);
                    return;
                }
                if (data.stato === 'errore') {
                    finish(copy.creditError, true);
                    return;
                }
                if (attempts >= 20) {
                    finish(copy.creditSlow, false);
                    return;
                }
                window.setTimeout(poll, 2000);
            }).catch(function () {
                if (attempts >= 20) {
                    finish(copy.creditSlow, false);
                } else {
                    window.setTimeout(poll, 3000);
                }
            });
        };
        window.setTimeout(poll, 1200);
    }

    document.querySelectorAll('#payment-toast:not([data-toast-persist]), #conversion-toast').forEach(function (toast) {
        hideToast(toast, 5000);
    });

    cleanReturnParams();

    // Un link interno alla pagina (#godos, #converti) cambia solo l'hash
    // senza ricaricare: lo si applica comunque.
    window.addEventListener('hashchange', function () {
        var hash = window.location.hash.replace('#', '');
        if (hash === 'godos' || hash === 'converti' || hash === 'godosConversionModal') {
            showTab(document.querySelector('.shop-tab-btn[data-tab-hash="godos"]'), false);
        } else if (hash === 'shards') {
            showTab(document.querySelector('.shop-tab-btn[data-tab-hash="shards"]'), false);
        }
        if ((hash === 'converti' || hash === 'godosConversionModal') && !activeModal && prepareConversion()) {
            openModal(conversionModal, document.querySelector('[data-shop-convert]'));
        }
    });

    // Scheda iniziale: dall'hash (#godos, #converti) o la prima.
    var initialHash = window.location.hash.replace('#', '');
    var initialTab = null;
    if (initialHash === 'godos' || initialHash === 'converti' || initialHash === 'godosConversionModal') {
        initialTab = document.querySelector('.shop-tab-btn[data-tab-hash="godos"]');
    }

    if (initialTab && !initialTab.classList.contains('active')) {
        document.querySelectorAll('.shop-tab-btn').forEach(function (button) {
            var active = button === initialTab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.shop-tab-content').forEach(function (content) {
            content.classList.toggle('active', content.id === initialTab.getAttribute('data-tab'));
        });
    }

    var initialActive = document.querySelector('.shop-tab-content.active');
    if (initialActive) {
        window.requestAnimationFrame(function () {
            initialActive.offsetHeight;
            initialActive.classList.add('show');
        });
    }

    if ((initialHash === 'converti' || initialHash === 'godosConversionModal') && prepareConversion()) {
        openModal(conversionModal, document.querySelector('[data-shop-convert]'));
    }
}());
