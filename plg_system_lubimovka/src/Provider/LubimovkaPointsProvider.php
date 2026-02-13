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

namespace Joomla\Plugin\System\Lubimovka\Provider;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Plugin\RadicalMart\CUG\Provider\Bonuses\PointsProvider;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class LubimovkaPointsProvider extends PointsProvider
{
	/**
	 * Method to accrual bonuses points.
	 *
	 * @param   object      $order    Current order object.
	 * @param   int         $status   New order status.
	 * @param   Registry    $params   Component params.
	 * @param   array       $records  Order points records.
	 * @param   OrderModel  $model    Administrator Order Model.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function accrualPoints(object $order, int $status, Registry $params, array $records, OrderModel $model): void
	{
		$statuses = ArrayHelper::toInteger($params->get('bonuses_points_accrual_statuses', []));
		if (empty($statuses) || !in_array($status, $statuses))
		{
			return;
		}

		foreach ($records as $record)
		{
			if ($record->reason === 'accrual')
			{
				return;
			}
		}

		$this->accrualShippingPoints($order, $records, $model);

		if (empty($order->plugins['bonuses']['codes']))
		{
			parent::accrualPoints($order, $status, $params, $records, $model);

			return;
		}

		$codes        = CodesHelper::getCodes($order->plugins['bonuses']['codes']);
		$referralCode = false;
		foreach ($codes as $code)
		{
			if (!empty($code->referral))
			{
				$referralCode = 'discount_code_' . $code->id;
			}
		}

		if (empty($referralCode))
		{
			parent::accrualPoints($order, $status, $params, $records, $model);

			return;
		}

		foreach ($order->products as $product)
		{
			if (!empty($product->order['plugins']['bonuses'][$referralCode]))
			{
				return;
			}
		}

		parent::accrualPoints($order, $status, $params, $records, $model);
	}

	/**
	 * Method to accrual shipping cashback points.
	 *
	 * @param   object      $order    Current order object.
	 * @param   array       $records  Order points records.
	 * @param   OrderModel  $model    Administrator Order Model.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function accrualShippingPoints(object $order, array $records, OrderModel $model): void
	{
		foreach ($records as $record)
		{
			if ($record->reason === 'accrual_shipping')
			{
				return;
			}
		}

		if (empty($order->shipping) || empty($order->shipping->id) || empty($order->shipping->plugin)
			|| $order->shipping->plugin !== 'apiship')
		{
			return;
		}

		if (empty($order->formData['shipping']['price']))
		{
			return;
		}

		$points = 0;
		if (!empty($order->formData['shipping']['price']['base']))
		{
			$points = $order->formData['shipping']['price']['base'];
		}
		elseif (!empty($order->formData['shipping']['price']['recipient']))
		{
			$points = $order->formData['shipping']['price']['recipient'];
		}

		$points = PointsHelper::clean($points);
		if (empty($points))
		{
			return;
		}

		$needle = 15000;
		$sum    = 0;
		foreach ($order->products as $product)
		{
			$sum += $product->order['sum_final'];
		}

		$sum = PriceHelper::clean($sum, $order->currency['code']);
		if ($sum < $needle)
		{
			return;
		}
		if ($points > 1500)
		{
			$points = 1500;
		}

		$record_id = PointsHelper::createRecord($order->created_by, $points,
			'com_radicalmart.order', [
				'order_id'   => $order->id,
				'reason'     => 'accrual_shipping',
				'created_by' => $order->created_by,
			]);

		$app     = Factory::getApplication();
		$user_id = (!empty($app->getIdentity()) && !empty($app->getIdentity()->id)) ? $app->getIdentity()->id : -1;
		$model->addLog($order->id, 'points_accrual_shipping', [
			'plugin'      => 'bonuses_lubimovka',
			'group'       => 'radicalmart',
			'record_id'   => $record_id,
			'points'      => $points,
			'user_id'     => $user_id,
			'customer_id' => $order->created_by,
		]);
	}

	/**
	 * Method to refund bonuses points.
	 *
	 * @param   object      $order    Current order object.
	 * @param   int         $status   New order status.
	 * @param   Registry    $params   Component params.
	 * @param   array       $records  Order points records.
	 * @param   OrderModel  $model    Administrator Order Model.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function refundPoints(object $order, int $status, Registry $params, array $records, OrderModel $model): void
	{
		parent::refundPoints($order, $status, $params, $records, $model);

		$statuses = ArrayHelper::toInteger($params->get('bonuses_points_refund_statuses', []));
		if (empty($statuses) || !in_array($status, $statuses))
		{
			return;
		}

		$points = 0;
		foreach ($records as $record)
		{
			if ($record->reason === 'accrual_shipping')
			{
				$points = $record->points * -1;
			}
		}

		if (empty($points))
		{
			return;
		}

		$app     = Factory::getApplication();
		$user_id = (!empty($app->getIdentity()) && !empty($app->getIdentity()->id)) ? $app->getIdentity()->id : -1;

		$record_id = PointsHelper::createRecord($order->created_by, $points, 'com_radicalmart.order', [
			'order_id'   => $order->id,
			'reason'     => 'refund_shipping',
			'created_by' => $user_id,
		]);

		$model->addLog($order->id, 'points_refund_shipping', [
			'plugin'      => 'bonuses_lubimovka',
			'group'       => 'radicalmart',
			'record_id'   => $record_id,
			'points'      => $points,
			'user_id'     => $user_id,
			'customer_id' => $order->created_by,
		]);
	}
}