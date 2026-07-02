<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Modules\PracticeProtocols\Models\PracticeProtocol;
use App\Modules\PracticeProtocols\Requests\StorePracticeProtocolRequest;
use App\Modules\PracticeProtocols\Requests\UpdatePracticeProtocolRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PracticeProtocolController extends Controller
{
    /** GET /practice-protocols — catalog grouped by role. */
    public function index()
    {
        $protocols = PracticeProtocol::with(['role', 'materials'])
            ->orderBy('role_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy(fn ($p) => $p->role?->name ?? 'Unassigned');

        return view('practice-protocols.index', compact('protocols'));
    }

    /** GET /practice-protocols/create */
    public function create()
    {
        $protocol = new PracticeProtocol([
            'frequency' => 'daily',
            'priority'  => 'medium',
            'category'  => 'admin',
            'is_active' => true,
        ]);

        return view('practice-protocols.form', $this->formData($protocol));
    }

    /** POST /practice-protocols */
    public function store(StorePracticeProtocolRequest $request)
    {
        $protocol = PracticeProtocol::create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        // Land on edit so the admin can immediately attach SOP/materials.
        return redirect()
            ->route('practice-protocols.edit', $protocol)
            ->with('success', 'Protocol created. You can now add its SOP or materials below.');
    }

    /** GET /practice-protocols/{protocol}/edit */
    public function edit(PracticeProtocol $protocol)
    {
        $protocol->load('materials');

        return view('practice-protocols.form', $this->formData($protocol));
    }

    /** PUT /practice-protocols/{protocol} */
    public function update(UpdatePracticeProtocolRequest $request, PracticeProtocol $protocol)
    {
        $protocol->update($request->validated());

        return redirect()
            ->route('practice-protocols.index')
            ->with('success', 'Protocol updated.');
    }

    /** DELETE /practice-protocols/{protocol} — soft delete. */
    public function destroy(PracticeProtocol $protocol)
    {
        $protocol->delete();

        return redirect()
            ->route('practice-protocols.index')
            ->with('success', 'Protocol removed.');
    }

    /** Shared data for the create/edit form. */
    private function formData(PracticeProtocol $protocol): array
    {
        return [
            'protocol'   => $protocol,
            'roles'      => Role::orderBy('name')->get(),
            'branches'   => DB::table('branches')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => PracticeProtocol::CATEGORIES,
            'frequencies'=> PracticeProtocol::FREQUENCIES,
            'priorities' => PracticeProtocol::PRIORITIES,
        ];
    }
}
