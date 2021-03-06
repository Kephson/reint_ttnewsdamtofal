<?php
/* * *************************************************************
 * Extension Manager/Repository config file for ext: "reint_ttnewsdamtofal"
 *
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 * ************************************************************* */

$EM_CONF[$_EXTKEY] = array(
	'title' => 'tt_news DAM media converter',
	'description' => 'Simple extension to convert DAM <media> entries in tt_news bodytext to FAL.',
	'category' => 'module',
	'author' => 'Ephraim Härer',
	'author_email' => 'ephraim.haerer@renolit.com',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'version' => '1.0.5',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.9-7.99.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
