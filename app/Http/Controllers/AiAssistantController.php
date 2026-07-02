<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AiAssistantController — HTTP endpoints for the "Tulip" chat widget (A3).
 * ----------------------------------------------------------------------------
 * Thin layer over AssistantService. All routes are behind 'auth', so we always
 * have the logged-in staff member.
 */
class AiAssistantController extends Controller
{
    /** Kill-switch: when the assistant is disabled, all endpoints 404. */
    public function __construct()
    {
        abort_unless(config('assistant.enabled'), 404);
    }

    /**
     * Send a message to the assistant and get her reply.
     * POST /assistant/chat
     */
    public function chat(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message'         => 'required|string|max:4000',
            'conversation_id' => 'nullable|integer',
            'page'            => 'nullable|string|max:300',
        ]);

        $user = $request->user();

        // Reuse the given conversation (if it belongs to this user) or start fresh.
        $conversation = !empty($data['conversation_id'])
            ? AiConversation::where('user_id', $user->id)->find($data['conversation_id'])
            : null;
        $conversation ??= $assistant->startConversation($user);

        try {
            $reply = $assistant->ask($conversation, $data['message'], $user, $data['page'] ?? null);
        } catch (\Throwable $e) {
            // Surface a friendly message instead of a 500 (e.g. Ollama not running).
            return response()->json([
                'conversation_id' => $conversation->id,
                'error'           => $this->friendlyError($e->getMessage()),
            ]);
        }

        // Which tools ran during this turn (for a subtle "used: …" hint).
        $tools = $conversation->actionLogs()
            ->where('created_at', '>=', now()->subSeconds(60))
            ->where('result', '!=', \App\Models\AiActionLog::RESULT_PENDING)
            ->latest('id')->limit(5)->get()
            ->pluck('tool_name')->unique()->values();

        $payload = [
            'conversation_id' => $conversation->id,
            'reply'           => $reply->content,
            'tools_used'      => $tools,
        ];

        // If a clinical/financial action was deferred, hand the UI a confirm card.
        if ($assistant->pendingAction) {
            $payload['pending_action'] = [
                'id'       => $assistant->pendingAction->id,
                'summary'  => $assistant->pendingAction->summary,
                'tool'     => $assistant->pendingAction->tool_name,
                'category' => $assistant->pendingAction->category,
            ];
        }

        return response()->json($payload);
    }

    /**
     * Approve a deferred (clinical/financial) action — runs it now.
     * POST /assistant/confirm/{action}
     */
    public function confirm(Request $request, AssistantService $assistant, \App\Models\AiActionLog $action): JsonResponse
    {
        abort_unless($action->user_id === $request->user()->id, 403);

        if (!$action->isPending()) {
            return response()->json(['reply' => 'That action was already handled.', 'resolved' => true]);
        }

        $reply = $assistant->confirmAction($action, $request->user());

        return response()->json([
            'conversation_id' => $action->ai_conversation_id,
            'reply'           => $reply->content,
            'resolved'        => true,
        ]);
    }

    /**
     * Decline a deferred action — discards it.
     * POST /assistant/reject/{action}
     */
    public function reject(Request $request, AssistantService $assistant, \App\Models\AiActionLog $action): JsonResponse
    {
        abort_unless($action->user_id === $request->user()->id, 403);

        if ($action->isPending()) {
            $assistant->rejectAction($action, $request->user());
        }

        return response()->json(['reply' => "Okay, cancelled.", 'resolved' => true]);
    }

    /**
     * Load a conversation's visible messages (to restore the panel).
     * GET /assistant/conversation/{conversation}
     */
    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->get(['role', 'content']);

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages'        => $messages,
        ]);
    }

    /**
     * Transcribe an uploaded audio clip to text (local Whisper).
     * POST /assistant/transcribe
     */
    public function transcribe(Request $request, \App\Services\Voice\TranscriptionService $service): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|max:25600', // up to 25 MB
        ]);

        $file = $request->file('audio');
        $dir  = storage_path('app/voice-tmp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $name = 'rec_' . uniqid() . '.' . ($file->getClientOriginalExtension() ?: 'webm');
        $file->move($dir, $name);
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        try {
            $result = $service->transcribe($path);
        } catch (\Throwable $e) {
            @unlink($path);
            $msg = str_contains($e->getMessage(), 'faster-whisper')
                ? 'Speech-to-text isn\'t set up yet. Install it with: pip install faster-whisper'
                : 'Sorry, I couldn\'t transcribe that. ' . $e->getMessage();
            return response()->json(['error' => $msg], 200);
        }

        @unlink($path);

        return response()->json([
            'text'     => $result['text'],
            'language' => $result['language'],
        ]);
    }

    private function friendlyError(string $raw): string
    {
        if (str_contains($raw, 'Ollama') || str_contains($raw, 'reach')) {
            return "I can't reach the local AI engine right now. Please make sure the Ollama app is running, then try again.";
        }
        if (str_contains($raw, 'not found') && str_contains($raw, 'model')) {
            return "My AI model isn't installed yet. Run: php artisan tulip:pull " . config('assistant.model');
        }
        return "Something went wrong on my side. Please try again in a moment.";
    }
}
