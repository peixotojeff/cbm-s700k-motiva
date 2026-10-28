<?php

namespace App\Enums;

enum Status: string
{
    case Vermelho = 'Vermelho';
    case Amarelo = 'Amarelo';
    case Verde = 'Verde';
    case Aguardando = 'Aguardando';

    public function ordem(): int
    {
        return match ($this) {
            self::Vermelho => 0,
            self::Amarelo => 1,
            self::Verde => 2,
            self::Aguardando => 3,
        };
    }

    /** Ícone (x-icone) com formato próprio por status: o status não depende só da cor. */
    public function icone(): string
    {
        return match ($this) {
            self::Vermelho => 'alerta',
            self::Amarelo => 'atencao',
            self::Verde => 'ok',
            self::Aguardando => 'aguardando',
        };
    }

    /** Cor do ícone (texto) com contraste ≥ 4,5:1 sobre branco. */
    public function corIcone(): string
    {
        return match ($this) {
            self::Vermelho => 'text-red-700',
            self::Amarelo => 'text-amber-700',
            self::Verde => 'text-emerald-700',
            self::Aguardando => 'text-slate-600',
        };
    }

    /** Classes Tailwind do selo. */
    public function selo(): string
    {
        return match ($this) {
            self::Vermelho => 'bg-red-100 text-red-800 ring-red-600/20',
            self::Amarelo => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Verde => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Aguardando => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
    }
}
