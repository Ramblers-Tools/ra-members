<?php

/**
 *
 * This model can be invoked more than once for the same file.
 * The first time, the file details are taken from the input->files array and
 * stored in the form->data. If processing is aborted, for example because the
 * wrong input parameters were given, the file details are taken from the form data.
 *
 * 18/09/24 CB add function validate
 * 25/09/24 CB code copied from com_ra_tools / Model / UploadModel
 * 08/10/24 CB use view process for the actual processing
 * 10/10/24 CB upload the file in function save
 * 12/02/25 CB replace getIdentity with getCurrentUser
 * 14/04/25 CB delete any existing copy of the upload file
 * 02/05/25 CN remove diagnostic
 * 04/05/25 CB cater for update of file name on upload
 * 09/06/25 CB correct error message for empty file
 * 18/10/25 CB allow text/comma-separated-values
 * 24/08/26 CB copied to com_ra_members
 * 24/08/26 CB process Insight CSV directly without retaining the upload
 */

namespace Ramblers\Component\Ra_members\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Filesystem\File;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\AdminModel;
use Ramblers\Component\Ra_members\Site\Helper\LoadHelper;
use Ramblers\Component\Ra_members\Site\Service\InsightCsvMapper;
use Ramblers\Component\Ra_members\Site\Service\MemberFeedMode;

/**
 * Mail_lst model.
 *
 * @since  1.0.6
 */
class DataloadModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.6
     */
    protected $text_prefix = 'RA Members';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.6
     */
    public $typeAlias = 'com_ra_members.dataload';
    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   1.0.4
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_members');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_members.edit.upload.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_members.edit.upload.id', $id);
        }
        return true;
    }

    /**
     * Method to get an object.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {
        return $this->item;
    }

    /**
     * Method to get the data form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   1.0.4
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_members.upload', 'dataload', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }

        // The operating mode is configuration-owned, never selected or trusted
        // from request data.
        $form->setValue('feed_mode', null, $this->getImportMode());

        return $form;
    }

    public function getImportMode(): string {
        $setting = ComponentHelper::getParams('com_ra_members')->get('enable_json_feed', 1);

        return MemberFeedMode::fromJsonSetting($setting);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   1.0.4
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_members.edit.upload.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        if ($data) {
            return $data;
        }

        return array();
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($data) {
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();

        // Check the user can create new items in this section
        $authorised = $user->authorise('core.create', 'com_ra_members');

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }


        $path = (string) ($data['tmp_name'] ?? '');
        $preview = (string) ($data['validation_type'] ?? '2') === '1';

        if ($path === '' || !is_uploaded_file($path)) {
            $app->enqueueMessage('The uploaded CSV file is no longer available.', 'error');
            return false;
        }

        $reportId = $this->createImportReport($data, $user);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $app->enqueueMessage('Unable to open the uploaded CSV file.', 'error');
            return false;
        }

        $mapper = new InsightCsvMapper();
        $rows = [];
        $mappingErrors = [];

        try {
            $headings = fgetcsv($handle);

            if (!is_array($headings)) {
                throw new \InvalidArgumentException('The CSV file has no heading row.');
            }

            $mapper->validateHeadings($headings);
            $importedAt = Factory::getDate()->toSql();
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                try {
                    $mapped = $mapper->mapRow($headings, $row, $importedAt);
                    $mapped['_row'] = $rowNumber;
                    $rows[] = $mapped;
                } catch (\Throwable $exception) {
                    $mappingErrors[] = 'Row ' . $rowNumber . ': ' . $exception->getMessage();
                }

                if ($preview && count($rows) + count($mappingErrors) >= 4) {
                    break;
                }
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            $this->updateImportReport($reportId, 0, 1, [], ['File validation failed: ' . $exception->getMessage()], null, $preview);
            $app->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }

        fclose($handle);

        foreach ($mappingErrors as $message) {
            $app->enqueueMessage($message, 'warning');
        }

        if ($rows === []) {
            $this->updateImportReport($reportId, count($mappingErrors), count($mappingErrors), [], $mappingErrors, null, $preview);
            $app->enqueueMessage('No valid Insight records were found.', 'error');
            return false;
        }

        $loader = new LoadHelper();
        $result = $loader->processInsightMembers($rows, $this->getImportMode(), $preview);

        foreach ($loader->messages as $message) {
            $app->enqueueMessage($message, $result ? 'info' : 'warning');
        }

        $allErrors = $mappingErrors;
        if ($loader->count_errors > 0) {
            $allErrors = array_merge($allErrors, $loader->messages);
        }
        $this->updateImportReport(
                $reportId,
                count($rows) + count($mappingErrors),
                count($mappingErrors) + (int) $loader->count_errors,
                $loader,
                $allErrors,
                $result,
                $preview
        );

        $action = $preview ? 'Previewed' : 'Processed';
        $app->enqueueMessage($action . ' ' . count($rows) . ' valid Insight record(s).', $result ? 'success' : 'warning');

        return $result && $mappingErrors === [];
    }

    private function createImportReport(array $data, $user): int {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ra_import_reports'))
                    ->columns($db->quoteName([
                        'date_phase1', 'method_id', 'list_id', 'user_id', 'input_file',
                        'ip_address', 'created_by', 'state'
                    ]))
                    ->values(implode(',', [
                        $db->quote(Factory::getDate()->toSql()),
                        '3',
                        '0',
                        (int) ($user->id ?? 0),
                        $db->quote((string) ($data['file'] ?? $data['name'] ?? 'Insight CSV')),
                        $db->quote((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
                        (int) ($user->id ?? 0),
                        '1',
                    ]));
            $db->setQuery($query)->execute();

            return (int) $db->insertid();
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage(
                    'Unable to create the Insight import report: ' . $exception->getMessage(),
                    'warning'
            );

            return 0;
        }
    }

    private function updateImportReport(
            int $reportId,
            int $records,
            int $errors,
            $loader,
            array $messages,
            ?bool $result,
            bool $preview
    ): void {
        if ($reportId < 1) {
            return;
        }

        try {
            $db = Factory::getDbo();
            $summary = [];
            if (is_object($loader)) {
                $summary[] = 'New profiles: ' . (int) $loader->count_new_profiles;
                $summary[] = 'New users: ' . (int) $loader->count_new_users;
                $summary[] = 'Updated profiles: ' . (int) $loader->count_updated;
                $summary[] = 'Not updated: ' . (int) $loader->count_not_updated;
                $summary[] = 'Potentially lapsed profiles: ' . (int) $loader->count_lapsed;
            }
            $reportText = implode('<br>', array_merge($summary, $messages));
            $lapsedText = '';
            if (is_object($loader) && $loader->lapsed_members !== []) {
                $lapsedText = implode('<br>', array_map(
                        static function (array $member): string {
                            return 'Profile ' . (int) $member['member_id']
                                    . ', membership ' . htmlspecialchars($member['membershipNo'], ENT_QUOTES, 'UTF-8')
                                    . ', ' . htmlspecialchars($member['preferred_name'], ENT_QUOTES, 'UTF-8')
                                    . ', ' . htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8');
                        },
                        $loader->lapsed_members
                ));
            }
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ra_import_reports'))
                    ->set($db->quoteName('num_records') . ' = ' . (int) $records)
                    ->set($db->quoteName('num_errors') . ' = ' . (int) $errors)
                    ->set($db->quoteName('num_users') . ' = ' . (int) (is_object($loader) ? $loader->count_new_users : 0))
                    ->set($db->quoteName('num_lapsed') . ' = ' . (int) (is_object($loader) ? $loader->count_lapsed : 0))
                    ->set($db->quoteName('error_report') . ' = ' . $db->quote($reportText))
                    ->set($db->quoteName('lapsed_members') . ' = ' . $db->quote($lapsedText))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getApplication()->getIdentity()->id)
                    ->where($db->quoteName('id') . ' = ' . $reportId);

            if (!$preview) {
                $query->set($db->quoteName('date_completed') . ' = ' . $db->quote(Factory::getDate()->toSql()));
            }

            $db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage(
                    'Unable to update the Insight import report: ' . $exception->getMessage(),
                    'warning'
            );
        }
    }

    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();

        // Ignore any posted mode. This prevents a request from bypassing the
        // component's JSON/Insight source selection.
        $data['feed_mode'] = $this->getImportMode();

        $validMimeTypes = ['text/plain', 'text/csv', 'text/comma-separated-values', 'application/vnd.ms-excel'];
        $files = $app->input->files->get('jform', array(), 'raw');
        $singleFile = $files['csv_file'] ?? null;

        if (!is_array($singleFile) || (int) ($singleFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $app->enqueueMessage('Please select an Insight CSV file.', 'warning');
            return false;
        }

        $fileError = (int) ($singleFile['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($fileError !== UPLOAD_ERR_OK) {
            $app->enqueueMessage('File upload failed with error code ' . $fileError . '.', 'warning');
            return false;
        }

        if ((int) ($singleFile['size'] ?? 0) === 0) {
            $app->enqueueMessage('Selected file is empty', 'error');
            return false;
        }

        $extension = strtolower(File::getExt((string) ($singleFile['name'] ?? '')));

        if ($extension !== 'csv') {
            $app->enqueueMessage('The selected file must have a .csv extension.', 'warning');
            return false;
        }

        $fileMime = strtolower((string) ($singleFile['type'] ?? ''));

        if ($fileMime !== '' && !in_array($fileMime, $validMimeTypes, true)) {
            $app->enqueueMessage('Filetype ' . htmlspecialchars($fileMime, ENT_QUOTES, 'UTF-8') . ' is not allowed.', 'warning');
            return false;
        }

        $data['file'] = $singleFile['name'];
        $data['tmp_name'] = $singleFile['tmp_name'];
        $data['feed_mode'] = $this->getImportMode();
        return $data;
    }

}
