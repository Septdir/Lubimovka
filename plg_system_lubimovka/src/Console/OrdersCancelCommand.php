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
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

/* @deprecated */
class OrdersCancelCommand extends AbstractCommand
{
	use DatabaseAwareTrait;
	use MVCFactoryAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  1.0.0
	 */
	protected static $defaultName = 'lubimovka:orders:cancel';

	/**
	 * Command methods for step by step run.
	 *
	 * @var  array
	 *
	 * @since 1.0.0
	 */
	protected array $methods = [
		'cancelOrders'
	];

	public function cancelOrders(): void
	{
		$io = $this->ioStyle;
		$io->title('Cancel orders');

		$timeout = new Date('-8 day');
		$timeout->setTime(0, 0, 0);

		$form = 1;
		$to   = 5;

		$io->text('Get Total');
		$io->progressStart(1);
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(id)')
			->from($db->quoteName('#__radicalmart_orders'))
			->where($db->quoteName('status') . ' = ' . $form)
			->where($db->quoteName('created') . ' < ' . $db->quote($timeout->toSql()));
		$total = (int) $db->setQuery($query)->loadResult();
		$io->progressFinish();

		if ($total === 0)
		{
			return;
		}

		$io->text('Progress');
		$io->progressStart($total);
		$limit = 10;
		$last  = 0;

		while (true)
		{
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__radicalmart_orders'))
				->where($db->quoteName('status') . ' = ' . $form)
				->where($db->quoteName('created') . ' < ' . $db->quote($timeout->toSql()))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER)
				->order($db->quoteName('id'));
			$pks   = $db->setQuery($query, 0, $limit)->loadColumn();

			$count = count($pks);
			if ($count === 0)
			{
				break;
			}

			// Run method
			foreach ($pks as $pk)
			{
				$last = (int) $pk;
				$this->ioStyle->progressAdvance();

				/** @var OrderModel $model */
				$model = $this->getMVCFactory()->createModel('Order', 'Administrator',
					['ignore_request' => true]);

				$model->updateStatus($last, $to, false, -1, 'Auto cancelled');
			}

			// Clean RAM
			$model = null;
			ParamsHelper::reset();
			$this->getDatabase()->disconnect();
			if ($count !== $limit)
			{
				break;
			}
		}

		$this->ioStyle->progressFinish();
	}
}