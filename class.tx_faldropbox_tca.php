<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Stefan froemken <firma@sfroemken.de>
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
 ***************************************************************/

/**
 * @package fal_dropbox
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class tx_faldropbox_tca {

	/**
	 * @var Tx_FalDropbox_Auth_OAuth
	 */
	protected $oAuth;

	/**
	 * @var TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $contentObject;

	/**
	 * @var \TYPO3\CMS\Core\Registry
	 */
	protected $registry;

	protected $parentArray = array();
	protected $configuration = array();
	protected $error = '';
	protected $flexformOptions = array(
		'parentTagMap' => array(
			'data' => 'sheet',
			'sheet' => 'language',
			'language' => 'field',
			'el' => 'field',
			'field' => 'value',
			'field:el' => 'el',
			'el:_IS_NUM' => 'section',
			'section' => 'itemType'
		),
		'disableTypeAttrib' => 2
	);





	/**
	 * initializes this object
	 *
	 * @param array $parentArray
	 * @return void
	 */
	protected function initialize(array $parentArray) {
		$this->parentArray = $parentArray;
		$this->configuration = $this->getConfiguration();
		$this->contentObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$this->registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
		$this->oAuth = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_FalDropbox_Auth_OAuth')->init();
	}

	/**
	 * show informations about dropbox authentication
	 *
	 * @param array $parentArray
	 * @param TYPO3\CMS\Backend\Form\FormEngine $formEngine
	 * @return string
	 */
	public function dropboxLink($parentArray, TYPO3\CMS\Backend\Form\FormEngine $formEngine) {
		$this->initialize($parentArray);
		if (!$this->checkConfiguration()) {
			return $this->error;
		}

		$settings = $this->registry->get('fal_dropbox', 'settings');
		if (empty($settings['requestToken'])) {
			return 'Something went wrong. Please try to save again. Normally a new request token should be generated.';
		}
		$this->oAuth->setKeys($settings['appKey'], $settings['appSecret']);

		if (!empty($settings['accessToken'])) {
			$this->oAuth->setRequestToken($settings['accessToken']['token'], $settings['accessToken']['token_secret']);
			return '<div style="color: green;">You\'re now authenticated to your dropbox account</div>';
		} else {
			$this->oAuth->setRequestToken($settings['requestToken']['token'], $settings['requestToken']['token_secret']);
			$accessToken = $this->oAuth->getAccessToken();
			if ($accessToken) {
				// to be compatible with original oAuth-PEAR package we rename the keys here
				$settings['accessToken']['token'] = $accessToken['oauth_token'];
				$settings['accessToken']['token_secret'] = $accessToken['oauth_token_secret'];
				$this->registry->set('fal_dropbox', 'settings', $settings);
				return '<div style="color: green;">You\'re now authenticated to your dropbox account</div>';
			} else {
				return '<div>Link: <a href="' . $this->oAuth->getAuthorizeUrl() . '" target="_blank" style="text-decoration: underline;">Connect App with your Dropbox account</a></div>';
			}
		}
	}

	/**
	 * get configuration
	 *
	 * @return array
	 */
	public function getConfiguration() {
		$config = array();
		if ($this->parentArray['row']['configuration']) {
			$xmlArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array(
				$this->parentArray['row']['configuration']
			);
			foreach ($xmlArray['data']['sDEF']['lDEF'] as $key => $value) {
				$config[$key] = $value['vDEF'];
			}
		}
		return $config;
	}

	/**
	 * check given flexform configuration
	 *
	 * @return boolean
	 */
	public function checkConfiguration() {
		if(empty($this->configuration['appKey'])) {
			$this->error = '<div>You have to set App key first and save the record.</div>';
			return false;
		}
		if(empty($this->configuration['appSecret'])) {
			$this->error = '<div>You have to set App key first and save the record.</div>';
			return false;
		}
		if(empty($this->configuration['accessType'])) {
			$this->error = '<div>You have to save this record first.</div>';
			return false;
		}
		return true;
	}

	/**
	 * get requestToken
	 *
	 * @param array $parentArray
	 * @param TYPO3\CMS\Backend\Form\FormEngine $formEngine
	 * @return string
	 */
	public function getRequestToken($parentArray, TYPO3\CMS\Backend\Form\FormEngine $formEngine) {
		$this->initialize($parentArray);
		if (!$this->checkConfiguration()) {
			return $this->error;
		}
		$settings = $this->registry->get('fal_dropbox', 'settings');

		// remove cache entry if tokens have changed
		if ($settings['appKey'] != $this->configuration['appKey'] || $settings['appSecret'] != $this->configuration['appSecret']) {
			$this->registry->remove('fal_dropbox', 'settings');
			$settings = $this->registry->get('fal_dropbox', 'settings');
		}

		// generate a token if never have done
		if (empty($settings['requestToken'])) {
			$this->oAuth->setKeys($this->configuration['appKey'], $this->configuration['appSecret']);
			$settings = $this->configuration;
			$requestToken = $this->oAuth->getRequestToken();
			// to be compatible with original oAuth-PEAR package we rename the keys here
			$settings['requestToken']['token'] = $requestToken['oauth_token'];
			$settings['requestToken']['token_secret'] = $requestToken['oauth_token_secret'];
			$this->registry->set('fal_dropbox', 'settings', $settings);
		}
		return $this->renderRequestToken($settings['requestToken']);
	}

	/**
	 * render array as table
	 *
	 * @param array $requestToken
	 * @return string
	 */
	public function renderRequestToken($requestToken) {
		if (!is_array($requestToken)) return '';

		$content = '';
		$table = '<table>|</table>';
		$tableRow = '<tr>|</tr>';
		$tableCol = '<td>|</td>';

		foreach ($requestToken as $key => $value) {
			$key = $this->contentObject->wrap($key, $tableCol);
			$value = $this->contentObject->wrap($value, $tableCol);
			$content .= $this->contentObject->wrap($key . $value, $tableRow);
		}

		return $this->contentObject->wrap($content, $table);
	}

	/**
	 * clear cache
	 *
	 * @param array $parentArray
	 * @param TYPO3\CMS\Backend\Form\FormEngine $formEngine
	 * @return string
	 */
	public function clearCache($parentArray, TYPO3\CMS\Backend\Form\FormEngine &$formEngine) {
		$this->initialize($parentArray);
		if (!$this->checkConfiguration()) {
			return $this->error;
		}
		$url = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . 'index.php?eID=falDropboxClearRegistry';
		$formEngine->additionalJS_post[] = '
			TYPO3.jQuery( "#falDropboxClearCache" ).click( function() {
				TYPO3.jQuery.ajax({
					type: "POST",
					url: "' . $url . '",
					success: function() {
						TYPO3.jQuery( "#falDropboxClearCache" ).css( "background", "#00FF00" );
						TYPO3.jQuery( "#falDropboxClearCache" ).text( "Cache was cleared, please save or reload this frame to generate new tokens" );
					}
				});
			});
		';
		return '
			<span id="falDropboxClearCache" style="cursor: pointer; border: 1px solid #000000; padding: 5px 10px; background: #FF7777;">Click to clear Cache</div>
		';
	}
}
?>