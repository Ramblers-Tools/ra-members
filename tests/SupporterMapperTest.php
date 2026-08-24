<?php

define('_JEXEC', 1);

require_once dirname(__DIR__) . '/com_ra_members/site/src/Service/SupporterMapper.php';

use Ramblers\Component\Ra_members\Site\Service\SupporterMapper;

$supporters = json_decode(
        file_get_contents(__DIR__ . '/fixtures/supporter-api/200-supporters.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
);
$supporter = $supporters[1];
$mapper = new SupporterMapper();
$fieldMap = SupporterMapper::getProfileFieldMap();
$documentedFields = array_keys($supporter);
$mappedFields = array_merge(array_keys($fieldMap), ['volunteerRoles']);

sort($documentedFields);
sort($mappedFields);

if ($documentedFields !== $mappedFields) {
    throw new RuntimeException('The fixture and exhaustive supporter field map differ.');
}

$profile = $mapper->mapProfile($supporter, 'NS03', '2026-08-24 12:00:00');
$snapshot = json_decode($profile['sourcePayload'], true, 512, JSON_THROW_ON_ERROR);

if ($snapshot !== $supporter) {
    throw new RuntimeException('The source payload snapshot is not lossless.');
}

if ($profile['memberRef'] !== $supporter['memberRef']
        || $profile['contactId'] !== $supporter['contactId']
        || $profile['email'] !== strtolower($supporter['email'])
        || $profile['membershipNo'] !== $supporter['membershipNo']
        || $profile['friendlyName'] !== $supporter['friendlyName']
        || $profile['groupCode'] !== 'NS03'
        || $profile['home_group'] !== 'NS03') {
    throw new RuntimeException('Core supporter fields were mapped incorrectly.');
}

if ($profile['doNotEmail'] !== 1 || $profile['wellbeingWalker'] !== 0
        || $profile['walkLeader'] !== 1) {
    throw new RuntimeException('Boolean values were not preserved as 1/0.');
}

$roles = $mapper->mapVolunteerRoles($supporter, 99, 'NS03');

if (count($roles) !== 1 || array_keys($roles[0]) !== [
    'member_id',
    'organisation_code',
    'role',
    'role_start_date',
    'display_name',
    'walk_leader_status',
    'wellbeing_walks_role',
]) {
    throw new RuntimeException('Volunteer role mapping is incomplete.');
}

$roleSql = file_get_contents(dirname(__DIR__) . '/com_ra_members/administrator/sql/install.mysql.utf8.sql');

foreach (['role', 'role_start_date', 'display_name', 'walk_leader_status', 'wellbeing_walks_role'] as $column) {
    if (strpos($roleSql, '`' . $column . '`') === false) {
        throw new RuntimeException('Role schema is missing ' . $column . '.');
    }
}

echo "36 supporter fields and five volunteer-role fields mapped\n";
