<?php
/*
 * Package Installation script
 * 25/10/26 CB Created from ra-treasurer
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Language\Text;

class pkg_ra_membersInstallerScript
{
    private const MINIMUM_MAILMAN_VERSION = '5.1.3';
    private const MINIMUM_TOOLS_VERSION = '4.0.16';

    private function getInstalledVersion(string $element = 'com_ra_members'): ?string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        $db->setQuery($query);

        $manifestCache = $db->loadResult();

        if ($manifestCache === null) {
            return null;
        }

        $manifest = json_decode((string) $manifestCache, true);
        return is_array($manifest) && isset($manifest['version']) ? (string)$manifest['version'] : null;
    }

    private function fail(string $message): bool
    {
        Factory::getApplication()->enqueueMessage($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');
        // echo also helps surface the message in some installers
        $this->message('<p style="color:#c00;"><strong>' . htmlspecialchars($message). '</strong></p>');
        return false;
    }

    private function message(string $message): void {
        Factory::getApplication()->enqueueMessage($message, 'message');
    }

    public function postflight($type, $parent) {
        if ($type == 'uninstall') {
            return true;
        }
        $new_version =$this->getInstalledVersion();
        if ($this->original_version !== $new_version) {
            echo '<p>com_ra_members has been upgraded from ' . $this->original_version . ' to ' . $new_version . '</p>';
        } else {
            echo '<p>com_ra_members is unchanged at ' . $new_version . '</p>';
        }
        if ($this->reconfigure_message == true) {
            $this->message('Please review and update the configuration settings for com_ra_members.');
        }
        $message = '<b>Useful links</b><br>'
                . $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->buildButton('index.php?option=com_config&view=component&component=com_ra_members', 'Configure');
        echo $message;
        return true;
    }

    public function preflight($type, $parent): bool
    {
        $this->message('Preflight RA Members (type=' . $type . ').');
        if ($type === 'uninstall') {
            return true;
        }

        // Check com_ra_tools 
        $toolsVersion = $this->getInstalledVersion('com_ra_tools');
        if ($toolsVersion === null || version_compare($toolsVersion, self::MINIMUM_TOOLS_VERSION, '<')) {
            return $this->fail('RA Members requires com_ra_tools ' . self::MINIMUM_TOOLS_VERSION . ' or later; found ' . ($toolsVersion ?: 'not installed') . '.');
        }

        // Check com_ra_mailman
        $mailmanVersion = $this->getInstalledVersion('com_ra_mailman');
        if ($mailmanVersion === null) {
            return $this->fail('RA Members requires com_ra_mailman ' . self::MINIMUM_MAILMAN_VERSION . ' or later; com_ra_mailman is not installed.');
        }
        if (version_compare($mailmanVersion, self::MINIMUM_MAILMAN_VERSION, '<')) {
            return $this->fail('RA Members requires com_ra_mailman ' . self::MINIMUM_MAILMAN_VERSION . ' or later; found ' . $mailmanVersion . '. Please upgrade com_ra_mailman first.');
        }


        // All good
        return true;
    }
}