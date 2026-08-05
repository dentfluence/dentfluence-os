# WhatsApp Cleanup V1 — Parked / Future Improvements
**These items are intentionally deferred. None are part of Cleanup V1. Do not begin any of these without a separate scoping decision.**

- **WhatsApp Inbox review** — `WhatsAppInboxController` and its routes/views still exist, unlinked from nav, unmodified. A decision on whether to formally remove it or repurpose it belongs to the PRE Communication UX phase, not this cleanup.
- **Marketing WhatsApp UI** — the campaign compose panel and `IntegrationController` WhatsApp settings form remain exactly as they were (confirmed non-functional for sending, per the original audit). Untouched.
- **PRE Communication UX** — the full experience layer (how staff and automations actually use communication day to day) is explicitly the subject of the next phase, per your direction. Nothing in this cleanup anticipated or designed for it.
- **Automation Rules UI** — no interface exists for configuring which `ActivityEngine` events trigger staff notifications or downstream actions (e.g. `RulesEngine` rules for `communication.confirmed`/`communication.reschedule_requested`). Currently config-file-only, and no config entry has been added yet.
- **Manual Communication UI** — no dedicated interface for staff-initiated, ad hoc communication distinct from the existing per-record buttons (prescription send, birthday send, etc.).
- **Campaign Builder** — bulk/scheduled communication campaigns across channels remain unbuilt (the old Marketing WhatsApp panel was never a working version of this).
- **Communication Dashboard** — no aggregate view of communication activity/effectiveness across channels exists.
- **Communication Settings** — no unified settings surface for channel configuration, templates, or consent policy exists; today these live scattered across `config/whatsapp.php`, `config/communication.php`, and `config/prm.php`.
- **Future channel expansion (SMS, Email, etc.)** — `communication.*` event naming was chosen specifically so these channels can write the same events later, but no SMS or email channel driver exists yet, and none was built in this cleanup.

None of the above were touched, designed around, or scaffolded for during Cleanup V1. They are named here only so they aren't lost track of before the next phase's scoping conversation.
