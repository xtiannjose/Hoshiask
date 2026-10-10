<?php

namespace App\Support;

use Illuminate\Http\Request;

final class LeadingUppercaseNormalizer
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        return mb_strtoupper(mb_substr($trimmed, 0, 1)).mb_substr($trimmed, 1);
    }

    /**
     * @return array{province: string, municipality: string, subject: string}
     */
    public static function normalizeTicketFields(Request $request): array
    {
        return [
            'province' => self::normalize($request->string('province')->toString()),
            'municipality' => self::normalize($request->string('municipality')->toString()),
            'subject' => self::normalize($request->string('subject')->toString()),
        ];
    }
}
