{{-- Due Date Picker Component --}}
<div class="due-date-picker">
    <div class="due-date-presets">
        <button class="preset-btn" onclick="setDueDate('today')">Today</button>
        <button class="preset-btn" onclick="setDueDate('tomorrow')">Tomorrow</button>
        <button class="preset-btn" onclick="setDueDate('next_working')">Next Working Day</button>
        <button class="preset-btn" onclick="setDueDate('this_week')">This Week</button>
    </div>
    <div class="due-date-inputs">
        <div class="input-with-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <input type="date" class="form-input" name="due_date" value="{{ date('Y-m-d') }}">
        </div>
        <div class="input-with-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <select class="form-select" name="due_time">
                @foreach(['09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM', '06:00 PM', '07:00 PM'] as $time)
                <option value="{{ $time }}" {{ ($time === '11:00 AM') ? 'selected' : '' }}>{{ $time }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
