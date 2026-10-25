<?php

/**
 * 22/10/26 CB created
 */

namespace Ramblers\Component\Ra_members\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Area controller class.
 *
 * @since  3.0.0
 */
class Access_permissionController extends FormController {

    protected $app;
    protected $back;
    protected $toolsHelper;
    protected $view_list = 'access_permissions';

    public function __construct(
            $config = [],
            MVCFactoryInterface $factory = null,
            CMSApplication $app = null,
            Input $input = null
    ) {
    parent::__construct($config, $factory, $app, $input);
        $this->app = Factory::getApplication();
        $this->toolsHelper = new ToolsHelper;
        $this->back = '/administrator/index.php?option=com_ra_members&view=access_permissions';
// Import CSS
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function add(): void {
        $this->setRedirect('index.php?option=com_ra_members&view=access_permission&layout=edit');
    }

    public function cancel($key = null): void {
        $this->setRedirect($this->back);
    }

    public function cancelList(): void {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    private function getDatabase(): DatabaseInterface {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private function setAccess(int $userId, string $component, bool $enabled): bool {
        $groupId = $this->lookupGroupId($component);

        if ($userId <= 0 || $groupId <= 0) {
            return false;
        }

        $db = $this->getDatabase();

        if ($enabled) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__user_usergroup_map'))
                ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
                ->where($db->quoteName('group_id') . ' = ' . (int) $groupId);
            $db->setQuery($query);
            $exists = (int) $db->loadResult() > 0;

            if (!$exists) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__user_usergroup_map'))
                    ->columns($db->quoteName(['user_id', 'group_id']))
                    ->values((int) $userId . ',' . (int) $groupId);
                $db->setQuery($query);
                $db->execute();
            }
        } else {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__user_usergroup_map'))
                ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
                ->where($db->quoteName('group_id') . ' = ' . (int) $groupId);
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }

    public function save($key = null, $urlVar = null): void {
        $this->checkToken();

        $data = $this->app->input->post->get('jform', [], 'array');
        $userId = (int) ($data['user'] ?? 0);
        $membersEnabled = (int) ($data['access_me'] ?? 0) === 1;
        $mailmanEnabled = (int) ($data['access_ma'] ?? 0) === 1;

        if ($userId <= 0) {
            $this->app->enqueueMessage('Please select a user.', 'error');
            $this->setRedirect('index.php?option=com_ra_members&view=access_permission&layout=edit');
            return;
        }

        $okMembers = $this->setAccess($userId, 'com_ra_members', $membersEnabled);
        $okMailman = $this->setAccess($userId, 'com_ra_mailman', $mailmanEnabled);

        if ($okMembers && $okMailman) {
            $this->app->enqueueMessage('Access permissions updated.', 'message');
        } else {
            $this->app->enqueueMessage('Failed to update one or more permissions.', 'warning');
        }

        $this->setRedirect($this->back);
    }

    public function grant(): void {
        $this->checkToken();

        $userId = $this->app->input->getInt('user_id', 0);
        $component = $this->app->input->getCmd('component', '');
        $groupId = $this->lookupGroupId($component);

        if ($userId <= 0 || $groupId <= 0) {
            $this->app->enqueueMessage('Invalid parameters for grant.', 'error');
            $this->setRedirect($this->back);
            return;
        }

        if ($this->setAccess($userId, $component, true)) {
            $this->app->enqueueMessage('Access granted for ' . $component . '.', 'message');
        } else {
            $this->app->enqueueMessage('Unable to grant access for ' . $component . '.', 'error');
        }

        $this->setRedirect($this->back);
    }

    private function lookupGroupId(string $component): int {
        if (!in_array($component, ['com_ra_members', 'com_ra_mailman'], true)) {
            return 0;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote($component));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    public function revoke(): void {
        $this->checkToken();

        $userId = $this->app->input->getInt('user_id', 0);
        $component = $this->app->input->getCmd('component', '');
        $groupId = $this->lookupGroupId($component);

        if ($userId <= 0 || $groupId <= 0) {
            $this->app->enqueueMessage('Invalid parameters for revoke.', 'error');
            $this->setRedirect($this->back);
            return;
        }

        if ($this->setAccess($userId, $component, false)) {
            $this->app->enqueueMessage('Access revoked for ' . $component . '.', 'message');
        } else {
            $this->app->enqueueMessage('Unable to revoke access for ' . $component . '.', 'error');
        }
        $this->setRedirect($this->back);
    }

}
