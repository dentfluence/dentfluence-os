@extends('layouts.app')
@section('page-title', 'Inventory — Vendors')
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Vendors · {{ $vendors->total() }} suppliers</div>
    </div>
    <div class="df-page-actions">
        <button onclick="document.getElementById('modal-add-vendor').style.display='flex'"
            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6a0f70;color:#fff;border-radius:3px;font-size:13px;font-weight:500;border:none;cursor:pointer;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Vendor
        </button>
    </div>
</div>

@include('inventory.partials.subnav')

@if(session('success'))
<div style="padding:10px 16px;background:#e8f7ef;border-left:3px solid #1a7a45;border-radius:3px;font-size:13px;color:#0e4a28;margin-bottom:16px;display:flex;justify-content:space-between;">
    {{ session('success') }}<button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;">✕</button>
</div>
@endif

<div class="df-card" style="overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Vendor</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Contact</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Phone / WhatsApp</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">GST No.</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Credit Days</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendors as $vendor)
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:12px 18px;">
                    <div style="font-weight:500;color:#1e0a2c;">{{ $vendor->vendor_name }}</div>
                    @if($vendor->city)<div style="font-size:11px;color:#9a85aa;">{{ $vendor->city }}{{ $vendor->state ? ', '.$vendor->state : '' }}</div>@endif
                </td>
                <td style="padding:12px 14px;">
                    <div style="font-size:13px;color:#2e1040;">{{ $vendor->contact_person ?? '—' }}</div>
                    @if($vendor->email)<div style="font-size:11px;color:#9a85aa;">{{ $vendor->email }}</div>@endif
                </td>
                <td style="padding:12px 14px;">
                    @if($vendor->phone)
                    <a href="tel:{{ $vendor->phone }}" style="font-size:13px;color:#1a5ea8;text-decoration:none;">{{ $vendor->phone }}</a>
                    @endif
                    @if($vendor->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/','',$vendor->whatsapp) }}" target="_blank"
                        style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#1a7a45;text-decoration:none;margin-top:2px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="#1a7a45"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WA
                    </a>
                    @endif
                    @if(!$vendor->phone && !$vendor->whatsapp)<span style="color:#9a85aa;">—</span>@endif
                </td>
                <td style="padding:12px 14px;font-family:'Inter', sans-serif;font-size:12px;color:#4e2060;">{{ $vendor->gst_no ?? '—' }}</td>
                <td style="padding:12px 14px;text-align:center;font-size:13px;color:#1e0a2c;">{{ $vendor->credit_days ?: '—' }}{{ $vendor->credit_days ? ' days' : '' }}</td>
                <td style="padding:12px 18px;text-align:center;">
                    @if($vendor->is_active)
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;background:#e8f7ef;color:#1a7a45;font-weight:500;">
                        <span style="width:5px;height:5px;border-radius:50%;background:#1a7a45;"></span>Active
                    </span>
                    @else
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;background:#f0f0f0;color:#888;font-weight:500;">
                        Inactive
                    </span>
                    @endif
                </td>
                <td style="padding:12px 14px;text-align:center;">
                    <button onclick='openEditVendor({{ $vendor->toJson() }})'
                            style="background:#f5f0f8;border:1px solid rgba(106,15,112,0.12);
                                   border-radius:4px;padding:5px 12px;font-size:12px;
                                   font-family:'Inter',sans-serif;color:#6a0f70;cursor:pointer;">
                        Edit
                    </button>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:48px;text-align:center;color:#9a85aa;">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.2)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                No vendors yet. Add your first supplier.
            </td></tr>
            @endforelse
        </tbody>
    </table>
    @if($vendors->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $vendors->links() }}</div>
    @endif
</div>

{{-- ADD VENDOR MODAL --}}
<div id="modal-add-vendor" style="display:none;position:fixed;inset:0;z-index:60;align-items:center;justify-content:center;background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(14,1,24,0.25);margin:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(185,92,183,0.10);background:#faf5fb;">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#1e0a2c;">Add Vendor</div>
                <div style="font-size:12px;color:#9a85aa;margin-top:2px;">Add a new supplier to your network</div>
            </div>
            <button onclick="document.getElementById('modal-add-vendor').style.display='none'" style="background:none;border:none;cursor:pointer;color:#9a85aa;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form action="{{ route('inventory.vendors.store') }}" method="POST" style="padding:24px;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Vendor / Company Name *</label>
                    <input type="text" name="vendor_name" required placeholder="e.g. Dentsply India Pvt Ltd"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Contact Person</label>
                    <input type="text" name="contact_person" placeholder="Sales rep name"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Phone</label>
                    <input type="text" name="phone" placeholder="+91 98765 43210"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">WhatsApp</label>
                    <input type="text" name="whatsapp" placeholder="+91 98765 43210"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Email</label>
                    <input type="email" name="email" placeholder="vendor@example.com"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">GST Number</label>
                    <input type="text" name="gst_no" placeholder="29AABCU9603R1ZX"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">City</label>
                    <input type="text" name="city" placeholder="Mumbai"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Credit Days</label>
                    <input type="number" name="credit_days" value="0" min="0" placeholder="0"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Address</label>
                    <textarea name="address" rows="2" placeholder="Full address"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid rgba(185,92,183,0.08);">
                <button type="submit" style="flex:1;padding:10px;background:#6a0f70;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;">Save Vendor</button>
                <button type="button" onclick="document.getElementById('modal-add-vendor').style.display='none'" style="padding:10px 20px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- EDIT VENDOR MODAL --}}
<div id="modal-edit-vendor" style="display:none;position:fixed;inset:0;z-index:60;align-items:center;justify-content:center;background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(14,1,24,0.25);margin:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(185,92,183,0.10);background:#faf5fb;">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#1e0a2c;">Edit Vendor</div>
                <div id="edit-vendor-subtitle" style="font-size:12px;color:#9a85aa;margin-top:2px;"></div>
            </div>
            <button onclick="document.getElementById('modal-edit-vendor').style.display='none'" style="background:none;border:none;cursor:pointer;color:#9a85aa;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="form-edit-vendor" method="POST" action="" style="padding:24px;">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Vendor / Company Name *</label>
                    <input type="text" id="ev-vendor_name" name="vendor_name" required
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Contact Person</label>
                    <input type="text" id="ev-contact_person" name="contact_person"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Phone</label>
                    <input type="text" id="ev-phone" name="phone"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">WhatsApp</label>
                    <input type="text" id="ev-whatsapp" name="whatsapp"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Email</label>
                    <input type="email" id="ev-email" name="email"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">GST Number</label>
                    <input type="text" id="ev-gst_no" name="gst_no"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">City</label>
                    <input type="text" id="ev-city" name="city"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Credit Days</label>
                    <input type="number" id="ev-credit_days" name="credit_days" min="0"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Address</label>
                    <textarea id="ev-address" name="address" rows="2"
                        style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid rgba(185,92,183,0.08);">
                <button type="submit" style="flex:1;padding:10px;background:#6a0f70;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;">Save Changes</button>
                <button type="button" onclick="document.getElementById('modal-edit-vendor').style.display='none'" style="padding:10px 20px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">Cancel</button>
            </div>
        </form>
    </div>
</div>

@endsection
@push('scripts')
<script>
document.getElementById('modal-add-vendor').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
document.getElementById('modal-edit-vendor').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });

function openEditVendor(v) {
    document.getElementById('ev-vendor_name').value    = v.vendor_name || '';
    document.getElementById('ev-contact_person').value = v.contact_person || '';
    document.getElementById('ev-phone').value          = v.phone || '';
    document.getElementById('ev-whatsapp').value       = v.whatsapp || '';
    document.getElementById('ev-email').value          = v.email || '';
    document.getElementById('ev-gst_no').value         = v.gst_no || '';
    document.getElementById('ev-city').value           = v.city || '';
    document.getElementById('ev-credit_days').value    = v.credit_days || '';
    document.getElementById('ev-address').value        = v.address || '';
    document.getElementById('edit-vendor-subtitle').textContent = 'Editing: ' + v.vendor_name;
    document.getElementById('form-edit-vendor').action = '/inventory/vendors/' + v.id;
    document.getElementById('modal-edit-vendor').style.display = 'flex';
}
</script>
@endpush
