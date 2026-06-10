<?php

namespace App\Enums;

/**
 * Mandatory project documentation for Program A (spec 7.3).
 */
enum ProgramADocument: string
{
    case ExecutiveSummary      = 'executive_summary';
    case TechnicalArchitecture = 'technical_architecture';
    case Roadmap               = 'roadmap';
    case Budget                = 'budget';
    case RiskAnalysis          = 'risk_analysis';
    case Monetization          = 'monetization';

    public function label(): string
    {
        return match ($this) {
            self::ExecutiveSummary      => 'Executive Summary',
            self::TechnicalArchitecture => 'Technická architektúra',
            self::Roadmap               => 'Roadmapa',
            self::Budget                => 'Rozpočet',
            self::RiskAnalysis          => 'Riziková analýza',
            self::Monetization          => 'Monetizačný model',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::ExecutiveSummary      => 'Stručný opis problému, riešenia, trhu a prínosu.',
            self::TechnicalArchitecture => 'Opis riešenia, technológií, modulov a prevádzky.',
            self::Roadmap               => 'Míľniky, plán realizácie a harmonogram.',
            self::Budget                => 'Plán čerpania grantu a očakávané náklady.',
            self::RiskAnalysis          => 'Identifikácia rizík, dopadov a mitigácií.',
            self::Monetization          => 'Spôsob vytvárania hodnoty a príjmov produktu.',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $d) => [
            'value' => $d->value,
            'label' => $d->label(),
            'hint'  => $d->hint(),
        ], self::cases());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }
}
