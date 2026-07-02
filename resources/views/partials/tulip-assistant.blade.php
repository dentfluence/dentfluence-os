{{--
|==============================================================================
| Tulip — floating AI assistant widget (A3)
| Included once in layouts/app.blade.php (inside @auth).
| Pure Alpine + scoped CSS. Talks to /assistant/chat. 100% local model.
|==============================================================================
--}}
@php
    $tulipName  = config('assistant.name', 'Tulip');
    $firstName  = trim(explode(' ', (string) (auth()->user()->name ?? ''))[0] ?? '');
@endphp

<div
    id="tulip-widget"
    x-data="tulipChat()"
    x-cloak
    data-name="{{ $tulipName }}"
    data-greeting="Hi{{ $firstName ? ' ' . $firstName : '' }} — I'm {{ $tulipName }}, your clinic assistant. Ask me about a patient, today's schedule, or how to do something in the app."
    @if(config('assistant.wake.enabled') && config('assistant.wake.access_key'))
    data-wake-enabled="1"
    data-wake-key="{{ config('assistant.wake.access_key') }}"
    data-wake-keyword="{{ config('assistant.wake.keyword_path') }}"
    data-wake-params="{{ config('assistant.wake.params_path') }}"
    data-wake-secs="{{ (int) config('assistant.wake.listen_secs', 5) }}"
    @endif
>
    {{-- ── Chat panel ──────────────────────────────────────────────── --}}
    <div
        class="tulip-panel"
        x-show="open"
        x-transition.origin.bottom.right
        @keydown.escape.window="open = false"
        style="display:none;"
    >
        {{-- Header --}}
        <div class="tulip-header">
            <div class="tulip-header-id">
                <span class="tulip-avatar" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l1.9 4.6L18.5 9l-4.6 1.4L12 15l-1.9-4.6L5.5 9l4.6-1.4z"/>
                        <path d="M19 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>
                    </svg>
                </span>
                <div>
                    <div class="tulip-title" x-text="name"></div>
                    <div class="tulip-sub">Local &amp; private · on this machine</div>
                </div>
            </div>
            <div class="tulip-header-actions">
                <button type="button" class="tulip-icon-btn" :class="{ 'tulip-icon-active': speak }" @click="toggleSpeak()" :title="speak ? 'Voice replies: ON' : 'Voice replies: OFF'" aria-label="Toggle voice replies">
                    {{-- Speaker on --}}
                    <svg x-show="speak" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a9 9 0 0 1 0 14"/></svg>
                    {{-- Speaker muted --}}
                    <svg x-show="!speak" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>
                </button>
                <button type="button" class="tulip-icon-btn" title="New chat" @click="newChat()" aria-label="New chat">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                </button>
                <button type="button" class="tulip-icon-btn" title="Close" @click="open = false" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="tulip-messages" x-ref="scroll">
            <template x-for="(m, i) in messages" :key="i">
                <div>
                    {{-- Confirm card (clinical/financial actions) --}}
                    <template x-if="m.role === 'confirm'">
                        <div class="tulip-confirm">
                            <div class="tulip-confirm-head">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                <span x-text="(m.action.category || 'action').toUpperCase() + ' — needs your confirmation'"></span>
                            </div>
                            <div class="tulip-confirm-summary" x-text="m.action.summary"></div>
                            <div class="tulip-confirm-actions" x-show="!m.resolved">
                                <button type="button" class="tulip-confirm-yes" @click="confirmAction(m)" :disabled="m.busy">Confirm</button>
                                <button type="button" class="tulip-confirm-no" @click="cancelAction(m)" :disabled="m.busy">Cancel</button>
                            </div>
                            <div class="tulip-confirm-resolved" x-show="m.resolved" x-text="m.resolvedText"></div>
                        </div>
                    </template>
                    {{-- Normal message bubble --}}
                    <template x-if="m.role !== 'confirm'">
                        <div class="tulip-row" :class="'tulip-row-' + m.role">
                            <div class="tulip-bubble" :class="'tulip-bubble-' + m.role" x-text="m.text"></div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Thinking indicator --}}
            <div class="tulip-row tulip-row-assistant" x-show="loading">
                <div class="tulip-bubble tulip-bubble-assistant tulip-thinking">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>

        {{-- Tools-used hint --}}
        <div class="tulip-tools" x-show="toolsUsed.length" x-transition>
            <span>Used:</span>
            <template x-for="t in toolsUsed" :key="t"><span class="tulip-tool-chip" x-text="t"></span></template>
        </div>

        {{-- Composer --}}
        <form class="tulip-composer" @submit.prevent="send()">
            <button type="button" class="tulip-mic" :class="{ 'tulip-mic-rec': recording }" @click="toggleMic()" :disabled="transcribing" :title="recording ? 'Stop recording' : 'Speak to Tulip'" aria-label="Voice input">
                {{-- Idle: mic --}}
                <svg x-show="!recording && !transcribing" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
                {{-- Recording: stop square --}}
                <svg x-show="recording" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                {{-- Transcribing: spinner --}}
                <span x-show="transcribing" style="display:none;" class="tulip-spin"></span>
            </button>
            <textarea
                x-ref="input"
                x-model="input"
                rows="1"
                class="tulip-input"
                :placeholder="recording ? 'Listening…' : (transcribing ? 'Transcribing…' : 'Ask Tulip…')"
                @keydown.enter.prevent="if(!$event.shiftKey){ send() }"
                @input="autosize($event.target)"
            ></textarea>
            <button type="submit" class="tulip-send" :disabled="loading || !input.trim()" aria-label="Send">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg>
            </button>
        </form>
    </div>

    {{-- ── Floating button ─────────────────────────────────────────── --}}
    <button type="button" class="tulip-fab" @click="toggle()" :class="{ 'tulip-fab-open': open }" aria-label="Open assistant">
        <svg x-show="!open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l1.9 4.6L18.5 9l-4.6 1.4L12 15l-1.9-4.6L5.5 9l4.6-1.4z"/>
            <path d="M19 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>
        </svg>
        <svg x-show="open" style="display:none;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
</div>

<style>
    [x-cloak] { display: none !important; }

    #tulip-widget { position: fixed; right: 24px; bottom: 24px; z-index: 9998; font-family: 'DM Sans', system-ui, sans-serif; }

    /* ── Floating button ── */
    .tulip-fab {
        width: 56px; height: 56px; border-radius: 50%; border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--df-color-primary, #6a0f70), #b95cb7);
        box-shadow: 0 6px 20px rgba(106,15,112,0.40);
        transition: transform 160ms ease, box-shadow 160ms ease;
        position: relative; z-index: 2;
    }
    .tulip-fab:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 10px 26px rgba(106,15,112,0.50); }
    .tulip-fab-open { transform: scale(0.92); }

    /* ── Panel ── */
    .tulip-panel {
        position: absolute; right: 0; bottom: 70px;
        width: 380px; max-width: calc(100vw - 32px);
        height: 560px; max-height: calc(100vh - 120px);
        background: var(--df-surface, #fff);
        border: 1px solid var(--df-border-ui, #e0d4ea);
        border-radius: 16px; overflow: hidden;
        display: flex; flex-direction: column;
        box-shadow: 0 18px 50px rgba(14,1,24,0.28);
    }

    /* ── Header ── */
    .tulip-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 14px; flex-shrink: 0;
        background: linear-gradient(135deg, var(--df-color-primary, #6a0f70), #4e0a53);
        color: #fff;
    }
    .tulip-header-id { display: flex; align-items: center; gap: 10px; }
    .tulip-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.16);
    }
    .tulip-title { font-family: 'Cormorant Garamond', serif; font-size: 18px; font-weight: 600; line-height: 1; }
    .tulip-sub { font-size: 10.5px; opacity: 0.8; margin-top: 2px; letter-spacing: 0.02em; }
    .tulip-header-actions { display: flex; gap: 4px; }
    .tulip-icon-btn {
        background: rgba(255,255,255,0.12); border: none; color: #fff; cursor: pointer;
        width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        transition: background 140ms;
    }
    .tulip-icon-btn:hover { background: rgba(255,255,255,0.26); }
    .tulip-icon-active { background: rgba(255,255,255,0.34); }

    /* ── Messages ── */
    .tulip-messages {
        flex: 1; min-height: 0; overflow-y: auto; padding: 16px 14px;
        display: flex; flex-direction: column; gap: 10px;
        background: var(--df-bg, #f5eef9);
    }
    .tulip-messages::-webkit-scrollbar { width: 5px; }
    .tulip-messages::-webkit-scrollbar-thumb { background: rgba(185,92,183,0.30); border-radius: 4px; }

    .tulip-row { display: flex; }
    .tulip-row-user { justify-content: flex-end; }
    .tulip-row-assistant, .tulip-row-error { justify-content: flex-start; }

    .tulip-bubble {
        max-width: 84%; padding: 9px 13px; border-radius: 14px; font-size: 13.5px; line-height: 1.5;
        white-space: pre-wrap; word-wrap: break-word;
    }
    .tulip-bubble-user {
        background: linear-gradient(150deg, var(--df-color-primary, #6a0f70), #8e24aa);
        color: #fff; border-bottom-right-radius: 4px;
    }
    .tulip-bubble-assistant {
        background: var(--df-surface, #fff); color: var(--df-text, #1a0a24);
        border: 1px solid var(--df-border-ui, #e8dff0); border-bottom-left-radius: 4px;
    }
    .tulip-bubble-error {
        background: #fdeaea; color: #6b1010; border: 1px solid rgba(181,32,32,0.25); border-bottom-left-radius: 4px;
    }

    /* Thinking dots */
    .tulip-thinking { display: flex; gap: 4px; align-items: center; }
    .tulip-thinking span {
        width: 6px; height: 6px; border-radius: 50%; background: var(--df-color-primary, #6a0f70); opacity: 0.5;
        animation: tulipBounce 1.2s infinite ease-in-out;
    }
    .tulip-thinking span:nth-child(2) { animation-delay: 0.15s; }
    .tulip-thinking span:nth-child(3) { animation-delay: 0.30s; }
    @keyframes tulipBounce { 0%,80%,100% { transform: translateY(0); opacity: 0.4; } 40% { transform: translateY(-5px); opacity: 1; } }

    /* Confirm card (clinical/financial actions) */
    .tulip-confirm {
        margin: 2px 0; padding: 12px 14px; border-radius: 12px;
        background: #fff8ec; border: 1px solid #f0d8a8; border-left: 3px solid #a05c00;
    }
    .tulip-confirm-head {
        display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700;
        letter-spacing: 0.04em; color: #a05c00; margin-bottom: 6px;
    }
    .tulip-confirm-summary { font-size: 13px; color: #5c3500; line-height: 1.45; margin-bottom: 10px; }
    .tulip-confirm-actions { display: flex; gap: 8px; }
    .tulip-confirm-yes, .tulip-confirm-no {
        border: none; border-radius: 8px; padding: 7px 16px; font-size: 12.5px; font-weight: 600;
        cursor: pointer; font-family: inherit; transition: opacity 140ms, transform 120ms;
    }
    .tulip-confirm-yes { background: linear-gradient(150deg, var(--df-color-primary, #6a0f70), #8e24aa); color: #fff; }
    .tulip-confirm-no  { background: #fff; color: #7a6884; border: 1px solid #e0d4ea; }
    .tulip-confirm-yes:hover:not(:disabled), .tulip-confirm-no:hover:not(:disabled) { transform: translateY(-1px); }
    .tulip-confirm-yes:disabled, .tulip-confirm-no:disabled { opacity: 0.5; cursor: default; }
    .tulip-confirm-resolved { font-size: 12px; font-weight: 600; color: #1a7a45; }

    /* Tools hint */
    .tulip-tools {
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        padding: 6px 14px; font-size: 10.5px; color: var(--df-text-muted, #7a6884);
        background: var(--df-bg, #f5eef9); border-top: 1px solid var(--df-border-ui, #e8dff0);
    }
    .tulip-tool-chip { background: var(--df-color-light, #f3e8f4); color: var(--df-color-primary, #6a0f70); padding: 1px 7px; border-radius: 10px; font-family: 'DM Mono', monospace; }

    /* Composer */
    .tulip-composer {
        display: flex; align-items: flex-end; gap: 8px; padding: 10px 12px; flex-shrink: 0;
        background: var(--df-surface, #fff); border-top: 1px solid var(--df-border-ui, #e8dff0);
    }
    .tulip-input {
        flex: 1; resize: none; max-height: 120px; border: 1px solid var(--df-border-ui, #e0d4ea);
        border-radius: 10px; padding: 9px 12px; font-size: 13.5px; font-family: inherit;
        background: var(--df-input-bg, #fff); color: var(--df-text, #1a0a24); line-height: 1.4;
    }
    .tulip-input:focus { outline: none; border-color: var(--df-color-primary, #6a0f70); box-shadow: 0 0 0 3px rgba(106,15,112,0.14); }
    .tulip-send {
        width: 40px; height: 40px; flex-shrink: 0; border: none; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--df-color-primary, #6a0f70), #8e24aa);
        transition: opacity 140ms, transform 140ms;
    }
    .tulip-send:hover:not(:disabled) { transform: scale(1.05); }
    .tulip-send:disabled { opacity: 0.4; cursor: not-allowed; }

    /* Mic button */
    .tulip-mic {
        width: 40px; height: 40px; flex-shrink: 0; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        background: var(--df-surface, #fff); color: var(--df-color-primary, #6a0f70);
        border: 1px solid var(--df-border-ui, #e0d4ea); transition: all 140ms;
    }
    .tulip-mic:hover:not(:disabled) { background: var(--df-color-light, #f3e8f4); }
    .tulip-mic:disabled { opacity: 0.6; cursor: default; }
    .tulip-mic-rec { background: #fdeaea; border-color: #b52020; color: #b52020; animation: tulipPulse 1.3s infinite; }
    @keyframes tulipPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(181,32,32,0.30); }
        50%      { box-shadow: 0 0 0 6px rgba(181,32,32,0); }
    }
    .tulip-spin {
        width: 16px; height: 16px; border-radius: 50%;
        border: 2px solid rgba(106,15,112,0.25); border-top-color: var(--df-color-primary, #6a0f70);
        animation: tulipRot 0.7s linear infinite;
    }
    @keyframes tulipRot { to { transform: rotate(360deg); } }

    @media (max-width: 480px) {
        #tulip-widget { right: 16px; bottom: 16px; }
        .tulip-panel { width: calc(100vw - 24px); height: calc(100vh - 110px); }
    }
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tulipChat', () => ({
        open: false,
        messages: [],
        input: '',
        loading: false,
        conversationId: null,
        toolsUsed: [],
        name: 'Tulip',
        greeting: '',
        restored: false,
        recording: false,
        transcribing: false,
        mediaRecorder: null,
        audioChunks: [],
        speak: false,
        voice: null,
        wakeAutoSend: false,

        init() {
            const el = this.$el;
            this.name = el.dataset.name || 'Tulip';
            this.greeting = el.dataset.greeting || '';
            try { this.conversationId = localStorage.getItem('tulip_conversation_id') || null; } catch (e) {}
            try { this.speak = localStorage.getItem('tulip_speak') === '1'; } catch (e) {}
            // Voices load asynchronously — pick now and again when they arrive.
            this.loadVoice();
            if ('speechSynthesis' in window) {
                try { window.speechSynthesis.addEventListener('voiceschanged', () => this.loadVoice()); } catch (e) {}
            }
            // Wake word ("Hey Tulip") — only if configured.
            if (el.dataset.wakeEnabled === '1') {
                this.initWakeWord();
            }
        },

        // ── Wake word (on-device Porcupine) ─────────────────────────────────
        async initWakeWord() {
            const el = this.$el;
            const key = el.dataset.wakeKey;
            if (!key) return;
            try {
                const [{ PorcupineWorker }, { WebVoiceProcessor }] = await Promise.all([
                    import('https://cdn.jsdelivr.net/npm/@picovoice/porcupine-web@3.0.3/dist/esm/index.js'),
                    import('https://cdn.jsdelivr.net/npm/@picovoice/web-voice-processor@4.0.9/dist/esm/index.js'),
                ]);
                const worker = await PorcupineWorker.create(
                    key,
                    [{ publicPath: el.dataset.wakeKeyword, label: this.name }],
                    () => this.onWake(),
                    { publicPath: el.dataset.wakeParams },
                );
                await WebVoiceProcessor.subscribe(worker);
                this._wakeSecs = parseInt(el.dataset.wakeSecs || '5', 10);
            } catch (e) {
                console.warn('Tulip wake word failed to start:', e);
            }
        },

        onWake() {
            // Open the panel and listen for the spoken command, then auto-send.
            if (!this.open) this.open = true;
            this.$nextTick(() => {
                this.scrollBottom();
                if (!this.recording && !this.transcribing) {
                    this.wakeAutoSend = true;
                    this.startRecording();
                    // Auto-stop after the configured window (hands-free).
                    setTimeout(() => { if (this.recording) this.stopRecording(); }, (this._wakeSecs || 5) * 1000);
                }
            });
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                if (!this.restored && this.conversationId) { this.restore(); }
                else if (this.messages.length === 0) { this.messages.push({ role: 'assistant', text: this.greeting }); }
                this.$nextTick(() => { this.scrollBottom(); this.$refs.input && this.$refs.input.focus(); });
            }
        },

        newChat() {
            this.conversationId = null;
            try { localStorage.removeItem('tulip_conversation_id'); } catch (e) {}
            this.messages = [{ role: 'assistant', text: this.greeting }];
            this.toolsUsed = [];
            this.$nextTick(() => this.scrollBottom());
        },

        async restore() {
            this.restored = true;
            try {
                const res = await fetch('/assistant/conversation/' + this.conversationId, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF || '' },
                });
                if (!res.ok) throw new Error('restore failed');
                const data = await res.json();
                if (data.messages && data.messages.length) {
                    this.messages = data.messages.map(m => ({ role: m.role, text: m.content }));
                } else {
                    this.messages = [{ role: 'assistant', text: this.greeting }];
                }
            } catch (e) {
                this.conversationId = null;
                try { localStorage.removeItem('tulip_conversation_id'); } catch (e2) {}
                this.messages = [{ role: 'assistant', text: this.greeting }];
            }
            this.$nextTick(() => this.scrollBottom());
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.loading) return;

            this.messages.push({ role: 'user', text });
            this.input = '';
            if (this.$refs.input) this.$refs.input.style.height = 'auto';
            this.toolsUsed = [];
            this.loading = true;
            this.$nextTick(() => this.scrollBottom());

            try {
                const res = await fetch('/assistant/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        message: text,
                        conversation_id: this.conversationId,
                        page: window.location.pathname + ' — ' + document.title,
                    }),
                });
                const data = await res.json();

                if (data.conversation_id) {
                    this.conversationId = data.conversation_id;
                    try { localStorage.setItem('tulip_conversation_id', data.conversation_id); } catch (e) {}
                }

                if (data.error) {
                    this.messages.push({ role: 'error', text: data.error });
                } else {
                    this.messages.push({ role: 'assistant', text: data.reply || '(no reply)' });
                    this.toolsUsed = data.tools_used || [];
                    this.speakText(data.reply);
                    if (data.pending_action) {
                        this.messages.push({ role: 'confirm', action: data.pending_action, resolved: false, busy: false, resolvedText: '' });
                    }
                }
            } catch (e) {
                this.messages.push({ role: 'error', text: 'Network error — could not reach the server.' });
            } finally {
                this.loading = false;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        async confirmAction(m) {
            if (m.busy || m.resolved) return;
            m.busy = true;
            await this.resolveAction(m, '/assistant/confirm/' + m.action.id, 'Confirmed ✓');
        },

        async cancelAction(m) {
            if (m.busy || m.resolved) return;
            m.busy = true;
            await this.resolveAction(m, '/assistant/reject/' + m.action.id, 'Cancelled');
        },

        async resolveAction(m, url, label) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                const data = await res.json();
                m.resolved = true;
                m.resolvedText = label;
                if (data.reply) { this.messages.push({ role: 'assistant', text: data.reply }); this.speakText(data.reply); }
            } catch (e) {
                m.resolved = true;
                m.resolvedText = 'Error — try again';
            } finally {
                m.busy = false;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        // ── Spoken replies (text-to-speech) ─────────────────────────────────
        toggleSpeak() {
            this.speak = !this.speak;
            try { localStorage.setItem('tulip_speak', this.speak ? '1' : '0'); } catch (e) {}
            if (!this.speak && 'speechSynthesis' in window) window.speechSynthesis.cancel();
        },

        loadVoice() {
            if (!('speechSynthesis' in window)) return;
            const voices = window.speechSynthesis.getVoices() || [];
            if (!voices.length) return;
            const score = (v) => {
                const n = (v.name || '').toLowerCase();
                const l = (v.lang || '').toLowerCase();
                let s = 0;
                if (n.includes('heera')) s += 120;              // Windows Indian-English female
                if (l === 'en-in') s += 50;                     // English (India)
                if (/female|woman|zira|hazel|priya|aditi|raveena|neerja|swara|kalpana|google/.test(n)) s += 30;
                if (/male|ravi|david|mark|george|guy|prabhat/.test(n)) s -= 60;
                if (l.startsWith('en')) s += 5;
                if (l.startsWith('hi')) s += 8;
                return s;
            };
            this.voice = voices.slice().sort((a, b) => score(b) - score(a))[0] || null;
        },

        speakText(text) {
            if (!this.speak || !text) return;
            if (!('speechSynthesis' in window)) return;
            if (!this.voice) this.loadVoice();

            let toSay = text;
            if (text.length > 800) {
                // Long content (e.g. the huddle) — speak just the section
                // headlines (the "▸ Title (headline)" lines), not every detail.
                const heads = text.split('\n')
                    .filter(l => l.trim().startsWith('▸'))
                    .map(l => l.replace('▸', '').trim());
                toSay = heads.length ? ('Daily huddle. ' + heads.join('. ')) : text.slice(0, 400);
            }

            try {
                window.speechSynthesis.cancel();
                const u = new SpeechSynthesisUtterance(toSay);
                if (this.voice) u.voice = this.voice;
                u.lang  = (this.voice && this.voice.lang) || 'en-IN';
                u.rate  = 0.95;  // slightly slower = smoother
                u.pitch = 1.05;  // gently softer/feminine
                window.speechSynthesis.speak(u);
            } catch (e) {}
        },

        // ── Voice input (push-to-talk) ──────────────────────────────────────
        toggleMic() {
            if (this.transcribing) return;
            if (this.recording) { this.stopRecording(); }
            else { this.startRecording(); }
        },

        async startRecording() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.messages.push({ role: 'error', text: 'Microphone needs a secure page. Open the app via https or http://localhost.' });
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.audioChunks = [];
                this.mediaRecorder = new MediaRecorder(stream);
                this.mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size) this.audioChunks.push(e.data); };
                this.mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const type = (this.mediaRecorder && this.mediaRecorder.mimeType) || 'audio/webm';
                    const blob = new Blob(this.audioChunks, { type });
                    this.uploadAudio(blob);
                };
                this.mediaRecorder.start();
                this.recording = true;
            } catch (e) {
                this.recording = false;
                this.messages.push({ role: 'error', text: 'Couldn\'t use the microphone — please allow mic permission and try again.' });
            }
        },

        stopRecording() {
            if (this.mediaRecorder && this.recording) {
                this.recording = false;
                this.transcribing = true;
                try { this.mediaRecorder.stop(); } catch (e) { this.transcribing = false; }
            }
        },

        async uploadAudio(blob) {
            const fd = new FormData();
            fd.append('audio', blob, 'recording.webm');
            try {
                const res = await fetch('/assistant/transcribe', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: fd,
                });
                const data = await res.json();
                if (data.error) {
                    this.messages.push({ role: 'error', text: data.error });
                } else if (data.text) {
                    this.input = (this.input ? this.input.trim() + ' ' : '') + data.text;
                    this.$nextTick(() => { if (this.$refs.input) { this.$refs.input.focus(); this.autosize(this.$refs.input); } });
                    // Hands-free: if triggered by the wake word, send automatically.
                    if (this.wakeAutoSend) { this.wakeAutoSend = false; if (this.input.trim()) this.send(); }
                } else {
                    this.wakeAutoSend = false;
                    this.messages.push({ role: 'error', text: 'I didn\'t catch that — try again.' });
                }
            } catch (e) {
                this.wakeAutoSend = false;
                this.messages.push({ role: 'error', text: 'Transcription request failed.' });
            } finally {
                this.transcribing = false;
            }
        },

        autosize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        },

        scrollBottom() {
            const s = this.$refs.scroll;
            if (s) s.scrollTop = s.scrollHeight;
        },
    }));
});
</script>
