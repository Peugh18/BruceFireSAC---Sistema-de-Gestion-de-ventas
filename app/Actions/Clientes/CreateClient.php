<?php

namespace App\Actions\Clientes;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create([
                ...$data,
                'codigo_interno' => 'CLI-TMP-'.Str::uuid()->toString(),
            ]);

            $client->update([
                'codigo_interno' => 'CLI-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT),
            ]);

            return $client;
        });
    }
}
