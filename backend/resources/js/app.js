// CSP-safe Alpine build: no eval. Behaviour lives in named components, not inline expressions.
import Alpine from '@alpinejs/csp';

Alpine.data('disclosure', () => ({
    open: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    get expanded() { return this.open ? 'true' : 'false'; },
    get drawerClass() { return this.open ? 'translate-x-0' : ''; },
}));

// Shows only the settings of the SMS provider currently selected in the dropdown.
Alpine.data('smsPicker', () => ({
    driver: 'none',
    init() { this.driver = this.$el.dataset.driver || 'none'; },
    pick(e) { this.driver = e.target.value; },
    get isTwilio() { return this.driver === 'twilio'; },
    get isFast2sms() { return this.driver === 'fast2sms'; },
    get hasProvider() { return this.driver !== 'none'; },
}));

window.Alpine = Alpine;
Alpine.start();
