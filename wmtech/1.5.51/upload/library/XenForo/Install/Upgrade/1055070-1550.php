<?php
/* XF1551-PATCH: see the package-root whatsnew.html for changes and upgrade prerequisites. */

class XenForo_Install_Upgrade_1055070 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.5.50';
	}

	/** Provider values are kept for XF2; the options accessor retires their XF1 use. */
	public function step1()
	{
	}

	/**
	 * Move boards off the retired Microsoft Ajax CDN.
	 *
	 * ajax.microsoft.com was decommissioned in 2022; a board still pointed at it serves no
	 * jQuery at all and is effectively dead in the browser. Fall back to the local copy.
	 */
	public function step2()
	{
		$this->executeUpgradeQuery("
			UPDATE xf_option
			SET option_value = 'local'
			WHERE option_id = 'jQuerySource'
				AND option_value = 'microsoft'
		");
	}

	/**
	 * The 'min' setting for uncompressedJs points at js/xenforo/min/, a directory that has
	 * never existed in any XenForo distribution, so selecting it 404s every script on the
	 * board. Send those installs back to the shipped minified files.
	 */
	public function step3()
	{
		$this->executeUpgradeQuery("
			UPDATE xf_option
			SET option_value = '0'
			WHERE option_id = 'uncompressedJs'
				AND option_value = '2'
		");
	}
}
