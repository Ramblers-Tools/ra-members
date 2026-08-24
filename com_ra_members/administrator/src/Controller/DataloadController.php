<?php

/**
 *
 * Processing to create the User records is done in the save function of the model
 *
 * 20/06/23 CB created from MailshotController
 * 01/01/24 CB comments added
 * 16/09/24 CB deleted function process (processing carried out in Model / save)
 * 25/09/24 CB copied function save from com_ra_tools
 * 04/05/25 CB deleted redundant code for processing
 * 24/08/26 CB copied from com_ra_mailman
 */

namespace Ramblers\Component\Ra_members\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Dataload controller class.
 *
 * @since  1.0.2
 */
class DataloadController extends FormController {

    protected $view_item = 'dataload';
// Ensure control returns to Dashboard, not dataloads
    protected $view_list = 'dashboard';

    public function cancel($key = null, $urlVar = null) {
        // Flush the data from the session..
        $this->app->setUserState('com_ra_members.edit.upload.data', null);
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    /**
     * Method to save data.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($key = NULL, $urlVar = NULL) {
        $return = $this->saveRecord($key, $urlVar);
        $this->app->setUserState('com_ra_members.edit.upload.data', null);
        $this->setRedirect(Route::_('index.php?option=com_ra_members&view=dataload', false));

        return $return;
    }

    public function saveRecord($key = NULL, $urlVar = NULL) {
        //       echo 'Controller: save<br>';
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $model = $this->getModel('Dataload', 'Administrator');

        // Get the user data.
        $data = $this->input->get('jform', array(), 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }

        // Send an object which can be modified through the plugin event
        $objData = (object) $data;
        $this->app->triggerEvent(
                'onContentNormaliseRequestData',
                array($this->option . '.' . $this->context, $objData, $form)
        );

        $data = (array) $objData;

        // Validate the posted data.
        $data = $model->validate($form, $data);
        // Check for errors.
        if ($data === false) {

            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');

            // Save the data in the session.
            $this->app->setUserState('com_ra_members.edit.upload.data', $jform);

            // Redirect back to the edit screen.

            $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_members&view=dataload', false));

            $this->redirect();
        }
        //        echo 'Controller save 2<br>';
        // Save the data in the session.
        $this->app->setUserState('com_ra_members.edit.upload.data', $data);

        // Attempt to save the data. This will carry out the file upload

        return $model->save($data);
    }

}
