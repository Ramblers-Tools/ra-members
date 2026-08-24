<?php

/**
 * Helper to load member data from the Central Office API feed
 * Usually run from a batch job via cron, but can be invoked from the view organisations
 *
 * If run on-line, messages are displayed directly; if in batch mode, messages are
 * stored in arrays for later display
 *
 * @version    4.7.0
 * @package    com_ra_members
 * @author     charles
 * 18/04/26 CB created
 * 05/06/26 CB include expired members
 * 21/06/26 CB add lookupMember
 */

namespace Ramblers\Component\Ra_members\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Service\SharedUserNameResolver;
use Ramblers\Component\Ra_members\Site\Service\SupporterApiConfig;
use Ramblers\Component\Ra_members\Site\Service\SupporterMapper;
use Ramblers\Component\Ra_members\Site\Service\MemberFeedMode;
use Ramblers\Component\Ra_tools\Administrator\Table\ProfileTable;

class LoadHelper {

    protected $db;
    protected $app;
    protected $toolsHelper;
    protected $jsonHelper;
    protected $mailHelper;
    protected $primary_list;
    protected $profileColumns;
    protected $auditColumns;
    protected $roleColumns;
    protected $sharedUserNameResolver;
    protected $supporterMapper;
    protected $userColumns;
    protected $duplicateFeedMembers = array();
    protected $duplicateFeedNotifications = array();
    protected $currentGroupCode = '';
    protected $currentRetrievedAt = '';
    private $counter = 0;
    public $batch_mode = false; // if true, messages are added to $this->messages instead of enqueued, for display at the end of the batch process
    public $comments;
    public $comment_count;
    public $count_new_profiles = 0;
    public $count_new_users = 0;
    public $count_legacy_profiles_reused = 0;
    public $count_not_updated = 0;
    public $count_updated = 0;
    public $errors;
    public $messages = array();

    public function __construct() {
        $this->app = Factory::getApplication();
        $this->db = Factory::getDbo();
        $this->jsonHelper = new JsonHelper;
        $this->mailHelper = new MailHelper;
        $this->sharedUserNameResolver = new SharedUserNameResolver();
        $this->supporterMapper = new SupporterMapper();
        $this->toolsHelper = new ToolsHelper;
        $this->comments = array();
        $this->errors = array();
        $this->comment_count = 0;
    }

    private function booleanToDatabase($value) {
        if ($value === true || $value === 1 || $value === '1' || $value === 'Y' || $value === 'true') {
            return 'Y';
        }

        return null;
    }

    private function buildPreferredName($member) {
        $firstName = trim((string) ($member['firstName'] ?? ''));
        $lastName = trim((string) ($member['lastName'] ?? ''));
        $preferredName = trim($firstName . ' ' . $lastName);

        return ($preferredName === '') ? null : $preferredName;
    }

    private function buildRoleRows($member, $memberId) {
        return $this->supporterMapper->mapVolunteerRoles(
                $this->normaliseMember($member),
                (int) $memberId,
                $this->currentGroupCode
        );
    }

    private function buildUserName($member) {
        $email = $this->normaliseScalar($member['email'] ?? null);

        if ($email !== null && $this->isDuplicateFeedEmail($email)) {
            $sharedName = $this->sharedUserNameResolver->resolve(
                    $this->duplicateFeedMembers[strtolower($email)]
            );

            if ($sharedName !== null) {
                return $sharedName;
            }
        }

        $firstName = trim((string) ($member['firstName'] ?? ''));
        $lastName = trim((string) ($member['lastName'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        return ($name === '') ? null : $name;
    }

    private function createUserFromMember($member) {
        $email = $this->normaliseScalar($member['email'] ?? null);

        if ($email === null) {
            return null;
        }

        $userHelper = new UserHelper;
        $userHelper->group_code = $this->normaliseScalar($member['groupCode'] ?? '');
        $userHelper->name = $this->buildUserName($member);
        $userHelper->email = $email;

        if (!$userHelper->createUserOnly()) {
            $this->messages[] = 'Unable to create user for ' . $email . ': ' . $userHelper->error;
            return false;
        }

        $this->count_new_users++;

        return (int) $userHelper->user_id;
    }

    private function ensurePrimarySubscription($userId) {
        if ((int) $userId <= 0 || !$this->primary_list) {
            return;
        }

        if ($this->mailHelper->subscribe($this->primary_list, (int) $userId, 1, 3)) {
            $this->messages[] = 'Subscription created to list ' . $this->primary_list;
        } else {
            $this->messages[] = 'Error creating subscription to list ' . $this->primary_list . ': ' . $this->mailHelper->message;
        }
    }

    private function failLoad(string $message): bool
    {
        $this->messages[] = $message;
        $this->logMessage($message, '3');
        return false;
    }

    private function getProfileByUserId($userId) {
        if ((int) $userId <= 0) {
            return null;
        }

        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $userId)
                ->order($this->db->quoteName('member_id') . ' ASC');

        $this->db->setQuery($query, 0, 1);

        return $this->db->loadObject();
    }

    private function getProfilesByUserId($userId, $excludeMemberId = 0) {
        if ((int) $userId <= 0) {
            return array();
        }

        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $userId);

        if ((int) $excludeMemberId > 0) {
            $query->where($this->db->quoteName('member_id') . ' <> ' . (int) $excludeMemberId);
        }

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList();

        return is_array($rows) ? $rows : array();
    }

    private function getUserColumns() {
        if ($this->userColumns === null) {
            $this->userColumns = $this->db->getTableColumns('#__users', false);
        }

        return $this->userColumns;
    }

    private function lookupUserById($userId) {
        if ((int) $userId <= 0) {
            return null;
        }

        $sql = 'SELECT id, name, username, email FROM #__users WHERE id=' . (int) $userId;
        return $this->toolsHelper->getItem($sql);
    }

    private function loadProfileTable($memberId = 0) {
        $profile = new ProfileTable($this->db);

        if ((int) $memberId > 0) {
            $profile->load((int) $memberId);
        }

        return $profile;
    }

    private function notifyUserConflict($member, $reason, $user = null, array $profiles = array()) {
        $params = ComponentHelper::getParams('com_ra_tools');
        $to = trim((string) $params->get('email_new_user', ''));
        $membershipNo = $this->normaliseScalar($member['membershipNo'] ?? null);
        $memberRef = $this->normaliseScalar($member['memberRef'] ?? null);
        $email = $this->normaliseScalar($member['email'] ?? null);

        $message = 'Membership sync requires manual intervention.<br>';
        $message .= 'Reason: ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '<br>';
        $message .= 'Member reference: ' . htmlspecialchars((string) $memberRef, ENT_QUOTES, 'UTF-8') . '<br>';
        $message .= 'Membership number: ' . htmlspecialchars((string) $membershipNo, ENT_QUOTES, 'UTF-8') . '<br>';
        $message .= 'Feed name: ' . htmlspecialchars((string) $this->buildUserName($member), ENT_QUOTES, 'UTF-8') . '<br>';
        $message .= 'Feed email: ' . htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') . '<br>';

        if (is_object($user) && !empty($user->id)) {
            $message .= 'User id: ' . (int) $user->id . '<br>';
            $message .= 'Current user name: ' . htmlspecialchars((string) $user->name, ENT_QUOTES, 'UTF-8') . '<br>';
            $message .= 'Current username: ' . htmlspecialchars((string) $user->username, ENT_QUOTES, 'UTF-8') . '<br>';
            $message .= 'Current email: ' . htmlspecialchars((string) $user->email, ENT_QUOTES, 'UTF-8') . '<br>';
        }

        if (!empty($profiles)) {
            $details = array();

            foreach ($profiles as $profile) {
                $details[] = 'member_id ' . (int) $profile->member_id
                        . ' membershipNo ' . (string) ($profile->membershipNo ?? '')
                        . ' memberRef ' . (string) ($profile->memberRef ?? '');
            }

            $message .= 'Linked profiles: ' . htmlspecialchars(implode('; ', $details), ENT_QUOTES, 'UTF-8') . '<br>';
        }

        if ($to !== '') {
            $this->toolsHelper->sendEmail($to, '', 'Membership sync conflict for shared email', $message);
        }

        $this->messages[] = 'Manual intervention required for memberRef ' . $memberRef . ': ' . $reason;
        $this->logMessage('Manual intervention required for memberRef ' . $memberRef . ': ' . $reason, '3');
    }

    private function notifyDuplicateFeedEmail($email, $user = null, array $profiles = array()) {
        $email = $this->normaliseScalar($email);

        if ($email === null) {
            return;
        }

        $key = strtolower($email);

        if (isset($this->duplicateFeedNotifications[$key])) {
            return;
        }

        $params = ComponentHelper::getParams('com_ra_tools');
        $to = trim((string) $params->get('email_new_user', ''));
        $members = $this->duplicateFeedMembers[$key] ?? array();
        $message = 'Membership sync found duplicate email usage in the Salesforce feed.<br>';
        $message .= 'Email: ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '<br>';
        $message .= 'Action required: decide which Joomla user name should be used for this shared email address.' . '<br>';
        $message .= 'If no Joomla user already exists for this email address, create one user manually using the shared email address and the chosen name.' . '<br>';
        $message .= 'If a Joomla user already exists for this email address, update that user manually if you want a different name to be used.' . '<br>';
        $message .= 'After that manual step is complete, run the membership import again. The next import will link both profile records to that Joomla user automatically.' . '<br>';

        if (!empty($members)) {
            $rows = array();

            foreach ($members as $member) {
                $rows[] = htmlspecialchars((string) $this->buildUserName($member), ENT_QUOTES, 'UTF-8')
                        . ' / memberRef ' . htmlspecialchars((string) ($member['memberRef'] ?? ''), ENT_QUOTES, 'UTF-8')
                        . ' / Membership number ' . htmlspecialchars((string) ($member['membershipNo'] ?? ''), ENT_QUOTES, 'UTF-8');
            }

            $message .= 'Feed members: ' . implode('<br>', $rows) . '<br>';
        }

        if (is_object($user) && !empty($user->id)) {
            $message .= 'Current Joomla user id: ' . (int) $user->id . '<br>';
            $message .= 'Current Joomla user name: ' . htmlspecialchars((string) $user->name, ENT_QUOTES, 'UTF-8') . '<br>';
            $message .= 'Current Joomla username: ' . htmlspecialchars((string) $user->username, ENT_QUOTES, 'UTF-8') . '<br>';
            $message .= 'Current Joomla email: ' . htmlspecialchars((string) $user->email, ENT_QUOTES, 'UTF-8') . '<br>';
        }

        if (!empty($profiles)) {
            $details = array();

            foreach ($profiles as $profile) {
                $details[] = 'member_id ' . (int) $profile->member_id
                        . ' membershipNo ' . (string) ($profile->membershipNo ?? '')
                        . ' memberRef ' . (string) ($profile->memberRef ?? '');
            }

            $message .= 'Linked profiles: ' . htmlspecialchars(implode('; ', $details), ENT_QUOTES, 'UTF-8') . '<br>';
        }

        if ($to !== '') {
            $this->toolsHelper->sendEmail($to, '', 'Membership sync duplicate email in feed', $message);
        }

        $this->duplicateFeedNotifications[$key] = true;
        $this->messages[] = 'Manual intervention required for duplicate feed email ' . $email;
        $this->logMessage('Manual intervention required for duplicate feed email ' . $email, '3');
    }

    private function resolveProfileRow($member) {
        $memberRef = $this->normaliseScalar($member['memberRef'] ?? null);

        return array($this->getProfileByMemberRef($memberRef), false);
    }

    private function resolveUserId($member, $profileRow) {
        $email = $this->normaliseScalar($member['email'] ?? null);
        $isDuplicateFeedEmail = $this->isDuplicateFeedEmail($email);

        if ($email === null) {
            return array(null, false, false);
        }

        $desiredName = $this->buildUserName($member);
        $existingUserId = is_object($profileRow) && !empty($profileRow->id) ? (int) $profileRow->id : 0;

        if ($existingUserId > 0) {
            $existingUser = $this->lookupUserById($existingUserId);

            if (is_object($existingUser) && !empty($existingUser->id)) {
                $emailOwner = $this->lookupUser($email);

                if (is_object($emailOwner) && (int) $emailOwner->id !== $existingUserId) {
                    $this->notifyUserConflict($member, 'email already belongs to a different Joomla user', $emailOwner, $this->getProfilesByUserId((int) $emailOwner->id));
                    return array($existingUserId, false, false);
                }

                $sharedProfiles = $this->getProfilesByUserId($existingUserId, (int) ($profileRow->member_id ?? 0));
                $needsUserUpdate = $this->normaliseScalar($existingUser->name ?? null) !== $this->normaliseScalar($desiredName) || $this->normaliseScalar($existingUser->email ?? null) !== $email || $this->normaliseScalar($existingUser->username ?? null) !== $email;

                if (!empty($sharedProfiles) && $needsUserUpdate && !$isDuplicateFeedEmail) {
                    $this->notifyUserConflict($member, 'shared Joomla user would need updating', $existingUser, $sharedProfiles);
                    return array($existingUserId, false, false);
                }

                $this->updateUserRecord($existingUserId, $member, $existingUser);

                return array($existingUserId, false, false);
            }
        }

        $matchedUser = $this->lookupUser($email);

        if (is_object($matchedUser) && !empty($matchedUser->id)) {
            $sharedProfiles = $this->getProfilesByUserId((int) $matchedUser->id, (int) ($profileRow->member_id ?? 0));

            if (!empty($sharedProfiles)
                    && $this->normaliseScalar($matchedUser->name ?? null) !== $this->normaliseScalar($desiredName)
                    && !$isDuplicateFeedEmail) {
                $this->notifyUserConflict($member, 'matched shared Joomla user would need a name change', $matchedUser, $sharedProfiles);
                return array((int) $matchedUser->id, false, true);
            }

            $this->updateUserRecord((int) $matchedUser->id, $member, $matchedUser);

            return array((int) $matchedUser->id, false, true);
        }

        $createdUserId = $this->createUserFromMember($member);

        if ($createdUserId === false) {
            return array(false, false, false);
        }

        return array($createdUserId, true, true);
    }

    private function updateUserRecord($userId, $member, $existingUser = null) {
        if ((int) $userId <= 0) {
            return false;
        }

        if (!is_object($existingUser)) {
            $existingUser = $this->lookupUserById($userId);
        }

        if (!is_object($existingUser) || empty($existingUser->id)) {
            return false;
        }

        $email = $this->normaliseScalar($member['email'] ?? null);
        $name = $this->buildUserName($member);
        $fields = array();

        if ($this->valuesDiffer($existingUser->name ?? null, $name)) {
            $fields[] = $this->db->quoteName('name') . ' = ' . $this->quoteValue($name);
        }

        if ($email !== null) {
            if ($this->valuesDiffer($existingUser->username ?? null, $email)) {
                $fields[] = $this->db->quoteName('username') . ' = ' . $this->quoteValue($email);
            }

            if ($this->valuesDiffer($existingUser->email ?? null, $email)) {
                $fields[] = $this->db->quoteName('email') . ' = ' . $this->quoteValue($email);
            }

            if (array_key_exists('signon', $this->getUserColumns())) {
                $fields[] = $this->db->quoteName('signon') . ' = ' . $this->quoteValue($email);
            }
        }

        if (empty($fields)) {
            return true;
        }

        $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__users'))
                ->set($fields)
                ->where($this->db->quoteName('id') . ' = ' . (int) $userId);

        $this->db->setQuery($query)->execute();

        return true;
    }

    private function saveProfileRecord($profileRow, $data) {
        $profile = $this->loadProfileTable((int) ($profileRow->member_id ?? 0));

        if (!$profile->save($data)) {
            $this->messages[] = 'Error saving profile: ' . $profile->getError();
            return null;
        }

        return $profile;
    }

    private function firstWord($token) {
        $token = trim((string) $token);

        if ($token === '') {
            return '';
        }

        $space = strpos($token, ' ');

        if ($space === false || $space === 0) {
            return $token;
        }

        if (($space === 1) && (substr($token, 0, 1) === 'O')) {
            return "O'" . substr($token, 2);
        }

        return substr($token, 0, $space);
    }

    private function formatDateForDatabase($value) {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Exception $e) {
            if (strlen($value) >= 10) {
                return substr($value, 0, 10);
            }

            return null;
        }
    }

    private function getAuditColumns() {
        if ($this->auditColumns === null) {
            $this->auditColumns = $this->db->getTableColumns('#__ra_profiles_audit', false);
        }

        return $this->auditColumns;
    }

    private function getCurrentUserId() {
        $user = $this->app->getSession()->get('user');

        if (is_object($user) && isset($user->id)) {
            return (int) $user->id;
        }

        return 0;
    }

    public function getDefaultList($code) {
        $sql = 'SELECT id FROM #__ra_mail_lists ';
        $sql .= 'WHERE group_code=' . $this->db->quote($code);
        $sql .= ' AND group_primary=' . $this->db->quote($code);
        return $this->toolsHelper->getValue($sql);
    }

    private function getApiSiteConfig(int $apiSiteId): SupporterApiConfig {
        $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'url', 'token', 'state', 'sub_system']))
                ->from($this->db->quoteName('#__ra_api_sites'))
                ->where($this->db->quoteName('id') . ' = ' . $apiSiteId);

        $this->db->setQuery($query, 0, 1);

        return SupporterApiConfig::fromRow($this->db->loadObject(), $apiSiteId);
    }

public function getJson(int $apiSiteId, string $code)
{
    try {
        $site = $this->getApiSiteConfig($apiSiteId);
    } catch (\Throwable $exception) {
        $this->logMessage('API site config error: ' . $exception->getMessage(), '3');
        return false;
    }

    $separator = strpos($site->getUrl(), '?') === false ? '?' : '&';
    $url = $site->getUrl() . $separator . http_build_query([
        'api_key'   => $site->getToken(),
        'team_code' => $code,
    ], '', '&', PHP_QUERY_RFC3986);

    if (JDEBUG) {
        $this->messages[] = 'Requesting supporters for API site ID ' . $site->getId()
                . ' and team ' . $code;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
        ],
    ]);

    $responseData = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($responseData === false) {
        $this->logMessage('Supporter feed cURL error: ' . $curlError, '3');
        return false;
    }

    if ($httpCode !== 200) {
        $snippet = substr(trim((string) $responseData), 0, 300);
        $this->logMessage('Supporter feed HTTP ' . $httpCode . ' response: ' . $snippet, '3');
        return false;
    }

    $decoded = json_decode($responseData, true);
    if (!is_array($decoded)) {
        $this->logMessage('Supporter feed JSON decode failed: ' . json_last_error_msg(), '3');
        return false;
    }

    return $decoded;
}

    private function getProfileByMemberRef($memberRef) {
        if ($memberRef === null) {
            return null;
        }

        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('memberRef') . ' = ' . $this->db->quote($memberRef));

        $this->db->setQuery($query, 0, 1);

        return $this->db->loadObject();
    }

    private function getProfilesByMembershipNo($membershipNo) {
        $membershipNo = $this->normaliseScalar($membershipNo);

        if ($membershipNo === null) {
            return array();
        }

        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('membershipNo') . ' = ' . $this->db->quote($membershipNo))
                ->order($this->db->quoteName('member_id') . ' ASC');

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList();

        return is_array($rows) ? $rows : array();
    }

    private function getProfileColumns() {
        if ($this->profileColumns === null) {
            $this->profileColumns = $this->db->getTableColumns('#__ra_profiles', false);
        }

        return $this->profileColumns;
    }

    private function getProfileReference($profile) {
        if (is_object($profile)) {
            if (isset($profile->member_id) && (int) $profile->member_id > 0) {
                return (int) $profile->member_id;
            }
        }

        return 0;
    }

    private function getRoleColumns() {
        if ($this->roleColumns === null) {
            $this->roleColumns = $this->db->getTableColumns('#__ra_roles', false);
        }

        return $this->roleColumns;
    }

    function lookupMember($member_id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE member_id=' . (int) $member_id;
        return $this->toolsHelper->getValue($sql);
    }

    private function lookupPreferredName($member_id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE member_id=' . (int) $member_id;
        return $this->toolsHelper->getValue($sql);
    }

    private function lookupUser($email) {
        $sql = 'SELECT id, name, username, email FROM #__users WHERE email=';
        $sql .= $this->db->quote($email);
        return $this->toolsHelper->getItem($sql);
    }

    private function normaliseMember($member) {
        if (is_object($member)) {
            $member = json_decode(json_encode($member), true);
        }

        return is_array($member) ? $member : array();
    }

    private function normaliseScalar($value) {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Y' : '';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $value = trim((string) $value);

        return ($value === '') ? null : $value;
    }
    
    private function mapSupporterToProfileData($member) {
        $member = $this->normaliseMember($member);
        $data = $this->supporterMapper->mapProfile(
                $member,
                $this->currentGroupCode,
                $this->currentRetrievedAt
        );

        return $data;
    }

    private function identifyDuplicateFeedEmails($members) {
        $groupedMembers = array();

        foreach ($members as $member) {
            $member = $this->normaliseMember($member);
            $email = $this->normaliseScalar($member['email'] ?? null);

            if ($email === null) {
                continue;
            }

            $key = strtolower($email);

            if (!isset($groupedMembers[$key])) {
                $groupedMembers[$key] = array();
            }

            $groupedMembers[$key][] = $member;
        }

        $this->duplicateFeedMembers = array();
        $this->duplicateFeedNotifications = array();

        foreach ($groupedMembers as $key => $items) {
            if (count($items) > 1) {
                $this->duplicateFeedMembers[$key] = $items;
            }
        }
    }

    private function isDuplicateFeedEmail($email) {
        $email = $this->normaliseScalar($email);

        if ($email === null) {
            return false;
        }

        return array_key_exists(strtolower($email), $this->duplicateFeedMembers);
    }

    private function normaliseEnumValue($value, array $allowedValues, $fieldName, $fallbackValue = null) {
        $value = $this->normaliseScalar($value);

        if ($value === null) {
            return null;
        }

        $lowerValue = strtolower($value);

        foreach ($allowedValues as $allowedValue) {
            if ($lowerValue === strtolower($allowedValue)) {
                return $allowedValue;
            }
        }

        $fallback = $allowedValues[0] ?? null;

        if ($fallbackValue !== null) {
            foreach ($allowedValues as $allowedValue) {
                if (strtolower($fallbackValue) === strtolower($allowedValue)) {
                    $fallback = $allowedValue;
                    break;
                }
            }
        }

        $message = 'Unknown ' . $fieldName . ' value "' . $value . '" from feed';

        if ($fallback !== null) {
            $message .= '; using fallback "' . $fallback . '"';
        }

        $this->messages[] = $message;
        $this->logMessage($message, '3');

        return $fallback;
    }

    private function quoteValue($value) {
        if ($value === null) {
            return 'NULL';
        }

        return $this->db->quote($value);
    }

    private function mapMemberToProfileData($member, $existingProfile = null, $userId = null) {
        $columns = $this->getProfileColumns();
        $data = array_intersect_key($member, $columns);

        // home_group currently duplicates the source groupCode. Keep legacy
        // profiles usable until that redundancy is removed by ensuring a blank
        // value is populated by either import path.
        $existingHomeGroup = is_object($existingProfile)
                ? $this->normaliseScalar($existingProfile->home_group ?? null)
                : null;

        if (array_key_exists('home_group', $columns) && $existingHomeGroup === null) {
            $incomingHomeGroup = $this->normaliseScalar($member['home_group'] ?? null);

            if ($incomingHomeGroup === null) {
                $incomingHomeGroup = $this->normaliseScalar($member['groupCode'] ?? null);
            }

            if ($incomingHomeGroup !== null) {
                $data['home_group'] = strtoupper($incomingHomeGroup);
            }
        }

        if (array_key_exists('preferred_name', $columns)
                && (!is_object($existingProfile) || empty($existingProfile->member_id))) {
            $data['preferred_name'] = $this->buildPreferredName($member);
        }

        if (array_key_exists('id', $columns)) {
            $data['id'] = $userId;
        }

        if (array_key_exists('state', $columns) && !isset($data['state'])) {
            $data['state'] = 1;
        }

        return $data;
    }

    private function createProfileAudit($objectId, $recordType, $fieldName = '', $oldValue = null, $newValue = null) {
        if ((int) $objectId <= 0) {
            return;
        }
        $this->toolsHelper->createAuditRecord($fieldName, $oldValue, $newValue, $objectId, 'ra_profiles');
    }

    private function syncRoles($profile, $member) {
        $columns = $this->getRoleColumns();

        if (!array_key_exists('member_id', $columns)) {
            return;
        }

        $memberId = $this->getProfileReference($profile);

        if ($memberId <= 0) {
            return;
        }

        $deleteQuery = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ra_roles'))
                ->where($this->db->quoteName('member_id') . ' = ' . (int) $memberId)
                ->where($this->db->quoteName('organisation_code') . ' = '
                        . $this->db->quote($this->currentGroupCode));

        $this->db->setQuery($deleteQuery)->execute();

        $roleRows = $this->buildRoleRows($member, $memberId);

        foreach ($roleRows as $roleRow) {
            $storedRole = array_intersect_key($roleRow, $columns);
            $columnNames = [];
            $values = [];

            foreach ($storedRole as $columnName => $value) {
                $columnNames[] = $this->db->quoteName($columnName);
                $values[] = $columnName === 'member_id'
                        ? (int) $value
                        : $this->quoteValue($value);
            }

            $query = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__ra_roles'))
                    ->columns($columnNames)
                    ->values(implode(',', $values));

            $this->db->setQuery($query)->execute();
        }
    }

    private function syncMember($member) {
        $supporter = $this->normaliseMember($member);

        try {
            $member = $this->mapSupporterToProfileData($supporter);
        } catch (\Throwable $exception) {
            $this->logMessage('Skipped supporter that could not be mapped: ' . $exception->getMessage(), '3');
            return false;
        }

        $memberRef = $this->normaliseScalar($member['memberRef'] ?? null);
        if (JDEBUG) {
            $this->messages[] = 'Syncing memberRef ' . $memberRef . ', member ' . ($member['membershipNo'] ?? '');
        }

        if ($memberRef === null) {
            $this->logMessage('Skipped record without memberRef', '3');
            return false;
        }

        $this->db->transactionStart();

        try {
            list($profileRow, $reusedLegacyProfile) = $this->resolveProfileRow($member);
            list($userId, $createdUser, $linkRequiresSubscription) = $this->resolveUserId($member, $profileRow);

            if ($userId === false) {
                $this->db->transactionRollback();
                return false;
            }

            $data = $this->mapMemberToProfileData($member, $profileRow, $userId);
            $changes = array();
            $isNewProfile = !is_object($profileRow) || empty($profileRow->member_id);

            if (!$isNewProfile) {
                foreach ($data as $columnName => $newValue) {
                    $oldValue = property_exists($profileRow, $columnName) ? $profileRow->$columnName : null;

                    if ($this->valuesDiffer($oldValue, $newValue)) {
                        $changes[$columnName] = array(
                            'old' => $oldValue,
                            'new' => $newValue,
                        );
                    }
                }
            }

            $profile = $this->saveProfileRecord($profileRow, $data);

            if ($profile === null) {
                $this->db->transactionRollback();
                $this->logMessage('Failed to save profile for ' . $memberRef, '3');
                return false;
            }

            if ($isNewProfile) {
                $this->messages[] = 'Created new profile for memberRef ' . $memberRef . ' with member_id ' . $profile->member_id;
                $this->count_new_profiles++;
                $this->createProfileAudit($this->getProfileReference($profile), 'C', '', null, '');
            } elseif ($reusedLegacyProfile) {
                $this->messages[] = 'Reused profile for memberRef ' . $memberRef . ' with member_id ' . $profile->member_id;
                $this->count_legacy_profiles_reused++;
                $this->count_updated++;

                foreach ($changes as $fieldName => $change) {
                    $this->createProfileAudit($this->getProfileReference($profile), 'U', $fieldName, $change['old'], $change['new']);
                }
            } elseif (empty($changes)) {
                $this->count_not_updated++;
            } else {
                $this->count_updated++;

                foreach ($changes as $fieldName => $change) {
                    $this->createProfileAudit($this->getProfileReference($profile), 'U', $fieldName, $change['old'], $change['new']);
                }
            }

            $this->syncRoles($profile, $supporter);

            if ($linkRequiresSubscription && (int) $userId > 0) {
                $this->ensurePrimarySubscription((int) $userId);
            }

            $this->db->transactionCommit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            $this->messages[] = 'Error syncing member ' . $memberRef . ': ' . $exception->getMessage();
            $this->logMessage('Error syncing member ' . $memberRef . ': ' . $exception->getMessage(), '3');
            return false;
        }
    }

    /**
     * Preview or persist rows already mapped from an Insight CSV file.
     */
    public function processInsightMembers(array $members, string $mode, bool $preview = false): bool {
        if (!in_array($mode, [MemberFeedMode::JSON_ENRICHMENT, MemberFeedMode::INSIGHT_PRIMARY], true)) {
            $this->messages[] = 'Unknown Insight import mode.';
            return false;
        }

        $this->count_new_profiles = 0;
        $this->count_new_users = 0;
        $this->count_updated = 0;
        $this->count_not_updated = 0;
        $this->identifyDuplicateFeedEmails($members);

        $membershipCounts = array_count_values(array_map(
            static fn ($member) => (string) ($member['membershipNo'] ?? ''),
            $members
        ));

        if ($mode === MemberFeedMode::INSIGHT_PRIMARY && !$preview) {
            $code = strtoupper(trim((string) ComponentHelper::getParams('com_ra_tools')->get('default_group', '')));

            if (!preg_match('/^[A-Z0-9]{4}$/', $code)) {
                $this->messages[] = 'com_ra_tools default_group must contain four letters or digits.';
                return false;
            }

            $this->currentGroupCode = $code;
            $this->primary_list = $this->getDefaultList($code);

            if (!$this->primary_list) {
                $this->messages[] = 'Unable to find the default mailing list for ' . $code;
                return false;
            }
        }

        $ok = true;

        foreach ($members as $member) {
            $membershipNo = (string) ($member['membershipNo'] ?? '');
            $rowNumber = (int) ($member['_row'] ?? 0);
            $rowLabel = $rowNumber > 0 ? 'Row ' . $rowNumber . ': ' : '';

            if ($membershipNo === '' || ($membershipCounts[$membershipNo] ?? 0) > 1) {
                $this->messages[] = $rowLabel . 'duplicate or missing membership number; record ignored.';
                $ok = false;
                continue;
            }

            $profiles = $this->getProfilesByMembershipNo($membershipNo);

            if (count($profiles) > 1) {
                $this->messages[] = $rowLabel . 'membership number ' . $membershipNo . ' matches multiple profiles; record ignored.';
                $ok = false;
                continue;
            }

            $profileRow = $profiles[0] ?? null;

            if ($mode === MemberFeedMode::JSON_ENRICHMENT && $profileRow === null) {
                $this->messages[] = $rowLabel . 'membership number ' . $membershipNo . ' is not present from the JSON feed; record ignored.';
                $ok = false;
                continue;
            }

            if ($preview) {
                $action = $profileRow === null ? 'would create a profile' : 'would update profile ' . (int) $profileRow->member_id;
                $this->messages[] = $rowLabel . 'membership number ' . $membershipNo . ' ' . $action . '.';
                continue;
            }

            if (!$this->syncInsightMember($member, $profileRow, $mode)) {
                $ok = false;
            }
        }

        return $ok;
    }

    private function syncInsightMember(array $member, $profileRow, string $mode): bool {
        $membershipNo = (string) $member['membershipNo'];
        unset($member['_row']);
        $this->db->transactionStart();

        try {
            $userId = is_object($profileRow) && !empty($profileRow->id) ? (int) $profileRow->id : null;
            $linkRequiresSubscription = false;

            if ($mode === MemberFeedMode::INSIGHT_PRIMARY) {
                list($userId, $createdUser, $linkRequiresSubscription) = $this->resolveUserId($member, $profileRow);

                if ($userId === false) {
                    $this->db->transactionRollback();
                    return false;
                }
            }

            $data = $this->mapMemberToProfileData($member, $profileRow, $userId);
            $changes = array();
            $isNewProfile = !is_object($profileRow) || empty($profileRow->member_id);

            if (!$isNewProfile) {
                foreach ($data as $columnName => $newValue) {
                    $oldValue = property_exists($profileRow, $columnName) ? $profileRow->$columnName : null;

                    if ($this->valuesDiffer($oldValue, $newValue)) {
                        $changes[$columnName] = ['old' => $oldValue, 'new' => $newValue];
                    }
                }
            }

            $profile = $this->saveProfileRecord($profileRow, $data);

            if ($profile === null) {
                $this->db->transactionRollback();
                return false;
            }

            if ($isNewProfile) {
                $this->count_new_profiles++;
                $this->createProfileAudit($this->getProfileReference($profile), 'C', '', null, '');
                $this->messages[] = 'Created profile for membership number ' . $membershipNo . '.';
            } elseif ($changes === []) {
                $this->count_not_updated++;
            } else {
                $this->count_updated++;

                foreach ($changes as $fieldName => $change) {
                    $this->createProfileAudit($this->getProfileReference($profile), 'U', $fieldName, $change['old'], $change['new']);
                }
            }

            if ($linkRequiresSubscription && (int) $userId > 0) {
                $this->ensurePrimarySubscription((int) $userId);
            }

            $this->db->transactionCommit();
            return true;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            $this->messages[] = 'Error syncing membership number ' . $membershipNo . ': ' . $exception->getMessage();
            $this->logMessage('Insight sync error for membership number ' . $membershipNo . ': ' . $exception->getMessage(), '3');
            return false;
        }
    }

    public function loadMembers(int $apiSiteId) {
        $jsonSetting = ComponentHelper::getParams('com_ra_members')->get('enable_json_feed', 1);

        if (!MemberFeedMode::isJsonEnabled($jsonSetting)) {
            $this->messages = array('The JSON supporter feed is disabled in RA Members configuration.');
            return false;
        }

        $code = strtoupper(trim((string) ComponentHelper::getParams('com_ra_tools')->get('default_group', '')));

        if (!preg_match('/^[A-Z0-9]{4}$/', $code)) {
            $this->messages = array('com_ra_tools default_group must contain four letters or digits.');
            return false;
        }

        $this->currentGroupCode = $code;

        $startedAt = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $this->currentRetrievedAt = $startedAt;

        $this->logMessage('Processing ' . $code, 1);
        $this->messages = array();
        $members = $this->getJson($apiSiteId, $code);
        if ($members === false) {
            $this->messages[] = 'getJson was false for ' . $code;
            return false;
        }
        // Find the Primary list for this Group
        $this->primary_list = $this->getDefaultList($code);
        if (!$this->primary_list) {
            $message = 'Unable to find Primary list for ' . $code;
            $this->logMessage($message, 1);
            $this->messages[] = $message;
            return false;
        }

        //       die('Load members for ' . $code . ', got ' . count($members) . ' records');
        $count = $this->processMembers($members);
        $this->updateOrganisationLastUpdated($code, $startedAt);
        $this->messages[] = $count . ' records processed for ' . $code;
        if ($count > 0) {
            $message = 'New profile records ' . $this->count_new_profiles;
            $message .= ', New user records ' . $this->count_new_users;
            $message .= ', Legacy profiles reused ' . $this->count_legacy_profiles_reused;
            $message .= ', Updated records ' . $this->count_updated;
            $message .= ', Not updated records ' . $this->count_not_updated;
            $message .= ', Watermark ' . $startedAt;
            $this->messages[] = $message;
        }
        return true;
    }

    /**
     *   Store a log entry
     */
    public function logMessage($text, $record_type = '3') {

        $query = $this->db->getQuery(true);

        $query->insert('#__ra_logfile')
                ->set("record_type = " . $this->db->quote($record_type))
                ->set("message = " . $this->db->quote($text))
                ->set("sub_system = 'RA Members'")
                ->set("ref = " . $this->db->quote('LoadMemb'))
        ;

        $result = $this->db->setQuery($query)->execute();
    }

   public function processMembers($members) {
        $count = 0;
        $this->count_new_profiles = 0;
        $this->count_new_users = 0;
        $this->count_legacy_profiles_reused = 0;
        $this->count_updated = 0;
        $this->count_not_updated = 0;

        if (is_object($members) && isset($members->members)) {
            $members = $members->members;
        } elseif (is_array($members) && isset($members['members'])) {
            $members = $members['members'];
        }

        if (!is_array($members) && !($members instanceof \Traversable)) {
            $this->logMessage('No member data returned from feed', '3');
            return 0;
        }

        if ($members instanceof \Traversable) {
            $members = iterator_to_array($members, false);
        }

        $this->identifyDuplicateFeedEmails($members);

        foreach ($members as $member) {
            $count++;
            $this->syncMember($member);
        }

        return $count;
    }

    private function updateOrganisationLastUpdated($code, $startedAt) {
        $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ra_organisations'))
                ->set($this->db->quoteName('last_updated') . ' = ' . $this->db->quote($startedAt))
                ->where($this->db->quoteName('code') . ' = ' . $this->db->quote($code));

        $this->db->setQuery($query)->execute();
    }

    private function valuesDiffer($oldValue, $newValue) {
        return $this->normaliseScalar($oldValue) !== $this->normaliseScalar($newValue);
    }

}
