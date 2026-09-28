<?php

namespace Ramblers\Component\Ra_members\Administrator\View\Groupselect;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $returnUrl;
    protected $isSuper;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $tools = new ToolsHelper();
        $this->isSuper = $tools->isSuperuser();
        $this->returnUrl = $this->safeReturnUrl($app->input->getString('return_url', ''));

        if (!$this->isSuper) {
            $app->enqueueMessage('Only Super Users may change the report group.', 'error');
            $this->setLayout('denied');
            parent::display($tpl);
            return;
        }

        $this->form = Form::getInstance(
                'com_ra_members.groupselect',
                JPATH_ADMINISTRATOR . '/components/com_ra_members/forms/groupselect.xml'
        );
        $this->form->setValue('group_code', null, $app->getUserState('com_ra_members.reports.group', ''));
        $this->form->setValue('return_url', null, $this->returnUrl);
        ToolbarHelper::title('Select report group', 'filter');
        parent::display($tpl);
    }

    private function safeReturnUrl(string $url): string
    {
        return (str_starts_with($url, 'index.php?option=com_ra_members&view=reports')
                || str_starts_with($url, 'index.php?option=com_ra_members&view=members'))
                ? $url
                : 'index.php?option=com_ra_members&view=reports';
    }
}
