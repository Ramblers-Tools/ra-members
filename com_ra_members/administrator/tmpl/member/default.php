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

$showDate = static function ($value) {
    if ($value === '' || is_null($value)) {
        return '';
    }

    return HTMLHelper::_('date', $value, 'd/M/y');
};

echo 'Mem No: <b>' . $this->item->membershipNo . '</b>, Member reference: <b>' . $this->item->memberRef . '</b>';
if (!empty($this->item->contactId)) {
    echo ', Contact id: <b>' . $this->item->contactId . '</b>';
}
if (!is_null($this->item->member_id)) {
    echo ', Internal id: <b>' . $this->item->member_id . '</b>';
}
if (!is_null($this->item->id)) {
    echo ', User id: <b>' . $this->item->id . '</b>';
}
echo '<br>';
echo 'Name: <b>' . $this->item->title . ' ' . $this->item->firstName . ' ' . $this->item->lastName . '</b><br>';
echo 'Preferred Name: <b>' . $this->item->preferred_name . '</b><br>';
echo 'Home group: <b>' . $this->item->home_group . '</b>';
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
    // $loadHelper->checkEmail
    $sql = 'SELECT id FROM `#__users` WHERE email="' . $this->item->email_spare . '"';
    $user_id = $this->toolsHelper->getValue($sql);
    if ($user_id) {
        $sql = 'UPDATE #__ra_profiles SET id=' . (int) $user_id;
        $sql .= ' WHERE member_id=' . (int) $this->item->member_id;
        $this->toolsHelper->executeCommand($sql);
        $sql = 'SELECT email FROM `#__users` WHERE id = ' . (int) $user_id;
        $email = $this->toolsHelper->getValue($sql);
    } else {
        $email = 'No email address';
    }
} else {
    $sql = 'SELECT email FROM `#__users` WHERE id = ' . (int) $this->item->id;
    $email = $this->toolsHelper->getValue($sql);
}
echo '<b>' . $email . '</b>';
//if ($this->item->id == 0) {
//    echo '<b>No email address</b>';
//} else {
//    $sql = 'SELECT email FROM `#__users` WHERE id = ' . (int) $this->item->id;
//    $email = $this->toolsHelper->getValue($sql);
//    echo '<b>' . $email . '</b>';
//}
echo '<br>';

// $this->toolsHelper
echo 'Member Type: <b>' . $this->item->memberType . '</b><br>';
echo 'Team Status: <b>' . $this->item->teamStatus . '</b><br>';
echo 'Member Status: <b>' . $this->item->membershipStatus . '</b><br>';
echo 'Membership Term:<b> ' . $this->item->memberTerm . '</b><br>';
echo 'Joined: ';
if ($this->item->membershipJoinDate !== '') {
    echo 'Ramblers <b>' . $showDate($this->item->membershipJoinDate) . '</b>';
}
if (!is_null($this->item->areaJoinedDate)) {
    echo ', Area <b>' . $showDate($this->item->areaJoinedDate) . '</b>';
}
if (!is_null($this->item->teamRelationshipFrom)) {
    echo ', Group <b>' . $showDate($this->item->teamRelationshipFrom) . '</b>';
}

echo '<br>';
echo 'Wellbeing Walker: <b>' . ($this->item->wellbeingWalker == 1 ? 'Yes' : 'No') . '</b>, ';
echo 'Walk Leader: <b>' . ($this->item->walkLeader == 1 ? 'Yes' : 'No') . '</b><br>';
echo 'Email Marketing Consent: <b>';
echo ($this->item->emailConsent == 1) ? 'Yes' : 'No';
echo '</b>';
if (!is_null($this->item->emailConsentLastUpdated)) {
    echo ' Last updated <b>' . $showDate($this->item->emailConsentLastUpdated) . '</b>';
}
echo '<br>';
echo 'Post Marketing Consent: <b>';
echo ($this->item->postConsent == 1) ? 'Yes' : 'No';
echo '</b>';
if (!is_null($this->item->postConsentLastUpdated)) {
    echo ' Last updated <b>' . $showDate($this->item->postConsentLastUpdated) . '</b>';
}
echo '<br>';
echo 'Telephone Marketing Consent: <b>';
echo ($this->item->phoneConsent == 1) ? 'Yes' : 'No';
if (!is_null($this->item->phoneConsentLastUpdated)) {
    echo '</b> Last updated <b>' . $showDate($this->item->phoneConsentLastUpdated);
}
echo '</b><br>';

echo 'Walk Programme Opt Out: <b>';
echo ($this->item->noWalkProgram == 1) ? 'Yes' : 'No';
echo '</b><br>';

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
