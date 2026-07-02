{{-- resources/views/components/followup/filter-sort-modal.blade.php --}}

<div class="fu-modal-overlay" id="filterModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Filters & Sort</h3>
            <button class="fu-modal-close" onclick="closeModal('filterModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fu-modal-tabs">
            <button class="fu-modal-tab fu-modal-tab-active" onclick="switchModalTab('filters', this)">Filters</button>
            <button class="fu-modal-tab" onclick="switchModalTab('sort', this)">Sort</button>
        </div>
        <div class="fu-modal-body" id="filterTabContent">
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Status</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Statuses</option><option>New Lead</option><option>Contacted</option><option>Interested</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Source</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Sources</option><option>Call</option><option>WhatsApp</option><option>Walk-in</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Follow-up Type</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Types</option><option>Call</option><option>WhatsApp</option><option>Clinic Visit</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Priority</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Priorities</option><option>High</option><option>Medium</option><option>Low</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Assigned To</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Users</option><option>Neha</option><option>Anjali</option><option>Priya</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Team / Department</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>All Teams</option><option>Front Desk</option><option>Treatment Coordinator</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Date Range</label>
                    <div class="fu-input-icon-wrap">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="text" class="fu-input fu-input-icon" value="18 May 2025 – 24 May 2025" readonly>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Next Follow-up Date</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select"><option>Anytime</option><option>Today</option><option>Tomorrow</option><option>This week</option></select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Communication Channel</label>
                <div class="fu-checkbox-inline-group">
                    <label class="fu-checkbox-inline"><input type="checkbox" checked class="fu-checkbox"> Call</label>
                    <label class="fu-checkbox-inline"><input type="checkbox" checked class="fu-checkbox"> WhatsApp</label>
                    <label class="fu-checkbox-inline"><input type="checkbox" class="fu-checkbox"> SMS</label>
                    <label class="fu-checkbox-inline"><input type="checkbox" class="fu-checkbox"> Email</label>
                    <label class="fu-checkbox-inline"><input type="checkbox" checked class="fu-checkbox"> Clinic Visit</label>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Tags</label>
                <div class="fu-select-wrap">
                    <select class="fu-select"><option value="">Select tags</option><option>High Value</option><option>Dental Implant</option><option>Walk-in</option></select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
        </div>
        <div class="fu-modal-footer fu-modal-footer-space">
            <button class="fu-btn-ghost" onclick="clearFilters()">Clear All</button>
            <div>
                <button class="fu-btn-outline" onclick="closeModal('filterModal')">Cancel</button>
                <button class="fu-btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>
