{{--
    Materials manager for a protocol (edit mode).
    $protocol — PracticeProtocol (with materials loaded)
--}}
<div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-top:18px;">
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:19px;font-weight:700;color:#1a0320;margin:0 0 4px;">SOP &amp; Materials</h2>
    <p style="font-size:12px;color:#9a7aaa;margin:0 0 16px;">Attach a step-by-step SOP, a document, or a link. Staff see these on the task via “View SOP”.</p>

    {{-- Existing materials --}}
    @if($protocol->materials->count())
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">
        @foreach($protocol->materials as $m)
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;background:#faf6fd;border:1px solid #ede4f3;border-radius:8px;padding:11px 14px;">
            <div style="min-width:0;">
                <div style="font-size:13px;font-weight:600;color:#1a0320;">
                    {{ $m->title }}
                    <span style="font-size:10.5px;font-weight:600;color:#6a0f70;background:#f3e6f8;padding:1px 7px;border-radius:4px;margin-left:6px;">
                        {{ ['sop_steps'=>'SOP','file'=>'File','link'=>'Link'][$m->type] ?? $m->type }}
                    </span>
                </div>
                @if($m->isSop() && is_array($m->body))
                <ol style="margin:6px 0 0;padding-left:18px;font-size:12px;color:#4a3a52;line-height:1.6;">
                    @foreach($m->body as $step)<li>{{ $step }}</li>@endforeach
                </ol>
                @elseif($m->isLink())
                <a href="{{ $m->url }}" target="_blank" rel="noopener" style="font-size:12px;color:#1a5ea8;word-break:break-all;">{{ $m->url }}</a>
                @elseif($m->isFile())
                <span style="font-size:12px;color:#7a6a85;">{{ basename($m->file_path) }}</span>
                @endif
            </div>
            <form action="{{ route('practice-protocols.materials.destroy', $m) }}" method="POST"
                  onsubmit="return confirm('Remove this material?');" style="flex-shrink:0;">
                @csrf @method('DELETE')
                <button type="submit" style="background:none;border:none;color:#b52020;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;padding:0;">Remove</button>
            </form>
        </div>
        @endforeach
    </div>
    @else
    <p style="font-size:12.5px;color:#b0a0bb;margin:0 0 18px;">No materials yet.</p>
    @endif

    {{-- Add material --}}
    <div x-data="{ type: 'sop_steps' }" style="border-top:1.5px solid #f3eef7;padding-top:18px;">
        <form action="{{ route('practice-protocols.materials.store', $protocol) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div style="display:grid;grid-template-columns:200px 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Type</label>
                    <select name="type" x-model="type" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="sop_steps">SOP steps</option>
                        <option value="file">File upload</option>
                        <option value="link">Link</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Standard steps"
                           style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- SOP steps --}}
            <div x-show="type==='sop_steps'" x-cloak style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Steps — one per line</label>
                <textarea name="steps" rows="4" placeholder="Load and start the test cycle&#10;Wait for the cycle to complete&#10;Check the indicator result&#10;Attach a photo of the printout"
                          style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;resize:vertical;"></textarea>
            </div>

            {{-- File --}}
            <div x-show="type==='file'" x-cloak style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Document (JPG, PNG, PDF, DOC — max 5MB)</label>
                <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                       style="width:100%;padding:8px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">
            </div>

            {{-- Link --}}
            <div x-show="type==='link'" x-cloak style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">URL</label>
                <input type="url" name="url" placeholder="https://…"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>

            <button type="submit" style="padding:9px 18px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">
                Add material
            </button>
        </form>
    </div>
</div>
