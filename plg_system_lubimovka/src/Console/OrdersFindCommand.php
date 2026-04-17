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

use Joomla\CMS\Date\Date;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMart\Administrator\Helper\LanguagesHelper;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

class OrdersFindCommand extends AbstractCommand
{
	use DatabaseAwareTrait;
	use MVCFactoryAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'lubimovka:orders:order_discounts';

	protected string $start_date = '2026-02-18 00:00:00';

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

		LanguagesHelper::changeLanguage('ru-RU');

		$this->ioStyle->text('Progress');
		$this->startProgressBar($total, true);
		$last = 0;

		$result = [
			'start_date'           => null,
			'end_date'             => null,
			'error_start_date'     => null,
			'error_end_date'       => null,
			'all_orders'           => 0,
			'error_orders'         => 0,
			'error_orders_precent' => 0,
			'sum_orders'           => 0,
			'sum_errors'           => 0,
			'sum_errors_precent'   => 0,
		];

		$path = JPATH_ROOT . '/bug-full.csv';

		$file = fopen($path, 'w');

		fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

		$header = [
			'Номер заказа',
			'Дата заказа',
			'Статус заказа',
			'Товар',
			'Цена без скидки',
			'Скидка',
			'Ошибочная скидка',
			'Кол-во в заказе',
			'Сумма скидки',
			'Сумма ошибочной скидки',
			'Сумма товара',
		];

		fputcsv($file, $header, ';');

		$skip_statuses = [5, 1, 2];
		while (true)
		{
			$query = $db->getQuery(true)
				->select(['id', 'status'])
				->from($db->quoteName('#__radicalmart_orders'))
				->where($db->quoteName('id') . ' > :last')
				->where($db->quoteName('created') . ' > ' . $db->quote($this->start_date))
				->bind(':last', $last, ParameterType::INTEGER)
				->order($db->quoteName('id') . ' ASC');
			$row   = $db->setQuery($query, 0, 1)->loadObject();

			if (empty($row))
			{
				break;
			}

			$last = (int) $row->id;

			if (in_array((int) $row->status, $skip_statuses))
			{
				$this->advanceProgressBar();
				continue;
			}

			/** @var OrderModel $model */
			$model = $this->getMVCFactory()->createModel('Order', 'Administrator', ['ignore_request' => true]);
			$order = $model->getItem($last);

			fputcsv($file, [
				$order->number,
				$order->created,
				$order->status->title,
			], ';');

			$order_error_discount = 0;
			if ($result['start_date'] === null)
			{
				$result['start_date'] = $order->created;
			}

			$result['end_date'] = $order->created;
			$result['all_orders']++;
			$result['sum_orders'] += $order->total['final'];

			foreach ($order->products as $product)
			{
				$product_row = [
					'order_number'                     => '',
					'order_date'                       => '',
					'order_status'                     => '',
					'product_title'                    => $product->title,
					'product_order_price'              => $product->order['base'],
					'product_order_discount'           => $product->order['discount'],
					'product_order_discount_error'     => '',
					'product_order_quantity'           => $product->order['quantity'],
					'product_order_discount_sum'       => $product->order['discount'] * $product->order['quantity'],
					'product_order_discount_sum_error' => '',
					'product_order_sum'                => $product->order['sum_final'],
				];
				if (empty($product->order['discount']))
				{
					$this->putToCSV($file, $product_row);

					continue;
				}

				if (empty($product->price['discount']))
				{
					$this->putToCSV($file, $product_row);
					continue;
				}


				if ($product->order['discount'] !== $product->price['discount'])
				{
					$this->putToCSV($file, $product_row);
					continue;
				}

				if ((new Date($product->price['discount_end']))->toUnix() >= (new Date($order->created))->toUnix())
				{
					$this->putToCSV($file, $product_row);
					continue;
				}

				$error_discount       = $product->order['discount'];
				$error_discount_sum   = (int) $error_discount * (int) $product->order['quantity'];
				$order_error_discount += $error_discount_sum;

				$product_row['product_order_discount_error']     = $error_discount;
				$product_row['product_order_discount_sum_error'] = $error_discount_sum;

				$this->putToCSV($file, $product_row);
			}


			$order_final_row = [
				'order_number'                     => 'Итог по заказу',
				'order_date'                       => '',
				'order_status'                     => '',
				'product_title'                    => '',
				'product_order_price'              => '',
				'product_order_discount'           => '',
				'product_order_discount_error'     => '',
				'product_order_quantity'           => '',
				'product_order_discount_sum'       => $order->total['discount'],
				'product_order_discount_sum_error' => '',
				'product_order_sum'                => $order->total['final'],
			];

			if (!empty($order_error_discount))
			{
				$order_final_row['product_order_discount_sum_error'] = $order_error_discount;

				if ($result['error_start_date'] === null)
				{
					$result['error_start_date'] = $order->created;
				}
				$result['error_end_date'] = $order->created;

				$result['error_orders']++;
				$result['sum_errors'] += $order_error_discount;
			}

			$this->putToCSV($file, $order_final_row);
			fputcsv($file, [], ';');
			fputcsv($file, [], ';');

			$this->advanceProgressBar();
		}
		$this->finishProgressBar();

		$result['error_orders_precent'] = $this->getPercent($result['error_orders'], $result['all_orders']);
		$result['sum_errors_precent']   = $this->getPercent($result['sum_errors'], $result['sum_orders']);


		fputcsv($file, ['Итог по выборке'], ';');
		$this->putToCSV($file, ['Начало выборки', $result['start_date']]);
		$this->putToCSV($file, ['Конец выборки', $result['end_date']]);
		$this->putToCSV($file, ['Первый заказ с ошибкой', $result['error_start_date']]);
		$this->putToCSV($file, ['Последний заказ с ошибкой', $result['error_end_date']]);

		fputcsv($file, [], ';');
		$this->putToCSV($file, ['Всего заказов за период', $result['all_orders']]);
		$this->putToCSV($file, ['Всего заказов за ошибкой', $result['error_orders']]);
		$this->putToCSV($file, ['Процент заказов с ошибкой', $result['error_orders_precent']]);

		fputcsv($file, [], ';');
		$this->putToCSV($file, ['Сумма всех заказов (получено денег)', $result['sum_orders']]);
		$this->putToCSV($file, ['Сумма ошибочных скидок', $result['sum_errors']]);
		$this->putToCSV($file, ['Процент от общей суммы', $result['sum_errors_precent']]);

		$this->ioStyle->text(print_r($result, true));
	}

	protected function putToCSV($file, $row): void
	{
		foreach ($row as &$val)
		{
			$val = str_replace('.', ',', $val);
		}

		fputcsv($file, $row, ';');
	}

	protected function getPercent($part, $total): float
	{

		$percent = ($part / $total) * 100;

		return round($percent, 2);
	}
}