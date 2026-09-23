<?php

$root = dirname(__DIR__);
$controller = file_get_contents(
    $root . '/com_ra_members/administrator/src/Controller/ReportsController.php'
);
$template = file_get_contents(
    $root . '/com_ra_members/administrator/tmpl/reports/default.php'
);

foreach (array(
    'p.title',
    'p.firstName',
    'p.lastName',
    'p.preferred_name',
    'p.address1',
    'p.address2',
    'p.address3',
    'p.town',
    'p.county',
    'p.country',
    'p.postcode',
) as $field) {
    if (strpos($controller, $field) === false) {
        throw new RuntimeException('Address-label export is missing ' . $field . '.');
    }
}

if (strpos($controller, "getInt('mode', 1)") === false
    || strpos($controller, 'if ($mode === 1)') === false) {
    throw new RuntimeException('Address-label export must support modes 1 and 2.');
}

if (strpos($controller, 'LEFT JOIN #__users AS u ON u.id = p.id') === false
    || strpos($controller, 'u.id IS NULL OR u.email IS NULL') === false
    || strpos($controller, 'TRIM(u.email)') === false) {
    throw new RuntimeException('Mode 1 must include every member without a usable email address.');
}

if (substr_count($controller, "buildCriterion('") < 2
    || strpos($controller, "buildCriterion('AND', 'p.home_group')") === false
    || strpos($controller, "buildCriterion('WHERE', 'p.home_group')") === false) {
    throw new RuntimeException('Both address-label modes must use the existing scope restriction.');
}

if (strpos($controller, 'set_csv($reportName)') === false
    || strpos($controller, '$this->app->close();') === false) {
    throw new RuntimeException('Address-label output must be returned as a CSV download.');
}

foreach (array('mode=1', 'mode=2') as $mode) {
    if (strpos($template, 'task=reports.addressLabels&' . $mode) === false) {
        throw new RuntimeException('Reports screen is missing the address-label link for ' . $mode . '.');
    }
}

echo "Address-label report tests passed\n";
