<?php
/**
 * @version    1.2.0
 * @package    com_ra_members
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$data = is_object($this->item) ? get_object_vars($this->item) : [];

// Any future columns returned by the model are rendered in an additional
// section below, so schema additions are not silently omitted from this view.
$fieldGroups = [
    'Identifiers' => [
        'member_id' => 'Internal profile ID',
        'id' => 'Joomla user ID',
        'membershipNo' => 'Membership number',
        'memberRef' => 'Member reference',
        'contactId' => 'Contact ID',
    ],
    'Names and organisations' => [
        'home_group' => 'Home group',
        'preferred_name' => 'Preferred name',
        'title' => 'Title',
        'initials' => 'Initials',
        'firstName' => 'First name',
        'lastName' => 'Last name',
        'friendlyName' => 'Friendly name',
        'groupName' => 'Group name',
        'areaName' => 'Area name',
        'groupCode' => 'Group code',
    ],
    'Contact details' => [
        'email' => 'Email (Joomla user)',
        'doNotEmail' => 'Do not email',
        'landline' => 'Landline',
        'mobile' => 'Mobile',
        'address1' => 'Address line 1',
        'address2' => 'Address line 2',
        'address3' => 'Address line 3',
        'town' => 'Town',
        'county' => 'County',
        'country' => 'Country',
        'postcode' => 'Postcode',
    ],
    'Membership' => [
        'membershipStatus' => 'Membership status',
        'memberType' => 'Member type',
        'memberTerm' => 'Member term',
        'membershipType' => 'Membership type',
        'jointWith' => 'Joint with',
        'membershipJoinDate' => 'Membership join date',
        'membershipExpiry' => 'Membership expiry',
        'membershipEndDate' => 'Membership end date',
        'areaJoinedDate' => 'Area joined date',
        'teamStatus' => 'Team status',
        'teamRelationshipFrom' => 'Team relationship from',
        'affiliateMemberPrimaryGroup' => 'Affiliate member primary group',
    ],
    'Roles and permissions' => [
        'wellbeingWalker' => 'Wellbeing walker',
        'walkLeader' => 'Walk leader',
        'volunteer' => 'Volunteer',
        'noWalkProgram' => 'Walk programme opt-out',
        'noCampaigning' => 'No campaigning',
        'noSurveys' => 'No surveys',
        'canEmailVolunteers' => 'Can email volunteers',
        'canEmailMembers' => 'Can email members',
        'canEmailWellbeingWalkers' => 'Can email wellbeing walkers',
        'canViewMemberData' => 'Can view member data',
        'canViewMemberDate' => 'Can view member date',
    ],
    'Consent' => [
        'emailConsent' => 'Email consent',
        'emailConsentLastUpdated' => 'Email consent last updated',
        'postConsent' => 'Post consent',
        'postConsentLastUpdated' => 'Post consent last updated',
        'phoneConsent' => 'Phone consent',
        'phoneConsentLastUpdated' => 'Phone consent last updated',
        'emailConsentWellbeingWalks' => 'Wellbeing Walks email consent',
        'emailConsent' => 'Insight email marketing consent',
        'emailPermissionLastUpdated' => 'Insight email permission last updated',
        'postDirectMarketing' => 'Insight post direct marketing',
        'postPermissionLastUpdated' => 'Insight post permission last updated',
        'telephoneDirectMarketing' => 'Insight telephone direct marketing',
        'telephonePermissionLastUpdated' => 'Insight telephone permission last updated',
    ],
    'Import information' => [
        'welcome_sent_date' => 'Welcome sent date',
        'sourceContractVersion' => 'Source contract version',
        'sourceRetrievedAt' => 'Source retrieved at',
        'insightImportedAt' => 'Insight imported at',
        'sourcePayload' => 'Source payload',
        'insightPayload' => 'Insight payload',
    ],
    'Joomla record information' => [
        'state' => 'State',
        'created' => 'Created',
        'created_by' => 'Created by',
        'modified' => 'Modified',
        'modified_by' => 'Modified by',
        'checked_out' => 'Checked out by',
        'checked_out_time' => 'Checked out time',
    ],
];

$booleanFields = array_fill_keys([
    'doNotEmail',
    'wellbeingWalker',
    'walkLeader',
    'volunteer',
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
    'emailConsent',
    'postDirectMarketing',
    'telephoneDirectMarketing',
        ], true);

$dateFields = array_fill_keys([
    'membershipJoinDate',
    'membershipExpiry',
    'membershipEndDate',
    'teamRelationshipFrom',
    'areaJoinedDate',
    'emailConsentLastUpdated',
    'postConsentLastUpdated',
    'phoneConsentLastUpdated',
    'emailPermissionLastUpdated',
    'postPermissionLastUpdated',
    'telephonePermissionLastUpdated',
    'welcome_sent_date',
        ], true);

$dateTimeFields = array_fill_keys([
    'sourceRetrievedAt',
    'insightImportedAt',
    'created',
    'modified',
    'checked_out_time',
        ], true);

$payloadFields = array_fill_keys(['sourcePayload', 'insightPayload'], true);

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$formatValue = static function (string $field, $value) use (
        $booleanFields,
        $dateFields,
        $dateTimeFields,
        $payloadFields,
        $escape
): string {
    if ($value === null || $value === '') {
        return '<span class="text-muted">Not supplied</span>';
    }

    if (isset($booleanFields[$field])) {
        $normalised = strtolower(trim((string) $value));

        if (in_array($normalised, ['1', 'y', 'yes', 'true'], true)) {
            return 'Yes';
        }

        if (in_array($normalised, ['0', 'n', 'no', 'false'], true)) {
            return 'No';
        }

        return $escape($value);
    }

    if ($field === 'state') {
        $states = [
            '-2' => 'Trashed',
            '0' => 'Unpublished',
            '1' => 'Published',
            '2' => 'Archived',
        ];

        return $escape($states[(string) $value] ?? $value);
    }

    if (isset($dateFields[$field]) || isset($dateTimeFields[$field])) {
        if (str_starts_with((string) $value, '0000-00-00')) {
            return '<span class="text-muted">Not supplied</span>';
        }

        $format = isset($dateTimeFields[$field]) ? 'd M Y H:i:s' : 'd M Y';

        try {
            return $escape(HTMLHelper::_('date', $value, $format));
        } catch (\Throwable $exception) {
            return $escape($value);
        }
    }

    if (isset($payloadFields[$field])) {
        $payload = (string) $value;

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $payload = json_encode(
                    $decoded,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            // Retain and safely display the stored value if it is not valid JSON.
        }

        return '<details><summary>View stored payload</summary><pre class="mt-2 mb-0">'
                . $escape($payload)
                . '</pre></details>';
    }

    return nl2br($escape($value));
};

$displayedFields = [];
?>

<div class="com-ra-members-member">
<?php foreach ($fieldGroups as $heading => $fields) : ?>
        <section class="card mb-3">
            <div class="card-header">
                <h2 class="h5 mb-0"><?php echo $escape($heading); ?></h2>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <tbody>
    <?php foreach ($fields as $field => $label) : ?>
                            <?php $displayedFields[$field] = true; ?>
                            <tr>
                                <th scope="row" class="w-25"><?php echo $escape($label); ?></th>
                                <td><?php echo $formatValue($field, $data[$field] ?? null); ?></td>
                            </tr>
    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php endforeach; ?>

    <?php
    $additionalFields = array_diff_key($data, $displayedFields);
    if ($additionalFields !== []) :
        ksort($additionalFields);
        ?>
        <section class="card mb-3">
            <div class="card-header">
                <h2 class="h5 mb-0">Additional profile fields</h2>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <tbody>
    <?php foreach ($additionalFields as $field => $value) : ?>
                            <tr>
                                <th scope="row" class="w-25"><?php echo $escape($field); ?></th>
                                <td><?php echo $formatValue($field, $value); ?></td>
                            </tr>
    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php endif; ?>

    <?php if (!empty($data['id'])) : ?>
        <?php $this->showRoles(); ?>
        <?php $this->showSubscriptions(); ?>
    <?php endif; ?>

    <?php $this->showAudit(); ?>

    <div class="mt-3">
<?php echo $this->toolsHelper->backButton('administrator/index.php?option=com_ra_members&view=members'); ?>
    </div>
</div>
