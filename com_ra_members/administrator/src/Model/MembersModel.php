<?php

/**
 * @version    1.1.4
 * @package    com_ra_members
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/-5/26 CB fix error when filters are not initialised
 * 21/06/26 CB always left join to Users
 */

namespace Ramblers\Component\Ra_members\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Members records.
 *
 * @since  1.0.0
 */
class MembersModel extends ListModel {

    protected $searchFields = [];

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'created_by', 'a.created_by',
                'modified_by', 'a.modified_by',
                'created', 'a.created',
                'modified', 'a.modified',
                'preferred_name', 'a.preferred_name',
                'membershipExpiry', 'a.membershipExpiry',
                'membershipNo', 'a.membershipNo',
                'home_group', 'a.home_group',
                'firstName', 'a.firstName',
                'lastName', 'a.lastName',
                'memberType', 'a.memberType',
                'memberTerm', 'a.memberTerm',
                'u.email',
                'membershipStatus', 'a.membershipStatus',
                'teamStatus', 'a.teamStatus',
                'noWalkProgram', 'a.noWalkProgram',
            );

            $this->searchFields = array(
                'a.preferred_name',
                'a.membershipNo',
                'a.home_group',
                'a.firstName',
                'a.lastName',
                'a.memberRef',
                'a.contactId',
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('a.lastName', 'ASC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        $context = $this->getUserStateFromRequest($this->context . '.filter.memberType', 'filter_memberType', '');
        $this->setState('filter.memberType', $context);

        $context = $this->getUserStateFromRequest($this->context . '.filter.membershipStatus', 'filter_membershipStatus', '');
        $this->setState('filter.membershipStatus', $context);

        $context = $this->getUserStateFromRequest($this->context . '.filter.memberTerm', 'filter_memberTerm', '');
        $this->setState('filter.memberTerm', $context);

        $context = $this->getUserStateFromRequest($this->context . '.filter.teamStatus', 'filter_teamStatus', '');
        $this->setState('filter.teamStatus', $context);

        $context = $this->getUserStateFromRequest($this->context . '.filter.noWalkProgram', 'filter_noWalkProgram', '');
        $this->setState('filter.noWalkProgram', $context);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.memberType');
        $id .= ':' . $this->getState('filter.membershipStatus');
        $id .= ':' . $this->getState('filter.memberTerm');
        $id .= ':' . $this->getState('filter.teamStatus');
        $id .= ':' . $this->getState('filter.noWalkProgram');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery() {
        // See if we are running the full version
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $group = $mailHelper->getDefaultGroup();
        // Create a new query object.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__ra_profiles` AS a');
        $query->select('u.email');
        $query->leftJoin('#__users AS u ON u.id=a.id');
        if ($group !== 'N') {
            $query->where($db->quoteName('a.home_group') . ' = ' . $db->quote($group));
        }
        $query->where('membershipNo IS NOT NULL');

        $memberType = $this->getState('filter.memberType');
//		var_dump($memberType);
        if ($memberType !== null && $memberType !== '') {
            $query->where($db->quoteName('a.memberType') . ' = ' . $db->quote($memberType));
        }

        $membershipStatus = $this->getState('filter.membershipStatus');
        if ($membershipStatus !== null && $membershipStatus !== '') {
            $query->where($db->quoteName('a.membershipStatus') . ' = ' . $db->quote($membershipStatus));
        }

        $memberTerm = $this->getState('filter.memberTerm');
        if ($memberTerm !== null && $memberTerm !== '') {
            $query->where($db->quoteName('a.memberTerm') . ' = ' . $db->quote($memberTerm));
        }

        $teamStatus = $this->getState('filter.teamStatus');
        if ($teamStatus !== null && $teamStatus !== '') {
            $query->where($db->quoteName('a.teamStatus') . ' = ' . $db->quote($teamStatus));
        }

        $noWalkProgram = $this->getState('filter.noWalkProgram');
        if ($noWalkProgram !== null && $noWalkProgram !== '') {
            $query->where($db->quoteName('a.noWalkProgram') . ' = ' . $db->quote($noWalkProgram));
        }

        // For non full version, only show Roles for the current User's Group
        if (($group !== 'N') AND ($toolsHelper->isSuperuser() === false)) {
            $query->where('a.home_group=' . $this->_db->quote($group));
        }
        // Filter by search
        $searchWord = $this->getState('filter.search');

        if (!empty($searchWord)) {
            $query = ToolsHelper::buildSearchQuery($searchWord, $this->searchFields, $query);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.lastName');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query), 'message');
        }
        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }

}
