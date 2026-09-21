

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
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
        const savedTheme = localStorage.getItem('theme') || 'light';

        document.documentElement.setAttribute(
            'data-bs-theme',
            savedTheme
        );

        updateThemeOption(savedTheme);
    });
</script>

</body>
</html>