<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE === 'BE') {

	/**
	 * Registers a Backend Module
	 */
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'RENOLIT.' . $_EXTKEY,
		'tools',	 // Make module a submodule of 'tools'
		'reintttnewsconv',	// Submodule key
		'',						// Position
		array(
			'Converter' => 'convert',
		),
		array(
			'access' => 'user,group',
			'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.gif',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_reintttnewsconv.xlf',
		)
	);

}