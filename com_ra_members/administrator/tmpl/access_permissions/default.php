<?php
/**
 * 22/10/26 CB created
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
HTMLHelper::_('script', 'administrator/components/com_ra_tools/assets/js/shared-task-buttons.js', ['version' => 'auto'], ['defer' => true]);

$editIcon = '<span class="fa fa-pen-square me-2" aria-hidden="true"></span>';
$objHelper = new ToolsHelper;
$self = 'index.php?option=com_ra_members&view=access_permissions';

function renderAccessButton($userId, $component, $hasAccess) {
    $task = $hasAccess ? 'access_permission.revoke' : 'access_permission.grant';
    $label = $hasAccess ? 'Revoke' : 'Grant';
    $colour = $hasAccess ? 'sunset' : 'forest';
    $target = 'index.php?option=com_ra_members';
    $target .= '&user_id=' . (int) $userId;
    $target .= '&component=' . urlencode($component);

    return '<button type="submit" class="btn btn-sm ' . $colour . '" data-task="' . $task . '" formaction="' . Route::_($target) . '">' . $label . '</button>';
}

echo '<form action="' . Route::_($self) . '" method="post" name="adminForm" id="adminForm" data-task-target="task">' . PHP_EOL;
echo '<div class="row">' . PHP_EOL;
echo '<div class="col-md-12">' . PHP_EOL;
echo '<div id="j-main-container" class="j-main-container">' . PHP_EOL;
if (empty($this->items)) {
    echo '<div class="alert alert-info">' . PHP_EOL;
    echo '<span class="fa fa-info-circle" aria-hidden="true"></span><span class="sr-only">';
    echo Text::_('INFO') . '</span>' . PHP_EOL;
    echo Text::_('JGLOBAL_NO_MATCHING_RESULTS') . PHP_EOL;
    echo '</div>' . PHP_EOL;
} else {
    echo '<table class="table" id="ra_areasList">' . PHP_EOL;
    echo '<thead>' . PHP_EOL;
    echo '<tr>' . PHP_EOL;

    echo '<th>' . 'Name' . '</th>' . PHP_EOL;

    echo '<th>' . 'Members' . '</th>' . PHP_EOL;
    echo '<th>' . 'Mailman' . '</th>' . PHP_EOL;
    echo '</tr>' . PHP_EOL;
    echo '</thead>' . PHP_EOL;
    echo '<tbody>' . PHP_EOL;

    $n = count($this->items);
    foreach ($this->items as $i => $item) {
        echo '<tr class="row' . $i % 2 . '">' . PHP_EOL;
        echo '<td>' . $item->preferred_name . '</td>';

        echo '<td>';
        if ((int) $item->access_me === 1) {
            echo 'Y';
        } else {
            echo 'N';
        }
        echo ' ' . renderAccessButton($item->id, 'com_ra_members', (int) $item->access_me === 1);
        echo '</td>';

        echo '<td>';
        if ((int) $item->access_ma === 1) {
            echo 'Y';
        } else {
            echo 'N';
        }
        echo ' ' . renderAccessButton($item->id, 'com_ra_mailman', (int) $item->access_ma === 1);
        echo '</td>';

        echo '</tr>' . PHP_EOL;
    }
    echo '</tbody>' . PHP_EOL;
    echo '</table>' . PHP_EOL;
}
?>

<input type="hidden" name="task" id="task" value="">
<?php echo HTMLHelper::_('form.token'); ?>
</div>
</div>
</div>
</form>

