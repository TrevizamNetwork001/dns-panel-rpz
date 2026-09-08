<script>
    (function () {
        var tabs = Array.from(document.querySelectorAll('.admin-settings [data-settings-tab]'));
        var panels = document.querySelectorAll('.admin-settings [data-settings-panel]');

        function activate(name) {
            tabs.forEach(function (tab) {
                var active = tab.dataset.settingsTab === name;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                if (active) {
                    var nav = tab.parentElement;
                    var bounds = tab.getBoundingClientRect();
                    var viewport = nav.getBoundingClientRect();
                    if (bounds.left < viewport.left || bounds.right > viewport.right) {
                        nav.scrollLeft += bounds.left - viewport.left - (nav.clientWidth - bounds.width) / 2;
                    }
                }
                if (active) {
                    var nav = tab.parentElement;
                    var bounds = tab.getBoundingClientRect();
                    var viewport = nav.getBoundingClientRect();
                    if (bounds.left < viewport.left || bounds.right > viewport.right) {
                        nav.scrollLeft += bounds.left - viewport.left - (nav.clientWidth - bounds.width) / 2;
                    }
                }
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.settingsPanel !== name;
            });
        }

        function navigate(tab) {
            activate(tab.dataset.settingsTab);
            var url = new URL(window.location.href);
            url.searchParams.set('aba', tab.dataset.settingsTab);
            window.history.replaceState({}, '', url);
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { navigate(tab); });
            tab.addEventListener('keydown', function (event) {
                var next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = tabs.length - 1;
                else return;
                event.preventDefault();
                tabs[next].focus();
                navigate(tabs[next]);
            });
        });

        var requestedTab = new URLSearchParams(window.location.search).get('aba');
        activate(tabs.some(function (tab) { return tab.dataset.settingsTab === requestedTab; }) ? requestedTab : 'geral');
    })();
</script>
