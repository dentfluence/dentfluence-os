<div id="appointmentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:white; border-radius:12px; width:520px; padding:24px;">

    {{-- Close button --}}
    <button onclick="closeModal()">✕</button>

    {{-- Tab buttons at top --}}
    <div id="modalTabs">
      <button onclick="switchTab('appointment')">Appointment</button>
      <button onclick="switchTab('walkin')">Walk-In</button>
      <button onclick="switchTab('prm')">PRM Call</button>
      <button onclick="switchTab('reminder')">Reminder</button>
      <button onclick="switchTab('followup')">Follow-up</button>
    </div>

    {{-- Each tab's form --}}
    <div id="tab-appointment">
      {{-- your existing appointment form fields here --}}
    </div>

    <div id="tab-walkin" style="display:none">
      {{-- quick walk-in fields --}}
    </div>

    <div id="tab-prm" style="display:none">
      {{-- PRM call fields --}}
    </div>

    {{-- ... and so on --}}

  </div>
</div>

<script>
function openModal(tab, patientId) {
    document.getElementById('appointmentModal').style.display = 'flex';
    switchTab(tab || 'appointment');
    // optionally pre-fill patient ID
}

function closeModal() {
    document.getElementById('appointmentModal').style.display = 'none';
}

{{-- COMBINED MODAL --}}
<div id="combined-modal-backdrop" class="modal-backdrop-custom" onclick="closeCombinedModal()"></div>
<div id="combined-modal" class="modal-custom">
    <div class="modal-custom-header">
        <div class="modal-custom-title" id="combined-modal-title">New Appointment</div>
        <button onclick="closeCombinedModal()" style="border:none;background:none;font-size:18px;color:#94a3b8;cursor:pointer;">✕</button>
    </div>
    <div class="modal-tabs">
        <button class="modal-tab-btn active" id="tab-btn-appointment" onclick="switchModalTab('appointment')">Appointment</button>
        <button class="modal-tab-btn" id="tab-btn-walkin" onclick="switchModalTab('walkin')">Walk-In</button>
    </div>

    {{-- Picker popups OUTSIDE modal (avoid transform stacking context) --}}
<div class="dp-popup" id="am-dp-popup"></div>
<div class="tp-popup" id="am-tp-popup"></div>
<div class="tp-popup" id="wi-tp-popup"></div>

function switchTab(tab) {
    // hide all tabs
    ['appointment','walkin','prm','reminder','followup'].forEach(t => {
        document.getElementById('tab-' + t).style.display = 'none';
    });
    // show selected
    document.getElementById('tab-' + tab).style.display = 'block';
}
</script>