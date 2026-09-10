{{--
    partials/handover.blade.php — N-2 Doctor handover (2026-09-09)

    "What should the desk do when this patient walks up?" — answered by the
    doctor with four taps and one optional line, saved with the same click as
    the consultation. It becomes the text of the front-desk popup.

    Plain inputs, no Alpine: drops into every consultation form (standard,
    minor visit, same issue, emergency) unchanged. Posts as handover[...]
    and is validated by App\Support\Handover::rules().

    Expected variables:
      $consultation   Consultation|null   (edit re-populates from ->handover)
--}}
@php
    $h = old('handover', $consultation?->handover ?? []);
    $h = is_array($h) ? $h : [];
@endphp
<div class="df-handover" style="margin-top:12px;padding:10px 12px;border:1px dashed #c9b3d1;border-radius:8px;background:#fcf8fd;">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:8px;">
        <span style="font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#6a0f70;">Message to Front Desk</span>
        <span style="font-size:10px;color:#9ca3af;font-style:italic;">Pops up at the desk the moment you save</span>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;font-size:12.5px;">
        <label style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;cursor:pointer;">
            <span>Collect ₹</span>
            <input type="number" name="handover[collect_amount]" min="0" step="1" inputmode="numeric"
                   value="{{ $h['collect_amount'] ?? '' }}" placeholder="0"
                   style="width:88px;padding:4px 6px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;">
        </label>

        <label style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;cursor:pointer;">
            <input type="checkbox" name="handover[offer_aocp]" value="1" {{ !empty($h['offer_aocp']) ? 'checked' : '' }}>
            <span>Offer AOCP</span>
        </label>

        <label style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;cursor:pointer;">
            <input type="checkbox" name="handover[xray]" value="1" {{ !empty($h['xray']) ? 'checked' : '' }}>
            <span>X-ray</span>
        </label>

        <label style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;cursor:pointer;">
            <span>Book in</span>
            <input type="number" name="handover[book_in_days]" min="1" max="365" step="1" inputmode="numeric"
                   value="{{ $h['book_in_days'] ?? '' }}" placeholder="—"
                   style="width:56px;padding:4px 6px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;">
            <span>days</span>
        </label>
    </div>

    <input type="text" name="handover[note]" maxlength="200"
           value="{{ $h['note'] ?? '' }}"
           placeholder="Anything else for the desk — e.g. explain the AOCP plan, patient wants an EMI"
           style="margin-top:8px;width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;">
</div>
