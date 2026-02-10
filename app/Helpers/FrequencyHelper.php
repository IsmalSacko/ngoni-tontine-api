<?php

namespace App\Helpers;

use App\Models\Tontine;

class FrequencyHelper
{
    /**
     * Convertit une fréquence française en anglais
     */
    public static function toEnglish(string $frequency): ?string
    {
        return Tontine::FREQUENCY_MAP[$frequency] ?? null;
    }

    /**
     * Convertit une fréquence anglaise en français
     */
    public static function toFrench(string $frequency): ?string
    {
        $map = array_flip(Tontine::FREQUENCY_MAP);
        return $map[$frequency] ?? null;
    }

    /**
     * Retourne toutes les fréquences disponibles en français
     */
    public static function getAllFrench(): array
    {
        return array_keys(Tontine::FREQUENCY_MAP);
    }

    /**
     * Retourne toutes les fréquences disponibles en anglais
     */
    public static function getAllEnglish(): array
    {
        return Tontine::getValidFrequencies();
    }
}
