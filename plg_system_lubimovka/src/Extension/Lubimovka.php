<?php
/*
 * @package     Lubimovka Site Package
 * @subpackage  plg_system_lubimovka
 * @version     __DEPLOY_VERSION__
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2026 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

namespace Joomla\Plugin\System\Lubimovka\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Button\CustomButton;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\Lubimovka\Console\OrdersCancelCommand;
use Joomla\Plugin\System\Lubimovka\Console\OrdersLogsFixCommand;
use Joomla\Plugin\System\Lubimovka\Provider\LubimovkaPointsProvider;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class Lubimovka extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalMartRegisterCLICommands'         => 'onRadicalMartRegisterCLICommands',
			'onRadicalMartPrepareAdministratorToolbar' => 'onRadicalMartPrepareAdministratorToolbar',
			'onRadicalMartBonusesGetPointsProviders'   => 'onRadicalMartBonusesGetPointsProviders',
			'onAjaxLubimovka'                          => 'onAjax',
			'onRadicalMartGetOrderLogs'                => 'onRadicalMartGetOrderLogs',
		];
	}

	/**
	 * Listener for the `onRadicalMartRegisterCLICommands` event.
	 *
	 * @param   array     $commands  Updated commands array.
	 * @param   Registry  $params    RadicalMart params.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function onRadicalMartRegisterCLICommands(array &$commands, Registry $params): void
	{
		$commands[] = OrdersCancelCommand::class;
		$commands[] = OrdersLogsFixCommand::class;
	}

	/**
	 * Listener for `onRadicalMartBonusesGetPointsProviders` event.
	 *
	 * @param   array  $providers  Current providers array.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function onRadicalMartBonusesGetPointsProviders(array &$providers): void
	{
		$providers['lubimovka'] = [
			'title'    => Text::_('PLG_SYSTEM_LUBIMOVKA_POINTS_PROVIDER'),
			'selector' => 'lubimovka',
			'class'    => LubimovkaPointsProvider::class,
		];
	}

	/**
	 * Method to ajax functions.
	 *
	 * @throws  \Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onAjax(Event $event): void
	{
		try
		{
			$action = $this->getApplication()->input->get('action');
			$method = 'ajax' . $action;
			if (empty($action) || !method_exists($this, $method))
			{
				throw new \Exception(Text::_('PLG_SYSTEM_LUBIMOVKA_ERROR_AJAX_METHOD_NOT_FOUND'), 500);
			}

			$result = $this->$method();
			$event->setArgument('result', $result);
			$event->setArgument('results', $result);
		}
		catch (\Exception $e)
		{
			throw new \Exception('Lubimovka - ' . $e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Listener for the `onRadicalMartPrepareForm` event.
	 *
	 * @param   string   $context  Context selector string.
	 * @param   Toolbar  $toolbar  Toolbar object.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function onRadicalMartPrepareAdministratorToolbar(string $context, Toolbar $toolbar): void
	{

		if ($context !== 'com_radicalmart_bonuses.code')
		{
			return;
		}

		$id = $this->getApplication()->input->getInt('id');
		if (empty($id))
		{
			return;
		}

		$text   = Text::_('PLG_SYSTEM_LUBIMOVKA_EXPORT_CODE');
		$html   = LayoutHelper::render('components.radicalmart.administrator.toolbar.link', [
			'link'  => Route::_(
				'index.php?option=com_ajax&plugin=lubimovka&group=system&action=exportCode&format=raw' .
				'&code_id=' . $id,
				false),
			'text'  => $text,
			'icon'  => ' fa-file-export',
			'order' => 10,
			'id'    => 'exportCode'
		]);
		$button = new CustomButton('exportCode', $text, ['html' => $html]);
		$toolbar->appendButton($button);
	}

	/**
	 * Method to export bonuses code data.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function ajaxExportCode(): void
	{
		$app     = $this->getApplication();
		$code_id = $app->input->getInt('code_id');


		if (empty($code_id))
		{
			throw new \Exception('PLG_SYSTEM_LUBIMOVKA_ERROR_CODE_NOT_FOUND', 404);
		}

		$code = CodesHelper::find($code_id, 'id', ParameterType::INTEGER);
		if (empty($code))
		{
			throw new \Exception('PLG_SYSTEM_LUBIMOVKA_ERROR_CODE_NOT_FOUND', 404);
		}

		$code_orders = CodesHelper::getOrders([$code_id]);
		$code_orders = (!empty($code_orders[$code_id])) ? $code_orders[$code_id] : [];

		$db         = $this->getDatabase();
		$orders_ids = ArrayHelper::getColumn($code_orders, 'id');
		$users_ids  = ArrayHelper::getColumn($code_orders, 'created_by');

		$query  = $db->getQuery(true)
			->select(['id', 'number', 'contacts', 'created', 'created_by', 'status', 'total', 'currency'])
			->from($db->quoteName('#__radicalmart_orders'))
			->whereIn($db->quoteName('id'), $orders_ids);
		$orders = $db->setQuery($query)->loadObjectList('id');

		$statuses_ids = ArrayHelper::getColumn($orders, 'status');
		$query        = $db->getQuery(true)
			->select(['id', 'title'])
			->from($db->quoteName('#__radicalmart_statuses'))
			->whereIn($db->quoteName('id'), $statuses_ids);
		$statuses     = $db->setQuery($query)->loadObjectList('id');

		$query = $db->getQuery(true)
			->select(['id', 'email', 'username', 'name'])
			->from($db->quoteName('#__users'))
			->whereIn($db->quoteName('id'), $users_ids);
		$users = $db->setQuery($query)->loadObjectList('id');

		$date     = new Date();
		$filename = '[' . $date->format('Ymd') . '] code_export ' . $code_id;

		$rows = [
			[Text::_('JGLOBAL_FIELD_ID_LABEL'), $code_id],
			[Text::_('JDATE'), $date->format('d.m.Y')],
			[Text::_('COM_RADICALMART_BONUSES_CODE'), $code->code],
			[Text::_('COM_RADICALMART_BONUSES_CODE_DISCOUNT'), $code->discount],
			[],
			[Text::_('COM_RADICALMART_ORDERS')],
			[
				Text::_('COM_RADICALMART_ORDER_NUMBER'),
				Text::_('COM_RADICALMART_ORDER_CREATED'),
				Text::_('COM_RADICALMART_STATUS'),
				Text::_('COM_RADICALMART_TOTAL'),
				Text::_('COM_RADICALMART_CUSTOMER'),
				Text::_('COM_RADICALMART_EMAIL'),
				Text::_('COM_RADICALMART_PHONE'),
			]
		];

		$all_total = 0;
		foreach ($orders as $order)
		{
			$order->user     = (isset($users[$order->created_by])) ? $users[$order->created_by] : false;
			$order->contacts = new Registry($order->contacts);
			$order->total    = new Registry($order->total);

			$customer  = UserHelper::nameToString($order->contacts);
			$status    = (isset($statuses[$order->status])) ? Text::_($statuses[$order->status]->title) : '';
			$total     = PriceHelper::toString($order->total->get('final'), $order->currency, 'seo');
			$all_total += $order->total->get('final');

			$rows[] = [
				$order->number,
				(new Date($order->created))->format('d.m.Y H:i:s'),
				$status,
				$total,
				$customer,
				$order->contacts->get('email'),
				$order->contacts->get('phone')
			];
		}
		$rows[] = [
			'',
			'',
			'',
			PriceHelper::toString($all_total, 'RUB', 'seo'),
			'',
			'',
			'',
		];

		$app->clearHeaders();
		$app->setHeader('Pragma', 'public');
		$app->setHeader('Expires', '0');
		$app->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0, private');
		$app->setHeader('Content-Type', 'application/octet-stream');
		$app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv";');
		$app->setHeader('Content-Transfer-Encoding', 'binary');
		$app->sendHeaders();

		$file = fopen('php://output', 'w');
		ob_start();
		fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
		foreach ($rows as $row)
		{
			fputcsv($file, $row, ';');
		}

		echo htmlspecialchars_decode(ob_get_clean(), ENT_NOQUOTES);
	}

	/**
	 * Listener for `onRadicalMartGetOrderLogs` event.
	 *
	 * @param   string|null  $context  Context selector string.
	 * @param   array        $log      Log data.
	 *
	 * @throws \Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onRadicalMartGetOrderLogs(?string $context = null, array &$log = []): void
	{
		if (in_array($log['action'], ['points_accrual_shipping', 'points_refund_shipping']))
		{
			$points = (float) $log['points'];
			if (!str_contains($log['action'], '_refund') && $points < 0)
			{
				$points = $points * -1;
			}
			$log['action_text'] = '';

			$log['action_text'] = Text::plural('COM_RADICALMART_BONUSES_' . $log['action'] . '_N_ITEMS',
				$points);

			if (!empty($log['customer_id']))
			{
				$customer = UserHelper::getUser((int) $log['customer_id']);
				if (!empty($customer->name))
				{
					$log['message'] = $customer->name;
				}
			}
		}
	}
}