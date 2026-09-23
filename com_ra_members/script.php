<?php

/*
 * Installation script
 * 14/06/25 CB add link to dashboard
 * 09/08/25 CB ra_mail_lists / emails_outstanding
 * 06/04/26 CB add mail_list/description
 * 08/07/26 CB new fields for organisations
 * 30/08/26 CB consolidate installed component version lookup
 * 06/09/26 CB correct deleteFolder and invokation of buildButton
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class Com_Ra_membersInstallerScript {

    private const MINIMUM_MAILMAN_VERSION = '5.0.18';
    private const MINIMUM_TOOLS_VERSION = '4.0.13';

    private $component;
    private $minimumJoomlaVersion = '4.0';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $reconfigure_message;

    private function message(string $message): void {
        Factory::getApplication()->enqueueMessage($message, 'message');
    }

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        //       echo "colour=$colour, code=$code, class=$class<br>";
        $q = chr(34);
        $out = "<a class=" . $q . $class . $q;
        $out .= " href=" . $q . $url . $q;
        $out .= " target =" . $q . "_self" . $q;
        $out .= ">";
        $out .= $text;
        $out .= "</a>";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data supplied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
//        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            return $this->fail('Installer could not update missing field ' . $table_name . '.' . $column . '.');
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
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->getValue($sql);
    }

    private function checkMinimumComponentVersion(string $component, string $requiredVersion): bool {
        try {
            $installedVersion = $this->getInstalledComponentVersion($component);
        } catch (\RuntimeException $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'jerror');
            return $this->fail('RA Members could not read the installed version of ' . $component . '.');
        }

        if ($installedVersion !== null && version_compare($installedVersion, $requiredVersion, 'ge')) {
            $this->message('Version ' . $requiredVersion . ' of ' . $component
                    . ' required; version ' . $installedVersion . ' found.');
            return true;
        }

        return $this->fail('RA Members requires ' . $component . ' version ' . $requiredVersion
                        . ' or later; found ' . ($installedVersion ?: 'no readable version') . '.');
    }

    function checkTools() {
        echo 'Checking version of com_ra_tools<br>';
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            try {
                $toolsVersion = $this->getInstalledComponentVersion('com_ra_tools');
            } catch (\RuntimeException $exception) {
                return $this->fail($exception->getMessage());
            }

            echo '<p>com_ra_tools is currently at version ' . ($toolsVersion ?? 'not recorded') . '</p>';
            if ($toolsVersion !== null && version_compare($toolsVersion, '5.0.2', '>')) {
                echo 'Greater than 5.0.2, OK<br>';
                return true;
            } else {
                return $this->fail('This operation requires com_ra_tools later than version 5.0.2.');
            }
        } else {
            return $this->fail('This operation requires the enabled component com_ra_tools.');
        }
        return true;
    }

    function checkTable($table, $details, $details2 = '') {

        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->getValue($sql);
        echo 'Seeking ' . $table_name . ', count=' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            return $this->fail('Installer failed to create database table ' . $table_name . '.');
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                return $this->fail('Installer failed to alter database table ' . $table_name . '.');
            }
        }
    }

    private function deleteFile($target) {
// Not needed, could use a built in function (if details were known!)
        $file = JPATH_ROOT . $target;
        if (file_exists($file)) {
            echo 'File ' . $file . ' found,';
            File::delete($file);
            if (file_exists($file)) {
                echo ' deleted<br>';
            } else {
                echo ' but unable to delete<br>';
            }
        } else {
            echo "Unable to delete $file: file not found<br>";
        }
    }

    private function deleteFolder($target) {
// created 08/10/24 - does not seem to work
        $folder = JPATH_ROOT . $target;
        if (file_exists($folder)) {
            echo 'Folder ' . $folder . ' found,';
            Folder::delete($folder);
            if (file_exists($folder)) {
                echo ' but unable to delete<br>';
            } else {
                echo ' deleted<br>';
            }
        } else {
            echo 'Unable to delete ' . $folder . ': folder not found<br>';
        }
    }

    public function deleteView($view, $application = '') {
// first character of View must be upper case
        $component = 'com_ra_members';
        echo 'Deleting ';
        if ($application == '') {
            echo 'Site ';
        } else {
            echo $application . ' ';
        }
        echo 'files<br>';

        $this->deleteFile($application . '/components/' . $component . '/forms/filter_' . strtolower($view) . '.xml');
        $this->deleteFile($application . '/components/' . $component . '/src/Controller/' . $view . 'Controller.php');
        $this->deleteFile($application . '/components/' . $component . '/src/Model/' . $view . 'Model.php');
        $this->deleteFile($application . '/components/' . $component . '/src/table/' . $view . 'Table.php');
        $this->deleteFolder($application . '/components/' . $component . '/src/View/' . $view);
        $this->deleteFolder($application . '/components/' . $component . '/tmpl/' . strtolower($view));
    }

    private function executeCommand($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->execute();
    }

    private function fail(string $message): bool {
        Factory::getApplication()->enqueueMessage($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    /**
     * Return the installed manifest version for a component.
     */
    private function getInstalledComponentVersion(string $component = 'com_ra_members'): ?string {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $extensionType = 'component';

        $query->select($db->quoteName('e.manifest_cache'))
                ->from($db->quoteName('#__extensions', 'e'))
                ->where($db->quoteName('e.element') . ' = :component')
                ->where($db->quoteName('e.type') . ' = :extensionType')
                ->bind(':component', $component, ParameterType::STRING)
                ->bind(':extensionType', $extensionType, ParameterType::STRING);

        $db->setQuery($query);
        $manifestCache = $db->loadResult();

        if ($manifestCache === null) {
            return null;
        }

        try {
            $manifest = json_decode((string) $manifestCache, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                            'Installer could not decode version information for ' . $component . '.',
                            0,
                            $exception
            );
        }

        if (!is_array($manifest)) {
            throw new \RuntimeException('Installer found invalid version information for ' . $component . '.');
        }

        $installedVersion = $manifest['version'] ?? null;

        return is_scalar($installedVersion) ? (string) $installedVersion : null;
    }

    private function getValue($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        $this->message('Installing RA Members (com_ra_members).');
        if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion));
        }
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion));
        }

        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            if (!$this->checkMinimumComponentVersion('com_ra_tools', self::MINIMUM_TOOLS_VERSION)) {
                return false;
            }
        } else {
            return $this->fail('RA Members requires the enabled component com_ra_tools.');
        }

        if (!ComponentHelper::isEnabled('com_ra_mailman', true)) {
            return $this->fail('RA Members requires the enabled component com_ra_mailman.');
        }

        if (!$this->checkMinimumComponentVersion('com_ra_mailman', self::MINIMUM_MAILMAN_VERSION)) {
            return false;
        }

        return true;
    }

    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function uninstall($parent): bool {
        echo '<p>Uninstalling RA Members (com_ra_members)<br>';
        try {
            $installedVersion = $this->getInstalledComponentVersion();
        } catch (\RuntimeException $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'jerror');
            $installedVersion = null;
        }

        if ($installedVersion === null) {
            echo '<p>Version information not available</p>';
        } else {
            echo '<p>Version ' . $installedVersion . '</p>';
        }
        return true;
    }

    public function update($parent): bool {
        echo '<p>Updating RA Members (com_ra_members)</p>';
//return true;
// You can have the backend jump directly to the newly updated component configuration page
// $parent->getParent()->setRedirectURL('index.php?option=com_ra_members');
        return true;
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA Members (com_ra_members).');
        if ($type == 'uninstall') {
            return true;
        }
//        if ($reconfigure_message == true) {
//            $this->red('Please review and update the configuration settings for com_ra_members.');
//        }
        $message = '<b>Useful links</b><br>'
                . $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->buildButton('index.php?option=com_config&view=component&component=com_ra_members', 'Configure');
        echo $message;
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA Members (type=' . $type . ').');
        if ($type == 'uninstall') {
            return true;
        }
        if ($type == 'install') {
            $this->message('No action required by preflight on install.');
            return true;
        }

        if (ComponentHelper::isEnabled('com_ra_members', true)) {
            try {
                $currentVersion = $this->getInstalledComponentVersion();
            } catch (\RuntimeException $exception) {
                return $this->fail($exception->getMessage());
            }

            if ($currentVersion === null) {
                return $this->fail('Installer could not find readable version information for com_ra_members.');
            }

            $this->message('com_ra_members already present, version=' . $currentVersion . '.');
        } else {
            return $this->fail('Installer could not find the existing com_ra_members installation.');
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            return $this->fail('RA Members requires the enabled component com_ra_tools.');
        }
        if (!ComponentHelper::isEnabled('com_ra_mailman', true)) {
            return $this->fail('RA Members requires the enabled component com_ra_mailman.');
        }

        if (!$this->checkMinimumComponentVersion('com_ra_mailman', self::MINIMUM_MAILMAN_VERSION)) {
            return false;
        }

        if (!$this->checkMinimumComponentVersion('com_ra_tools', self::MINIMUM_TOOLS_VERSION)) {
            return false;
        }

        $versionRequired = '1.1.0';

        if (version_compare($currentVersion, $versionRequired, 'ge')) {
            $this->message('Current version is ' . $currentVersion . '; no additional processing required.');
            return true;
        } else {
            $this->message('Version is currently ' . $currentVersion
                    . '; version ' . $versionRequired . ' or later is required to skip upgrade processing.');
        }
        if (version_compare($currentVersion, '1.1.0', 'le')) {
            $this->checkColumn('ra_organisations', 'mailman_active', 'A', 'VARCHAR(1) DEFAULT "N" AFTER longitude; ');
            /*
              $this->checkColumn('ra_mail_shots', 'record_type', 'A', 'VARCHAR(1) DEFAULT "M" AFTER id; ');
              $this->checkColumn('ra_mail_shots', 'mail_list_id', 'U', 'INT NULL; ');
              $this->checkColumn('ra_mail_shots', 'event_id', 'A', 'INT NULL AFTER mail_list_id; ');

             */
        }
        if (version_compare($currentVersion, '1.2', 'le')) {
            $this->checkColumn('ra_organisations', 'notes', 'A', 'MEDIUMTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL AFTER details; ');
            $this->checkColumn('ra_organisations', 'uses_ra_tools', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER mailman_active; ');
            $this->checkColumn('ra_organisations', 'uses_ra_mailman', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER uses_ra_tools; ');
            $this->checkColumn('ra_organisations', 'uses_ngx', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER uses_ra_mailman; ');
            $this->checkColumn('ra_organisations', 'email_header', 'U', 'VARCHAR(255); ');
        }
        return true;
    }

}
