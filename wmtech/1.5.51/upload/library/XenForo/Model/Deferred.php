<?php
/* XF1551-PATCH: see the package-root whatsnew.html for changes and upgrade prerequisites. */

class XenForo_Model_Deferred extends XenForo_Model
{
	const HELD_TRIGGER_DATE = 2147483647;
	public function getDeferredById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_deferred
			WHERE deferred_id = ?
		', $id);
	}

	public function getDeferredByKey($key)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_deferred
			WHERE unique_key = ?
		', $this->_getSafeUniqueKey($key));
	}

	protected function _getSafeUniqueKey($key)
	{
		if (is_string($key) && strlen($key) > 50)
		{
			return md5($key);
		}

		return $key;
	}

	public function getRunnableDeferreds($manual = false)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND trigger_date <> ' . self::HELD_TRIGGER_DATE . '
				AND manual_execute = ' . ($manual ? 1 : 0) . '
			ORDER BY trigger_date
			LIMIT 1000
		', 'deferred_id', XenForo_Application::$time);
	}

	public function countRunnableDeferreds($manual = false)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND trigger_date <> ' . self::HELD_TRIGGER_DATE . '
				AND manual_execute = ' . ($manual ? 1 : 0) . '
		', XenForo_Application::$time);
	}

	public function getStoppedManualDefers()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND manual_execute = 1
			ORDER BY trigger_date
		', 'deferred_id', XenForo_Application::$time - 60);
	}

	protected static $_uniqueDefers = array();

	public function defer($class, array $data, $uniqueKey = null, $manual = false, $triggerDate = null)
	{
		$runner = XenForo_Deferred_Abstract::create($class);
		if (!$runner)
		{
			return false;
		}

		if (!$triggerDate)
		{
			$triggerDate = XenForo_Application::$time;
		}
		if (!$uniqueKey)
		{
			$uniqueKey = null;
		}

		$manual = ($manual ? 1 : 0);

		$db = $this->_getDb();

		if ($uniqueKey)
		{
			$uniqueHash = "$uniqueKey-$triggerDate" . ($data ? '-' . md5(serialize($data)) : '');
			if (isset(self::$_uniqueDefers[$uniqueHash]))
			{
				return self::$_uniqueDefers[$uniqueHash];
			}

			$uniqueKey = $this->_getSafeUniqueKey($uniqueKey);
		}
		else
		{
			$uniqueHash = false;
		}

		try
		{
			$db->query('
				INSERT INTO xf_deferred
					(execute_class, execute_data, unique_key, manual_execute, trigger_date)
				VALUES
					(?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					execute_class = VALUES(execute_class),
					execute_data = VALUES(execute_data),
					manual_execute = VALUES(manual_execute),
					trigger_date = VALUES(trigger_date)
			', array($class, serialize($data), $uniqueKey, $manual, $triggerDate));
		}
		catch (Zend_Db_Exception $exception)
		{
			if (XenForo_Db::inTransaction($db))
			{
				throw $exception;
			}

			// If we got an error, it's almost certainly a deadlock which has caused a rollback.
			// If we're not in a transaction, we can try again as there was nothing to rollback.
			// The deadlock is likely related to trying to delete the corresponding entry by
			// the runner.
			$db->query('
				INSERT INTO xf_deferred
					(execute_class, execute_data, unique_key, manual_execute, trigger_date)
				VALUES
					(?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					execute_class = VALUES(execute_class),
					execute_data = VALUES(execute_data),
					manual_execute = VALUES(manual_execute),
					trigger_date = VALUES(trigger_date)
			', array($class, serialize($data), $uniqueKey, $manual, $triggerDate));
		}

		$id = $db->lastInsertId();

		if (!$manual)
		{
			$this->updateNextDeferredTime();
			XenForo_Application::$autoDeferredIds[] = $id;
		}
		else
		{
			XenForo_Application::$manualDeferredIds[] = $id;
		}

		if ($uniqueHash)
		{
			self::$_uniqueDefers[$uniqueHash] = $id;
		}

		return $id;
	}

	public function resetUniqueDeferInserts()
	{
		self::$_uniqueDefers = array();
	}

	public function updateNextDeferredTime()
	{
		$date = intval($this->_getDb()->fetchOne('
			SELECT MIN(trigger_date)
			FROM xf_deferred
			WHERE manual_execute = 0
				AND trigger_date <> ' . self::HELD_TRIGGER_DATE . '
		'));

		$this->_getDataRegistryModel()->set('deferredRun', $date);

		return $date;
	}

	public function setNextDeferredTime($time)
	{
		$time = intval($time);
		if ($time <= 0)
		{
			return false;
		}

		$this->_getDataRegistryModel()->set('deferredRun', $time);

		return true;
	}

	public function deleteDeferredById($id)
	{
		$db = $this->_getDb();
		try
		{
			return $this->_getDb()->delete('xf_deferred', 'deferred_id = ' . $db->quote($id));
		}
		catch (Zend_Db_Exception $e)
		{
			if (XenForo_Db::inTransaction($db))
			{
				throw $e;
			}

			// This error is likely a deadlock, probably from an insert overwriting.
			// Let this get picked up by another runner.
			return 0;
		}
	}

	public function cancelFirstRunnableDeferred($manual = true)
	{
		$defers = $this->getRunnableDeferreds($manual);
		if (!$defers)
		{
			return false;
		}

		$deferred = reset($defers);
		$runner = XenForo_Deferred_Abstract::create($deferred['execute_class']);
		if (!$runner)
		{
			return false;
		}

		if (!$runner->canCancel())
		{
			return false;
		}

		$this->deleteDeferredById($deferred['deferred_id']);

		return $deferred;
	}

	protected static $_shutdownRegistered = false;
	protected static $_runningDeferred = false;

	public static function shutdownHandleFatalDeferred()
	{
		if (!self::$_runningDeferred)
		{
			return;
		}
		$running = self::$_runningDeferred;
		self::$_runningDeferred = false;
		$deferred = $running['deferred'];
		try
		{
			XenForo_Db::rollbackAll();
			XenForo_Application::defer(
				$deferred['execute_class'], $running['data'], $deferred['unique_key'], $deferred['manual_execute'],
				$deferred['manual_execute'] ? $deferred['trigger_date'] : time() + 300, true
			);
		}
		catch (Throwable $e)
		{
			XenForo_Error::logException($e, false);
		}
	}

	public function runDeferred(array $deferred, $targetRunTime, &$status, &$canCancel)
	{
		$this->resetUniqueDeferInserts();

		$canCancel = false;

		if ((int)$deferred['trigger_date'] === self::HELD_TRIGGER_DATE)
		{
			$status = 'Deferred entry is held for operator review.';
			return false;
		}
		$data = XenForo_Helper_Php::safeUnserialize($deferred['execute_data']);
		if (!is_array($data))
		{
			return $this->_retainInvalidDeferred($deferred, new UnexpectedValueException('Deferred ' . $deferred['deferred_id'] . ' has unsafe or invalid array data; its row was retained.'), $status);
		}
		try
		{
			$runner = XenForo_Deferred_Abstract::create($deferred['execute_class']);
			if (!$runner)
			{
				throw new UnexpectedValueException('Deferred ' . $deferred['deferred_id'] . ' has no runner; its row was retained.');
			}
		}
		catch (Throwable $e)
		{
			return $this->_retainInvalidDeferred($deferred, $e, $status);
		}
		if (!$this->_claimDeferred($deferred))
		{
			return false; // another worker claimed it or a producer replaced its data
		}

		if (!self::$_shutdownRegistered)
		{
			self::$_shutdownRegistered = true;
			register_shutdown_function(array(__CLASS__, 'shutdownHandleFatalDeferred'));
		}

		self::$_runningDeferred = array('deferred' => $deferred, 'data' => $data);

		try
		{
			$output = $runner->execute($deferred, $data, $targetRunTime, $status);
			self::$_runningDeferred = false;
		}
		catch (Throwable $e)
		{
			self::$_runningDeferred = false;

			// transactions are likely from the manual runner, so we need to roll them back
			// as they probably won't be committed
			XenForo_Db::rollbackAll();

			// Preserve failed automatic jobs too; retry later instead of hot-looping now.
			XenForo_Application::defer(
				$deferred['execute_class'], $data, $deferred['unique_key'], $deferred['manual_execute'],
				$deferred['manual_execute'] ? $deferred['trigger_date'] : time() + 300, true
			);
			if ($deferred['manual_execute'])
			{
				throw $e;
			}
			XenForo_Error::logException($e, false);
			$output = false;
			$status = "$deferred[execute_class] failed; data retained for retry. See error log.";

		}

		if ($output === 'exit')
		{
			// this is for debugging - restore to previous state
			XenForo_Db::rollbackAll();
			XenForo_Application::defer(
				$deferred['execute_class'], $data, $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
			);
			exit;
		}
		else if (is_array($output))
		{
			$canCancel = $runner->canCancel();

			return XenForo_Application::defer(
				$deferred['execute_class'], $output, $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
			);
		}
		else
		{
			return false;
		}
	}

	/** Claim only the snapshot that was validated; retain a concurrent producer's update. */
	protected function _claimDeferred(array $deferred)
	{
		$db = $this->_getDb();
		try
		{
			return $db->query('
				DELETE FROM xf_deferred
				WHERE deferred_id = ? AND execute_class = ? AND execute_data = ?
					AND trigger_date = ? AND manual_execute = ? AND unique_key <=> ?
			', array($deferred['deferred_id'], $deferred['execute_class'], $deferred['execute_data'],
				$deferred['trigger_date'], $deferred['manual_execute'], $deferred['unique_key']))->rowCount();
		}
		catch (Zend_Db_Exception $e)
		{
			if (XenForo_Db::inTransaction($db)) { throw $e; }
			return 0;
		}
	}

	/** Hold invalid automatic work once; manual callers receive the original visible error. */
	protected function _retainInvalidDeferred(array $deferred, $error, &$status)
	{
		if ($deferred['manual_execute'])
		{
			throw $error;
		}
		$updated = $this->_getDb()->query('
			UPDATE xf_deferred SET trigger_date = ?
			WHERE deferred_id = ? AND trigger_date = ? AND execute_data = ?
				AND execute_class = ? AND manual_execute = ? AND unique_key <=> ?
		', array(self::HELD_TRIGGER_DATE, $deferred['deferred_id'], $deferred['trigger_date'], $deferred['execute_data'],
			$deferred['execute_class'], $deferred['manual_execute'], $deferred['unique_key']))->rowCount();
		if ($updated)
		{
			XenForo_Error::logException($error, false);
		}
		$status = 'Invalid automatic job held for operator review; its data was retained.';
		return false;
	}

	public function runByUniqueKey($uniqueKey, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$deferred = $this->getDeferredByKey($uniqueKey);
		if (!$deferred)
		{
			return false;
		}

		$continued = $this->_runInternal(array($deferred), $targetRunTime, $status, $canCancel);
		return $continued ? reset($continued) : false;
	}

	public function runById($id, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$deferred = $this->getDeferredById($id);
		if (!$deferred)
		{
			return false;
		}

		$continued = $this->_runInternal(array($deferred), $targetRunTime, $status, $canCancel);
		return $continued ? reset($continued) : false;
	}

	public function run($manual = false, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$runnable = $this->getRunnableDeferreds($manual);
		$continued = $this->_runInternal($runnable, $targetRunTime, $status, $canCancel);

		if (!$manual)
		{
			$nextRun = $this->updateNextDeferredTime();
			if ($nextRun && $nextRun <= time())
			{
				$continued = true;
			}
			if (!$nextRun)
			{
				XenForo_Application::defer('Cron', array(), 'cron', false, time() + 300);
			}
		}

		return $continued;
	}

	protected function _runInternal(array $runnable, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		if ($targetRunTime === null)
		{
			$targetRunTime = XenForo_Application::getConfig()->rebuildMaxExecution;
		}

		if ($targetRunTime < 0)
		{
			$targetRunTime = 0;
		}
		else if ($targetRunTime > 0 && $targetRunTime < 2)
		{
			$targetRunTime = 2;
		}

		$continued = array();
		$limitTime = ($targetRunTime > 0);
		$startTime = microtime(true);

		foreach ($runnable AS $deferred)
		{
			if ($limitTime)
			{
				$remainingTime = $targetRunTime - (microtime(true) - $startTime);
				if ($remainingTime < 1)
				{
					// ran out of time - have some pick up later
					$continued[] = $deferred['deferred_id'];
					continue;
				}
			}
			else
			{
				$remainingTime = 0;
			}

			$continue = $this->runDeferred($deferred, $remainingTime, $status, $canCancel);
			if ($continue)
			{
				$continued[] = $continue;
			}
		}

		return $continued;
	}
}