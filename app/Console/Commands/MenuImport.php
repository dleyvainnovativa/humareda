<?php

namespace App\Console\Commands;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el menú desde el JSON que produjo el cliente (del PDF).
 *
 *   php artisan menu:import storage/app/menu_humareda.json
 *   php artisan menu:import ruta/al/menu.json --truncate   (vacía antes)
 *
 * Es reejecutable: hace upsert por ext_id (el id del JSON), así que cuando el
 * cliente mande un menú nuevo lo vuelves a correr y se actualizan precios y
 * descripciones. Nota: una reimportación sobrescribe los cambios hechos a mano
 * en la pantalla de Menú para los platillos que vengan en el JSON.
 */
class MenuImport extends Command
{
    protected $signature = 'menu:import {file : Ruta al archivo JSON del menú} {--truncate : Vaciar el menú antes de importar}';
    protected $description = 'Importa/actualiza el menú (categorías y platillos) desde un JSON.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['categories'])) {
            $this->error('JSON inválido o sin "categories".');
            return self::FAILURE;
        }

        $cats = 0;
        $items = 0;

        DB::transaction(function () use ($data, &$cats, &$items) {
            if ($this->option('truncate')) {
                MenuItem::query()->delete();
                MenuCategory::query()->delete();
            }

            foreach ($data['categories'] as $cOrder => $c) {
                $category = MenuCategory::updateOrCreate(
                    ['ext_id' => $c['id'] ?? null],
                    [
                        'name'        => $c['name'] ?? 'Sin nombre',
                        'description' => $c['description'] ?? null,
                        'source_page' => $c['source_page'] ?? null,
                        'extras'      => $c['extras'] ?? [],
                        'sort_order'  => $cOrder,
                        'is_active'   => true,
                    ],
                );
                $cats++;

                foreach (($c['items'] ?? []) as $iOrder => $it) {
                    MenuItem::updateOrCreate(
                        ['menu_category_id' => $category->id, 'ext_id' => $it['id'] ?? null],
                        [
                            'name'         => $it['name'] ?? 'Sin nombre',
                            'description'  => $it['description'] ?? null,
                            'portion_qty'  => data_get($it, 'portion.quantity'),
                            'portion_unit' => data_get($it, 'portion.unit'),
                            'price'        => $it['price'] ?? null,
                            'price_type'   => $it['price_type'] ?? MenuItem::TYPE_FIXED,
                            'options'      => $it['options'] ?? [],
                            'notes'        => $it['notes'] ?? [],
                            'sort_order'   => $iOrder,
                            'is_active'    => true,
                        ],
                    );
                    $items++;
                }
            }
        });

        $this->info("Menú importado: {$cats} categorías, {$items} platillos.");
        return self::SUCCESS;
    }
}
