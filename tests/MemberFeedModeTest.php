<?php

define('_JEXEC', 1);

require_once dirname(__DIR__) . '/com_ra_members/site/src/Service/MemberFeedMode.php';

use Ramblers\Component\Ra_members\Site\Service\MemberFeedMode;

foreach ([true, 1, '1', 'yes', 'true'] as $enabled) {
    if (!MemberFeedMode::isJsonEnabled($enabled)) {
        throw new RuntimeException('Expected JSON to be enabled for ' . var_export($enabled, true));
    }

    if (MemberFeedMode::fromJsonSetting($enabled) !== MemberFeedMode::JSON_ENRICHMENT) {
        throw new RuntimeException('Expected JSON enrichment mode.');
    }
}

foreach ([false, 0, '0', '', null, 'no', 'false'] as $disabled) {
    if (MemberFeedMode::isJsonEnabled($disabled)) {
        throw new RuntimeException('Expected JSON to be disabled for ' . var_export($disabled, true));
    }

    if (MemberFeedMode::fromJsonSetting($disabled) !== MemberFeedMode::INSIGHT_PRIMARY) {
        throw new RuntimeException('Expected Insight primary mode.');
    }
}

echo "MemberFeedMode tests passed\n";
