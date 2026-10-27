<?php
/* XF1551-PATCH: see the package-root whatsnew.html for changes and upgrade prerequisites. */
class XenForo_Install_Upgrade_1055170 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.5.51';
	}

	public function step1()
	{
		if (!XenForo_Application::getInstance()->hasExplicitGlobalSalt())
		{
			throw new XenForo_Exception('Before upgrading, run tools/prepare-upgrade.php against the previous installed code to pin its effective salt. Restore the old code first if the new files were already uploaded.');
		}
	}
}
