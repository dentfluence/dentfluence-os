/**
 * Tasks & Assignments — Session 6
 * Vanilla JS for all task interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    initTextareaCounters();
    initPrioritySelectors();
});

// ─── Tab Switching ─────────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // In real version, this would filter tasks or fetch from server
    const sections = document.querySelectorAll('.task-section');

    if (tab === 'overdue') {
        sections.forEach(s => s.style.display = 'none');
        document.getElementById('overdue-section')?.style.removeProperty('display');
    } else if (tab === 'completed') {
        sections.forEach(s => s.style.display = 'none');
        showToast('Completed tasks will be loaded here.');
    } else {
        sections.forEach(s => s.style.removeProperty('display'));
    }
}

// ─── Section Toggle ────────────────────────────────────────────
function toggleSection(sectionId) {
    const el = document.getElementById(sectionId);
    if (!el) return;

    const isOpen = el.style.display !== 'none';
    el.style.display = isOpen ? 'none' : '';

    // Rotate chevron
    const btn = el.previousElementSibling?.querySelector('.section-toggle svg');
    if (btn) {
        btn.style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
    }
}

// ─── Add Task Modal ────────────────────────────────────────────
function openAddTaskModal() {
    document.getElementById('add-task-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddTaskModal() {
    document.getElementById('add-task-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function saveTask() {
    // Validation placeholder
    const title = document.querySelector('#add-task-modal .form-input')?.value;
    if (!title?.trim()) {
        showToast('Please enter a task title.', 'error');
        return;
    }
    closeAddTaskModal();
    showToast('Task saved successfully!', 'success');
}

// ─── Escalation Modal ──────────────────────────────────────────
function openEscalationModal(taskId) {
    document.getElementById('escalation-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEscalationModal() {
    document.getElementById('escalation-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function confirmEscalation() {
    closeEscalationModal();
    showToast('Task has been escalated successfully!', 'success');
}

// ─── Task Actions ──────────────────────────────────────────────
function markComplete(taskId) {
    const card = document.querySelector(`[data-task-id="${taskId}"]`);
    if (card) {
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
        showToast('Task marked as complete!', 'success');

        setTimeout(() => {
            card.remove();
        }, 1500);
    }
}

function openAssignModal(taskId) {
    openAddTaskModal();
}

function openTaskDetail(taskId) {
    // Navigate to task detail — will be a full page or drawer in wiring session
    console.log('Opening task detail for ID:', taskId);
}

function openMoreMenu(taskId, event) {
    event.stopPropagation();
    // Context menu — will be implemented in wiring session
    console.log('More menu for task:', taskId);
}

function openBulkAssignModal() {
    showToast('Bulk assign — coming soon!');
}

function viewEscalated() {
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        if (btn.textContent.includes('Escalated')) {
            btn.click();
        }
    });
}

// ─── Filter by Assignee ────────────────────────────────────────
function filterByAssignee(name) {
    // In real app, filters the task list
    showToast(`Filtered by: ${name}`);
}

// ─── Priority Selector ─────────────────────────────────────────
function setPriority(priority, btn) {
    const parent = btn.closest('.priority-selector');
    parent.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function initPrioritySelectors() {
    document.querySelectorAll('.priority-selector').forEach(selector => {
        const buttons = selector.querySelectorAll('.priority-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', function () {
                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
}

// ─── Assignee Dropdown ─────────────────────────────────────────
function toggleAssigneeDropdown() {
    const dd = document.getElementById('assignee-dropdown');
    if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}

function selectAssignee(name, initial, color) {
    const display = document.querySelector('.assignee-select-display');
    if (display) {
        display.innerHTML = `
            <div class="assignee-avatar-sm" style="background: ${color}20; color: ${color}">${initial}</div>
            <span>${name}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        `;
    }
    const dd = document.getElementById('assignee-dropdown');
    if (dd) dd.style.display = 'none';
}

// ─── Textarea Counters ─────────────────────────────────────────
function initTextareaCounters() {
    document.querySelectorAll('.form-textarea').forEach(textarea => {
        const max = textarea.getAttribute('maxlength') || 500;
        const counter = textarea.nextElementSibling;

        if (counter?.classList.contains('char-count')) {
            textarea.addEventListener('input', function () {
                counter.textContent = `${this.value.length} / ${max}`;
            });
        }
    });
}

// ─── Toast Notification ────────────────────────────────────────
function showToast(message, type = 'info') {
    const existing = document.getElementById('prm-toast');
    if (existing) existing.remove();

    const colors = {
        success: '#38A169',
        error: '#E53E3E',
        info: '#5B4FBE',
    };

    const toast = document.createElement('div');
    toast.id = 'prm-toast';
    toast.style.cssText = `
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%);
        background: ${colors[type]};
        color: #fff;
        padding: 12px 22px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 500;
        box-shadow: 0 4px 20px rgba(0,0,0,0.18);
        z-index: 9999;
        animation: slideUp 0.25s ease;
        white-space: nowrap;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

// ─── Close modals on overlay click ────────────────────────────
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
        document.body.style.overflow = '';
    }
});

// ─── CSS for toast animation ───────────────────────────────────
const style = document.createElement('style');
style.textContent = `
@keyframes slideUp {
    from { transform: translateX(-50%) translateY(12px); opacity: 0; }
    to { transform: translateX(-50%) translateY(0); opacity: 1; }
}
`;
document.head.appendChild(style);
