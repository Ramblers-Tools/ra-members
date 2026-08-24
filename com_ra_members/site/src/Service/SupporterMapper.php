<?php

/**
 * @package     com_ra_members
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_members\Site\Service;

defined('_JEXEC') or die;

/**
 * Maps the complete supporter contract to typed profile and role data.
 */
final class SupporterMapper {

    public const CONTRACT_VERSION = '1.0.0';

    private const PROFILE_FIELDS = [
        'membershipNo' => 'membershipNumber',
        'memberRef' => 'memberRef',
        'contactId' => 'contactId',
        'title' => 'title',
        'firstName' => 'firstName',
        'lastName' => 'lastName',
        'email' => 'sourceEmail',
        'doNotEmail' => 'doNotEmail',
        'landline' => 'landlineTelephone',
        'mobile' => 'mobileNumber',
        'friendlyName' => 'friendlyName',
        'membershipStatus' => 'memberStatus',
        'memberType' => 'memberType',
        'membershipJoinDate' => 'ramblersJoinedDate',
        'membershipExpiry' => 'membershipExpiryDate',
        'membershipEndDate' => 'membershipEndDate',
        'teamStatus' => 'teamStatus',
        'teamRelationshipFrom' => 'groupJoinedDate',
        'wellbeingWalker' => 'wellbeingWalker',
        'walkLeader' => 'walkLeader',
        'noWalkProgram' => 'walkProgrammeOptOut',
        'noCampaigning' => 'noCampaigning',
        'noSurveys' => 'noSurveys',
        'canEmailVolunteers' => 'canEmailVolunteers',
        'canEmailMembers' => 'canEmailMembers',
        'canEmailWellbeingWalkers' => 'canEmailWellbeingWalkers',
        'canViewMemberData' => 'canViewMemberData',
        'canViewMemberDate' => 'canViewMemberDate',
        'emailConsent' => 'emailConsent',
        'emailConsentLastUpdated' => 'emailConsentLastUpdated',
        'postConsent' => 'postConsent',
        'postConsentLastUpdated' => 'postConsentLastUpdated',
        'phoneConsent' => 'phoneConsent',
        'phoneConsentLastUpdated' => 'phoneConsentLastUpdated',
        'emailConsentWellbeingWalks' => 'emailConsentWellbeingWalks',
    ];

    public static function getProfileFieldMap(): array {
        return self::PROFILE_FIELDS;
    }

    public function mapProfile(array $supporter, string $groupCode, string $retrievedAt): array {
        if ($this->normaliseString($supporter['memberRef'] ?? null) === null) {
            throw new \InvalidArgumentException('Supporter memberRef is required.');
        }

        if ($this->normaliseString($supporter['lastName'] ?? null) === null) {
            throw new \InvalidArgumentException('Supporter lastName is required.');
        }

        $data = [];

        foreach (self::PROFILE_FIELDS as $sourceField => $targetField) {
            $data[$targetField] = $this->normaliseValue($sourceField, $supporter[$sourceField] ?? null);
        }

        $data['home_group'] = strtoupper(trim($groupCode));
        $data['groupCode'] = $data['home_group'];
        $data['sourcePayload'] = json_encode(
                $supporter,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $data['sourceContractVersion'] = self::CONTRACT_VERSION;
        $data['sourceRetrievedAt'] = $retrievedAt;

        return $data;
    }

    public function mapVolunteerRoles(array $supporter, int $memberId, string $groupCode): array {
        $rows = [];

        foreach (($supporter['volunteerRoles'] ?? []) as $role) {
            if (is_object($role)) {
                $role = get_object_vars($role);
            }

            if (!is_array($role)) {
                continue;
            }

            $rows[] = [
                'member_id' => $memberId,
                'organisation_code' => strtoupper(trim($groupCode)),
                'role' => $this->normaliseString($role['roleName'] ?? null),
                'role_start_date' => $this->normaliseString($role['startDate'] ?? null),
                'display_name' => $this->normaliseString($role['displayName'] ?? null),
                'walk_leader_status' => $this->normaliseString($role['walkLeaderStatus'] ?? null),
                'wellbeing_walks_role' => $this->normaliseBoolean($role['wellbeingWalksRole'] ?? null),
            ];
        }

        return $rows;
    }

    private function normaliseValue(string $field, $value) {
        if (in_array($field, [
            'doNotEmail',
            'wellbeingWalker',
            'walkLeader',
            'noWalkProgram',
            'noCampaigning',
            'noSurveys',
            'canEmailVolunteers',
            'canEmailMembers',
            'canEmailWellbeingWalkers',
            'canViewMemberData',
            'canViewMemberDate',
            'emailConsent',
            'postConsent',
            'phoneConsent',
            'emailConsentWellbeingWalks',
        ], true)) {
            return $this->normaliseBoolean($value);
        }

        $value = $this->normaliseString($value);

        if ($field === 'email' && $value !== null) {
            return strtolower($value);
        }

        return $value;
    }

    private function normaliseString($value): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normaliseBoolean($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $normalised = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalised === null) {
            throw new \InvalidArgumentException('Supporter boolean fields must contain true or false.');
        }

        return $normalised ? 1 : 0;
    }
}
