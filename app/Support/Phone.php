<?php

namespace App\Support;

/**
 * Numéros de téléphone : l'app les envoie au format international (+223…,
 * +33…), mais des membres ont été saisis au format local (« 76 00 00 01 »).
 * Deux écritures du même numéro doivent désigner la même personne.
 */
final class Phone
{
    /** Le plus petit numéro national qu'on accepte de reconnaître par sa fin. */
    private const MIN_LOCAL_DIGITS = 8;

    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        // « 00 223… » s'écrit aussi « +223… ».
        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    /**
     * Même numéro ? Identiques en chiffres, ou l'un est la fin de l'autre (le
     * numéro local et son écriture internationale) — un 0 national en tête
     * mis à part (« 06 09… » et « +33 6 09… »).
     */
    public static function same(?string $a, ?string $b): bool
    {
        $a = self::digits($a);
        $b = self::digits($b);

        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        [$short, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
        $short = ltrim($short, '0');

        return strlen($short) >= self::MIN_LOCAL_DIGITS && str_ends_with($long, $short);
    }

    /**
     * Écriture enregistrée : chiffres seuls, le « + » international gardé
     * (« +223 76 00-00-01 » → « +22376000001 »). Sans espaces, la recherche par
     * la fin du numéro marche en base.
     */
    public static function clean(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if (str_starts_with($trimmed, '00')) {
            return '+'.substr($digits, 2);
        }

        return (str_starts_with($trimmed, '+') ? '+' : '').$digits;
    }

    /** Les 8 derniers chiffres, pour un premier tri en base avant [same()]. */
    public static function tail(?string $phone): string
    {
        return substr(self::digits($phone), -self::MIN_LOCAL_DIGITS);
    }
}
