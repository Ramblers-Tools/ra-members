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
use Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
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

    public function formatBoolean($value) {
        if ($value === 1) {
            return 'Yes';
        } elseif ($value === 0) {
            return 'No';
        } else {
            return 'Not given';
        }
    }
    
    /**
     * Format a date value.
     *
     * @param   string  $value  The date value to format.
     *
     * @return string  The formatted date or 'Not given' if the value is empty or null.
     */
    
    public function formatDate($value) {
        if ($value === '' || is_null($value)) {
            return 'Not given';
        }

        return HTMLHelper::_('date', $value, 'd/M/y');
    }

    public function showAudit() {
        $sql='SELECT * FROM #__ra_profiles_audit ';
        $sql .= 'WHERE object_id = ' . $this->item->id;
        $sql .= ' ORDER BY date_amended DESC';
        $rows = $this->toolsHelper->getRows($sql);    
        if (!empty($rows)) {
            echo '<h4>Audit Trail</h4>';
            $table = new ToolsTable();
            $table->add_header("Updated,Field,Action,Value");     
            foreach ($rows as $row) {        
                $table->add_item(HTMLHelper::_('date', $row->date_amended, 'Y-m-d H:i'));
                $table->add_item($row->field_name); // ;
                $table->add_item($row->action);
                $table->add_item($row->field_value);
                $table->generate_line();
            }
            $table->generate_table();
        }
    }

    public function showRoles() {
        //      echo 'Roles<br>';
    }

    public function showSubscriptions() {
        $sql = 'SELECT DISTINCT ms.id, ms.list_id, ms.user_id, ms.record_type, ms.method_id, ms.created, ';
        $sql .= "ml.group_code,ml.name,mm.name as 'Method'  ";
        $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
        $sql .= 'LEFT JOIN #__ra_mail_methods AS mm on mm.id = ms.method_id ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = ms.user_id ';
        $sql .= 'LEFT JOIN #__ra_mail_lists as ml ON ml.id = ms.list_id ';
        $sql .= 'WHERE ms.user_id = ' . $this->item->id;
        $sql .= ' ORDER BY ms.created DESC';
        $rows = $this->toolsHelper->getRows($sql);    
        if (!empty($rows)) {
            echo '<h4>Subscriptions</h4>';
            $table = new ToolsTable();
            $table->add_header("Created,List,Access,Method");
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                $table->add_item(HTMLHelper::_('date', $row->created, 'Y-m-d H:i'));
                $table->add_item($row->group_code . '/' . $row->name);
                
                $table->add_item(($row->record_type == 1 ? 'Subscriber' : 'Author'));
                $table->add_item($row->Method);
                $table->generate_line();
            }
            $table->generate_table('Subscriptions');
        }
    }

}
