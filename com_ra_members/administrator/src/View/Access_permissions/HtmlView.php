<?php

/**
 * 22/10/26 CB created
 */

namespace Ramblers\Component\Ra_members\Administrator\View\Access_permissions;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of mywalks.
 *
 * @since  1.6
 */
class HtmlView extends BaseHtmlView {

    protected $items;
    protected $toolsHelper;

    /**
     * The model state
     *
     * @var  \JObject
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise an Error object.
     */
    public function display($tpl = null) {
        $this->toolsHelper = new ToolsHelper;
        $this->getItems();

        $this->addToolbar();

        return parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function addToolbar() {
        // Suppress menu side panel
       Factory::getApplication()->input->set('hidemainmenu', true);

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title('Access permissions');
        $this->canDo = ContentHelper::getActions('com_ra_members');

        if ($this->canDo->get('core.create')) {
            $toolbar->addNew('access_permission.add');
        }

        // code from https://docs.joomla.org/J4.x:Joomla_4_Tips_and_Tricks:_Number_of_Records
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->item_count) . ' Users')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);

        ToolbarHelper::cancel('access_permission.cancelList', 'Return to Dashboard');
    }

    private function getItems() {
        $sql = 'SELECT u.id, p.preferred_name, ';
        $sql .= 'MAX(CASE WHEN g.title = "com_ra_members" THEN 1 ELSE 0 END) AS access_me, ';
        $sql .= 'MAX(CASE WHEN g.title = "com_ra_mailman" THEN 1 ELSE 0 END) AS access_ma ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = u.id ';
        $sql .= 'INNER JOIN #__user_usergroup_map AS m ON m.user_id = u.id ';
        $sql .= 'INNER JOIN #__usergroups AS g ON g.id = m.group_id ';
        $sql .= 'WHERE g.title IN ("com_ra_members", "com_ra_mailman") ';
        $sql .= 'GROUP BY u.id, p.preferred_name ';
        $sql .= 'ORDER BY p.preferred_name';

        $this->items = $this->toolsHelper->getRows($sql);
        $this->item_count = (is_null($this->items) ? 0 : count($this->items));
    }

}
