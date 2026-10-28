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

// Birth-place combobox (WAI-ARIA combobox pattern) backed by /places/search.
Alpine.data('placePicker', () => ({
    query: '', results: [], active: -1, open: false, placeId: '', status: '', timer: null, controller: null,
    init() {
        this.query = this.$el.dataset.label || '';
        this.placeId = this.$el.dataset.id || '';
    },
    get expanded() { return this.open ? 'true' : 'false'; },
    get activeId() { return this.active >= 0 ? 'place-opt-' + this.active : ''; },
    get hasResults() { return this.results.length > 0; },
    onInput() {
        this.placeId = '';
        clearTimeout(this.timer);
        if (this.query.trim().length < 2) { this.results = []; this.open = false; this.status = ''; return; }
        this.timer = setTimeout(() => this.fetch(), 220);
    },
    async fetch() {
        if (this.controller) this.controller.abort();
        this.controller = new AbortController();
        this.status = 'Searching…';
        try {
            const r = await fetch('/places/search?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' }, signal: this.controller.signal });
            if (!r.ok) throw new Error(String(r.status));
            const j = await r.json();
            this.results = j.data; this.active = -1; this.open = true;
            this.status = this.results.length ? this.results.length + ' places found. Use up and down arrows to choose.' : 'No matching place. Try the nearest larger town.';
        } catch (e) {
            if (e.name !== 'AbortError') { this.status = 'Could not search right now. Check your connection and try again.'; this.open = false; }
        }
    },
    onKey(e) {
        if (!this.open || !this.results.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); this.active = (this.active + 1) % this.results.length; }
        else if (e.key === 'ArrowUp') { e.preventDefault(); this.active = (this.active - 1 + this.results.length) % this.results.length; }
        else if (e.key === 'Enter' && this.active >= 0) { e.preventDefault(); this.select(this.active); }
        else if (e.key === 'Escape') { this.open = false; }
    },
    choose(e) { this.select(Number(e.currentTarget.dataset.index)); },
    select(i) {
        const p = this.results[i]; if (!p) return;
        this.query = p.label; this.placeId = String(p.id); this.open = false;
        this.status = 'Selected ' + p.label + ', time zone ' + p.tz + '.';
    },
    close() { setTimeout(() => { this.open = false; }, 150); },
}));

// Disables the "resend code" button until the cooldown ends.
Alpine.data('countdown', () => ({
    left: 0,
    init() {
        this.left = Number(this.$el.dataset.seconds || 0);
        const t = setInterval(() => { if (this.left > 0) this.left--; else clearInterval(t); }, 1000);
    },
    get waiting() { return this.left > 0; },
    get label() { return this.left > 0 ? 'Resend code in ' + this.left + 's' : 'Resend code'; },
}));

// Razorpay Checkout. Options come from a JSON <script> block (no secrets). The result is posted
// to the server, which verifies the signature and re-checks the payment with Razorpay.
Alpine.data('rzpCheckout', () => ({
    message: '', busy: false, opts: null,
    init() {
        this.opts = JSON.parse(document.getElementById('rzp-options').textContent);
        if (this.$el.dataset.autoopen === '1') this.$nextTick(() => this.pay());
    },
    load() {
        return new Promise((resolve, reject) => {
            if (window.Razorpay) return resolve();
            const s = document.createElement('script');
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            s.onload = resolve; s.onerror = () => reject(new Error('load'));
            document.head.appendChild(s);
        });
    },
    async pay() {
        this.busy = true; this.message = 'Opening secure payment window…';
        try { await this.load(); } catch (e) {
            this.busy = false; this.message = 'The payment window could not load. Check your connection and try again.'; return;
        }
        const form = this.$refs.form;
        const rzp = new window.Razorpay(Object.assign({}, this.opts, {
            handler: (r) => {
                this.message = 'Payment received. Confirming…';
                form.querySelector('[name=razorpay_payment_id]').value = r.razorpay_payment_id;
                form.querySelector('[name=razorpay_order_id]').value = r.razorpay_order_id;
                form.querySelector('[name=razorpay_signature]').value = r.razorpay_signature;
                form.submit();
            },
            modal: { ondismiss: () => { this.busy = false; this.message = 'Payment window closed. No money was taken unless you completed the payment.'; }, confirm_close: true },
        }));
        rzp.on('payment.failed', (r) => {
            this.busy = false;
            this.message = 'Payment failed: ' + ((r && r.error && r.error.description) || 'please try again or use another method') + '.';
        });
        rzp.open();
    },
}));

window.Alpine = Alpine;
Alpine.start();

// PWA: offline fallback page + static asset caching (never caches account/admin/API responses).
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === '127.0.0.1' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
