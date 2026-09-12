(function () {
    'use strict';

    function initPhonebook(wrapper) {
        if (!wrapper || wrapper.dataset.rdscoContactListReady === '1') {
            return;
        }

        const form = wrapper.querySelector('.rdsco-contact-list-form');
        const results = wrapper.querySelector('.rdsco-contact-list-results');
        const loading = wrapper.querySelector('.rdsco-contact-list-loading');
        const search = form ? form.querySelector('[name="search"]') : null;
        const zone = form ? form.querySelector('[name="zone"]') : null;
        const unit = form ? form.querySelector('[name="unit"]') : null;
        const tag = form ? form.querySelector('[name="tag_id"]') : null;
        const page = form ? form.querySelector('[name="paged"]') : null;
        const layer = wrapper.querySelector('.rdsco-contact-list-sidebar-layer');
        const sidebarContent = wrapper.querySelector('.rdsco-contact-list-sidebar-content');
        const closeButton = wrapper.querySelector('.rdsco-contact-list-sidebar-close');
        const backdrop = wrapper.querySelector('.rdsco-contact-list-sidebar-backdrop');
        const resetButton = wrapper.querySelector('.rdsco-contact-list-reset');

        if (!form || !results || !loading || !search || !zone || !unit || !tag || !page || !layer || !sidebarContent || !closeButton || !backdrop) {
            return;
        }

        wrapper.dataset.rdscoContactListReady = '1';

        let controller = null;
        let timer = null;
        let lastFocus = null;
        let activeVcardUrl = '';
        const cards = new Map();

        function renderUnits(list) {
            unit.innerHTML = '<option value="">همه واحدهای سازمانی</option>';

            Object.entries(list).forEach(function (entry) {
                const option = document.createElement('option');
                option.value = entry[0];
                option.textContent = entry[1];
                unit.appendChild(option);
            });
        }

        function loadResults(options) {
            const settings = Object.assign(
                { delay: 0, updateUnits: false, scroll: false },
                options || {}
            );

            clearTimeout(timer);
            timer = setTimeout(async function () {
                if (controller) {
                    controller.abort();
                }

                controller = new AbortController();
                const formData = new FormData(form);
                formData.set('update_units', settings.updateUnits ? '1' : '0');

                wrapper.classList.add('is-loading');
                loading.hidden = false;

                try {
                    const response = await fetch(wrapper.dataset.ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        signal: controller.signal
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error('خطا در دریافت نتایج');
                    }

                    results.innerHTML = data.data.html;

                    if (settings.updateUnits && data.data.units) {
                        renderUnits(data.data.units);
                    }

                    if (settings.scroll) {
                        window.scrollTo({
                            top: results.getBoundingClientRect().top + window.scrollY - 100,
                            behavior: 'smooth'
                        });
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        results.innerHTML = '<div class="rdsco-contact-list-empty"><p>خطا در دریافت اطلاعات.</p></div>';
                    }
                } finally {
                    wrapper.classList.remove('is-loading');
                    loading.hidden = true;
                }
            }, settings.delay);
        }

        function decodeBase64(value) {
            const binary = atob(value);
            const bytes = Uint8Array.from(binary, function (character) {
                return character.charCodeAt(0);
            });

            if (window.TextDecoder) {
                return new TextDecoder('utf-8').decode(bytes);
            }

            return decodeURIComponent(
                Array.from(bytes, function (byte) {
                    return '%' + byte.toString(16).padStart(2, '0');
                }).join('')
            );
        }

        function waitForQr(callback, attempt) {
            const currentAttempt = attempt || 0;

            if (window.RDSCOContactListQRCode) {
                callback();
                return;
            }

            if (currentAttempt > 10) {
                callback(new Error('موتور QR در مرورگر فعال نشد'));
                return;
            }

            window.setTimeout(function () {
                waitForQr(callback, currentAttempt + 1);
            }, 50);
        }

        function showQrError(section, message) {
            const box = section.querySelector('.rdsco-contact-list-qr-box');
            const error = section.querySelector('.rdsco-contact-list-qr-error');

            box.hidden = true;
            error.textContent = message || 'ساخت QR Code ممکن نشد.';
            error.hidden = false;
        }

        function drawQr(section, data) {
            const box = section.querySelector('.rdsco-contact-list-qr-box');
            const download = section.querySelector('.rdsco-contact-list-vcard-download');

            if (activeVcardUrl) {
                URL.revokeObjectURL(activeVcardUrl);
            }

            activeVcardUrl = URL.createObjectURL(
                new Blob([data.vcard], { type: 'text/vcard;charset=utf-8' })
            );

            download.href = activeVcardUrl;
            download.download = data.filename;
            download.hidden = false;

            waitForQr(function (error) {
                if (error || !window.RDSCOContactListQRCode) {
                    showQrError(section, error ? error.message : 'موتور QR یافت نشد');
                    return;
                }

                box.innerHTML = '';

                try {
                    new window.RDSCOContactListQRCode(box, {
                        text: data.vcard,
                        width: 174,
                        height: 174,
                        colorDark: '#103d38',
                        colorLight: '#ffffff',
                        correctLevel: window.RDSCOContactListQRCode.CorrectLevel.L
                    });
                    box.removeAttribute('title');
                } catch (drawError) {
                    console.error('RDSCO Contact List QR draw error:', drawError);
                    showQrError(
                        section,
                        'خطا در رسم QR: ' + (drawError.message || 'خطای نامشخص')
                    );
                }
            });
        }

        async function loadQr(contactId) {
            const section = sidebarContent.querySelector('[data-qr-contact]');
            if (!section) {
                return;
            }

            if (cards.has(contactId)) {
                drawQr(section, cards.get(contactId));
                return;
            }

            const formData = new FormData();
            formData.set('action', 'rdsco_contact_list_vcard');
            formData.set('post_id', contactId);
            formData.set('token', section.dataset.qrToken || '');
            formData.set('fields_config', section.dataset.fieldsConfig || '');
            formData.set('fields_signature', section.dataset.fieldsSignature || '');

            try {
                const response = await fetch(wrapper.dataset.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const raw = await response.text();
                let data;

                try {
                    data = JSON.parse(raw);
                } catch (parseError) {
                    throw new Error('پاسخ سرور JSON نیست؛ کد ' + response.status);
                }

                if (!response.ok || !data.success) {
                    const message = data && data.data && data.data.message
                        ? data.data.message
                        : 'خطای سرور ' + response.status;
                    throw new Error(message);
                }

                if (!data.data || !data.data.vcard) {
                    throw new Error('اطلاعات vCard دریافت نشد');
                }

                const item = {
                    vcard: decodeBase64(data.data.vcard),
                    filename: data.data.filename || 'contact.vcf'
                };

                cards.set(contactId, item);
                drawQr(section, item);
            } catch (error) {
                console.error('RDSCO Contact List vCard error:', error);
                showQrError(
                    section,
                    'خطا در دریافت اطلاعات QR: ' + (error.message || 'خطای نامشخص')
                );
            }
        }

        function closeSidebar() {
            layer.classList.remove('open');
            layer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('rdsco-contact-list-sidebar-open');

            window.setTimeout(function () {
                sidebarContent.innerHTML = '';
            }, 350);

            if (lastFocus) {
                lastFocus.focus();
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            page.value = '1';
            loadResults();
        });

        search.addEventListener('input', function () {
            page.value = '1';
            loadResults({ delay: 350 });
        });

        wrapper.querySelectorAll('.rdsco-contact-list-zone').forEach(function (button) {
            button.addEventListener('click', function () {
                wrapper.querySelectorAll('.rdsco-contact-list-zone').forEach(function (item) {
                    item.classList.remove('active');
                });

                button.classList.add('active');
                zone.value = button.dataset.zone;
                unit.value = '';
                page.value = '1';
                loadResults({ updateUnits: true });
            });
        });

        unit.addEventListener('change', function () {
            page.value = '1';
            loadResults();
        });

        wrapper.querySelectorAll('.rdsco-contact-list-tag-link').forEach(function (button) {
            button.addEventListener('click', function () {
                const wasActive = button.classList.contains('active');

                wrapper.querySelectorAll('.rdsco-contact-list-tag-link').forEach(function (item) {
                    item.classList.remove('active');
                    item.setAttribute('aria-pressed', 'false');
                });

                tag.value = wasActive ? '0' : button.dataset.tagId;

                if (!wasActive) {
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                }

                page.value = '1';
                loadResults();
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                search.value = '';
                zone.value = '';
                unit.value = '';
                tag.value = '0';
                page.value = '1';

                wrapper.querySelectorAll('.rdsco-contact-list-zone').forEach(function (item) {
                    item.classList.toggle('active', item.dataset.zone === '');
                });

                wrapper.querySelectorAll('.rdsco-contact-list-tag-link').forEach(function (item) {
                    item.classList.remove('active');
                    item.setAttribute('aria-pressed', 'false');
                });

                loadResults({ updateUnits: true });
            });
        }

        results.addEventListener('click', function (event) {
            const paginationButton = event.target.closest('.rdsco-contact-list-page-button');

            if (paginationButton && !paginationButton.disabled) {
                page.value = paginationButton.dataset.page;
                loadResults({ scroll: true });
                return;
            }

            const detailsButton = event.target.closest('.rdsco-contact-list-details-button');
            if (!detailsButton) {
                return;
            }

            const contactId = detailsButton.dataset.contactId;
            const template = results.querySelector(
                'template[data-contact-template="' + contactId + '"]'
            );

            if (!template) {
                return;
            }

            lastFocus = detailsButton;
            sidebarContent.innerHTML = '';
            sidebarContent.appendChild(template.content.cloneNode(true));
            layer.classList.add('open');
            layer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('rdsco-contact-list-sidebar-open');
            closeButton.focus();
            loadQr(contactId);
        });

        closeButton.addEventListener('click', closeSidebar);
        backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && layer.classList.contains('open')) {
                closeSidebar();
            }
        });
    }

    function boot(scope) {
        const root = scope || document;

        if (root.matches && root.matches('.rdsco-contact-list')) {
            initPhonebook(root);
        }

        root.querySelectorAll('.rdsco-contact-list').forEach(initPhonebook);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            boot(document);
        });
    } else {
        boot(document);
    }

    window.addEventListener('elementor/frontend/init', function () {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
            return;
        }

        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/rdsco-contact-list.default',
            function (scope) {
                boot(scope && scope[0] ? scope[0] : document);
            }
        );
    });
})();
