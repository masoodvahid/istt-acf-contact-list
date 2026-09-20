(function () {
    'use strict';

    const SELECTOR = '.rdsco-search-box';

    function initSearchBox(wrapper) {
        if (!wrapper || wrapper.dataset.rdscoInitialized === '1') {
            return;
        }

        wrapper.dataset.rdscoInitialized = '1';

        const form = wrapper.querySelector('.rdsco-search-form');
        const input = wrapper.querySelector('.rdsco-search-input');
        const category = wrapper.querySelector('.rdsco-search-category');
        const dateFrom = wrapper.querySelector('.rdsco-search-date-from');
        const dateTo = wrapper.querySelector('.rdsco-search-date-to');
        const reset = wrapper.querySelector('.rdsco-search-reset');
        const results = wrapper.querySelector('.rdsco-search-results');
        const pagination = wrapper.querySelector('.rdsco-search-pagination-wrap');
        const count = wrapper.querySelector('.rdsco-search-count');
        const help = wrapper.querySelector('.rdsco-search-help');
        const liveSearch = wrapper.dataset.liveSearch === '1';
        const delay = Math.max(300, parseInt(wrapper.dataset.delay || '1000', 10));
        const minimum = Math.max(3, parseInt(wrapper.dataset.minimum || '3', 10));

        let settings = {};

        try {
            settings = JSON.parse(wrapper.dataset.settings || '{}');
        } catch (error) {
            settings = {};
        }

        const state = {
            timer: 0,
            controller: null,
            page: 1
        };

        function fieldValue(field) {
            return field ? field.value.trim() : '';
        }

        function hasFilters() {
            return (category && category.value !== '0') || fieldValue(dateFrom) || fieldValue(dateTo);
        }

        function setHelp(message, isError) {
            if (!help) {
                return;
            }

            help.textContent = message || '';
            help.classList.toggle('is-error', Boolean(isError));
        }

        function setLoading(isLoading) {
            wrapper.classList.toggle('is-loading', isLoading);
            wrapper.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }

        function validateDates() {
            const from = fieldValue(dateFrom);
            const to = fieldValue(dateTo);

            if (from && to && from > to) {
                setHelp('تاریخ شروع باید پیش از تاریخ پایان باشد.', true);
                return false;
            }

            return true;
        }

        function searchableValue() {
            const value = fieldValue(input);
            return value.length >= minimum ? value : '';
        }

        function validateSearch(showMessage) {
            const value = fieldValue(input);

            if (value && value.length < minimum) {
                if (hasFilters()) {
                    if (showMessage) {
                        setHelp('عبارت کوتاه نادیده گرفته شد و فیلترها اعمال شدند.', false);
                    }
                    return true;
                }

                if (showMessage) {
                    setHelp('برای جستجو حداقل ' + minimum.toLocaleString('fa-IR') + ' حرف وارد کنید.', true);
                }
                return false;
            }

            return true;
        }

        async function fetchResults(page, scrollToResults) {
            if (!results || !window.RDSCOSearchBox || !window.RDSCOSearchBox.ajaxUrl) {
                return;
            }

            if (!validateSearch(true) || !validateDates()) {
                return;
            }

            window.clearTimeout(state.timer);

            if (state.controller) {
                state.controller.abort();
            }

            const controller = new AbortController();
            state.controller = controller;
            setHelp('', false);
            setLoading(true);

            const body = new FormData();
            body.append('action', 'rdsco_search_box_fetch');
            body.append('nonce', wrapper.dataset.nonce || '');
            body.append('signature', wrapper.dataset.signature || '');
            body.append('settings', JSON.stringify(settings));
            body.append('search', searchableValue());
            body.append('category_id', category ? category.value : '0');
            body.append('date_from', fieldValue(dateFrom));
            body.append('date_to', fieldValue(dateTo));
            body.append('page', String(page || 1));

            try {
                const response = await fetch(window.RDSCOSearchBox.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body,
                    signal: controller.signal
                });
                const payload = await response.json();

                if (!payload.success || !payload.data) {
                    const message = payload.data && payload.data.message
                        ? payload.data.message
                        : 'انجام جستجو با خطا روبه‌رو شد.';
                    setHelp(message, true);
                    return;
                }

                results.innerHTML = payload.data.html || '';

                if (pagination) {
                    pagination.innerHTML = payload.data.pagination || '';
                }

                if (count) {
                    const total = parseInt(payload.data.total || '0', 10);
                    const number = count.querySelector('strong');
                    if (number) {
                        number.textContent = total.toLocaleString('fa-IR');
                    }
                    count.hidden = payload.data.showCount === false;
                }

                state.page = parseInt(payload.data.page || page || '1', 10);

                if (scrollToResults) {
                    const top = wrapper.getBoundingClientRect().top + window.pageYOffset - 90;
                    window.scrollTo({top: top, behavior: 'smooth'});
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setHelp('ارتباط با سرور برقرار نشد. دوباره تلاش کنید.', true);
                }
            } finally {
                if (state.controller === controller) {
                    state.controller = null;
                    setLoading(false);
                }
            }
        }

        function scheduleLiveSearch() {
            if (!liveSearch) {
                return;
            }

            window.clearTimeout(state.timer);
            const value = fieldValue(input);

            if (value && value.length < minimum) {
                setHelp('برای جستجوی زنده حداقل ' + minimum.toLocaleString('fa-IR') + ' حرف وارد کنید.', false);
                return;
            }

            setHelp('', false);
            state.timer = window.setTimeout(function () {
                fetchResults(1, false);
            }, delay);
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                fetchResults(1, false);
            });
        }

        if (input && liveSearch) {
            input.addEventListener('input', scheduleLiveSearch);
        }

        [category, dateFrom, dateTo].forEach(function (field) {
            if (!field) {
                return;
            }

            field.addEventListener('change', function () {
                if (!validateSearch(false) && !hasFilters()) {
                    validateSearch(true);
                    return;
                }
                fetchResults(1, false);
            });
        });

        if (reset) {
            reset.addEventListener('click', function () {
                if (input) {
                    input.value = '';
                }
                if (category) {
                    category.value = '0';
                }
                if (dateFrom) {
                    dateFrom.value = '';
                }
                if (dateTo) {
                    dateTo.value = '';
                }
                setHelp('', false);
                fetchResults(1, false);
                if (input) {
                    input.focus();
                }
            });
        }

        wrapper.addEventListener('click', function (event) {
            const button = event.target.closest('.rdsco-search-page');

            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();
            const page = parseInt(button.dataset.page || '1', 10);

            if (page > 0 && page !== state.page) {
                fetchResults(page, true);
            }
        });
    }

    function initAll(scope) {
        const root = scope || document;

        if (root.matches && root.matches(SELECTOR)) {
            initSearchBox(root);
        }

        root.querySelectorAll(SELECTOR).forEach(initSearchBox);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll(document);
        });
    } else {
        initAll(document);
    }

    if (window.jQuery && window.elementorFrontend) {
        window.jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction(
                    'frontend/element_ready/rdsco-search-box.default',
                    function ($scope) {
                        initAll($scope[0]);
                    }
                );
            }
        });
    }
})();
