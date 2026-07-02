/**
 * Dentfluence PRM — Website Chatbot widget (Phase 6)
 * ----------------------------------------------------------------------------
 * Drop-in, dependency-free chat widget. Runs a short scripted qualification
 * (treatment → name → phone) in the visitor's browser, then POSTs the result
 * to the PRM, where the lead is auto-assigned, gets a follow-up, and is
 * AI-enriched.
 *
 * Embed on any site:
 *   <script src="https://YOURDOMAIN/js/prm-chatbot.js"
 *           data-endpoint="https://YOURDOMAIN/api/webhooks/prm/chatbot"
 *           data-clinic="Your Clinic Name"
 *           data-greeting="Hi! How can we help with your smile today?"
 *           data-treatments="Dental Implant,Teeth Whitening,Braces / Orthodontics,Root Canal Treatment,Smile Makeover,Other">
 *   </script>
 */
(function () {
    var script = document.currentScript;
    var cfg = {
        endpoint:   script.getAttribute('data-endpoint') || '/api/webhooks/prm/chatbot',
        clinic:     script.getAttribute('data-clinic') || 'our clinic',
        greeting:   script.getAttribute('data-greeting') || 'Hi! 👋 How can we help with your smile today?',
        treatments: (script.getAttribute('data-treatments') ||
            'Dental Implant,Teeth Whitening,Braces / Orthodontics,Root Canal Treatment,Smile Makeover,Other')
            .split(',').map(function (t) { return t.trim(); }),
        accent: script.getAttribute('data-accent') || '#534AB7',
    };

    // ── Styles ────────────────────────────────────────────────────────────────
    var css = `
    .pcb-bubble{position:fixed;bottom:22px;right:22px;width:58px;height:58px;border-radius:50%;
      background:${cfg.accent};color:#fff;border:none;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.25);
      font-size:26px;z-index:2147483000;display:flex;align-items:center;justify-content:center;}
    .pcb-panel{position:fixed;bottom:90px;right:22px;width:340px;max-width:calc(100vw - 32px);height:460px;
      max-height:calc(100vh - 120px);background:#fff;border-radius:16px;box-shadow:0 16px 50px rgba(0,0,0,.28);
      z-index:2147483000;display:none;flex-direction:column;overflow:hidden;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;}
    .pcb-panel.pcb-open{display:flex;}
    .pcb-head{background:${cfg.accent};color:#fff;padding:14px 16px;font-weight:600;display:flex;align-items:center;justify-content:space-between;}
    .pcb-head small{display:block;font-weight:400;opacity:.85;font-size:11px;margin-top:2px;}
    .pcb-x{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;}
    .pcb-body{flex:1;overflow-y:auto;padding:14px;background:#F7F6FB;}
    .pcb-msg{max-width:80%;padding:9px 12px;border-radius:12px;margin-bottom:9px;font-size:13.5px;line-height:1.45;}
    .pcb-bot{background:#fff;color:#222;border:1px solid #eee;border-bottom-left-radius:3px;}
    .pcb-user{background:${cfg.accent};color:#fff;margin-left:auto;border-bottom-right-radius:3px;}
    .pcb-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
    .pcb-chip{background:#fff;border:1px solid ${cfg.accent};color:${cfg.accent};border-radius:18px;
      padding:6px 11px;font-size:12.5px;cursor:pointer;}
    .pcb-chip:hover{background:${cfg.accent};color:#fff;}
    .pcb-foot{display:flex;gap:6px;padding:10px;border-top:1px solid #eee;background:#fff;}
    .pcb-foot input{flex:1;border:1px solid #ddd;border-radius:10px;padding:9px 11px;font-size:13px;outline:none;}
    .pcb-foot button{background:${cfg.accent};color:#fff;border:none;border-radius:10px;padding:0 14px;cursor:pointer;font-size:14px;}
    .pcb-foot[hidden]{display:none;}`;
    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    // ── DOM ─────────────────────────────────────────────────────────────────────
    var bubble = document.createElement('button');
    bubble.className = 'pcb-bubble';
    bubble.innerHTML = '💬';
    bubble.setAttribute('aria-label', 'Chat with us');

    var panel = document.createElement('div');
    panel.className = 'pcb-panel';
    panel.innerHTML =
        '<div class="pcb-head"><div>' + esc(cfg.clinic) + '<small>Typically replies in minutes</small></div>' +
        '<button class="pcb-x" aria-label="Close">×</button></div>' +
        '<div class="pcb-body" id="pcbBody"></div>' +
        '<div class="pcb-foot" id="pcbFoot"><input id="pcbInput" type="text" autocomplete="off"><button id="pcbSend">➤</button></div>';

    document.body.appendChild(bubble);
    document.body.appendChild(panel);

    var body  = panel.querySelector('#pcbBody');
    var foot  = panel.querySelector('#pcbFoot');
    var input = panel.querySelector('#pcbInput');

    // ── State machine ────────────────────────────────────────────────────────────
    var data = { treatment: '', name: '', phone: '', message: '' };
    var step = 'treatment';
    var started = false;

    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function scroll(){ body.scrollTop = body.scrollHeight; }
    function botMsg(t){ var m=document.createElement('div'); m.className='pcb-msg pcb-bot'; m.textContent=t; body.appendChild(m); scroll(); }
    function userMsg(t){ var m=document.createElement('div'); m.className='pcb-msg pcb-user'; m.textContent=t; body.appendChild(m); scroll(); }

    function showChips(options){
        var wrap=document.createElement('div'); wrap.className='pcb-chips';
        options.forEach(function(opt){
            var b=document.createElement('button'); b.className='pcb-chip'; b.textContent=opt;
            b.onclick=function(){ wrap.remove(); handle(opt); };
            wrap.appendChild(b);
        });
        body.appendChild(wrap); scroll();
    }

    function start(){
        if (started) return; started = true;
        botMsg(cfg.greeting);
        setTimeout(function(){
            botMsg('What treatment are you interested in?');
            showChips(cfg.treatments);
            foot.hidden = true; // use chips first
        }, 350);
    }

    function handle(value){
        value = (value || '').trim();
        if (!value) return;
        userMsg(value);

        if (step === 'treatment') {
            data.treatment = value;
            step = 'name';
            foot.hidden = false; input.placeholder = 'Type your name…'; input.focus();
            setTimeout(function(){ botMsg('Great choice! May I have your name?'); }, 300);
        } else if (step === 'name') {
            data.name = value;
            step = 'phone';
            input.placeholder = 'Your phone number…';
            setTimeout(function(){ botMsg('Thanks ' + data.name + '! And your phone number so we can help you book?'); }, 300);
        } else if (step === 'phone') {
            var digits = value.replace(/\D/g, '');
            if (digits.length < 7) { botMsg('That doesn\'t look like a valid number — could you re-enter it?'); return; }
            data.phone = value;
            step = 'sending';
            foot.hidden = true;
            submit();
        }
    }

    function submit(){
        botMsg('One moment…');
        fetch(cfg.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data),
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res && res.success) {
                botMsg('Thank you, ' + data.name + '! 🦷 Our team will reach out shortly about ' + data.treatment + '.');
            } else {
                botMsg('Sorry, something went wrong. Please call us or try again.');
            }
        })
        .catch(function(){ botMsg('Sorry, we couldn\'t send that. Please try again later.'); });
    }

    // ── Wiring ────────────────────────────────────────────────────────────────────
    function openPanel(){ panel.classList.add('pcb-open'); start(); }
    function closePanel(){ panel.classList.remove('pcb-open'); }
    bubble.onclick = function(){ panel.classList.contains('pcb-open') ? closePanel() : openPanel(); };
    panel.querySelector('.pcb-x').onclick = closePanel;
    panel.querySelector('#pcbSend').onclick = function(){ var v=input.value; input.value=''; handle(v); };
    input.addEventListener('keydown', function(e){ if (e.key==='Enter'){ var v=input.value; input.value=''; handle(v); } });
})();
