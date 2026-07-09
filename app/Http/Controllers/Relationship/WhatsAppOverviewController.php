<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\WaThread;

/**
 * WhatsAppOverviewController — PRE-native "all conversations" list.
 * ----------------------------------------------------------------------------
 * This is deliberately NOT the same page as the legacy standalone inbox at
 * /communication/whatsapp (WhatsAppInboxController). That page still carries
 * the pre-retirement Communication/PRM tab bar and is being kept in the
 * background, not linked to from anywhere (see feedback_pre_only_no_prm_links
 * memory). This controller exists so staff can still triage "all WhatsApp
 * conversations at once" without leaving PRE — it lives in the PRE subnav
 * (relationship/layouts/app.blade.php $relTabs) and every row links into
 * relationship.profile (the real chat lives there), never into
 * communication.whatsapp.show.
 */
class WhatsAppOverviewController extends Controller
{
    public function index()
    {
        $threads = WaThread::with(['patient', 'lead'])
            ->recent()
            ->paginate(25);

        $unreadTotal = (int) WaThread::sum('unread_count');

        return view('relationship.whatsapp.index', compact('threads', 'unreadTotal'));
    }
}
