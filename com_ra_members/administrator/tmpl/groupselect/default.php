<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>
<form action="<?php echo Route::_('index.php?option=com_ra_members&task=reports.chooseGroup'); ?>" method="post" class="form-validate">
    <div class="alert alert-info">Choose the group scope to use for RA Members reports. This override applies only to your current session.</div>
    <?php echo $this->form->renderField('group_code'); ?>
    <input type="hidden" 
        name="jform[return_url]" 
        value="<?php echo htmlspecialchars($this->returnUrl, ENT_QUOTES, 'UTF-8'); 
    ?>">
    <button 
        type="submit" 
        class="btn btn-primary">Use selection
    </button>
    <a class="btn btn-secondary" href="<?php echo Route::_($this->returnUrl); ?>">Cancel</a>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
