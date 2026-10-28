<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Peca extends Model
{
    protected $table = 'pecas';

    protected $fillable = ['codigo', 'nome', 'componente', 'qtd_por_intervencao', 'estoque', 'estoque_minimo', 'prazo_reposicao_dias', 'fonte', 'ficticio'];

    protected $casts = [
        'qtd_por_intervencao' => 'integer', 'estoque' => 'integer', 'estoque_minimo' => 'integer',
        'prazo_reposicao_dias' => 'integer', 'ficticio' => 'boolean',
    ];

    public function rotuloComponente(): string
    {
        return config("cbm.componentes.{$this->componente}.0", $this->componente);
    }
}
