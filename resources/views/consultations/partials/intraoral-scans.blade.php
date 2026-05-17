{{--
    partials/intraoral-scans.blade.php
    Section 4 — Intraoral Scans
    Alpine state: scanFiles[], handleScanUpload(), form.scan_date
    Files go to Laravel storage as scan_file_0, scan_file_1 … via FormData.
--}}
<div class="c-card" x-data="{open: {{ $consultation?->scan_date ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">4</span>Intraoral Scans</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span x-show="scanFiles.length > 0"
                  style="font-size:10px;font-weight:700;color:#16a34a;"
                  x-text="scanFiles.length + ' file(s)'"></span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;">

        {{-- Drop zone --}}
        <div style="border:2px dashed #d1d5db;border-radius:10px;padding:28px 16px;text-align:center;cursor:pointer;transition:all .15s;background:#fafafa;"
             @click="document.getElementById('scan-upload').click()"
             onmouseover="this.style.borderColor='#6a0f70';this.style.background='#faf5fb';"
             onmouseout="this.style.borderColor='#d1d5db';this.style.background='#fafafa';">
            <div style="width:44px;height:44px;border-radius:50%;background:#f5f3ff;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
            </div>
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:3px;">Upload Scan Files</div>
            <div style="font-size:11px;color:#9ca3af;">STL · DICOM · JPG · PNG — Multiple files allowed</div>
            <input type="file"
                   id="scan-upload"
                   name="scan_files[]"
                   multiple
                   accept=".stl,.dcm,.jpg,.jpeg,.png,.pdf"
                   style="display:none;"
                   @change="handleScanUpload($event)">
        </div>

        {{-- Newly selected files --}}
        <template x-if="scanFiles.length > 0">
            <div style="margin-top:12px;">
                <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                    Queued for Upload
                </div>
                <template x-for="(f, i) in scanFiles" :key="i">
                    <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f9fafb;border-radius:6px;margin-bottom:4px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <div style="flex:1;overflow:hidden;">
                            <div x-text="f.name" style="font-size:12px;color:#374151;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                            <div x-text="(f.size/1024/1024).toFixed(2)+' MB'" style="font-size:10px;color:#9ca3af;"></div>
                        </div>
                        <button type="button" @click="scanFiles.splice(i,1)"
                                style="background:none;border:1px solid #fecaca;border-radius:4px;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:10px;font-weight:600;">✕</button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Previously saved scan files (edit mode) --}}
        @if($consultation && $consultation->scan_files)
            @php $saved = is_array($consultation->scan_files) ? $consultation->scan_files : json_decode($consultation->scan_files, true) ?? []; @endphp
            @if(count($saved))
            <div style="margin-top:12px;">
                <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                    Previously Saved
                </div>
                @foreach($saved as $path)
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;margin-bottom:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span style="font-size:12px;color:#374151;flex:1;">{{ basename($path) }}</span>
                    <a href="{{ asset('storage/'.$path) }}" target="_blank"
                       style="font-size:10px;color:#2563eb;font-weight:600;text-decoration:none;">View</a>
                </div>
                @endforeach
            </div>
            @endif
        @endif

        {{-- Scan date --}}
        <div style="margin-top:14px;">
            <label class="df-label">Scan Date</label>
            <input type="date"
                   name="scan_date"
                   x-model="form.scan_date"
                   class="df-input"
                   style="max-width:220px;"
                   value="{{ old('scan_date', $consultation?->scan_date) }}">
        </div>
    </div>
</div>
