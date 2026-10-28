<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoFalha extends Model
{
    protected $table = 'eventos_falhas';

    protected $fillable = ['maquina', 'data', 'fonte', 'slide', 'categoria', 'anomalia_corrente', 'sintoma', 'causa_acao', 'observacao'];

    protected $casts = ['data' => 'date'];

    /** @return list<string> arquivos de imagem do slide, relativos a public/ */
    public function imagens(): array
    {
        $dir = public_path('falhas');

        return collect(glob($dir.DIRECTORY_SEPARATOR.$this->slide.'_*') ?: [])
            ->sort()->take(3)
            ->map(fn ($f) => 'falhas/'.basename($f))
            ->values()->all();
    }
}
