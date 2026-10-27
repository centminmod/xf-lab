<?php
require __DIR__.'/xf1-bootstrap.php';
$install=XenForo_Model::create('XenForo_Install_Model_Install');$errors=$install->getRequirementErrors(XenForo_Application::getDb());
$hashes=XenForo_Install_Data_FileSums::getHashes();$mismatches=XenForo_Helper_Hash::compareHashes($hashes);
$compatible=PHP_VERSION_ID>=70300 && PHP_VERSION_ID<80000;
$result=array('php'=>PHP_VERSION,'phpRequirementMessage'=>isset($errors['phpVersion'])?(string)$errors['phpVersion']:null,'requirementErrorKeys'=>array_keys($errors),'healthEntries'=>count($hashes),'hashMismatches'=>$mismatches);
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
if ($mismatches || ($compatible && $errors) || (!$compatible && !isset($errors['phpVersion']))) {throw new RuntimeException('Native health or minimum-version check failed');}
