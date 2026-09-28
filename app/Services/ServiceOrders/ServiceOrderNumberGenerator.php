<?php

namespace App\Services\ServiceOrders;

use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;

class ServiceOrderNumberGenerator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $year = (int) now()->year;
            $sequence = DB::table('service_order_sequences')->where('year', $year)->lockForUpdate()->first();
            if (! $sequence) {
                DB::table('service_order_sequences')->insert(['year' => $year, 'last_number' => $this->lastUsed($year), 'created_at' => now(), 'updated_at' => now()]);
                $sequence = DB::table('service_order_sequences')->where('year', $year)->lockForUpdate()->first();
            }
            $next = ((int) $sequence->last_number) + 1;
            DB::table('service_order_sequences')->where('year', $year)->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('OT-%d-%04d', $year, $next);
        });
    }

    private function lastUsed(int $year): int
    {
        $prefix = "OT-{$year}-";

        return (int) ServiceOrder::where('codigo', 'like', $prefix.'%')->pluck('codigo')->map(fn (string $code): int => (int) substr($code, strlen($prefix)))->max();
    }
}
