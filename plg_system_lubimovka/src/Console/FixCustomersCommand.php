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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class FixCustomersCommand extends AbstractCommand
{
	use DatabaseAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'lubimovka:fix:customers';

	/**
	 * Command methods for step by step run.
	 *
	 * @var  array
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected array $methods = [
		'cleanUsersPhones',
		'fixCustomers',
	];

	public function cleanUsersPhones(): void
	{
		PluginHelper::importPlugin('radicalmart');
		$this->ioStyle->title('Cleaning users phones');
		$this->ioStyle->text('Get total records');
		$this->startProgressBar(1, true);
		$total = CommandsHelper::getTotalItems('#__radicalmart_users_phones', 'user_id');
		$this->finishProgressBar();

		$this->ioStyle->title('Clean users phones');
		$this->startProgressBar($total, true);
		$db     = $this->getDatabase();
		$limit  = 100;
		$last   = 0;
		$errors = [];
		while (true)
		{
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__radicalmart_users_phones'))
				->where($db->quoteName('user_id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER);
			$rows  = $db->setQuery($query, $limit)->loadObjectList();
			$count = count($rows);
			if ($count === 0)
			{
				break;
			}
			foreach ($rows as $row)
			{
				$last = (int) $row->user_id;

				$query = $db->getQuery(true)
					->select('id')
					->from($db->quoteName('#__users'))
					->where($db->quoteName('id') . ' = ' . ':last')
					->bind(':last', $last, ParameterType::INTEGER);
				$user  = $db->setQuery($query, 0, 1)->loadResult();
				if (empty($user))
				{
					$query = $db->getQuery(true)
						->delete($db->quoteName('#__radicalmart_users_phones'))
						->where($db->quoteName('user_id') . ' = :delete_id')
						->bind(':delete_id', $last, ParameterType::INTEGER);
					$db->setQuery($query)->execute();

					$this->advanceProgressBar();
					continue;
				}

				$phone = UserHelper::cleanPhone($row->phone);
				if (empty($phone))
				{
					$query = $db->getQuery(true)
						->delete($db->quoteName('#__radicalmart_users_phones'))
						->where($db->quoteName('user_id') . ' = :delete_id')
						->bind(':delete_id', $last, ParameterType::INTEGER);
					$db->setQuery($query)->execute();

					$this->advanceProgressBar();
					continue;
				}
				if ($phone !== $row->phone)
				{
					$query = $db->getQuery(true)
						->select('*')
						->from($db->quoteName('#__radicalmart_users_phones'))
						->where($db->quoteName('user_id') . ' <> :user_id')
						->where($db->quoteName('phone') . ' = :phone')
						->bind(':user_id', $row->user_id, ParameterType::INTEGER)
						->bind(':phone', $phone)
						->order('user_id');
					$find  = $db->setQuery($query)->loadObject();
					if ($find)
					{
						if (!isset($errors[$phone]))
						{
							$errors[$phone] = [];
						}
						if (!isset($errors[$phone][$row->user_id]))
						{
							$errors[$phone][$row->user_id] = $row;
						}

						if (!isset($errors[$phone][$find->user_id]))
						{
							$errors[$phone][$find->user_id] = $find;
						}

						$query = $db->getQuery(true)
							->select(['id', 'lastvisitDate'])
							->from($db->quoteName('#__users'))
							->whereIn($db->quoteName('id'), [(int) $row->user_id, (int) $find->user_id]);
						$users = $db->setQuery($query)->loadAssocList('id', 'lastvisitDate');

						$row_last  = (new Date($users[$row->user_id]))->toUnix();
						$find_last = (new Date($users[$find->user_id]))->toUnix();

						$continue  = false;
						$delete_id = 0;
						if ($row_last > $find_last)
						{
							$delete_id                              = $find->user_id;
							$errors[$phone][$find->user_id]->delete = true;
						}
						else
						{
							$delete_id                             = $row->user_id;
							$errors[$phone][$row->user_id]->delete = true;
							$continue                              = true;
						}

						$query = $db->getQuery(true)
							->delete($db->quoteName('#__radicalmart_users_phones'))
							->where($db->quoteName('user_id') . ' = :delete_id')
							->bind(':delete_id', $delete_id, ParameterType::INTEGER);
						$db->setQuery($query)->execute();

						if ($continue)
						{
							$this->advanceProgressBar();

							continue;
						}
					}

					$row->phone = $phone;

					$db->updateObject('#__radicalmart_users_phones', $row, 'user_id');
				}

				$this->advanceProgressBar();
			}

			if ($count < $limit)
			{
				break;
			}

			$db->disconnect();
		}

		$this->finishProgressBar();
	}

	public function fixCustomers(): void
	{
		$this->ioStyle->title('Fix Customers users phones');

		$this->ioStyle->text('Get total customers');
		$this->startProgressBar(1, true);
		$total = CommandsHelper::getTotalItems('#__radicalmart_customers');
		$this->finishProgressBar();

		$this->ioStyle->title('Advance items');
		$this->startProgressBar($total, true);
		$db    = $this->getDatabase();
		$limit = 100;
		$last  = 0;
		while (true)
		{
			$query = $db->getQuery(true)
				->select(['id', 'user', 'contacts'])
				->from($db->quoteName('#__radicalmart_customers'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last)
				->order('id');
			$rows  = $db->setQuery($query, 0, $limit)->loadObjectList();
			$count = count($rows);
			if ($count === 0)
			{
				break;
			}
			foreach ($rows as $row)
			{
				$last = (int) $row->id;

				$row->contacts = new Registry($row->contacts);
				$row->user     = new Registry($row->user);

				if (empty($row->contacts->get('phone')) && !empty($row->user->get('phone')))
				{
					$row->contacts->set('phone', $row->user->get('phone'));
				}
				if (empty($row->contacts->get('email')) && !empty($row->user->get('email')))
				{
					$row->contacts->set('email', $row->user->get('email'));
				}

				if (!empty($row->user->get('name')))
				{
					$name = explode(' ', $row->user->get('name'));
					if (empty($row->contacts->get('first_name')))
					{
						$row->contacts->set('first_name', $name[0]);
					}
					if (empty($row->contacts->get('last_name')) && count($name) > 1)
					{
						$row->contacts->set('last_name', $name[1]);
					}
					if (empty($row->contacts->get('second_name')) && count($name) > 2)
					{
						$row->contacts->set('second_name', $name[2]);
					}
				}

				$user_phone    = $row->user->get('phone');
				$contact_phone = $row->contacts->get('phone');

				$user_phone    = UserHelper::cleanPhone($user_phone);
				$contact_phone = UserHelper::cleanPhone($contact_phone);

				if (empty($user_phone) && !empty($contact_phone))
				{
					$user_phone = $contact_phone;
				}

				if (!empty($user_phone))
				{
					$query  = $db->getQuery(true)
						->select('user_id')
						->from($db->quoteName('#__radicalmart_users_phones'))
						->where($db->quoteName('phone') . ' = :phone')
						->where($db->quoteName('user_id') . ' <> :user_id')
						->bind(':phone', $contact_phone)
						->bind(':user_id', $last, ParameterType::INTEGER);
					$double = $db->setQuery($query)->loadResult();
					if ($double)
					{
						$user_phone = '';
					}
					else
					{
						$query = $db->getQuery(true)
							->select('user_id')
							->from($db->quoteName('#__radicalmart_users_phones'))
							->where($db->quoteName('phone') . ' = :phone')
							->bind(':phone', $user_phone);
						$exist = $db->setQuery($query)->loadResult();

						if (empty($exist))
						{
							$insert          = new \stdClass();
							$insert->user_id = $row->id;
							$insert->phone   = $user_phone;
							$db->insertObject('#__radicalmart_users_phones', $insert);
						}
					}
				}
				$row->user->set('phone', $user_phone);
				$row->contacts->set('phone', $contact_phone);

				$row->user     = $row->user->toString();
				$row->contacts = $row->contacts->toString();

				$db->updateObject('#__radicalmart_customers', $row, 'id');

				$this->advanceProgressBar();
			}

			$db->disconnect();

			if ($count < $limit)
			{
				break;
			}
		}

		$this->finishProgressBar();
	}
}