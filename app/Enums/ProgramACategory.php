<?php

namespace App\Enums;

/**
 * Program A thematic categories / stacks (spec 7.1).
 */
enum ProgramACategory: string
{
    case SoftwareDev  = 'software_dev';
    case AiData       = 'ai_data';
    case WebApps      = 'web_apps';
    case GameDev      = 'game_dev';
    case IotEmbedded  = 'iot_embedded';

    public function label(): string
    {
        return match ($this) {
            self::SoftwareDev => 'Vývoj softvéru (desktop, mobil, embedded)',
            self::AiData      => 'AI a dátové technológie',
            self::WebApps     => 'Webové aplikácie',
            self::GameDev     => 'Herný vývoj',
            self::IotEmbedded => 'IoT a embedded systémy',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ], self::cases());
    }
}
