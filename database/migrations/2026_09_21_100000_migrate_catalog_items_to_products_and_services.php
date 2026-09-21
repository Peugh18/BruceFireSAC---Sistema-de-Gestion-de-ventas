<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crear tabla products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida')->default('NIU');
            $table->decimal('precio_venta', 10, 2);
            $table->boolean('aplica_igv')->default(true);
            $table->boolean('serializado')->default(false);
            $table->integer('stock_minimo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Crear tabla services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida')->default('ZZ');
            $table->decimal('precio_venta', 10, 2);
            $table->boolean('aplica_igv')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Migrar datos existentes de catalog_items si existen
        if (Schema::hasTable('catalog_items')) {
            $catalogItems = DB::table('catalog_items')->get();

            foreach ($catalogItems as $item) {
                if ($item->tipo === 'producto') {
                    DB::table('products')->insert([
                        'id' => $item->id,
                        'codigo' => $item->codigo,
                        'nombre' => $item->nombre,
                        'descripcion' => null,
                        'unidad_medida' => $item->unidad_medida ?? 'NIU',
                        'precio_venta' => $item->precio_venta,
                        'aplica_igv' => $item->aplica_igv,
                        'serializado' => false,
                        'stock_minimo' => null,
                        'activo' => $item->activo,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                } else {
                    DB::table('services')->insert([
                        'id' => $item->id,
                        'codigo' => $item->codigo,
                        'nombre' => $item->nombre,
                        'descripcion' => null,
                        'unidad_medida' => $item->unidad_medida ?? 'ZZ',
                        'precio_venta' => $item->precio_venta,
                        'aplica_igv' => $item->aplica_igv,
                        'activo' => $item->activo,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }
        }

        // 4. Actualizar inventory_units
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->cascadeOnDelete();
        });
        if (Schema::hasColumn('inventory_units', 'catalog_item_id')) {
            DB::table('inventory_units')->update([
                'product_id' => DB::raw('catalog_item_id'),
            ]);
            Schema::table('inventory_units', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        // 5. Actualizar inventory_movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('inventory_unit_id')->constrained('products')->cascadeOnDelete();
        });
        if (Schema::hasColumn('inventory_movements', 'catalog_item_id')) {
            DB::table('inventory_movements')->update([
                'product_id' => DB::raw('catalog_item_id'),
            ]);
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        // 6. Actualizar equipment
        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('client_id')->constrained('products')->cascadeOnDelete();
        });
        if (Schema::hasColumn('equipment', 'catalog_item_id')) {
            DB::table('equipment')->update([
                'product_id' => DB::raw('catalog_item_id'),
            ]);
            Schema::table('equipment', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        // 7. Actualizar quote_items
        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('quote_id')->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
        });
        if (Schema::hasColumn('quote_items', 'catalog_item_id') && Schema::hasTable('catalog_items')) {
            $quoteItems = DB::table('quote_items')->get();
            foreach ($quoteItems as $qItem) {
                $cat = DB::table('catalog_items')->where('id', $qItem->catalog_item_id)->first();
                if ($cat && $cat->tipo === 'servicio') {
                    DB::table('quote_items')->where('id', $qItem->id)->update(['service_id' => $qItem->catalog_item_id]);
                } else {
                    DB::table('quote_items')->where('id', $qItem->id)->update(['product_id' => $qItem->catalog_item_id]);
                }
            }
            Schema::table('quote_items', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        // 8. Actualizar sale_items
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('sale_id')->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
        });
        if (Schema::hasColumn('sale_items', 'catalog_item_id')) {
            $saleItems = DB::table('sale_items')->get();
            foreach ($saleItems as $sItem) {
                if ($sItem->tipo_linea === 'recarga_servicio') {
                    DB::table('sale_items')->where('id', $sItem->id)->update(['service_id' => $sItem->catalog_item_id]);
                } else {
                    DB::table('sale_items')->where('id', $sItem->id)->update(['product_id' => $sItem->catalog_item_id]);
                }
            }
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        // 9. Eliminar tabla catalog_items
        Schema::dropIfExists('catalog_items');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->enum('tipo', ['producto', 'servicio']);
            $table->string('unidad_medida')->default('UND');
            $table->decimal('precio_venta', 10, 2);
            $table->boolean('aplica_igv')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('id')->constrained('catalog_items')->cascadeOnDelete();
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('inventory_unit_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('client_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('quote_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('sale_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });

        Schema::dropIfExists('services');
        Schema::dropIfExists('products');
    }
};
