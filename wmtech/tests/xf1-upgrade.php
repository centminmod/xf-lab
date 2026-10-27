<?php
require __DIR__.'/xf1-bootstrap.php';
$from=(int)$argv[1];$target=(int)$argv[2];
$db=XenForo_Application::getDb();
if ((int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'")!==$from
	|| XenForo_Application::$versionId!==$target) { throw new RuntimeException('Unexpected upgrade endpoints'); }
$model=XenForo_Model::create('XenForo_Install_Model_Upgrade');
$versions=$model->getRemainingUpgradeVersionIds($from);
if (!$versions || end($versions)!==$target) { throw new RuntimeException('Wrong native upgrade sequence'); }
XenForo_Visitor::setup(1);
foreach ($versions AS $version)
{
	$upgrade=$model->getUpgrade($version);
	for ($step=1;method_exists($upgrade,'step'.$step);$step++)
	{
		$result=$upgrade->{'step'.$step}();
		if ($result!==null && $result!==true) { throw new RuntimeException('Unexpected step continuation'); }
		echo "Completed $version step $step\n";
	}
	$model->insertUpgradeLog($version);
}
XenForo_Model::create('XenForo_Install_Model_Install')->insertDeferredRebuild();
$deferred=XenForo_Model::create('XenForo_Model_Deferred');
for ($i=0;$i<200;$i++)
{
	$continued=$deferred->runByUniqueKey('installUpgradeRebuild',15,$status);
	if (!$continued) { break; }
}
if ($continued) { throw new RuntimeException('Native rebuild did not finish'); }
$model->updateVersion();
XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('boardUrl'=>'http://localhost:8774'));
XenForo_Model::create('XenForo_Model_BbCode')->updateBbCodeParseCacheVersion();
echo json_encode(array('from'=>$from,'to'=>$target,'nativeUpgradeIds'=>$versions,'completed'=>true)),"\n";
