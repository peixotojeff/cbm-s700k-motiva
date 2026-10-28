<?php

namespace Database\Seeders;

use App\Services\Cbm\MockLeituras;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([EventoFalhaSeeder::class, PecaSeeder::class]);

        if (config('app.env') !== 'production') {
            app(MockLeituras::class)->carregar();
        }
    }
}
