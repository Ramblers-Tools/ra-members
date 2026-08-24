<?php

define('_JEXEC', 1);

require_once dirname(__DIR__) . '/com_ra_members/site/src/Service/InsightCsvMapper.php';

use Ramblers\Component\Ra_members\Site\Service\InsightCsvMapper;

$headingFile = dirname(__DIR__, 2) . '/InsightColumnHeadings.csv';
$headings = str_getcsv(trim((string) file_get_contents($headingFile)));
$mapper = new InsightCsvMapper();
$indexes = $mapper->validateHeadings($headings);
$unmappedHeadings = array_diff($headings, array_keys(InsightCsvMapper::getFieldMap()), ['Last Name (Sorting)']);

if ($unmappedHeadings !== []) {
    throw new RuntimeException('Unexpected unmapped Insight headings: ' . implode(', ', $unmappedHeadings));
}

if (count(InsightCsvMapper::getFieldMap()) !== 36) {
    throw new RuntimeException('Expected 35 profile mappings plus email.');
}

if (isset($indexes['Last Name (Sorting)']) && isset(InsightCsvMapper::getFieldMap()['Last Name (Sorting)'])) {
    throw new RuntimeException('Last Name (Sorting) must not be mapped.');
}

$row = array_fill(0, count($headings), '');
$values = [
    'Mem No.' => ' 12345 ',
    'Forenames' => ' Jill ',
    'Last Name' => ' Spratt ',
    'Email Address' => ' JILL@EXAMPLE.TEST ',
    'Group Code' => ' ns01 ',
    'Expiry date' => '31/12/2027',
    'Ramblers Join Date' => '21/01/2018 00:00:00',
    'Area Joined Date' => '7/2/2019 00.00.00',
    'Volunteer' => 'Yes',
    'Post Direct Marketing' => 'No',
];

foreach ($values as $heading => $value) {
    $row[$indexes[$heading]] = $value;
}

$mapped = $mapper->mapRow($headings, $row, '2026-08-24 12:00:00');

foreach ([
    'membershipNo' => '12345',
    'firstName' => 'Jill',
    'lastName' => 'Spratt',
    'email' => 'jill@example.test',
    'groupCode' => 'NS01',
    'home_group' => 'NS01',
    'membershipExpiry' => '2027-12-31',
    'membershipJoinDate' => '2018-01-21',
    'areaJoinedDate' => '2019-02-07',
    'volunteer' => 1,
    'postDirectMarketing' => 0,
    'address1' => null,
] as $field => $expected) {
    if (($mapped[$field] ?? null) !== $expected) {
        throw new RuntimeException($field . ' was not mapped as expected.');
    }
}

$payload = json_decode($mapped['insightPayload'], true, 512, JSON_THROW_ON_ERROR);

if (($payload['Last Name (Sorting)'] ?? null) !== null) {
    throw new RuntimeException('Blank working fields must remain null in the source snapshot.');
}

$missingHeadings = array_values(array_filter($headings, static fn ($heading) => $heading !== 'Mem No.'));

try {
    $mapper->validateHeadings($missingHeadings);
    throw new RuntimeException('Missing membership heading was accepted.');
} catch (InvalidArgumentException $exception) {
    if (strpos($exception->getMessage(), 'Mem No.') === false) {
        throw $exception;
    }
}

echo "Insight CSV mapper tests passed\n";
