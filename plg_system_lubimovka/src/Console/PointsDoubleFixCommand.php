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

namespace Joomla\Plugin\System\Lubimovka\Console;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

class PointsDoubleFixCommand extends AbstractCommand
{
	use DatabaseAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'lubimovka:orders:fix_double_points';

	protected string $start_date = '2026-02-01 00:00:00';

	/**
	 * Command methods for step by step run.
	 *
	 * @var  array
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected array $methods = [
		'executeCommand',
	];

	public function executeCommand(): void
	{
		$this->ioStyle->title('Fix double points logs');
		$this->ioStyle->text('Get total');
		$this->startProgressBar(1, true);

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(id)')
			->from($db->quoteName('#__radicalmart_orders'))
			->where($db->quoteName('created') . ' > ' . $db->quote($this->start_date));
		$total = $db->setQuery($query)->loadResult();
		$this->finishProgressBar();

		if ($total === 0)
		{
			return;
		}
		$this->ioStyle->text('Get records');
		$this->startProgressBar($total, true);
		$last      = 0;
		$customers = [];
		$orders    = [];
		while (true)
		{
			$query = $db->getQuery(true)
				->select(['id', 'created_by', 'number'])
				->from($db->quoteName('#__radicalmart_orders'))
				->where($db->quoteName('id') . ' > :last')
				->where($db->quoteName('created') . ' > ' . $db->quote($this->start_date))
				->bind(':last', $last, ParameterType::INTEGER)
				->order($db->quoteName('id') . ' ASC');
			$order = $db->setQuery($query, 0, 1)->loadObject();

			if (empty($order))
			{
				break;
			}

			$last    = (int) $order->id;
			$query   = $db->getQuery(true)
				->select(['bp.id', 'bp.points'])
				->from($db->quoteName('#__radicalmart_bonuses_points', 'bp'))
				->where($db->quoteName('bp.context') . ' = ' . $db->quote('com_radicalmart.order'))
				->where('JSON_VALUE(bp.data, ' . $db->quote('$.order_id') . ') = :order_id')
				->where('JSON_VALUE(bp.data, ' . $db->quote('$.reason') . ') = ' . $db->quote('accrual'))
				->bind(':order_id', $last, ParameterType::INTEGER);
			$records = $db->setQuery($query)->loadObjectList();

			if (count($records) <= 1)
			{
				$this->advanceProgressBar();

				continue;
			}


			$query = $db->getQuery(true)
				->select(['bp.id'])
				->from($db->quoteName('#__radicalmart_bonuses_points', 'bp'))
				->where($db->quoteName('bp.context') . ' = ' . $db->quote('com_radicalmart_bonuses.points'))
				->where('JSON_VALUE(bp.data, ' . $db->quote('$.fix_double') . ') = 1')
				->where('JSON_VALUE(bp.data, ' . $db->quote('$.reason') . ') = ' . $db->quote('create'));
			$exist = $db->setQuery($query)->loadObject();
			if (!empty($exist))
			{
				$this->advanceProgressBar();
				continue;
			}

			foreach ($records as $r => $record)
			{
				if ($r > 0)
				{
					if (!isset($customers[$order->created_by]))
					{
						$customers[$order->created_by] = 0;
					}
					$customers[$order->created_by] += $record->points;
				}
			}
			$orders[] = $order->number;

			$this->advanceProgressBar();
		}
		$this->finishProgressBar();

		$this->ioStyle->text('Create Records');
		$this->startProgressBar(count($customers), true);
		foreach ($customers as $customer => $points)
		{
			if ($points > 0)
			{
				$points = $points * -1;
				PointsHelper::createRecord($customer, $points, 'com_radicalmart_bonuses.points', [
					'fix_double' => 1,
					'reason'     => 'create',
					'created_by' => -1,
					'text'       => 'Корректировка баллов'
				]);
			}

			$this->advanceProgressBar($customer);
		}
		$this->finishProgressBar();

		$this->ioStyle->text('Orders - ' . count($orders));
		$this->ioStyle->text(print_r($orders, true));

		$this->ioStyle->text('Customers - ' . count($customers));
		$this->ioStyle->text(print_r($customers, true));

		$this->ioStyle->text('Sum - ' . array_sum($customers));
		$this->ioStyle->text('Max - ' . max($customers));
	}
}