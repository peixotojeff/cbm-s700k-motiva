<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maquina extends Model
{
    use HasFactory;

    protected $table = 'maquinas';

    protected $fillable = ['codigo', 'tipo', 'linha', 'local', 'grupo_pmp', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public function leituras(): HasMany
    {
        return $this->hasMany(Leitura::class)->orderBy('medido_em');
    }

    /** Versão pelo sufixo do código: "... AG" = agulha, "... JM" = jacaré móvel. */
    public static function inferirTipo(string $codigo): string
    {
        return preg_match('/\bJM$/i', trim($codigo)) ? 'jacare' : 'agulha';
    }

    /** Grupo do PMP a partir do local (X03, X12 → estratégico; X08 → não estratégico; PVS → pátio). */
    public static function inferirGrupo(?string $local): ?string
    {
        if (! $local) {
            return null;
        }
        foreach (config('cbm.pmp') as $grupo => $cfg) {
            if (in_array(strtoupper($local), $cfg['locais'], true)) {
                return $grupo;
            }
        }

        return null;
    }

    /** @return array{tempo:array{0:float,1:float}, forca:array{0:float,1:float}, tempo_nominal:float, forca_nominal:float} */
    public static function limites(string $tipo): array
    {
        $v = config("cbm.versoes.$tipo") ?? config('cbm.versoes.agulha');
        $tol = config('cbm.tempo_tolerancia_s');

        return [
            'tempo' => [$v['tempo_nominal'] - $tol, $v['tempo_nominal'] + $tol],
            'forca' => [$v['forca_nominal'] - $v['forca_tol'], $v['forca_nominal'] + $v['forca_tol']],
            'tempo_nominal' => $v['tempo_nominal'],
            'forca_nominal' => $v['forca_nominal'],
        ];
    }

    public function rotuloTipo(): string
    {
        return config("cbm.versoes.{$this->tipo}.rotulo", $this->tipo);
    }

    public function rotuloGrupo(): ?string
    {
        return $this->grupo_pmp ? config("cbm.pmp.{$this->grupo_pmp}.rotulo") : null;
    }
}
