(function () {
    'use strict';

    const SELECTOR = '.rdsco-ajax-archive';

    function parseSettings(wrapper) {
        try {
            return JSON.parse(wrapper.dataset.settings || '{}');
        } catch (error) {
            return {};
        }
    }

    function setupFilterMarquees(wrapper) {
        let frame = 0;

        function refresh() {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(function () {
                wrapper.querySelectorAll('.rdsco-filter-marquee').forEach(function (viewport) {
                    const track = viewport.querySelector('.rdsco-filter-marquee-track');

                    if (!track) {
                        return;
                    }

                    viewport.classList.remove('is-overflowing');
                    viewport.style.removeProperty('--rdsco-marquee-distance');
                    viewport.style.removeProperty('--rdsco-marquee-duration');

                    const overflow = Math.ceil(track.scrollWidth - viewport.clientWidth);

                    if (overflow > 2) {
                        viewport.style.setProperty('--rdsco-marquee-distance', overflow + 'px');
                        viewport.style.setProperty('--rdsco-marquee-duration', Math.min(14, Math.max(6, 5 + (overflow / 24))) + 's');
                        viewport.classList.add('is-overflowing');
                    }
                });
            });
        }

        refresh();

        if (window.ResizeObserver) {
            const observer = new ResizeObserver(refresh);
            observer.observe(wrapper);
            wrapper.rdscoMarqueeObserver = observer;
        } else {
            window.addEventListener('resize', refresh, {passive: true});
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(refresh);
        }
    }

    function initArchive(wrapper) {
        if (!wrapper || wrapper.dataset.rdscoInitialized === '1') {
            return;
        }

        wrapper.dataset.rdscoInitialized = '1';

        const results = wrapper.querySelector('.rdsco-archive-results');
        const paginationWrap = wrapper.querySelector('.rdsco-pagination-wrap');
        const loadMoreWrap = wrapper.querySelector('.rdsco-load-more-wrap');
        const resultCount = wrapper.querySelector('.rdsco-result-count strong');
        const searchInput = wrapper.querySelector('.rdsco-search-input');
        const searchClear = wrapper.querySelector('.rdsco-search-clear');
        const tagsWrap = wrapper.querySelector('.rdsco-archive-tags-wrap');

        const state = {
            rootId: parseInt(wrapper.dataset.rootId || '0', 10),
            filterRoot: parseInt(wrapper.dataset.filterRoot || '0', 10),
            categoryId: parseInt(wrapper.dataset.categoryId || '0', 10),
            tagId: parseInt(wrapper.dataset.tagId || '0', 10),
            page: 1,
            maxPages: parseInt(wrapper.dataset.maxPages || '1', 10),
            search: '',
            settings: parseSettings(wrapper),
            searchTimer: null,
            controller: null
        };

        function setLoading(isLoading) {
            wrapper.classList.toggle('is-loading', isLoading);
        }

        function updateLoadMoreVisibility() {
            if (!loadMoreWrap) {
                return;
            }

            loadMoreWrap.hidden = state.maxPages <= 1 || state.page >= state.maxPages;
        }

        function appendCards(html) {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const incomingGrid = temp.querySelector('.rdsco-archive-grid');
            const currentGrid = results ? results.querySelector('.rdsco-archive-grid') : null;

            if (!incomingGrid || !currentGrid) {
                if (results) {
                    results.innerHTML = html;
                }
                return;
            }

            Array.from(incomingGrid.children).forEach((card) => currentGrid.appendChild(card));
        }

        async function fetchArchive(page, append) {
            if (!results) {
                return;
            }

            if (state.controller) {
                state.controller.abort();
            }

            const controller = new AbortController();
            state.controller = controller;
            setLoading(true);

            const body = new FormData();
            body.append('action', 'rdsco_archive_fetch');
            body.append('nonce', wrapper.dataset.nonce || '');
            body.append('root_id', String(state.rootId));
            body.append('filter_root', String(state.filterRoot));
            body.append('category_id', String(state.categoryId));
            body.append('tag_id', String(state.tagId));
            body.append('search', state.search);
            body.append('page', String(page));
            body.append('settings', JSON.stringify(state.settings));

            try {
                const response = await fetch(window.RDSCOAjaxArchive.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body,
                    signal: controller.signal
                });

                const payload = await response.json();
                if (!payload.success || !payload.data) {
                    return;
                }

                if (append) {
                    appendCards(payload.data.html || '');
                } else {
                    results.innerHTML = payload.data.html || '';
                }

                state.page = parseInt(payload.data.page || page, 10);
                state.maxPages = parseInt(payload.data.maxPages || '1', 10);

                wrapper.dataset.currentPage = String(state.page);
                wrapper.dataset.maxPages = String(state.maxPages);

                if (paginationWrap) {
                    paginationWrap.innerHTML = payload.data.pagination || '';
                }

                if (resultCount) {
                    resultCount.textContent = payload.data.total || '0';
                }

                if (tagsWrap) {
                    const tagsHtml = payload.data.tagsHtml || '';
                    tagsWrap.innerHTML = tagsHtml;
                    tagsWrap.hidden = !tagsHtml.trim();
                    state.tagId = parseInt(payload.data.activeTag || '0', 10);
                }

                updateLoadMoreVisibility();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('RDSCO AJAX Archive:', error);
                }
            } finally {
                if (state.controller === controller) {
                    setLoading(false);
                    state.controller = null;
                }
            }
        }

        wrapper.addEventListener('click', function (event) {
            const categoryButton = event.target.closest('.rdsco-filter-item.is-category');
            if (categoryButton) {
                event.preventDefault();
                state.categoryId = parseInt(categoryButton.dataset.category || '0', 10);
                state.tagId = 0;
                state.page = 1;

                wrapper.querySelectorAll('.rdsco-filter-item.is-category').forEach((button) => {
                    button.classList.remove('is-active');
                });
                categoryButton.classList.add('is-active');
                fetchArchive(1, false);
                return;
            }

            const tagButton = event.target.closest('.rdsco-archive-tag');
            if (tagButton) {
                event.preventDefault();
                state.tagId = parseInt(tagButton.dataset.tag || '0', 10);
                state.page = 1;

                wrapper.querySelectorAll('.rdsco-archive-tag').forEach((button) => {
                    button.classList.remove('is-active');
                    button.setAttribute('aria-pressed', 'false');
                });
                tagButton.classList.add('is-active');
                tagButton.setAttribute('aria-pressed', 'true');
                fetchArchive(1, false);
                return;
            }

            const pageButton = event.target.closest('.rdsco-page-button');
            if (pageButton) {
                event.preventDefault();
                const page = parseInt(pageButton.dataset.page || '1', 10);
                fetchArchive(page, false);

                window.scrollTo({
                    top: wrapper.getBoundingClientRect().top + window.scrollY - 100,
                    behavior: 'smooth'
                });
                return;
            }

            const loadMore = event.target.closest('.rdsco-load-more');
            if (loadMore) {
                event.preventDefault();
                if (state.page < state.maxPages) {
                    fetchArchive(state.page + 1, true);
                }
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                window.clearTimeout(state.searchTimer);
                state.searchTimer = window.setTimeout(function () {
                    state.search = searchInput.value.trim();
                    state.page = 1;
                    fetchArchive(1, false);
                }, 400);
            });
        }

        if (searchClear && searchInput) {
            searchClear.addEventListener('click', function () {
                if (!searchInput.value && !state.search) {
                    searchInput.focus();
                    return;
                }

                searchInput.value = '';
                state.search = '';
                state.page = 1;
                fetchArchive(1, false);
                searchInput.focus();
            });
        }

        updateLoadMoreVisibility();
        setupFilterMarquees(wrapper);
    }

    function initAll(scope) {
        const root = scope || document;
        if (root.matches && root.matches(SELECTOR)) {
            initArchive(root);
        }
        root.querySelectorAll(SELECTOR).forEach(initArchive);
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
                    'frontend/element_ready/rdsco-ajax-archive.default',
                    function ($scope) {
                        initAll($scope[0]);
                    }
                );
            }
        });
    }
})();
