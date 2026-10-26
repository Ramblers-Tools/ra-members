<?php

/**
 * Minimal prototype model for access_permission form view.
 */

namespace Ramblers\Component\Ra_members\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;

class Access_permissionModel extends FormModel {

    /**
     * Load the prototype form definition.
     */
    public function getForm($data = [], $loadData = true) {
        $form = $this->loadForm(
            'com_ra_members.access_permission',
            'access_permission',
            [
                'control' => 'jform',
                'load_data' => $loadData,
            ]
        );

        return $form ?: false;
    }

    /**
     * Seed defaults for a new access_permission form.
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_members.edit.access_permission.data', []);

        if (empty($data)) {
            $data = [
                'id' => 0,
                'user' => 0,
                'access_me' => 0,
                'access_ma' => 0,
            ];
        }

        return $data;
    }
}
