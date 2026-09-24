<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('sidebarToggle');

        if (!toggle) {
            return;
        }

        const updateSidebarToggle = function () {
            const collapsed = document.body.classList.contains('sidebar-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
            toggle.setAttribute('title', collapsed ? 'Expandir menú' : 'Contraer menú');
        };

        toggle.addEventListener('click', function () {
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
            updateSidebarToggle();
        });

        updateSidebarToggle();
    });

    document.addEventListener('DOMContentLoaded', function () {

        const tooltipTriggerList =
            document.querySelectorAll('[data-bs-toggle="tooltip"]');

        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });

    });
</script>
<script>
    function changeTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            return;
        }

        document.documentElement.setAttribute(
            'data-bs-theme',
            theme
        );

        localStorage.setItem('theme', theme);

        updateThemeOption(theme);
    }

    function updateThemeOption(theme) {
        const lightOption = document.getElementById('optionThemeLight');
        const darkOption = document.getElementById('optionThemeDark');

        if (!lightOption || !darkOption) {
            return;
        }

        if (theme === 'dark') {
            lightOption.classList.remove('d-none');
            darkOption.classList.add('d-none');
        } else {
            lightOption.classList.add('d-none');
            darkOption.classList.remove('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const currentTheme = localStorage.getItem('theme') || 'light';

        document.documentElement.setAttribute(
            'data-bs-theme',
            currentTheme
        );

        updateThemeOption(currentTheme);
    });
</script>

</body>
</html>