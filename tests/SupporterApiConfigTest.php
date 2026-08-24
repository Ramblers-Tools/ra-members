<?php

define('JPATH_PLATFORM', __DIR__);
define('_JEXEC', 1);

require_once dirname(__DIR__) . '/com_ra_members/site/src/Service/SupporterApiConfig.php';

use Ramblers\Component\Ra_members\Site\Service\SupporterApiConfig;

function expectFailure(object $row, string $message): void {
    try {
        SupporterApiConfig::fromRow($row, 42);
    } catch (Throwable $exception) {
        if (strpos($exception->getMessage(), $message) !== false) {
            return;
        }

        throw new RuntimeException('Unexpected validation message: ' . $exception->getMessage());
    }

    throw new RuntimeException('Expected validation failure containing: ' . $message);
}

$valid = (object) [
    'id' => 42,
    'state' => 1,
    'sub_system' => 'RA Members',
    'url' => 'https://supporters.example.test/get_supporters',
    'token' => 'test-secret',
];

$config = SupporterApiConfig::fromRow($valid, 42);

if ($config->getId() !== 42 || $config->getUrl() !== $valid->url || $config->getToken() !== $valid->token) {
    throw new RuntimeException('Valid configuration was not retained.');
}

foreach ([
    ['state', 0, 'not enabled'],
    ['sub_system', 'RA Events', 'not configured for RA Members'],
    ['url', 'http://supporters.example.test/get_supporters', 'valid HTTPS URL'],
    ['token', ' ', 'no API key'],
] as [$property, $value, $message]) {
    $invalid = clone $valid;
    $invalid->{$property} = $value;
    expectFailure($invalid, $message);
}

echo "SupporterApiConfig tests passed\n";
