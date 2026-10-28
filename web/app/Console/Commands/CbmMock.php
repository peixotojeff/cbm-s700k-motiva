<?php

namespace App\Console\Commands;

use App\Services\Cbm\MockLeituras;
use Illuminate\Console\Command;

class CbmMock extends Command
{
    protected $signature = 'cbm:mock {--limpar : Remove apenas as leituras de exemplo}';

    protected $description = 'Carrega (ou remove) leituras de exemplo (responsável = MOCK)';

    public function handle(MockLeituras $mock): int
    {
        if ($this->option('limpar')) {
            $this->info($mock->limpar().' leituras de exemplo removidas');
        } else {
            $this->info($mock->carregar().' leituras de exemplo carregadas');
        }

        return self::SUCCESS;
    }
}
