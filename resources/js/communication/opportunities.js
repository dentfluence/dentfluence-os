// resources/js/communication/opportunities.js
// Session 7 — Opportunity Engine UI

document.addEventListener('DOMContentLoaded', function () {
    initDragDrop();
});

// View switching
function switchView(view) {
    const boardEl = document.getElementById('view-board');
    const listEl = document.getElementById('view-list');
    const btnBoard = document.getElementById('btn-board');
    const btnList = document.getElementById('btn-list');

    if (view === 'board') {
        boardEl.style.display = 'flex';
        listEl.style.display = 'none';
        btnBoard.classList.add('active');
        btnList.classList.remove('active');
    } else {
        boardEl.style.display = 'none';
        listEl.style.display = 'block';
        btnBoard.classList.remove('active');
        btnList.classList.add('active');
    }
}

// Drag and drop
function initDragDrop() {
    const cards = document.querySelectorAll('.opp-card');
    const columns = document.querySelectorAll('.opp-col-body');

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            document.querySelectorAll('.opp-col-body').forEach(col => col.classList.remove('drag-over'));
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            col.classList.add('drag-over');
            const dragging = document.querySelector('.dragging');
            const addBtn = col.querySelector('.opp-add-card-btn');
            if (dragging && addBtn) col.insertBefore(dragging, addBtn);
        });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('drag-over');
            updateColumnCounts();
        });
    });
}

function updateColumnCounts() {
    document.querySelectorAll('.opp-column').forEach(col => {
        const key = col.dataset.stage;
        const count = col.querySelectorAll('.opp-card').length;
        const badge = col.querySelector('.opp-col-badge');
        if (badge) badge.textContent = count;
    });
}

// Add Opportunity Modal
function openAddOpportunityModal(stage = null) {
    document.getElementById('add-opp-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddOpportunityModal(event) {
    if (event && event.target !== document.getElementById('add-opp-modal')) return;
    document.getElementById('add-opp-modal').style.display = 'none';
    document.body.style.overflow = '';
}

// Convert to PRM Modal
function openConvertModal(name) {
    const modal = document.getElementById('convert-prm-modal');
    if (name) {
        document.getElementById('convert-name').textContent = name;
        const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2);
        document.getElementById('convert-avatar').textContent = initials;
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeConvertModal(event) {
    if (event && event.target !== document.getElementById('convert-prm-modal')) return;
    document.getElementById('convert-prm-modal').style.display = 'none';
    document.body.style.overflow = '';
}

// Opportunity detail (placeholder for a drawer or page)
function openOpportunityDetail(el) {
    // Future: navigate to detail page or open drawer
    console.log('Open opportunity detail');
}

function toggleCardMenu(btn) {
    // Future: show context menu
}

function openFiltersModal() {
    // Future: open full filters modal
    alert('Filters coming soon');
}

// ESC key closes modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAddOpportunityModal();
        closeConvertModal();
    }
});
