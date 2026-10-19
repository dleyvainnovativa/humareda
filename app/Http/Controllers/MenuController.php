<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Editor del menú en el panel. El bot cotiza platillos y precios ÚNICAMENTE con
 * lo que está activo aquí (ver PromptBuilder + MenuDigest). Los cambios aplican
 * en el siguiente mensaje — sin redeploy. Carga inicial: `php artisan menu:import`.
 */
class MenuController extends Controller
{
    private const TYPES = [MenuItem::TYPE_FIXED, MenuItem::TYPE_PER_100G, MenuItem::TYPE_NOT_LISTED];

    public function index(): View
    {
        $categories = MenuCategory::with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return view('menu.index', [
            'categories' => $categories,
            'types'      => self::TYPES,
        ]);
    }

    // ---- Categorías --------------------------------------------------------
    public function storeCategory(Request $request): JsonResponse
    {
        $category = MenuCategory::create($this->categoryData($request));
        return response()->json(['ok' => true, 'category' => $category]);
    }

    public function updateCategory(Request $request, MenuCategory $category): JsonResponse
    {
        $category->update($this->categoryData($request));
        return response()->json(['ok' => true, 'category' => $category]);
    }

    public function toggleCategory(MenuCategory $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);
        return response()->json(['ok' => true, 'is_active' => $category->is_active]);
    }

    public function destroyCategory(MenuCategory $category): JsonResponse
    {
        $category->delete(); // cascade borra sus platillos
        return response()->json(['ok' => true]);
    }

    // ---- Platillos ---------------------------------------------------------
    public function storeItem(Request $request): JsonResponse
    {
        $item = MenuItem::create($this->itemData($request));
        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function updateItem(Request $request, MenuItem $item): JsonResponse
    {
        $item->update($this->itemData($request));
        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function toggleItem(MenuItem $item): JsonResponse
    {
        $item->update(['is_active' => ! $item->is_active]);
        return response()->json(['ok' => true, 'is_active' => $item->is_active]);
    }

    public function destroyItem(MenuItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(['ok' => true]);
    }

    // ---- Validación --------------------------------------------------------
    private function categoryData(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active', true);
        return $data;
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'menu_category_id' => ['required', 'exists:menu_categories,id'],
            'name'             => ['required', 'string', 'max:160'],
            'description'      => ['nullable', 'string', 'max:800'],
            'portion_qty'      => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'portion_unit'     => ['nullable', 'string', 'max:16'],
            'price'            => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_type'       => ['required', Rule::in(self::TYPES)],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active', true);
        if (($data['price_type'] ?? null) === MenuItem::TYPE_NOT_LISTED) {
            $data['price'] = null;
        }
        return $data;
    }
}
