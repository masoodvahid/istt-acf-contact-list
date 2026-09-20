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

        function toEnglishDigits(value) {
            return String(value || '')
                .replace(/[۰-۹]/g, function (digit) {
                    return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit));
                })
                .replace(/[٠-٩]/g, function (digit) {
                    return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit));
                });
        }

        function toPersianDigits(value) {
            return String(value || '').replace(/\d/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'[parseInt(digit, 10)];
            });
        }

        function isJalaliLeapYear(year) {
            return [1, 5, 9, 13, 17, 22, 26, 30].indexOf(year % 33) !== -1;
        }

        function parseJalaliDate(value) {
            const normalized = toEnglishDigits(value)
                .replace(/[.\\-]/g, '/')
                .replace(/\s+/g, '');
            const match = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);

            if (!match) {
                return null;
            }

            const year = parseInt(match[1], 10);
            const month = parseInt(match[2], 10);
            const day = parseInt(match[3], 10);
            const maximumDay = month <= 6 ? 31 : (month <= 11 ? 30 : (isJalaliLeapYear(year) ? 30 : 29));

            if (year < 1200 || year > 1600 || month < 1 || month > 12 || day < 1 || day > maximumDay) {
                return null;
            }

            return [
                String(year).padStart(4, '0'),
                String(month).padStart(2, '0'),
                String(day).padStart(2, '0')
            ].join('/');
        }

        function formatJalaliInput(field) {
            if (!field) {
                return;
            }

            const digits = toEnglishDigits(field.value).replace(/\D/g, '').slice(0, 8);
            let formatted = digits.slice(0, 4);

            if (digits.length > 4) {
                formatted += '/' + digits.slice(4, 6);
            }

            if (digits.length > 6) {
                formatted += '/' + digits.slice(6, 8);
            }

            field.value = toPersianDigits(formatted);
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
            const fromRaw = fieldValue(dateFrom);
            const toRaw = fieldValue(dateTo);
            const from = fromRaw ? parseJalaliDate(fromRaw) : '';
            const to = toRaw ? parseJalaliDate(toRaw) : '';

            if (fromRaw && !from) {
                setHelp('تاریخ شروع شمسی معتبر نیست. نمونه صحیح: ۱۴۰۵/۰۱/۰۱', true);
                return false;
            }

            if (toRaw && !to) {
                setHelp('تاریخ پایان شمسی معتبر نیست. نمونه صحیح: ۱۴۰۵/۱۲/۲۹', true);
                return false;
            }

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

        [dateFrom, dateTo].forEach(function (field) {
            if (!field) {
                return;
            }

            field.addEventListener('input', function () {
                formatJalaliInput(field);
            });

            field.addEventListener('blur', function () {
                const parsed = parseJalaliDate(field.value);

                if (parsed) {
                    field.value = toPersianDigits(parsed);
                }
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
