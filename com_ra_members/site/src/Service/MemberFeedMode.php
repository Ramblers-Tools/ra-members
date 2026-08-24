<?php

namespace Ramblers\Component\Ra_members\Site\Service;

defined('_JEXEC') or die;

/**
 * Resolves the configured source of authoritative membership data.
 */
final class MemberFeedMode
{
    public const JSON_ENRICHMENT = 'json_enrichment';
    public const INSIGHT_PRIMARY = 'insight_primary';

    public static function isJsonEnabled($value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'true'], true);
    }

    public static function fromJsonSetting($value): string
    {
        return self::isJsonEnabled($value)
            ? self::JSON_ENRICHMENT
            : self::INSIGHT_PRIMARY;
    }
}
