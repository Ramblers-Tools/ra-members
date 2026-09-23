<?php

/**
 * @version     1.1.7
 * @package     com_ra_members
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 *
 * 09/06/26 CB created
 * 15/06/26 CB add new fields in ra_profiles, check ra_logfile / sub_system
 * 15/06/26 CB add new field welcome_sent_date to ra_profiles
 */

namespace Ramblers\Component\Ra_members\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;


class SystemController extends FormController {

    protected $app;
    protected $back;
    protected $db;
    protected $toolsHelper;

    public function __construct(
            $config = [],
            MVCFactoryInterface $factory = null,
            CMSApplication $app = null,
            Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        $this->db = Factory::getDbo();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field
//  $mode = U: update the field (keeping name the same)
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            echo 'Field ' . $column . ' not found in ' . $table_name . '<br>';
            return false;
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        echo "$sql<br>";
        $response = $this->toolsHelper->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->toolsHelper->getValue($sql);
    }

    function checkTable($table, $details, $details2 = '') {

        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->toolsHelper->getValue($sql);
        echo 'Seeking ' . $table_name . ', count=' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->toolsHelper->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            echo 'Failure<br>';
            return false;
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->toolsHelper->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                echo 'Failure<br>';
                return false;
            }
        }
    }

    public function checkSchema() {
        $toolsHelper = new ToolsHelper;
        if (!$toolsHelper->isSuperuser()) {
            return;
        }
        $helper = New SchemaHelper;
// table ra_import_reports
        $details = '(
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `date_phase1` DATETIME NOT NULL ,
            `date_completed` DATETIME NULL ,
            `method_id` int(11) NOT NULL,
            `list_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `num_records` INT  NOT NULL DEFAULT "0",
            `num_errors` INT  NOT NULL DEFAULT "0",
            `num_users` INT  NOT NULL DEFAULT "0",
            `num_subs` INT  NOT NULL DEFAULT "0",
            `num_lapsed` INT  NOT NULL DEFAULT "0",
            `ip_address` VARCHAR(255)  NULL  DEFAULT "",
            `error_report` MEDIUMTEXT  DEFAULT NULL,
            `new_users` MEDIUMTEXT DEFAULT NULL,
            `new_subs` MEDIUMTEXT DEFAULT NULL,
            `lapsed_members` MEDIUMTEXT DEFAULT NULL,
            `input_file` VARCHAR(255) NOT NULL,
            `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_by` INT NULL DEFAULT "0",
            `modified` DATETIME NULL DEFAULT NULL,
            `modified_by` INT NULL DEFAULT "0",
            `checked_out_time` DATETIME NULL  DEFAULT NULL ,
            `checked_out` INT NULL,
            `state` TINYINT(1)  NULL  DEFAULT 1,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
        $helper->checkTable('ra_import_reports', $details);
        $helper->checkColumn('ra_api_sites', 'sub_system', 'U', 'VARCHAR(12) ');
//        $helper->checkColumn('ra_events', 'max_bookings', 'A', 'INT NOT NULL DEFAULT "1" AFTER bookable; ');
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($target);
    }

    private function createList($code) {
        $sql = 'SELECT id FROM #__ra_mail_lists ';
        $sql .= 'WHERE group_code=' . $this->db->quote($code) . ' ';
        $sql .= 'AND name=' . $this->db->quote('Members Newsletter') . ' ';
        $id = $this->toolsHelper->getValue($sql);
        if (!$id) {
            $sql = 'INSERT INTO `#__ra_mail_lists` (`state`, `name`, `group_code`, `group_primary`, `owner_id`, `record_type`, `home_group_only`, `chat_list`, `footer`, `emails_outstanding`, `ordering`, `checked_out_time`, `created`, `created_by`, `modified`, `modified_by`) ';
            $sql .= 'VALUES ("1", "Walk  Leaders", ';
            $sql .= $this->db->quote($code) . ',NULL';
            $sql .= ' "C", "1", "1", "0", ';
            $sql .= $this->db->quote('Sent to you as a walk leader for ' . $name);
            // $sql .= 'VALUES ("1", "Members Newsletter", ';
            // $sql .= $this->db->quote($code) . ', ' . $this->db->quote($code) . ',';
            // $sql .= ' "O", "1", "1", "0", ';
            //  $sql .= $this->db->quote('Sent to you as a member of the Ramblers ' . $name);
            $sql .= ', "0", NULL, NULL, current_timestamp(), "1", NULL, "0");';
            $this->toolsHelper->executeCommand($sql);
        }
    }

    public function createLists() {
        $sql = 'SELECT code FROM #__ra_organisations ';
        $sql .= 'ORDER BY code';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->createList($row->code);
        }
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($target);
    }

    function logMessage($record_type, $ref, $message) {
        $db = Factory::getDbo();

// Create a new query object.
        $query = $this->db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_logfile'))
                ->set('record_type =' . $db->quote($record_type))
                ->set('ref = ' . $db->quote($record_type))
                ->set('message =' . $db->quote($message));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
    }

    public function purgeAllUsers() {
        ToolBarHelper::title($this->prefix . 'Purging Blocked users');
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Invalid access<br>';
            return;
        }
        $sql = "SELECT id, name as 'User', email  ";
        $sql .= 'FROM `#__users` ';
        $sql .= ' WHERE block=1';
        $sql .= ' ORDER BY id';
        $target = 'administrator/index.php?option=com_ra_members&task=system.purgeUser&id=';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->purgeUserRecord($row->id);
        }
        $userHelper = new UserHelper;
        $userHelper->purgeProfiles();
        $back = 'administrator/index.php?option=com_ra_members&view=reports';
        echo $this->toolsHelper->backButton($back);
    }

    function test() {
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $helper = New SchemaHelper;
        $helper->checkColumn('ra_logfile', 'sub_system', 'U', 'VARCHAR(10) NOT NULL; ');
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
//        return;

        $date = Factory::getDate();
        echo $date . '<br>';

        $sql = 'SELECT id, group_code, name, emails_outstanding ';
        $sql .= 'FROM #__ra_mail_lists ';
        $sql .= 'WHERE emails_outstanding>0 ORDER BY group_code, name';
        $rows = $toolsHelper->getRows($sql);
        $toolsHelper->showQuery($sql);
        $id = 0;
        foreach ($rows as $row) {
            if ($id == 0) {
                $id = $row->id;
                $name = $row->group_code . '/' . $row->name;
            }
            $message .= 'Group ' . $row->group_code . ', List ' . $row->name;
            $message .= ',' . $row->emails_outstanding . ' emails to be sent<br>';
        }
        if ($id > 0) {
            $message .= 'Sending emails for ' . $name . '<br>';
            echo $message;
        }
    }

    public function UpdateMembership() {
        //       $id = $this->objApp->input->getInt('id', '0');
        ToolBarHelper::title($this->prefix . 'UpdateMembership');
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Invalid access<br>';
        } else {
            $sql = 'SELECT id FROM #__ra_profiles WHERE membershipNo IS NULL';
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                $sql = 'UPDATE #__ra_profiles SET membershipNo=' . (3 * $row->id);
                $sql .= ' WHERE id=' . $row->id;
                echo $sql . '<br>';
                $this->toolsHelper->executeCommand($sql);
            }
        }
        $back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($back);
    }

    public function updateSchema() {
        //     index.php?option=com_ra_members&task=system.UpdateSchema
        ToolBarHelper::title($this->prefix . 'UpdateSchema');
        echo '<p>The #__ra_profiles schema is owned by com_ra_tools. Reinstall the revised RA Tools package to update it.</p>';
        $back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($back);
    }

    /**
     * Explicitly attach an unlinked Member profile to an existing Joomla user.
     *
     * This is the only supported path for the exceptional case where two
     * Member profiles intentionally share one Joomla user/email address.
     * It is deliberately restricted to Super Users and never runs implicitly
     * while displaying a Member record.
     */
    public function attachSharedProfile(): void {
        if (!$this->toolsHelper->isSuperuser()) {
            throw new \RuntimeException('Only a Super User may attach a shared-email profile.', 403);
        }

        $memberId = $this->app->input->getInt('member_id', 0);
        $userId = $this->app->input->getInt('user_id', 0);

        if ($memberId < 1 || $userId < 1) {
            $form = Form::getInstance(
                    'com_ra_members.sharedprofile',
                    JPATH_ADMINISTRATOR . '/components/com_ra_members/forms/sharedprofile.xml'
            );
            echo '<h1>Attach shared-email profiles</h1>';
            echo '<p>Select the existing Joomla user, the second unlinked Member profile, and the combined name to use.</p>';
            echo '<form method="post" action="index.php?option=com_ra_members&task=system.attachSharedProfile">';
            echo $form->renderField('user_id');
            echo $form->renderField('member_id');
            echo $form->renderField('combined_name');
            echo '<button type="submit" class="btn btn-warning">Attach shared profile</button>';
            echo HTMLHelper::_('form.token');
            echo '</form>';
            return;
        }

        $this->checkToken();

        $profileQuery = $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'preferred_name']))
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('member_id') . ' = :memberId')
                ->bind(':memberId', $memberId);
        $this->db->setQuery($profileQuery);
        $profile = $this->db->loadObject();

        if (!$profile) {
            throw new \RuntimeException('The Member profile could not be found.');
        }
        if (!empty($profile->id)) {
            throw new \RuntimeException('The Member profile is already linked to a Joomla user.');
        }

        $combinedName = trim((string) $this->app->input->get('combined_name', '', 'string'));
        if ($combinedName === '') {
            throw new \InvalidArgumentException('A combined Joomla user name is required.');
        }

        $userQuery = $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'email']))
                ->from($this->db->quoteName('#__users'))
                ->where($this->db->quoteName('id') . ' = :userId')
                ->bind(':userId', $userId);
        $this->db->setQuery($userQuery);
        $joomlaUser = $this->db->loadObject();

        if (!$joomlaUser) {
            throw new \RuntimeException('The Joomla user could not be found.');
        }

        $userFactory = $this->app->getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class);
        $user = $userFactory->loadUserById($userId);
        if (!$user->bind(['name' => $combinedName]) || !$user->save()) {
            throw new \RuntimeException('Unable to update the Joomla user name: ' . $user->getError());
        }

        $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ra_profiles'))
                ->set($this->db->quoteName('id') . ' = :userId')
                ->where($this->db->quoteName('member_id') . ' = :memberId')
                ->bind(':userId', $userId)
                ->bind(':memberId', $memberId);
        $this->db->setQuery($update)->execute();

        $message = 'Member profile ' . $memberId . ' was explicitly attached to Joomla user '
                . $userId . ' (' . $joomlaUser->email . ') for shared-email use.';
        Log::add($message, Log::NOTICE, 'ra_members');
        $this->app->enqueueMessage($message, 'message');
        $this->setRedirect('index.php?option=com_ra_members&view=member&member_id=' . $memberId);
    }

}
