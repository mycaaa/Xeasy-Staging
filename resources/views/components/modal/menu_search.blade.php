@can('menu_globle_search')
<div class="modal fade" id="menuSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Menu Search (Ctrl+Shift+F)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <div class="p-3 border-bottom">
                    <input type="text"
                        id="menuSearchInput"
                        class="form-control"
                        placeholder="Type to filter menus..." aria-label="Type to filter menus"
                        autocomplete="off">
                </div>

                <div class="menu-list-container" style="max-height: 60vh;overflow-y:auto;padding: 0 10px;">
                    <ul id="menuSearchList" class="list-group list-group-flush">
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
    $(document).ready(function() {
        const baseUrl = window.location.origin;
        const $modalEl = $('#menuSearchModal');
        const modal = new bootstrap.Modal($modalEl[0], {
            keyboard: true
        });
        const $searchInput = $('#menuSearchInput');
        const $listEl = $('#menuSearchList');

        let allMenus = [];
        let filtered = [];
        let activeIdx = -1;
        let menusLoaded = false;

        function loadMenus() {
            if (menusLoaded) return;
            $.ajax({
                    url: baseUrl + '/search-menu-list',
                    method: 'GET',
                    dataType: 'json'
                })
                .done(function(menus) {
                    menusLoaded = true;
                    allMenus = menus.sort((a, b) => a.order - b.order);
                    renderList(allMenus);
                })
                .fail(function() {
                    $listEl.html('<li class="list-group-item text-danger">Failed to load menus</li>');
                });
        }



        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'f') {
                e.preventDefault();
                modal.show();
                setTimeout(() => $searchInput.focus(), 150);
            }
        });



        $modalEl.on('shown.bs.modal', () => {
            loadMenus();
            $searchInput.focus();
        });



        let debounceTimer;
        $searchInput.on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                const term = $searchInput.val().trim().toLowerCase();
                filtered = term ?
                    // MENU-01: match the address too (e.g. "btc", "boarding")
                    allMenus.filter(m => ((m.title || '') + ' ' + (m.url || '')).toLowerCase().includes(term)) :
                    allMenus;

                activeIdx = filtered.length ? 0 : -1;
                renderList(filtered);
            }, 180);
        });



        $searchInput.on('keydown', function(e) {
            if (!filtered.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = (activeIdx + 1) % filtered.length;
                highlight(activeIdx);

            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = activeIdx <= 0 ? filtered.length - 1 : activeIdx - 1;
                highlight(activeIdx);

            } else if (e.key === 'Enter' && activeIdx > -1) {
                e.preventDefault();
                const menu = filtered[activeIdx];
                if (menu.url) window.location.href = baseUrl + "/" + menu.url.replace(/^\/+/, "");

            } else if (e.key === 'Escape') {
                modal.hide();
            }
        });



        function renderList(items) {
            $listEl.empty();

            if (!items.length) {
                $listEl.append('<li class="list-group-item text-muted text-center">No menus found</li>');
                return;
            }

            const $frag = $(document.createDocumentFragment());

            items.forEach((menu, i) => {
                const indent = '&nbsp;'.repeat((menu.level ?? 0) * 4);

                const $li = $('<li>', {
                    class: 'list-group-item list-group-item-action d-flex align-items-center p-2',
                    'data-idx': i,
                    html: `
                        ${menu.icon ? `<i class="${menu.icon} me-2"></i>` : ''}
                        <span>${indent}${menu.title}</span>
                    `
                }).on('click', () => {
                    if (menu.url) {
                        window.location.href = baseUrl + "/" + menu.url.replace(/^\/+/, "");
                    }
                });
                $frag.append($li);
            });

            $listEl.append($frag);
            if (activeIdx > -1) highlight(activeIdx);
        }



        function highlight(idx) {
            $listEl.find('.list-group-item').removeClass('active');
            const $active = $listEl.find(`[data-idx="${idx}"]`).addClass('active');
            $active[0]?.scrollIntoView({
                block: 'nearest'
            });
        }



        $modalEl.on('hidden.bs.modal', function() {
            $searchInput.val('');
            activeIdx = -1;
            renderList(allMenus);
        });
    });
</script>
@endpush
@endcan