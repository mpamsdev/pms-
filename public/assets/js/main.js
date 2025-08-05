document.addEventListener('DOMContentLoaded', () => {
    // Get all "navbar-burger" elements for mobile responsiveness
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Handle burger menu toggle for mobile
    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }

    // Get all dropdowns on the page
    const $dropdowns = Array.prototype.slice.call(document.querySelectorAll('.navbar-item.has-dropdown'), 0);

    // Handle dropdown menu toggle
    if ($dropdowns.length > 0) {
        $dropdowns.forEach(el => {
            const $dropdownTrigger = el.querySelector('.navbar-link');

            $dropdownTrigger.addEventListener('click', event => {
                // Toggle the "is-active" class on the dropdown parent
                el.classList.toggle('is-active');
                event.stopPropagation(); // Prevent click from closing dropdown instantly
            });
        });

        // Close dropdowns if clicked outside
        document.addEventListener('click', event => {
            $dropdowns.forEach(el => {
                if (el.classList.contains('is-active')) {
                    el.classList.remove('is-active');
                }
            });
        });

        // Close dropdown if clicked on a menu item
        document.querySelectorAll('.navbar-dropdown .navbar-item').forEach(item => {
            item.addEventListener('click', () => {
                $dropdowns.forEach(el => el.classList.remove('is-active'));
            });
        });
    }
});