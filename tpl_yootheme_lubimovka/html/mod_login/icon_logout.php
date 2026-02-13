<?php
/*
 * @package     Lubimovka Site Package
 * @subpackage  tpl_yootheme_lubimovka
 * @version     __DEPLOY_VERSION__
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2026 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<a href="<?php echo Route::_('index.php?option=com_radicalmart&view=orders'); ?>"
   class="uk-icon-link" uk-icon="lubimovka_office"
   title="<?php echo Text::_('PLG_SYSTEM_LUBIMOVKA_OFFICE'); ?>">
</a>