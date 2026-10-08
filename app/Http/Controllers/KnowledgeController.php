<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Panel editor for the FAQ knowledge base. The bot answers menu/hours/policy/
 * dietary questions ONLY from the active entries here (see PromptBuilder) and
 * never invents — so this is where staff keep the answers current without a
 * redeploy. Edits take effect on the next message (PromptBuilder reads live).
 */
class KnowledgeController extends Controller
{
    private const CATEGORIES = ['menu', 'hours', 'location', 'dietary', 'policy', 'general'];

    public function index(): View
    {
        $entries = KnowledgeEntry::orderBy('category')->orderBy('sort_order')->get();
        return view('knowledge.index', [
            'entries'    => $entries,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $entry = KnowledgeEntry::create($this->validated($request));
        return response()->json(['ok' => true, 'entry' => $entry]);
    }

    public function update(Request $request, KnowledgeEntry $entry): JsonResponse
    {
        $entry->update($this->validated($request));
        return response()->json(['ok' => true, 'entry' => $entry]);
    }

    public function toggle(KnowledgeEntry $entry): JsonResponse
    {
        $entry->update(['is_active' => ! $entry->is_active]);
        return response()->json(['ok' => true, 'is_active' => $entry->is_active]);
    }

    public function destroy(KnowledgeEntry $entry): JsonResponse
    {
        $entry->delete();
        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category'    => ['required', Rule::in(self::CATEGORIES)],
            'question_es' => ['required', 'string', 'max:255'],
            'answer_es'   => ['required', 'string', 'max:2000'],
            'question_en' => ['nullable', 'string', 'max:255'],
            'answer_en'   => ['nullable', 'string', 'max:2000'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active', true);
        return $data;
    }
}
