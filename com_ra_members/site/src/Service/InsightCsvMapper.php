<?php

namespace Ramblers\Component\Ra_members\Site\Service;

defined('_JEXEC') or die;

/**
 * Maps an Insight Hub CSV row to #__ra_profiles fields.
 */
final class InsightCsvMapper {

    private const FIELD_MAP = [
        'Group' => 'groupName',
        'Mem No.' => 'membershipNo',
        'Member Type' => 'memberType',
        'Member Term' => 'memberTerm',
        'Member Status' => 'membershipStatus',
        'Type' => 'membershipType',
        'Joint With' => 'jointWith',
        'Title' => 'title',
        'Initials' => 'initials',
        'Forenames' => 'firstName',
        'Last Name' => 'lastName',
        'Address1' => 'address1',
        'Address2' => 'address2',
        'Address3' => 'address3',
        'Town' => 'town',
        'County' => 'county',
        'Country' => 'country',
        'Postcode' => 'postcode',
        'Email Address' => 'email',
        'Landline Telephone' => 'landline',
        'Mobile Telephone' => 'mobile',
        'Expiry date' => 'membershipExpiry',
        'Ramblers Join Date' => 'membershipJoinDate',
        'Area' => 'areaName',
        'Area Joined Date' => 'areaJoinedDate',
        'Group Code' => 'groupCode',
        'Group Joined Date' => 'teamRelationshipFrom',
        'Volunteer' => 'volunteer',
        'Email Marketing Consent' => 'emailConsent',
        'Email Permission Last Updated' => 'emailConsentLastUpdated',
        'Post Direct Marketing' => 'postConsent',
        'Post Permission Last Updated' => 'postConsentLastUpdated',
        'Telephone Direct Marketing' => 'phoneConsent',
        'Telephone Permission Last Updated' => 'phoneConsentLastUpdated',
        'Walk Programme Opt-Out' => 'noWalkProgram',
        'Affiliate Member Primary Group' => 'affiliateMemberPrimaryGroup',
    ];
    private const DATE_FIELDS = [
        'membershipExpiry',
        'membershipJoinDate',
        'areaJoinedDate',
        'teamRelationshipFrom',
        'emailPermissionLastUpdated',
        'postPermissionLastUpdated',
        'telephonePermissionLastUpdated',
    ];
    private const BOOLEAN_FIELDS = [
        'volunteer',
        'emailConsent',
        'postConsent',
        'phonConsent',
        'noWalkProgram',
    ];

    public static function getFieldMap(): array {
        return self::FIELD_MAP;
    }

    public function validateHeadings(array $headings): array {
        $indexes = [];

        foreach ($headings as $index => $heading) {
            $heading = $this->normaliseHeading($heading, $index === 0);

            if ($heading === '') {
                continue;
            }

            if (isset($indexes[$heading])) {
                throw new \InvalidArgumentException('Duplicate Insight heading: ' . $heading);
            }

            $indexes[$heading] = (int) $index;
        }

        $missing = array_diff(array_keys(self::FIELD_MAP), array_keys($indexes));

        if ($missing !== []) {
            throw new \InvalidArgumentException('Missing Insight headings: ' . implode(', ', $missing));
        }

        return $indexes;
    }

    public function mapRow(array $headings, array $row, string $importedAt): array {
        $indexes = $this->validateHeadings($headings);
        $source = [];

        foreach ($headings as $index => $heading) {
            $heading = $this->normaliseHeading($heading, $index === 0);

            if ($heading !== '') {
                $source[$heading] = $this->normaliseText($row[$index] ?? null);
            }
        }

        $data = [];

        foreach (self::FIELD_MAP as $heading => $target) {
            $value = $this->normaliseText($row[$indexes[$heading]] ?? null);

            if (in_array($target, self::DATE_FIELDS, true)) {
                $value = $this->normaliseDate($value, $heading);
            } elseif (in_array($target, self::BOOLEAN_FIELDS, true)) {
                $value = $this->normaliseBoolean($value, $heading);
            } elseif ($target === 'email' && $value !== null) {
                $value = strtolower($value);

                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \InvalidArgumentException('Invalid Email Address.');
                }
            }

            $data[$target] = $value;
        }

        if ($data['membershipNo'] === null) {
            throw new \InvalidArgumentException('Mem No. is required.');
        }

        if ($data['groupCode'] === null || !preg_match('/^[A-Z0-9]{4}$/', strtoupper($data['groupCode']))) {
            throw new \InvalidArgumentException('Group Code must contain four letters or digits.');
        }

        $data['groupCode'] = strtoupper($data['groupCode']);
        $data['home_group'] = $data['groupCode'];
        $data['insightPayload'] = json_encode(
                $source,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $data['insightImportedAt'] = $importedAt;

        return $data;
    }

    private function normaliseHeading($value, bool $first): string {
        $value = trim((string) $value);

        if ($first) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        }

        return $value;
    }

    private function normaliseText($value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normaliseBoolean(?string $value, string $heading): ?int {
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        if (in_array($value, ['1', 'y', 'yes', 'true'], true)) {
            return 1;
        }

        if (in_array($value, ['0', 'n', 'no', 'false'], true)) {
            return 0;
        }

        throw new \InvalidArgumentException($heading . ' must contain Yes, No, True, False, 1, 0, Y, or N.');
    }

    private function normaliseDate(?string $value, string $heading): ?string {
        if ($value === null) {
            return null;
        }

        foreach ([
    '!Y-m-d',
    '!d/m/Y',
    '!j/n/Y',
    '!d-m-Y',
    '!j-n-Y',
    '!d/m/Y H:i:s',
    '!j/n/Y H:i:s',
    '!d/m/Y H.i.s',
    '!j/n/Y H.i.s',
        ] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        throw new \InvalidArgumentException('Invalid ' . $heading . ': ' . $value);
    }

}
