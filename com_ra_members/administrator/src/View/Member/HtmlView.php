<?php

/**
 * @version    1.1.7
 * @package    com_ra_members
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_members\Administrator\View\Member;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\TableHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a single Role.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView {

    protected $state;
    protected $item;
    protected $form;
    protected $mailHelper;
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
        $this->item = $this->get('Item');
        $this->mailHelper = new Mailhelper;
        $this->toolsHelper = new ToolsHelper;
        //       $this->form = $this->get('Form');
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        Factory::getApplication()->input->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_('Member'), "generic");

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


        ToolbarHelper::title(Text::_('Add Role'), "generic");
    }

    public function showAudit() {
        //      echo 'Roles<br>';
    }

    public function showRoles() {
        //      echo 'Roles<br>';
    }

    public function showSubscriptions() {
        //      echo 'Roles<br>';
    }

}
