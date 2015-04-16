<?php

namespace RENOLIT\ReintTtnewsdamtofal\Controller;

/* * *************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Ephraim Härer <ephraim.haerer@renolit.com>, RENOLIT SE
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * ConverterController
 */
class ConverterController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * action convert
	 * starts the conversion after form submit
	 *
	 * @return void
	 */
	public function convertAction() {

		if ($this->request->hasArgument('tx_reintttnewsdamtofal_tools_reintttnewsdamtofalreintttnewsconv')) {
			$request = $this->request->getArgument('tx_reintttnewsdamtofal_tools_reintttnewsdamtofalreintttnewsconv');
			if (isset($request['start']) && $request['start'] === 'convert') {

				if (isset($request['convertnum']) && (int) $request['convertnum'] > 0) {
					$convertnum = (int) $request['convertnum'];
				} else {
					$convertnum = 100;
				}
				//DebuggerUtility::var_dump($convertnum);

				$tt_news_elements = $this->load_tt_news_media($convertnum);
				$dam_entries = $this->get_related_dam_entries($tt_news_elements);
				$fal_entries = $this->get_related_fal_entries($dam_entries);
				$fal_entries_replaced = $this->replace_media_elements($fal_entries);

				//DebuggerUtility::var_dump($fal_entries_replaced);
				//$count = count($fal_entries_replaced);
				$count = $this->write_new_fal_data($fal_entries_replaced);

				// warn message if there are more entries, else ok
				if ($count > $convertnum) {
					$message = GeneralUtility::makeInstance(
									'TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $count . ' ' . LocalizationUtility::translate('text1', 'reint_ttnewsdamtofal'), LocalizationUtility::translate('head1', 'reint_ttnewsdamtofal'), FlashMessage::WARNING, FALSE
					);
				} else {
					$message = GeneralUtility::makeInstance(
									'TYPO3\\CMS\\Core\\Messaging\\FlashMessage', LocalizationUtility::translate('text2', 'reint_ttnewsdamtofal'), LocalizationUtility::translate('head2', 'reint_ttnewsdamtofal'), // the header is optional
									FlashMessage::OK, FALSE
					);
				}

				\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($message);
			}
		}
	}

	/**
	 * write the new fal entries to database
	 * 
	 * @param array $fal_entries
	 * @return boolean
	 */
	protected function write_new_fal_data($fal_entries) {

		$into_table = 'tt_news';
		$counter = 0;

		foreach ($fal_entries as $k => $t) {

			if (isset($t['bodytext_replaced'])) {

				$field_values = array('bodytext' => $t['bodytext_replaced'], 'tstamp' => time());
				$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($into_table, '`uid`=' . $k, $field_values);
				$cnt = $GLOBALS['TYPO3_DB']->sql_affected_rows();
				if ($cnt > 0) {
					$counter++;
				}
			}
		}

		return $counter;
	}

	/**
	 * replace the media element with the new link element to the file
	 * 
	 * @param array $fal_entries
	 * @return array
	 */
	protected function replace_media_elements($fal_entries) {

		$fal_entries_new = array();

		foreach ($fal_entries as $k => $tt) {

			if (isset($tt['media'])) {

				$fal_entries_new[$k] = $tt;

				// bugfix see https://github.com/Kephson/reint_ttnewsdamtofal/issues/3
				$bodytext = str_replace('http://', 'http:', $tt['bodytext']);

				$html = new \RENOLIT\ReintTtnewsdamtofal\Lib\simple_html_dom();
				// syntax: ($str, $lowercase=true, $stripRN=true, $defaultBRText=DEFAULT_BR_TEXT, $defaultSpanText=DEFAULT_SPAN_TEXT)				
				// FIX set stripRN to false: do not remove \r\n, might be a bit harsh
				$html->load($bodytext, true, false, "", "");

				$media_elements = $html->find("media");

				foreach ($media_elements as $key => $m) {

					if (isset($tt['media'][$key])) {
						$new_element = '<link file:' . $tt['media'][$key]['sys_file_uid'] . ' _blank download "' . $tt['media'][$key]['text'] . '">' . $tt['media'][$key]['text'] . '</link>';
						$html->find("media", $key)->outertext = $new_element;
					}
				}

				$bodytext_replaced = $html->save();

				// bugfix see https://github.com/Kephson/reint_ttnewsdamtofal/issues/3
				$fal_entries_new[$k]['bodytext_replaced'] = str_replace('http:', 'http://', $bodytext_replaced);
			}
		}

		return $fal_entries_new;
	}

	/**
	 * find all related FAL entries for the old DAM entries
	 * 
	 * @param array $dam_entries
	 * @return array
	 */
	protected function get_related_fal_entries($dam_entries) {

		$fal_entries = array();

		foreach ($dam_entries as $k => $tt) {

			if (isset($tt['media'])) {

				$fal_entries[$k] = $tt;

				foreach ($tt['media'] as $tk => $t) {

					if (isset($t['file_name'])) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
								'`uid`,`identifier`', 'sys_file', '`identifier` LIKE \'%' . $t['file_name'] . '%\'', '', '', 100
						);
						//DebuggerUtility::var_dump($res);
						if (isset($res[0])) {
							$fal_entries[$k]['media'][$tk] = $t;
							$fal_entries[$k]['media'][$tk]['sys_file_uid'] = $res[0]['uid'];
							$fal_entries[$k]['media'][$tk]['identifier'] = $res[0]['identifier'];
						}

						// debug
						/*
						  $fal_entries[$k]['media'][$tk]['sys_file_uid'] = 1;
						  $fal_entries[$k]['media'][$tk]['identifier'] = '/Configuration/TsConfig/Page/ext.cyz_change_notify.ts';
						 */
					}
				}
			}
		}

		return $fal_entries;
	}

	/**
	 * find all related DAM entries for the media elements
	 * 
	 * @param array $tt_news_elements
	 * @return array
	 */
	protected function get_related_dam_entries($tt_news_elements) {

		$dam_entries = array();

		foreach ($tt_news_elements as $k => $tt) {

			if (isset($tt['media'])) {

				$dam_entries[$k] = $tt;

				foreach ($tt['media'] as $tk => $t) {
					if (isset($t['dam_id'])) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
								'`file_name`,`file_path`', 'tx_dam', '`uid` = ' . $t['dam_id'], '', '', 100
						);
						//DebuggerUtility::var_dump($res);
						if (isset($res[0])) {
							$dam_entries[$k]['media'][$tk] = $t;
							$dam_entries[$k]['media'][$tk]['file_name'] = $res[0]['file_name'];
							$dam_entries[$k]['media'][$tk]['file_path'] = $res[0]['file_path'];
						}

						// debug
						/*
						  $dam_entries[$k]['media'][$tk] = $t;
						  $dam_entries[$k]['media'][$tk]['file_name'] = '';
						  $dam_entries[$k]['media'][$tk]['file_path'] = '';
						 */
					}
				}
			}
		}

		return $dam_entries;
	}

	/**
	 * load all tt_news media elements in an array
	 * 
	 * @return array
	 */
	protected function load_tt_news_media($convertnum = 100) {

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'`uid`,`bodytext`', 'tt_news', '`bodytext` LIKE \'%<media %\' OR `bodytext` LIKE \'%&lt;media %\'', '`uid`', '', $convertnum
		);

		//DebuggerUtility::var_dump($res); die();

		$tt_news_elements = array();

		foreach ($res as $r) {
			if (isset($r['bodytext'])) {

				$r['bodytext'] = $this->replaceConvertedChars($r['bodytext']);

				$tt_news_elements[$r['uid']] = array();

				$html = new \RENOLIT\ReintTtnewsdamtofal\Lib\simple_html_dom();
				$html->load($r['bodytext']);
				$media = $html->find("media");

				$tt_news_elements[$r['uid']]['bodytext'] = $r['bodytext'];

				// get the link attributes and the DAM id of the link
				if (is_array($media) && !empty($media)) {

					foreach ($media as $mk => $m) {
						foreach ($m->attr as $k => $a) {
							if ((int) $k > 0) {
								// PROBLEM:
								// (int) $k might be any number, found in a media tag.
								// then the last would be stored as dam_id
								// e.g. <media 240 - - "TEXT,  Report_2014.pdf, 3.2 MB">The Report as PDF</media> 
								// would set 3.2 as dam_id, correct would be 240
								// FIX: check if dam_id is already set
								if ($tt_news_elements[$r['uid']]['media'][$mk]['dam_id'] == "") {
									$tt_news_elements[$r['uid']]['media'][$mk]['dam_id'] = (int) $k;
									// DebuggerUtility::var_dump("dam_id: ".$k);
								}
							}
							if ($k === '_blank') {
								$tt_news_elements[$r['uid']]['media'][$mk]['target'] = $k;
							}
							// get the content of the link
							if (isset($m->nodes[0]->_[4])) {
								$tt_news_elements[$r['uid']]['media'][$mk]['text'] = $m->nodes[0]->_[4];
							}
						}
					}
				}
			}
		}

		return $tt_news_elements;
	}

	/**
	 * if html chars of media element are converted to specialchars, replace it
	 * 
	 * @param type $subject
	 * @return type
	 */
	protected function replaceConvertedChars($subject) {

		$search = array('&lt;media', 'download &quot;', '_blank &quot;', '&quot;&gt;',
			'&lt;/media&gt;');
		$replace = array('<media', 'download ";', '_blank ";', '">', '</media>');

		return str_replace($search, $replace, $subject);
	}

}
