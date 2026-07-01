document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),

        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('homie-theme', this.dark ? 'dark' : 'light');
        },
    });

    Alpine.store('sidebar', {
        open: false,
    });
});
