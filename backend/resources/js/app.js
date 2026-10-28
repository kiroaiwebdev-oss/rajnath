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

// Razorpay Checkout: opens the hosted payment sheet; on success posts ids + signature to the
// server, which verifies the signature and confirms the captured amount with Razorpay.
Alpine.data('razorpayCheckout', () => ({
    busy: false, error: '',
    pay() {
        const d = this.$el.dataset;
        if (!window.Razorpay) { this.error = 'The payment window could not load. Check your connection and try again.'; return; }
        this.busy = true; this.error = '';
        const rzp = new window.Razorpay({
            key: d.key, order_id: d.order, amount: Number(d.amount), currency: 'INR', name: d.name,
            prefill: { name: d.customer, email: d.email || undefined, contact: d.contact || undefined },
            theme: { color: '#141a3c' },
            handler: (res) => {
                this.$refs.pid.value = res.razorpay_payment_id; this.$refs.oid.value = res.razorpay_order_id; this.$refs.sig.value = res.razorpay_signature;
                this.$refs.verify.submit();
            },
            modal: { ondismiss: () => { this.busy = false; } },
        });
        rzp.on('payment.failed', (r) => { this.busy = false; this.error = (r.error && r.error.description) || 'Payment failed. You have not been charged; please try again.'; });
        rzp.open();
    },
}));

// Assistant chat. Text is always rendered with x-text (never HTML).
Alpine.data('assistant', () => ({
    messages: [], draft: '', busy: false, error: '', conversation: null, handoff: false, limited: false,
    get empty() { return this.messages.length === 0; },
    get cannotSend() { return this.busy || this.limited || this.draft.trim().length < 2; },
    async send() {
        const text = this.draft.trim();
        if (text.length < 2 || this.busy) return;
        this.messages.push({ id: Date.now(), role: 'user', content: text, cls: 'chat-user' });
        this.draft = ''; this.busy = true; this.error = '';
        try {
            const r = await fetch(this.$root.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ message: text, conversation: this.conversation }),
            });
            const data = await r.json().catch(() => ({}));
            if (r.status === 429) { this.limited = true; this.error = (data.error && data.error.message) || 'Daily limit reached. Please try again tomorrow.'; return; }
            if (r.status === 503) { this.error = 'The assistant is not available right now. Please contact our team.'; this.handoff = true; return; }
            if (!r.ok) { this.error = (data.errors && data.errors.message && data.errors.message[0]) || 'Something went wrong. Please try again.'; return; }
            this.conversation = data.conversation;
            this.handoff = this.handoff || data.handoff;
            this.messages.push({ id: Date.now() + 1, role: 'assistant', content: data.reply, cls: 'chat-bot' });
            this.$nextTick(() => { const l = this.$refs.log; if (l) l.scrollTop = l.scrollHeight; });
        } catch (e) {
            this.error = 'No connection. Please check your internet and try again.';
        } finally {
            this.busy = false;
        }
    },
}));

// In-app consultation call (LiveKit). The SDK is loaded only on the call page.
Alpine.data('callRoom', () => ({
    state: 'idle', error: '', micOn: true, camOn: true, remoteCount: 0, room: null,
    get idle() { return this.state === 'idle' || this.state === 'ended'; },
    get connecting() { return this.state === 'connecting'; },
    get live() { return this.state === 'live'; },
    get ended() { return this.state === 'ended'; },
    get micLabel() { return this.micOn ? 'Mute' : 'Unmute'; },
    get camLabel() { return this.camOn ? 'Turn camera off' : 'Turn camera on'; },
    get waiting() { return this.live && this.remoteCount === 0; },
    get audioOnly() { return this.$root.dataset.audioOnly === '1'; },
    get camControl() { return this.live && this.$root.dataset.audioOnly !== '1'; },
    async join() {
        this.state = 'connecting'; this.error = '';
        try {
            const r = await fetch(this.$root.dataset.tokenUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) { this.state = 'idle'; this.error = (d.error && d.error.message) || 'Could not start the call.'; return; }
            const { Room, RoomEvent, Track } = await import('livekit-client');
            const room = new Room({ adaptiveStream: true, dynacast: true });
            this.room = room;
            const remote = this.$refs.remote, local = this.$refs.local;
            room.on(RoomEvent.TrackSubscribed, (track) => {
                const el = track.attach();
                el.dataset.sid = track.sid;
                if (track.kind === Track.Kind.Video) { el.className = 'h-full w-full rounded-2xl bg-ink-900 object-cover'; }
                remote.appendChild(el);
            });
            room.on(RoomEvent.TrackUnsubscribed, (track) => { track.detach().forEach((el) => el.remove()); });
            room.on(RoomEvent.ParticipantConnected, () => { this.remoteCount = room.remoteParticipants.size; });
            room.on(RoomEvent.ParticipantDisconnected, () => { this.remoteCount = room.remoteParticipants.size; });
            room.on(RoomEvent.Disconnected, () => { this.state = 'ended'; });
            await room.connect(d.url, d.token);
            await room.localParticipant.setMicrophoneEnabled(true);
            if (!d.audio_only) {
                const pub = await room.localParticipant.setCameraEnabled(true);
                if (pub && pub.track) { const el = pub.track.attach(); el.muted = true; el.className = 'h-full w-full rounded-xl object-cover'; local.appendChild(el); }
            } else { this.camOn = false; }
            this.remoteCount = room.remoteParticipants.size;
            this.state = 'live';
        } catch (e) {
            this.state = 'idle';
            this.error = (e && e.name === 'NotAllowedError') ? 'Please allow microphone/camera access in your browser and try again.' : 'Could not connect the call. Check your internet and try again.';
            if (this.room) { this.room.disconnect(); }
        }
    },
    async toggleMic() { this.micOn = !this.micOn; await this.room.localParticipant.setMicrophoneEnabled(this.micOn); },
    async toggleCam() { this.camOn = !this.camOn; await this.room.localParticipant.setCameraEnabled(this.camOn); if (!this.camOn) { this.$refs.local.innerHTML = ''; } else { const p = this.room.localParticipant.getTrackPublications().find((x) => x.kind === 'video'); if (p && p.track) { const el = p.track.attach(); el.muted = true; el.className = 'h-full w-full rounded-xl object-cover'; this.$refs.local.appendChild(el); } } },
    leave() { if (this.room) { this.room.disconnect(); } this.state = 'ended'; this.$refs.remote.innerHTML = ''; this.$refs.local.innerHTML = ''; },
}));

Alpine.data('printer', () => ({ print() { window.print(); } }));

window.Alpine = Alpine;
Alpine.start();

// PWA: offline fallback page + static asset caching (never caches account/admin/API responses).
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === '127.0.0.1' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
