<?php
/**
 * 22/10/26 CB created
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_members&task=access_permission.save'); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="area-form" class="form-validate form-horizontal">

    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php echo $this->form->renderField('id'); ?>
                <?php echo $this->form->renderField('user'); ?>
                <?php echo $this->form->renderField('access_me'); ?>
                <?php echo $this->form->renderField('access_ma'); ?>
            </fieldset>
        </div>
    </div>

    <input type="hidden" name="task" value="access_permission.save"/>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
