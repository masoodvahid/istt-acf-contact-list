(function () {
    'use strict';

    const SELECTOR = '.rdsco-post-display';

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

    function initPostDisplay(wrapper) {
        if (!wrapper || wrapper.dataset.rdscoInitialized === '1') {
            return;
        }

        wrapper.dataset.rdscoInitialized = '1';

        const results = wrapper.querySelector('.rdsco-post-display-results');
        const searchInput = wrapper.querySelector('.rdsco-post-display-search-input');
        const searchClear = wrapper.querySelector('.rdsco-post-display-search-clear');
        const count = wrapper.querySelector('.rdsco-post-display-count strong');
        const loadMore = wrapper.querySelector('.rdsco-post-display-load-more');
        const tagsWrap = wrapper.querySelector('.rdsco-post-display-tags-wrap');
        const tagFilters = wrapper.querySelector('.rdsco-post-display-tag-filters');

        const state = {
            activeCategory: parseInt(wrapper.dataset.activeCategory || '0', 10),
            activeTag: parseInt(wrapper.dataset.activeTag || '0', 10),
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

        function updateLoadMore() {
            if (!loadMore) {
                return;
            }

            loadMore.hidden = state.maxPages <= 1 || state.page >= state.maxPages;
        }

        function syncCategoryFilters(categoryId) {
            wrapper.querySelectorAll('.rdsco-post-display-sidebar-filter').forEach((item) => {
                const itemCategory = parseInt(item.dataset.category || '0', 10);
                const isActive = itemCategory === categoryId;

                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function syncTagFilters(tagId) {
            wrapper.querySelectorAll('.rdsco-post-display-tag-filter').forEach((item) => {
                const itemTag = parseInt(item.dataset.tag || '0', 10);
                const isActive = itemTag === tagId;

                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function replaceTagFilters(html, activeTag) {
            if (!tagFilters || !tagsWrap) {
                return;
            }

            tagFilters.innerHTML = html || '';
            tagsWrap.hidden = !html;

            state.activeTag = parseInt(activeTag || '0', 10);
            wrapper.dataset.activeTag = String(state.activeTag);

            syncTagFilters(state.activeTag);
        }

        function appendCards(html) {
            const temp = document.createElement('div');
            temp.innerHTML = html;

            const incomingGrid = temp.querySelector('.rdsco-post-display-grid');
            const currentGrid = results ? results.querySelector('.rdsco-post-display-grid') : null;

            if (!incomingGrid || !currentGrid) {
                if (results) {
                    results.innerHTML = html;
                }
                return;
            }

            Array.from(incomingGrid.children).forEach((card) => currentGrid.appendChild(card));
        }

        async function fetchPosts(page, append, refreshTags) {
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
            body.append('action', 'rdsco_post_display_fetch');
            body.append('nonce', wrapper.dataset.nonce || '');
            body.append('active_category', String(state.activeCategory));
            body.append('tag_id', String(state.activeTag));
            body.append('search', state.search);
            body.append('page', String(page));
            body.append('settings', JSON.stringify(state.settings));

            try {
                const response = await fetch(window.RDSCOPostDisplay.ajaxUrl, {
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
                state.activeTag = parseInt(payload.data.activeTag || '0', 10);

                wrapper.dataset.currentPage = String(state.page);
                wrapper.dataset.maxPages = String(state.maxPages);
                wrapper.dataset.activeCategory = String(state.activeCategory);
                wrapper.dataset.activeTag = String(state.activeTag);

                if (refreshTags && tagFilters) {
                    replaceTagFilters(payload.data.tagsHtml || '', state.activeTag);
                } else {
                    syncTagFilters(state.activeTag);
                }

                if (count) {
                    count.textContent = payload.data.total || '0';
                }

                updateLoadMore();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('RDSCO Post Display:', error);
                }
            } finally {
                if (state.controller === controller) {
                    setLoading(false);
                    state.controller = null;
                }
            }
        }

        wrapper.addEventListener('click', function (event) {
            const categoryFilter = event.target.closest('.rdsco-post-display-sidebar-filter');

            if (categoryFilter) {
                event.preventDefault();

                state.activeCategory = parseInt(categoryFilter.dataset.category || '0', 10);
                state.activeTag = 0;
                state.page = 1;

                syncCategoryFilters(state.activeCategory);
                fetchPosts(1, false, true);
                return;
            }

            const tagFilter = event.target.closest('.rdsco-post-display-tag-filter');

            if (tagFilter) {
                event.preventDefault();

                state.activeTag = parseInt(tagFilter.dataset.tag || '0', 10);
                state.page = 1;

                syncTagFilters(state.activeTag);
                fetchPosts(1, false, false);
                return;
            }

            const more = event.target.closest('.rdsco-post-display-load-more');

            if (more && state.page < state.maxPages) {
                event.preventDefault();
                fetchPosts(state.page + 1, true, false);
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                window.clearTimeout(state.searchTimer);

                state.searchTimer = window.setTimeout(function () {
                    state.search = searchInput.value.trim();
                    state.page = 1;
                    fetchPosts(1, false, false);
                }, 400);
            });
        }

        if (searchClear && searchInput) {
            searchClear.addEventListener('click', function () {
                searchInput.value = '';
                state.search = '';
                state.page = 1;
                fetchPosts(1, false, false);
                searchInput.focus();
            });
        }

        syncCategoryFilters(state.activeCategory);
        syncTagFilters(state.activeTag);
        updateLoadMore();
        setupFilterMarquees(wrapper);
    }

    function initAll(scope) {
        const root = scope || document;

        if (root.matches && root.matches(SELECTOR)) {
            initPostDisplay(root);
        }

        root.querySelectorAll(SELECTOR).forEach(initPostDisplay);
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
                    'frontend/element_ready/rdsco-post-display.default',
                    function ($scope) {
                        initAll($scope[0]);
                    }
                );
            }
        });
    }
})();
