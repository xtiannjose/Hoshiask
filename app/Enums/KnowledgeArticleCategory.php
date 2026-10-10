<?php

namespace App\Enums;

enum KnowledgeArticleCategory: string
{
    case Faq = 'faq';
    case Policy = 'policy';
    case Guide = 'guide';

    public function label(): string
    {
        return match ($this) {
            self::Faq => 'FAQ',
            self::Policy => 'Policy',
            self::Guide => 'Guide',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
