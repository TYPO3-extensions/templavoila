<?php

// DO NOT REMOVE OR CHANGE THESE 2 LINES:
define('TYPO3_MOD_PATH', '../typo3conf/ext/templavoila/mod1/');
$BACK_PATH = '../../../../typo3/';
$MCONF['name'] = 'web_txtemplavoilaM1';

$MCONF['access'] = 'user,group';
//$MCONF['script'] = 'index.php';
$MCONF['script'] = '_DISPATCH';

$MLANG['default']['tabs_images']['tab'] = '../Resources/Public/Icon/Modules/PageModuleIcon.png';
$MLANG['default']['ll_ref'] = 'LLL:EXT:templavoila/mod1/locallang_mod.xlf';
