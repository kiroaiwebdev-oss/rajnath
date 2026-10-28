// CSP-safe Alpine build: no eval. Behaviour lives in named components, not inline expressions.
import Alpine from '@alpinejs/csp';

Alpine.data('disclosure', () => ({
    open: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    get expanded() { return this.open ? 'true' : 'false'; },
    get drawerClass() { return this.open ? 'translate-x-0' : ''; },
}));

window.Alpine = Alpine;
Alpine.start();
