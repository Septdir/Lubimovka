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
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class OrdersLogsFixCommand extends AbstractCommand
{
	use DatabaseAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'lubimovka:orders:fix_logs';

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
		$this->ioStyle->title('Fix orders logs');

		$this->ioStyle->text('Get total');
		$this->startProgressBar(1, true);
		$total = CommandsHelper::getTotalItems('#__radicalmart_orders');
		$this->finishProgressBar();

		$this->ioStyle->text('Fix orders logs');
		$this->startProgressBar($total, true);
		$limit = 50;
		$last  = 0;
		$db    = $this->getDatabase();
		while (true)
		{
			$query = $db->createQuery()
				->select(['id', 'logs'])
				->from($db->quoteName('#__radicalmart_orders'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER)
				->order('id');
			$rows  = $db->setQuery($query, 0, $limit)->loadObjectList();
			if (count($rows) === 0)
			{
				break;
			}

			foreach ($rows as $row)
			{
				$last = (int) $row->id;
				if (!str_contains($row->logs, '"bonuses"'))
				{

					$this->advanceProgressBar();
					continue;
				}

				$row->logs  = (new Registry($row->logs))->toArray();
				$needUpdate = false;
				foreach ($row->logs as &$log)
				{
					if (empty($log['plugin']) || $log['plugin'] !== 'bonuses')
					{
						continue;
					}

					if (str_starts_with($log['action'], 'points_'))
					{
						$log['plugin'] = 'bonuses_points';
						$needUpdate    = true;
					}
					elseif ($log['action'] === 'create_codes')
					{
						$log['plugin'] = 'bonuses_codes';
						$log['action'] = 'bonuses_codes_create';
						$needUpdate    = true;
					}
				}
				if ($needUpdate)
				{
					$row->logs = (new Registry($row->logs))->toString();
					$db->updateObject('#__radicalmart_orders', $row, 'id');
				}

				$this->advanceProgressBar();
			}

			$db->disconnect();
			if (count($rows) < $limit)
			{
				break;
			}
		}

	}
}