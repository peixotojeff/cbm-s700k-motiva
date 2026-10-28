<?php

namespace App\Enums;

enum Prioridade: string
{
    case Imediato = 'Imediato';
    case Programar = 'Programar';
    case Monitorar = 'Monitorar';

    public function ordem(): int
    {
        return match ($this) {
            self::Imediato => 0,
            self::Programar => 1,
            self::Monitorar => 2,
        };
    }

    public function selo(): string
    {
        return match ($this) {
            self::Imediato => 'bg-red-100 text-red-800 ring-red-600/20',
            self::Programar => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Monitorar => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
    }
}
