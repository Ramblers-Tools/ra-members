<?php

/*
 * Installation script
 * 14/06/25 CB add link to dashboard
 * 09/08/25 CB ra_mail_lists / emails_outstanding
 * 06/04/26 CB add mail_list/description
 * 08/07/26 CB new fields for organisations
 * 30/08/26 CB consolidate installed component version lookup
 * 06/09/26 CB correct deleteFolder and invokation of buildButton
 * 24/01/26 CB dependent on tools 4.0.19 (shared js assets)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Exception\FilesystemException;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class Com_Ra_membersInstallerScript {

    private $component;
    private $original_version;
    private $reconfigure_message;
    private $schemaHelper;
    private $toolsHelper;

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

    private function getSchemaHelper(): SchemaHelper {
        if ($this->schemaHelper === null) {
            $this->schemaHelper = new SchemaHelper();
        }

        return $this->schemaHelper;
    }

    private function getToolsHelper(): ToolsHelper {
        if ($this->toolsHelper === null) {
            $this->toolsHelper = new ToolsHelper();
        }

        return $this->toolsHelper;
    }

    public function install($parent): bool {
        $this->message('Installing RA Members (com_ra_members).');
        return true;
    }

    private function message(string $message): void {
        Factory::getApplication()->enqueueMessage($message, 'message');
    }

    public function postflight($type, $parent) {
        if ($type == 'uninstall') {
            return true;
        }
        $new_version =$this->getInstalledComponentVersion();
        if ($this->original_version !== $new_version) {
            echo '<p>com_ra_members has been upgraded from ' . $this->original_version . ' to ' . $new_version . '</p>';
        } else {
            echo '<p>com_ra_members is unchanged at ' . $new_version . '</p>';
        }
        if ($this->reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_members.');
        }
        $message = '<b>Useful links</b><br>'
                . $this->toolsHelper->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->toolsHelper->buildButton('index.php?option=com_config&view=component&component=com_ra_members', 'Configure');
        echo $message;
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA Members (type=' . $type . ').');
        if ($type == 'uninstall') {
            return true;
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            return $this->fail('RA Treasurer requires com_ra_tools to be installed and enabled.');
        }

        if (!class_exists(ToolsHelper::class)) {
            return $this->fail('RA Treasurer could not load com_ra_tools helpers.');
        }
        if (!class_exists(SchemaHelper::class)) {
            return $this->fail('RA Treasurer could not load com_ra_tools schema helper.');
        }

        $this->getToolsHelper();
        $this->getSchemaHelper();

        if (ComponentHelper::isEnabled('com_ra_members', true)) {
            try {
                $this->original_version = $this->getInstalledComponentVersion();
            } catch (\RuntimeException $exception) {
                $this->message($exception->getMessage());
            }

            if ($this->original_version === null) {
                $this->message('Installer could not find readable version information for com_ra_members.');
            }

            $this->message('com_ra_members already present, version=' . $this->original_version . '.');
        } else {
            $this->message('Installer could not find the existing com_ra_members installation.');
            return false;
        }
        $versionRequired = '1.1.0';

        if (version_compare($this->original_version, $versionRequired, 'ge')) {
            $this->message('Current version is ' . $this->original_version . '; no additional processing required.');
            return true;
        } else {
            $this->message('Version is currently ' . $this->original_version
                    . '; version ' . $versionRequired . ' or later is required to skip upgrade processing.');
        }

        if (version_compare($this->original_version, '1.2', 'le')) {
            $this->schemaHelper->checkColumn('ra_organisations', 'notes', 'A', 'MEDIUMTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL AFTER details; ');
            $this->schemaHelper->checkColumn('ra_organisations', 'uses_ra_tools', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER mailman_active; ');
            $this->schemaHelper->checkColumn('ra_organisations', 'uses_ra_mailman', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER uses_ra_tools; ');
            $this->schemaHelper->checkColumn('ra_organisations', 'uses_ngx', 'A', 'CHAR(1) NULL DEFAULT NULL AFTER uses_ra_mailman; ');
            $this->checkColumn('ra_organisations', 'email_header', 'U', 'VARCHAR(255); ');
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
//return true;
// You can have the backend jump directly to the newly updated component configuration page
// $parent->getParent()->setRedirectURL('index.php?option=com_ra_members');
        return true;
    }

}
