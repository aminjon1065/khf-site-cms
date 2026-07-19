<?php

namespace App\Enums;

/**
 * Administrative type of a top-level region of the Republic of Tajikistan.
 */
enum RegionType: string
{
    case City = 'city';
    case Oblast = 'oblast';
    case Gbao = 'gbao';
    case Rrp = 'rrp';

    public function label(): string
    {
        return match ($this) {
            self::City => 'Город республиканского значения',
            self::Oblast => 'Область',
            self::Gbao => 'Автономная область',
            self::Rrp => 'Районы республиканского подчинения',
        };
    }
}
