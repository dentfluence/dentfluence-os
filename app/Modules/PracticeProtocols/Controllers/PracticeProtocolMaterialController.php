<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PracticeProtocols\Models\PracticeProtocol;
use App\Modules\PracticeProtocols\Models\PracticeProtocolMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PracticeProtocolMaterialController extends Controller
{
    /** POST /practice-protocols/{protocol}/materials */
    public function store(Request $request, PracticeProtocol $protocol)
    {
        $data = $request->validate([
            'type'  => ['required', 'in:sop_steps,file,link'],
            'title' => ['required', 'string', 'max:255'],
            'steps' => ['nullable', 'string', 'max:5000'],          // one step per line
            'url'   => ['nullable', 'url', 'required_if:type,link'],
            'file'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120', 'required_if:type,file'],
        ]);

        $material = new PracticeProtocolMaterial([
            'practice_protocol_id' => $protocol->id,
            'type'                 => $data['type'],
            'title'                => $data['title'],
            'sort_order'           => (int) $protocol->materials()->max('sort_order') + 1,
        ]);

        if ($data['type'] === 'sop_steps') {
            // Split the textarea into clean, non-empty step lines.
            $steps = collect(preg_split('/\r\n|\r|\n/', (string) ($data['steps'] ?? '')))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->values()
                ->all();
            $material->body = $steps;
        } elseif ($data['type'] === 'link') {
            $material->url = $data['url'];
        } elseif ($data['type'] === 'file') {
            $material->file_path = $request->file('file')->store("practice-protocols/{$protocol->id}", 'local');
        }

        $material->save();

        return redirect()
            ->route('practice-protocols.edit', $protocol)
            ->with('success', 'Material added.');
    }

    /** DELETE /practice-protocols/materials/{material} */
    public function destroy(PracticeProtocolMaterial $material)
    {
        $protocolId = $material->practice_protocol_id;

        if ($material->file_path) {
            Storage::disk('local')->delete($material->file_path);
        }

        $material->delete();

        return redirect()
            ->route('practice-protocols.edit', $protocolId)
            ->with('success', 'Material removed.');
    }
}
