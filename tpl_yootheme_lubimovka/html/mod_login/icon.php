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


use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;

$app = Factory::getApplication();

$app->getLanguage()->load('com_radicalmart', JPATH_ROOT);

/** @var \Joomla\CMS\WebAsset\WebAssetManager $assets */
$assets = $app->getDocument()->getWebAssetManager();
$assets->getRegistry()->addExtensionRegistryFile('com_radicalmart');
$assets->useScript('com_radicalmart.site.login')
	->useScript('showon')
	->useScript('field.passwordview');

if (ParamsHelper::getComponentParams()->get('radicalmart_js', 1))
{
	$assets->useScript('com_radicalmart.site');
}
?>
<button type="button" radicalmart-login="display_form"
		class="uk-icon-link" uk-icon="lubimovka_office"
		title="<?php echo Text::_('PLG_SYSTEM_LUBIMOVKA_OFFICE'); ?>">
</button>