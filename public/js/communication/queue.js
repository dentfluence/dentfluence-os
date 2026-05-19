/**
 * Queue JS — Live client-side filtering, card interactions, search
 * Works on the rendered queue without page reload for instant UX.
 */

document.addEventListener('DOMContentLoaded', () => {
    initQueueSearch();
    initCardHover();
    highlightUrgent();
});

// ── Live search across all cards ───────────────────────────────────────────
function filterQueue(query) {
    const q = query.toLowerCase().trim();
    const cards = document.querySelectorAll('.cm-card');

    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = (!q || text.includes(q)) ? '' : 'none';
    });

    // Show/hide section headers if all cards in section are hidden
    document.querySelectorAll('.cm-section').forEach(section => {
        const visible = [...section.querySelectorAll('.cm-card')]
            .some(c => c.style.display !== 'none');
        section.style.display = visible ? '' : 'none';
    });
}

function initQueueSearch() {
    const input = document.querySelector('.cm-search-input');
    if (!input) return;

    let debounceTimer;
    input.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => filterQueue(e.target.value), 200);
    });

    // Clear on Escape
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            input.value = '';
            filterQueue('');
            input.blur();
        }
    });
}

// ── Card hover: reveal action buttons ─────────────────────────────────────
function initCardHover() {
    // Cards already show actions — this adds keyboard focus support
    document.querySelectorAll('.cm-card').forEach(card => {
        card.setAttribute('tabindex', '0');

        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstBtn = card.querySelector('.cm-action-btn');
                if (firstBtn) firstBtn.focus();
            }
        });
    });
}

// ── Pulse overdue cards on load ────────────────────────────────────────────
function highlightUrgent() {
    const overdueCards = document.querySelectorAll('.cm-card.overdue');

    // Brief flash animation to draw attention
    overdueCards.forEach((card, i) => {
        card.style.animation = `none`;
        card.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            card.style.transform = 'translateX(2px)';
            setTimeout(() => {
                card.style.transform = '';
            }, 120);
        }, i * 80);
    });
}

// ── Card count badge updater (called after filter) ─────────────────────────
function updateSectionCounts() {
    document.querySelectorAll('.cm-section').forEach(section => {
        const visible = [...section.querySelectorAll('.cm-card')]
            .filter(c => c.style.display !== 'none').length;
        const badge = section.querySelector('.badge');
        if (badge) badge.textContent = visible;
    });
}

// ── Source filter quick buttons ────────────────────────────────────────────
// Allows clicking a stat card to filter by that metric
document.querySelectorAll('.cm-stat').forEach(stat => {
    stat.addEventListener('click', () => {
        document.querySelectorAll('.cm-stat').forEach(s => s.classList.remove('active'));
        stat.classList.add('active');
    });
});

// ── Keyboard shortcut: '/' to focus search ────────────────────────────────
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT'
        && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        const input = document.querySelector('.cm-search-input');
        if (input) input.focus();
    }
});
