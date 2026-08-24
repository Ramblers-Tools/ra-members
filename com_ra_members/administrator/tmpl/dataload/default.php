<?php

/**
 *              Actual processing is carried out in site/helpers/UserHelper.php
 * 05/12/22 CB Created from com ramblers
 * 14/07/25 CB allow checking of file format
 * 27/07/25 CB abbreviated name
 * 24/08/26 CB copied to com_ra_members
 */
use \Joomla\CMS\HTML\HTMLHelper;

// No direct access
defined('_JEXEC') or die;

$jsonFeedEnabled = $this->importMode === 'json_enrichment';
?>
<div class="alert alert-info">
    <strong><?php echo $jsonFeedEnabled ? 'JSON feed enabled' : 'JSON feed disabled'; ?>.</strong>
    <?php if ($jsonFeedEnabled) : ?>
        This Insight CSV will enrich profiles whose membership numbers are already known from the JSON feed.
    <?php else : ?>
        This Insight CSV will be treated as the primary membership source.
    <?php endif; ?>
</div>
Choose how the Insight file is to be handled:
<ul>
    <li><strong>Check format</strong> validates and previews the first four records without writing to the database.</li>
    <li><strong>Process</strong> validates the complete file and applies valid records. Invalid records are reported and ignored.</li>
</ul>
<form action="<?php echo JRoute::_('index.php?option=com_ra_members&layout=edit'); ?>" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" class="form-validate">
    <div class="row-fluid">
        <div id="j-main-container" class="span10">
            <fieldset class="adminform">

                <?php
                echo '<div class="control-group"><div class="control-label">';
                echo $this->form->getLabel('csv_file');
                echo '</div>' . PHP_EOL;
                echo '<div class="controls">';
                echo $this->form->getInput('csv_file');
                echo '</div></div>' . PHP_EOL;

                echo $this->form->renderField('validation_type') . PHP_EOL;
                echo $this->form->renderField('feed_mode') . PHP_EOL;
                ?>
            </fieldset>
        </div>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
    <div id="validation-form-failed" data-backend-detail="dataload" data-message="<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED')); ?>">
    </div>
</form>
