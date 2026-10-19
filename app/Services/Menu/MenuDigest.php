<?php

namespace App\Services\Menu;

use App\Models\MenuCategory;

/**
 * Arma un resumen compacto del menú ACTIVO para inyectarlo en el prompt del bot
 * (va en el bloque estático/cacheado, así que su costo por turno es mínimo).
 * Deja claro el precio por peso de los cortes "S/T" para que el bot no cotice un
 * total fijo donde el precio depende del tamaño de la pieza.
 */
class MenuDigest
{
    public function text(): string
    {
        $cats = MenuCategory::active()
            ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        if ($cats->isEmpty()) {
            return '(sin menú cargado)';
        }

        $lines = [];
        foreach ($cats as $cat) {
            if ($cat->items->isEmpty()) {
                continue;
            }

            $head = "[{$cat->name}]";
            if ($cat->description) {
                $head .= " — {$cat->description}";
            }
            $lines[] = $head;

            foreach ($cat->items as $it) {
                $meta = $it->priceLabel();
                if ($portion = $it->portionLabel()) {
                    $meta .= ", {$portion}";
                }
                $line = "- {$it->name} ({$meta})";
                if ($it->description) {
                    $line .= ": {$it->description}";
                }
                if ($opts = $this->options($it->options)) {
                    $line .= " Opciones: {$opts}.";
                }
                $lines[] = $line;
            }

            if ($extras = $this->extras($cat->extras)) {
                $lines[] = "  Extras: {$extras}.";
            }
        }

        return implode("\n", $lines);
    }

    /** Opciones pueden venir como strings o como objetos {name, description}. */
    private function options(?array $options): string
    {
        if (empty($options)) {
            return '';
        }

        return collect($options)->map(function ($o) {
            if (is_array($o)) {
                return $o['name'] ?? '';
            }
            return (string) $o;
        })->filter()->implode('; ');
    }

    /** Extras por categoría: [{name, price, portion}]. */
    private function extras(?array $extras): string
    {
        if (empty($extras)) {
            return '';
        }

        return collect($extras)->map(function ($e) {
            $name  = $e['name'] ?? '';
            $price = isset($e['price']) ? ' $' . number_format((float) $e['price'], 0) : '';
            return trim($name . $price);
        })->filter()->implode('; ');
    }
}
