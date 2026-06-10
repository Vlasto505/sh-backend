<?php

namespace App\Enums;

/**
 * Program A qualification stacks 01–05 (spec 7.1).
 */
enum ProgramAStack: string
{
    case Stack01 = 'stack_01';
    case Stack02 = 'stack_02';
    case Stack03 = 'stack_03';
    case Stack04 = 'stack_04';
    case Stack05 = 'stack_05';

    public function label(): string
    {
        return match ($this) {
            self::Stack01 => 'Stack 01 – objektové technológie, softvérové inžinierstvo, mobilné aplikácie, senzory, manažment projektov, testovanie',
            self::Stack02 => 'Stack 02 – databázové systémy, analýza dát, AI, strojové učenie, neurónové siete, hĺbková analýza dát',
            self::Stack03 => 'Stack 03 – jazyky webu, FE/BE technológie, webové aplikácie na platforme Java',
            self::Stack04 => 'Stack 04 – herné vývojové prostredia, vývoj 3D aplikácií, virtuálna a rozšírená realita',
            self::Stack05 => 'Stack 05 – programovanie v jazyku C, internet vecí, inteligentné, robotické a priemyselné systémy',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ], self::cases());
    }
}
