<?php

/**
 * @version    1.1.7
 * @package    com_ra_members
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/04/26 CB created
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

echo '<h3>Identifiers</h3>';
echo 'Membership Number: <b>' . $this->item->membershipNo . '</b>, ';
echo 'Member reference: <b>' . $this->item->memberRef . ($this->item->memberRef == '' ? 'Not given' : $this->item->memberRef) . '</b> ';
if (!empty($this->item->contactId)) {
    echo ', Contact id: <b>' . $this->item->contactId . '</b>';
}
if (!is_null($this->item->member_id)) {
    echo ', Member id: <b>' . $this->item->member_id . '</b>';
}
if (!is_null($this->item->id)) {
    echo ', User id: <b>' . $this->item->id . '</b>';
}
echo '<br>';
echo '<h3>Personal Information</h3>';
echo 'Name: <b>' . $this->item->title . ' ' . $this->item->firstName . ' ' . $this->item->lastName . '</b><br>';
echo 'Preferred Name: <b>' . $this->item->preferred_name . '</b><br>';
echo 'Home group: <b>' . $this->item->home_group . '</b>'. $this->toolsHelper->lookupGroup($this->item->home_group);
if ($this->item->home_group !== $this->item->groupCode) {
    echo ', Group code: <b>';
    if (is_null($this->item->groupCode)) {
        echo '(blank)';
    } else {
        echo $this->item->groupCode;
    }
}
echo '</b><br>';

echo 'Address: <b>' . $this->item->address1;
if ($this->item->address2 !== '') {
    echo ', ' . $this->item->address2;
}
if ($this->item->address3 !== '') {
    echo ', ' . $this->item->address3;
}
if ($this->item->town !== '') {
    echo ', ' . $this->item->town;
}
if ($this->item->county !== '') {
    echo ', ' . $this->item->county;
}
if ($this->item->country !== '') {
    echo ', ' . $this->item->country;
}
if ($this->item->postcode !== '') {
    echo ', ' . $this->item->postcode;
}
echo '</b><br>';
echo 'Phone: <b>';
$phones = '';
if (!is_null($this->item->mobile)) {
    $phones = 'Mobile <b>' . $this->item->mobile . '</b>';
}
if (!is_null($this->item->landline)) {
    if ($phones !== '') {
        $phones .= ', ';
    }
    $phones .= 'Landline <b>' . $this->item->landline . '</b>';
}
if ($phones == '') {
    echo 'No phone';
} else {
    echo $phones;
}
echo '</b><br>';
echo 'Email: ';
if (is_null($this->item->id)) {
    $email = 'No Joomla user linked';
} else {
    $sql = 'SELECT email FROM `#__users` WHERE id = ' . (int) $this->item->id;
    $email = $this->toolsHelper->getValue($sql);
}
echo '<b>' . $email . '</b> ';
echo 'Do not email: <b>';
echo ($this->item->doNotEmail == 1) ? 'Yes' : 'No';
//if ($this->item->id == 0) {
//    echo '<b>No email address</b>';
//} else {
//    $sql = 'SELECT email FROM `#__users` WHERE id = ' . (int) $this->item->id;
//    $email = $this->toolsHelper->getValue($sql);
//    echo '<b>' . $email . '</b>';
//}
echo '</b><br>';

// $this->toolsHelper
echo '<h3>Membership</h3>';
echo 'Member Status: <b>' . $this->item->membershipStatus . '</b><br>';
echo 'Member Type: <b>' . $this->item->memberType . '</b><br>';
echo 'Membership Term:<b> ' . $this->item->memberTerm . '</b><br>';
echo 'Membership Type: <b>' . $this->item->membershipType . '</b><br>';

echo 'Joined Ramblers: <b>' . $this->formatDate($this->item->membershipJoinDate) . '</b>';
echo ', Joined Area <b>' . $this->formatDate($this->item->areaJoinedDate) . '</b>';
echo ', Membership Expiry <b>' . $this->formatDate($this->item->membershipExpiry) . '</b>';
echo ', Membership End date <b>' . $this->formatDate($this->item->membershipEndDate) . '</b>';

echo 'Team Status: <b>' . $this->item->teamStatus . '</b>';
echo ', Affiliate Member Primary Group: <b>' . $this->item->affiliateMemberPrimaryGroup . '</b><br>';
echo '<br>';

echo '<h4>Roles and permissions</h4>';
echo 'Wellbeing Walker: <b>' . $this->formatBoolean($this->item->wellbeingWalker) . '</b>, ';
echo 'Walk Leader: <b>' . $this->formatBoolean($this->item->walkLeader) . '</b><br>';
echo 'Volunteer: <b>' . $this->formatBoolean($this->item->volunteer) . '</b><br>';

echo 'Walk Programme Opt Out: <b>';
echo $this->formatBoolean($this->item->noWalkProgram);
echo '</b>';
echo ' No campaigning: <b>';
echo $this->formatBoolean($this->item->noCampaigning);
echo '</b>';
echo ' No surveys: <b>';
echo $this->formatBoolean($this->item->noSurveys);
echo '</b><br>';
echo 'Can view member data: <b>';
echo $this->formatBoolean($this->item->canViewMemberData);
echo '</b>';
echo ' Can email members: <b>';
echo $this->formatBoolean($this->item->canEmailMembers);
echo '</b>';
echo ' Can email volunteers: <b>';
echo $this->formatBoolean($this->item->canEmailVolunteers);
echo '</b>';
echo ' Can email wellbeing walkers: <b>';
echo $this->formatBoolean($this->item->canEmailWellbeingWalkers);
echo '</b><br>';

echo '<h4>Consents</h4>';
echo 'Email Marketing Consent: <b>';
echo $this->formatBoolean($this->item->emailConsent);
echo '</b>';
if (!is_null($this->item->emailConsentLastUpdated)) {
    echo ' Last updated <b>' . $this->formatDate($this->item->emailConsentLastUpdated) . '</b>';
}
echo '<br>';
echo 'Post Marketing Consent: <b>';
echo $this->formatBoolean($this->item->postConsent);
echo '</b>';
if (!is_null($this->item->postConsentLastUpdated)) {
    echo ' Last updated <b>' . $this->formatDate($this->item->postConsentLastUpdated) . '</b>';
}
echo '<br>';
echo 'Telephone Marketing Consent: <b>';
echo $this->formatBoolean($this->item->phoneConsent);
if (!is_null($this->item->phoneConsentLastUpdated)) {
    echo '</b> Last updated <b>' . $this->formatDate($this->item->phoneConsentLastUpdated);
}
echo '</b><br>';

if (!is_null($this->item->id)) {

// Show any Roles
    $this->showRoles();
// Show any subsc// $this->showRoles();riptions
    $this->showSubscriptions();
}

$this->showAudit();

$back = 'administrator/index.php?option=com_ra_members&view=members';
echo $this->toolsHelper->backButton($back);
echo '<br>';
//echo var_dump($this->item);
//echo '<br>';
