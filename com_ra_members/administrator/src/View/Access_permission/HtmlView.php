<?php

/**
 * 22/10/26 CB created
 */

namespace Ramblers\Component\Ra_members\Administrator\View\Access_permission;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

/**
 * View class for a single Area.
 *
 * @since  3.0.0
 */
class HtmlView extends BaseHtmlView {

    protected $canDo;
    protected $state;
    protected $item;
    protected $form;
    protected $toolsHelper;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        $this->state = $this->get('State');
        $this->form = $this->get('Form');
        $this->item = (object) ['id' => 0];
        $this->canDo = ContentHelper::getActions('com_ra_members');
        // Check for errors.
        if (empty($this->form)) {
            throw new \RuntimeException('Unable to load access permission form definition.');
        }
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar() {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user = Factory::getApplication()->getIdentity();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        } else {
            $checkedOut = false;
        }

        ToolbarHelper::title(Text::_('Access permission'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($this->canDo->get('core.edit') || $this->canDo->get('core.create'))) {
            ToolbarHelper::save('access_permission.save', 'JTOOLBAR_SAVE');
        }


        if (empty($this->item->id)) {
            ToolbarHelper::cancel('access_permission.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('access_permission.cancel', 'JTOOLBAR_CLOSE');
        }
    }

}
