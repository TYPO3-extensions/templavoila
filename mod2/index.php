<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

unset($MCONF);
require(dirname(__FILE__) . '/conf.php');
// require($BACK_PATH . 'init.php');
$GLOBALS['LANG']->includeLLFile('EXT:templavoila/mod2/locallang.xlf');
$GLOBALS['BE_USER']->modAccess($MCONF, 1); // This checks permissions and exits if the users has no permission for entry.

/**
 * Module 'TemplaVoila' for the 'templavoila' extension.
 *
 * @author Kasper Skaarhoj <kasper@typo3.com>
 */
class tx_templavoila_module2 extends \TYPO3\CMS\Backend\Module\BaseScriptClass {

	/**
	 * @var array
	 */
	protected $pidCache;

	/**
	 * @var string
	 */
	protected $backPath;

	/**
	 * Import as first page in root!
	 *
	 * @var integer
	 */
	public $importPageUid = 0;

	/**
	 * Session data during wizard
	 *
	 * @var array
	 */
	public $wizardData = array();

	/**
	 * @var array
	 */
	public $pageinfo;

	/**
	 * @var array
	 */
	public $modTSconfig;

	/**
	 * Extension key of this module
	 *
	 * @var string
	 */
	public $extKey = 'templavoila';

	/**
	 * @var array
	 */
	public $tFileList = array();

	/**
	 * @var array
	 */
	public $errorsWarnings = array();

	/**
	 * holds the extconf configuration
	 *
	 * @var array
	 */
	public $extConf;

	/**
	 * @var string
	 */
	public $cm1Link = '../cm1/index.php';

	/**
	 * @return void
	 */
	public function init() {
		parent::init();

		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);
	}

	/**
	 * Preparing menu content
	 *
	 * @return void
	 */
	public function menuConfig() {
		$this->MOD_MENU = array(
			'set_details' => '',
			'set_unusedDs' => '',
			'wiz_step' => ''
		);

		// page/be_user TSconfig settings and blinding of menu-items
		$this->modTSconfig = \TYPO3\CMS\Backend\Utility\BackendUtility::getModTSconfig($this->id, 'mod.' . $this->MCONF['name']);

		// CLEANSE SETTINGS
		$this->MOD_SETTINGS = \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData($this->MOD_MENU, \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('SET'), $this->MCONF['name']);
	}

	/**
	 * Main function of the module.
	 *
	 * @return void
	 */
	public function main() {
		global $BACK_PATH;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		$this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->docType = 'xhtml_trans';
		$this->doc->backPath = $BACK_PATH;
		$this->doc->setModuleTemplate('EXT:templavoila/Resources/Private/Templates/mod2_default.html');
		$this->doc->bodyTagId = 'typo3-mod-php';
		$this->doc->divClass = '';
		$this->doc->form = '<form action="' . htmlspecialchars('index.php?id=' . $this->id) . '" method="post" autocomplete="off">';

		if ($access) {
			// Draw the header.

			// Add custom styles
			$this->doc->styleSheetFile2 = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($this->extKey) . "mod2/styles.css";

			// Adding classic jumpToUrl function, needed for the function menu.
			// Also, the id in the parent frameset is configured.
			$this->doc->JScode = $this->doc->wrapScriptTags('
				function jumpToUrl(URL)	{ //
					document.location = URL;
					return false;
				}
				function setHighlight(id)	{	//
					if (top.fsMod) {
						top.fsMod.recentIds["web"]=id;
						top.fsMod.navFrameHighlightedID["web"]="pages"+id+"_"+top.fsMod.currentBank;	// For highlighting

						if (top.content && top.content.nav_frame && top.content.nav_frame.refresh_nav)	{
							top.content.nav_frame.refresh_nav();
						}
					}
				}
			');

			$this->doc->loadJavascriptLib('sysext/backend/Resources/Public/JavaScript/tabmenu.js');

			$this->renderModuleContent();

			// Setting up support for context menus (when clicking the items icon)
			$CMparts = $this->doc->getContextMenuCode();
			$this->doc->bodyTagAdditions = $CMparts[1];
			$this->doc->JScode .= $CMparts[0];
			$this->doc->postCode .= $CMparts[2];
		} else {
			$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				\TYPO3\CMS\Core\Messaging\FlashMessage::class,
				\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noaccess'),
				'',
				\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
			);
			$this->content = $flashMessage->render();
		}
		// Place content inside template
		$content = $this->doc->startPage(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('title'));
		$content .= $this->doc->moduleBody(
			$this->pageinfo,
			$this->getDocHeaderButtons(),
			array('CONTENT' => $this->content)
		);
		$content .= $this->doc->endPage();

		// Replace content with templated content
		$this->content = $content;
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/**
	 * Gets the buttons that shall be rendered in the docHeader.
	 *
	 * @return array Available buttons for the docHeader
	 */
	protected function getDocHeaderButtons() {
		$buttons = array(
			'csh' => \TYPO3\CMS\Backend\Utility\BackendUtility::cshItem('_MOD_web_txtemplavoilaM2', '', $this->backPath),
			'shortcut' => $this->getShortcutButton(),
		);

		return $buttons;
	}

	/**
	 * Gets the button to set a new shortcut in the backend (if current user is allowed to).
	 *
	 * @return string HTML representiation of the shortcut button
	 */
	protected function getShortcutButton() {
		$result = '';
		if (\Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->mayMakeShortcut()) {
			$result = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
		}

		return $result;
	}

	/******************************
	 *
	 * Rendering module content:
	 *
	 *******************************/

	/**
	 * Renders module content:
	 *
	 * @return void
	 */
	public function renderModuleContent() {

		if ($this->MOD_SETTINGS['wiz_step']) { // Run wizard instead of showing overview.
			$this->renderNewSiteWizard_run();
		} else {

			// Select all Data Structures in the PID and put into an array:
			$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
				'count(*)',
				'tx_templavoila_datastructure',
				'pid=' . (int)$this->id . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tx_templavoila_datastructure')
			);
			list($countDS) = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_row($res);
			\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);

			// Select all Template Records in PID:
			$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
				'count(*)',
				'tx_templavoila_tmplobj',
				'pid=' . (int)$this->id . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tx_templavoila_tmplobj')
			);
			list($countTO) = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_row($res);
			\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);

			// If there are TO/DS, render the module as usual, otherwise do something else...:
			if ($countTO || $countDS) {
				$this->renderModuleContent_mainView();
			} else {
				$this->renderModuleContent_searchForTODS();
				$this->renderNewSiteWizard_overview();
			}
		}
	}

	/**
	 * Renders module content, overview of pages with DS/TO on.
	 *
	 * @return void
	 */
	public function renderModuleContent_searchForTODS() {
		$dsRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\DataStructureRepository::class);
		$toRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\TemplateRepository::class);
		$list = $toRepo->getTemplateStoragePids();

		// Traverse the pages found and list in a table:
		$tRows = array();
		$tRows[] = '
			<tr class="bgColor5 tableheader">
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('storagefolders', TRUE) . '</td>
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('datastructures', TRUE) . '</td>
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('templateobjects', TRUE) . '</td>
			</tr>';

		if (is_array($list)) {
			foreach ($list as $pid) {
				$path = $this->findRecordsWhereUsed_pid($pid);
				if ($path) {
					$tRows[] = '
						<tr class="bgColor4">
							<td><a href="index.php?id=' . $pid . '" onclick="setHighlight(' . $pid . ')">' .
						\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('pages', \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('pages', $pid)) .
						htmlspecialchars($path) . '</a></td>
							<td>' . $dsRepo->getDatastructureCountForPid($pid) . '</td>
							<td>' . $toRepo->getTemplateCountForPid($pid) . '</td>
						</tr>';
				}
			}

			// Create overview
			$outputString = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('description_pagesWithCertainDsTo');
			$outputString .= '<br /><table border="0" cellpadding="1" cellspacing="1" class="typo3-dblist">' . implode('', $tRows) . '</table>';

			// Add output:
			$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('title'), $outputString, 0, 1);
		}
	}

	/**
	 * Renders module content main view:
	 *
	 * @return void
	 */
	public function renderModuleContent_mainView() {
		// Traverse scopes of data structures display template records belonging to them:
		// Each scope is places in its own tab in the tab menu:
		$dsScopes = array(
			\Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_PAGE,
			\Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_FCE,
			\Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_UNKNOWN
		);

		$toIdArray = $parts = array();
		foreach ($dsScopes as $scopePointer) {

			// Create listing for a DS:
			list($content, $dsCount, $toCount, $toIdArrayTmp) = $this->renderDSlisting($scopePointer);
			$toIdArray = array_merge($toIdArrayTmp, $toIdArray);
			$scopeIcon = '';

			// Label for the tab:
			switch ((string) $scopePointer) {
				case \Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_PAGE:
					$label = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('pagetemplates');
					$scopeIcon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('pages', array());
					break;
				case \Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_FCE:
					$label = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('fces');
					$scopeIcon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('tt_content', array());
					break;
				case \Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_UNKNOWN:
					$label = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('other');
					break;
				default:
					$label = sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('unknown'), $scopePointer);
					break;
			}

			// Error/Warning log:
			$errStat = $this->getErrorLog($scopePointer);

			// Add parts for Tab menu:
			$parts[] = array(
				'label' => $label,
				'icon' => $scopeIcon,
				'content' => $content,
				'linkTitle' => 'DS/TO = ' . $dsCount . '/' . $toCount,
				'stateIcon' => $errStat['iconCode']
			);
		}

		// Find lost Template Objects and add them to a TAB if any are found:
		$lostTOs = '';
		$lostTOCount = 0;

		$toRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\TemplateRepository::class);
		$toList = $toRepo->getAll($this->id);
		foreach ($toList as $toObj) {
			/** @var \Extension\Templavoila\Domain\Model\Template $toObj */
			if (!in_array($toObj->getKey(), $toIdArray)) {
				$rTODres = $this->renderTODisplay($toObj, -1, 1);
				$lostTOs .= $rTODres['HTML'];
				$lostTOCount++;
			}
		}
		if ($lostTOs) {
			// Add parts for Tab menu:
			$parts[] = array(
				'label' => sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('losttos', TRUE), $lostTOCount),
				'content' => $lostTOs
			);
		}

		// Complete Template File List
		$parts[] = array(
			'label' => \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('templatefiles', TRUE),
			'content' => $this->completeTemplateFileList()
		);

		// Errors:
		if (FALSE !== ($errStat = $this->getErrorLog('_ALL'))) {
			$parts[] = array(
				'label' => 'Errors (' . $errStat['count'] . ')',
				'content' => $errStat['content'],
				'stateIcon' => $errStat['iconCode']
			);
		}

		$showDetails = sprintf(' <label for="set_details">%s</label> &nbsp;&nbsp;&nbsp;', \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('showdetails', TRUE));
		$showUnused = sprintf(' <label for="set_details">%s</label> &nbsp;&nbsp;&nbsp;', \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('showuused', TRUE));

		// Create setting handlers:
		$settings = '<p>' .
			\TYPO3\CMS\Backend\Utility\BackendUtility::getFuncCheck('', 'SET[set_details]', $this->MOD_SETTINGS['set_details'], 'index.php', \TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $_GET, '', 1, 1), 'id="set_details"') . $showDetails .
			\TYPO3\CMS\Backend\Utility\BackendUtility::getFuncCheck('', 'SET[set_unusedDs]', $this->MOD_SETTINGS['set_unusedDs'], 'index.php', \TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $_GET, '', 1, 1), 'id="set_unusedDs"') . $showUnused .
			'</p>';

		// Add output:
		$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('title'),
			$settings .
			$this->doc->getDynTabMenu($parts, 'TEMPLAVOILA:templateOverviewModule:' . $this->id, 0, 0, 300)
			, 0, 1);
	}

	/**
	 * Renders Data Structures from $dsScopeArray
	 *
	 * @param integer $scope
	 *
	 * @return array Returns array with three elements: 0: content, 1: number of DS shown, 2: number of root-level template objects shown.
	 */
	public function renderDSlisting($scope) {

		$currentPid = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id');
		/** @var \Extension\Templavoila\Domain\Repository\DataStructureRepository $dsRepo */
		$dsRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\DataStructureRepository::class);
		/** @var \Extension\Templavoila\Domain\Repository\TemplateRepository $toRepo */
		$toRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\TemplateRepository::class);

		if ($this->MOD_SETTINGS['set_unusedDs']) {
			$dsList = $dsRepo->getDatastructuresByScope($scope);
		} else {
			$dsList = $dsRepo->getDatastructuresByStoragePidAndScope($currentPid, $scope);
		}

		$dsCount = 0;
		$toCount = 0;
		$content = '';
		$index = '';
		$toIdArray = array(-1);

		// Traverse data structures to list:
		if (count($dsList)) {
			foreach ($dsList as $dsObj) {
				/** @var \Extension\Templavoila\Domain\Model\AbstractDataStructure $dsObj */

				// Traverse template objects which are not children of anything:
				$TOcontent = '';
				$indexTO = '';

				$toList = $toRepo->getTemplatesByDatastructure($dsObj, $currentPid);

				$newPid = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id');
				$newFileRef = '';
				$newTitle = $dsObj->getLabel() . ' [TEMPLATE]';
				if (count($toList)) {
					foreach ($toList as $toObj) {
						/** @var \Extension\Templavoila\Domain\Model\Template $toObj */
						$toIdArray[] = $toObj->getKey();
						if ($toObj->hasParentTemplate()) {
							continue;
						}
						$rTODres = $this->renderTODisplay($toObj, $scope);
						$TOcontent .= '<a name="to-' . $toObj->getKey() . '"></a>' . $rTODres['HTML'];
						$indexTO .= '
							<tr class="bgColor4">
								<td>&nbsp;&nbsp;&nbsp;</td>
								<td><a href="#to-' . $toObj->getKey() . '">' . htmlspecialchars($toObj->getLabel()) . $toObj->hasParentTemplate() . '</a></td>
								<td>&nbsp;</td>
								<td>&nbsp;</td>
								<td align="center">' . $rTODres['mappingStatus'] . '</td>
								<td align="center">' . $rTODres['usage'] . '</td>
							</tr>';
						$toCount++;

						$newPid = -$toObj->getKey();
						$newFileRef = $toObj->getFileref();
						$newTitle = $toObj->getLabel() . ' [ALT]';
					}
				}
				// New-TO link:
				$TOcontent .= '<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick(
						'&edit[tx_templavoila_tmplobj][' . $newPid . ']=new' .
						'&defVals[tx_templavoila_tmplobj][datastructure]=' . rawurlencode($dsObj->getKey()) .
						'&defVals[tx_templavoila_tmplobj][title]=' . rawurlencode($newTitle) .
						'&defVals[tx_templavoila_tmplobj][fileref]=' . rawurlencode($newFileRef)
						, $this->doc->backPath)) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-new') . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('createnewto', TRUE) . '</a>';

				// Render data structure display
				$rDSDres = $this->renderDataStructureDisplay($dsObj, $scope, $toIdArray);
				$content .= '<a name="ds-' . md5($dsObj->getKey()) . '"></a>' . $rDSDres['HTML'];
				$index .= '
					<tr class="bgColor4-20">
						<td colspan="2"><a href="#ds-' . md5($dsObj->getKey()) . '">' . htmlspecialchars($dsObj->getLabel()) . '</a></td>
						<td align="center">' . $rDSDres['languageMode'] . '</td>
						<td>' . $rDSDres['container'] . '</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					</tr>';
				if ($indexTO) {
					$index .= $indexTO;
				}
				$dsCount++;

				// Wrap TO elements in a div-tag and add to content:
				if ($TOcontent) {
					$content .= '<div style="margin-left: 102px;">' . $TOcontent . '</div>';
				}
			}
		}

		if ($index) {
			$content = '<h4>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('overview', TRUE) . '</h4>
						<table border="0" cellpadding="0" cellspacing="1">
							<tr class="bgColor5 tableheader">
								<td colspan="2">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dstotitle', TRUE) . '</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('localization', TRUE) . '</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('containerstatus', TRUE) . '</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('mappingstatus', TRUE) . '</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usagecount', TRUE) . '</td>
							</tr>
						' . $index . '
						</table>' .
				$content;
		}

		return array($content, $dsCount, $toCount, $toIdArray);
	}

	/**
	 * Rendering a single data structures information
	 *
	 * @param \Extension\Templavoila\Domain\Model\AbstractDataStructure $dsObj Structure information
	 * @param integer $scope Scope.
	 * @param array $toIdArray
	 *
	 * @return string HTML content
	 */
	public function renderDataStructureDisplay(\Extension\Templavoila\Domain\Model\AbstractDataStructure $dsObj, $scope, $toIdArray) {

		$tableAttribs = ' border="0" cellpadding="1" cellspacing="1" width="98%" style="margin-top: 10px;" class="lrPadding"';

		$XMLinfo = array();
		if ($this->MOD_SETTINGS['set_details']) {
			$XMLinfo = $this->DSdetails($dsObj->getDataprotXML());
		}

		if ($dsObj->isFilebased()) {
			$onClick = 'document.location=\'' . $this->doc->backPath . 'file_edit.php?target=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($dsObj->getKey())) . '&returnUrl=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::sanitizeLocalUrl(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REQUEST_URI'))) . '\';';
			$dsIcon = '<a href="#" onclick="' . htmlspecialchars($onClick) . '"><img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($this->doc->backPath, 'gfx/fileicons/xml.gif', 'width="18" height="16"') . ' alt="" title="' . $dsObj->getKey() . '" class="absmiddle" /></a>';
		} else {
			$dsIcon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('tx_templavoila_datastructure', array(), array('title' => $dsObj->getKey()));
			$dsIcon = $this->doc->wrapClickMenuOnIcon($dsIcon, 'tx_templavoila_datastructure', $dsObj->getKey(), 1, '&callingScriptId=' . rawurlencode($this->doc->scriptID));
		}

		// Preview icon:
		if ($dsObj->getIcon()) {
			if (isset($this->modTSconfig['properties']['dsPreviewIconThumb']) && $this->modTSconfig['properties']['dsPreviewIconThumb'] != '0') {
				$path = realpath(dirname(__FILE__) . '/' . preg_replace('/\w+\/\.\.\//', '', $GLOBALS['BACK_PATH'] . $dsObj->getIcon()));
				$path = str_replace(realpath(PATH_site) . '/', PATH_site, $path);
				if ($path == FALSE) {
					$previewIcon = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noicon', TRUE);
				} else {
					$previewIcon = \TYPO3\CMS\Backend\Utility\BackendUtility::getThumbNail($this->doc->backPath . 'thumbs.php', $path,
						'hspace="5" vspace="5" border="1"',
						strpos($this->modTSconfig['properties']['dsPreviewIconThumb'], 'x') ? $this->modTSconfig['properties']['dsPreviewIconThumb'] : '');
				}
			} else {
				$previewIcon = '<img src="' . $this->doc->backPath . $dsObj->getIcon() . '" alt="" />';
			}
		} else {
			$previewIcon = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noicon', TRUE);
		}

		// Links:
		$lpXML = '';
		if ($dsObj->isFilebased()) {
			$editLink = $editDataprotLink = '';
			$dsTitle = $dsObj->getLabel();
		} else {
			$editLink = $lpXML .= '<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[tx_templavoila_datastructure][' . $dsObj->getKey() . ']=edit', $this->doc->backPath)) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-open') . '</a>';
			$dsTitle = '<a href="' . htmlspecialchars('../cm1/index.php?table=tx_templavoila_datastructure&uid=' . $dsObj->getKey() . '&id=' . $this->id . '&returnUrl=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::sanitizeLocalUrl(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REQUEST_URI')))) . '">' . htmlspecialchars($dsObj->getLabel()) . '</a>';
		}

		// Compile info table:
		$content = '
		<table' . $tableAttribs . '>
			<tr class="bgColor5">
				<td colspan="3" style="border-top: 1px solid black;">' .
			$dsIcon .
			$dsTitle .
			$editLink .
			'</td>
	</tr>
	<tr class="bgColor4">
		<td rowspan="' . ($this->MOD_SETTINGS['set_details'] ? 4 : 2) . '" style="width: 100px; text-align: center;">' . $previewIcon . '</td>
				' .
			($this->MOD_SETTINGS['set_details'] ? '<td style="width:200px">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('templatestatus', TRUE) . '</td>
				<td>' . $this->findDSUsageWithImproperTOs($dsObj, $scope, $toIdArray) . '</td>' : '') .
			'</tr>
			<tr class="bgColor4">
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('globalprocessing_xml') . '</td>
				<td>
					' . $lpXML . ($dsObj->getDataprotXML() ?
				\TYPO3\CMS\Core\Utility\GeneralUtility::formatSize(strlen($dsObj->getDataprotXML())) . ' bytes' .
				($this->MOD_SETTINGS['set_details'] ? '<hr/>' . $XMLinfo['HTML'] : '') : '') . '
				</td>
			</tr>' . ($this->MOD_SETTINGS['set_details'] ? '
			<tr class="bgColor4">
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('created', TRUE) . '</td>
				<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($dsObj->getCrdate()) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('byuser', TRUE) . ' [' . $dsObj->getCruser() . ']</td>
			</tr>
			<tr class="bgColor4">
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('updated', TRUE) . '</td>
				<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($dsObj->getTstamp()) . '</td>
			</tr>' : '') . '
		</table>
		';

		// Format XML if requested (renders VERY VERY slow)
		if ($this->MOD_SETTINGS['set_showDSxml']) {
			if ($dsObj->getDataprotXML()) {
				$hlObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Service\SyntaxHighlightingService::class);
				$content .= '<pre>' . str_replace(chr(9), '&nbsp;&nbsp;&nbsp;', $hlObj->highLight_DS($dsObj->getDataprotXML())) . '</pre>';
			}
		}

		$containerMode = '';
		if ($this->MOD_SETTINGS['set_details']) {
			if ($XMLinfo['referenceFields']) {
				$containerMode = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('yes', TRUE);
				if ($XMLinfo['languageMode'] === 'Separate') {
					$containerMode .= ' ' . $this->doc->icons(3) . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('containerwithseparatelocalization', TRUE);
				} elseif ($XMLinfo['languageMode'] === 'Inheritance') {
					$containerMode .= ' ' . $this->doc->icons(2);
					if ($XMLinfo['inputFields']) {
						$containerMode .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('mixofcontentandref', TRUE);
					} else {
						$containerMode .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('nocontentfields', TRUE);
					}
				}
			} else {
				$containerMode = 'No';
			}

			$containerMode .= ' (ARI=' . $XMLinfo['rootelements'] . '/' . $XMLinfo['referenceFields'] . '/' . $XMLinfo['inputFields'] . ')';
		}

		// Return content
		return array(
			'HTML' => $content,
			'languageMode' => $XMLinfo['languageMode'],
			'container' => $containerMode
		);
	}

	/**
	 * Render display of a Template Object
	 *
	 * @param \Extension\Templavoila\Domain\Model\Template $toObj Template Object record to render
	 * @param integer $scope Scope of DS
	 * @param integer $children If set, the function is asked to render children to template objects (and should not call it self recursively again).
	 *
	 * @return string HTML content
	 */
	public function renderTODisplay($toObj, $scope, $children = 0) {

		// Put together the records icon including content sensitive menu link wrapped around it:
		$recordIcon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord('tx_templavoila_tmplobj', array(), array('title' => $toObj->getKey()));
		$recordIcon = $this->doc->wrapClickMenuOnIcon($recordIcon, 'tx_templavoila_tmplobj', $toObj->getKey(), 1, '&callingScriptId=' . rawurlencode($this->doc->scriptID));

		// Preview icon:
		if ($toObj->getIcon()) {
			if (isset($this->modTSconfig['properties']['toPreviewIconThumb']) && $this->modTSconfig['properties']['toPreviewIconThumb'] != '0') {
				$path = realpath(dirname(__FILE__) . '/' . preg_replace('/\w+\/\.\.\//', '', $GLOBALS['BACK_PATH'] . $toObj->getIcon()));
				$path = str_replace(realpath(PATH_site) . '/', PATH_site, $path);
				if ($path == FALSE) {
					$icon = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noicon', TRUE);
				} else {
					$icon = \TYPO3\CMS\Backend\Utility\BackendUtility::getThumbNail($this->doc->backPath . 'thumbs.php', $path,
						'hspace="5" vspace="5" border="1"',
						strpos($this->modTSconfig['properties']['toPreviewIconThumb'], 'x') ? $this->modTSconfig['properties']['toPreviewIconThumb'] : '');
				}
			} else {
				$icon = '<img src="' . $this->doc->backPath . $toObj->getIcon() . '" alt="" />';
			}
		} else {
			$icon = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noicon', TRUE);
		}

		// Mapping status / link:
		$linkUrl = '../cm1/index.php?table=tx_templavoila_tmplobj&uid=' . $toObj->getKey() . '&_reload_from=1&id=' . $this->id . '&returnUrl=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REQUEST_URI'));

		$fileReference = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($toObj->getFileref());
		if (@is_file($fileReference)) {
			$this->tFileList[$fileReference]++;
			$fileRef = '<a href="' . htmlspecialchars($this->doc->backPath . '../' . substr($fileReference, strlen(PATH_site))) . '" target="_blank">' . htmlspecialchars($toObj->getFileref()) . '</a>';
			$fileMsg = '';
			$fileMtime = filemtime($fileReference);
		} else {
			$fileRef = htmlspecialchars($toObj->getFileref());
			$fileMsg = '<div class="typo3-red">ERROR: File not found</div>';
			$fileMtime = 0;
		}

		$mappingStatus_index = '';
		if ($fileMtime && $toObj->getFilerefMtime()) {
			if ($toObj->getFilerefMD5() != '') {
				$modified = (@md5_file($fileReference) != $toObj->getFilerefMD5());
			} else {
				$modified = ($toObj->getFilerefMtime() != $fileMtime);
			}
			if ($modified) {
				$mappingStatus = $mappingStatus_index = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-warning');
				$mappingStatus .= sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('towasupdated', TRUE), \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($toObj->getTstamp()));
				$this->setErrorLog($scope, 'warning', sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('warning_mappingstatus', TRUE), $mappingStatus, $toObj->getLabel()));
			} else {
				$mappingStatus = $mappingStatus_index = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok');
				$mappingStatus .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('mapping_uptodate', TRUE);
			}
			$mappingStatus .= '<br/><input type="button" onclick="jumpToUrl(\'' . htmlspecialchars($linkUrl) . '\');" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('update_mapping', TRUE) . '" />';
		} elseif (!$fileMtime) {
			$mappingStatus = $mappingStatus_index = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error');
			$mappingStatus .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('notmapped', TRUE);
			$this->setErrorLog($scope, 'fatal', sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('warning_mappingstatus', TRUE), $mappingStatus, $toObj->getLabel()));

			$mappingStatus .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('updatemapping_info');
			$mappingStatus .= '<br/><input type="button" onclick="jumpToUrl(\'' . htmlspecialchars($linkUrl) . '\');" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('map', TRUE) . '" />';
		} else {
			$mappingStatus = '';
			$mappingStatus .= '<input type="button" onclick="jumpToUrl(\'' . htmlspecialchars($linkUrl) . '\');" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('remap', TRUE) . '" />';
			$mappingStatus .= '&nbsp;<input type="button" onclick="jumpToUrl(\'' . htmlspecialchars($linkUrl . '&_preview=1') . '\');" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('preview', TRUE) . '" />';
		}

		if ($this->MOD_SETTINGS['set_details']) {
			$XMLinfo = $this->DSdetails($toObj->getLocalDataprotXML(TRUE));
		} else {
			$XMLinfo = array('HTML' => '');
		}

		// Format XML if requested
		$lpXML = '';
		if ($this->MOD_SETTINGS['set_details']) {
			if ($toObj->getLocalDataprotXML(TRUE)) {
				$hlObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Service\SyntaxHighlightingService::class);
				$lpXML = '<pre>' . str_replace(chr(9), '&nbsp;&nbsp;&nbsp;', $hlObj->highLight_DS($toObj->getLocalDataprotXML(TRUE))) . '</pre>';
			}
		}
		$lpXML .= '<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[tx_templavoila_tmplobj][' . $toObj->getKey() . ']=edit&columnsOnly=localprocessing', $this->doc->backPath)) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-open') . '</a>';

		// Compile info table:
		$tableAttribs = ' border="0" cellpadding="1" cellspacing="1" width="98%" style="margin-top: 3px;" class="lrPadding"';

		// Links:
		$toTitle = '<a href="' . htmlspecialchars($linkUrl) . '">' . htmlspecialchars(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->sL($toObj->getLabel())) . '</a>';
		$editLink = '<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[tx_templavoila_tmplobj][' . $toObj->getKey() . ']=edit', $this->doc->backPath)) . '">' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-open') . '</a>';

		$fRWTOUres = array();

		if (!$children) {
			if ($this->MOD_SETTINGS['set_details']) {
				$fRWTOUres = $this->findRecordsWhereTOUsed($toObj, $scope);
			}

			$content = '
			<table' . $tableAttribs . '>
				<tr class="bgColor4-20">
					<td colspan="3">' .
				$recordIcon .
				$toTitle .
				$editLink .
				'</td>
		</tr>
		<tr class="bgColor4">
			<td rowspan="' . ($this->MOD_SETTINGS['set_details'] ? 7 : 4) . '" style="width: 100px; text-align: center;">' . $icon . '</td>
					<td style="width:200px;">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('filereference', TRUE) . ':</td>
					<td>' . $fileRef . $fileMsg . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('description', TRUE) . ':</td>
					<td>' . htmlspecialchars($toObj->getDescription()) . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('mappingstatus', TRUE) . ':</td>
					<td>' . $mappingStatus . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('localprocessing_xml') . ':</td>
					<td>
						' . $lpXML . ($toObj->getLocalDataprotXML(TRUE) ?
					\TYPO3\CMS\Core\Utility\GeneralUtility::formatSize(strlen($toObj->getLocalDataprotXML(TRUE))) . ' bytes' .
					($this->MOD_SETTINGS['set_details'] ? '<hr/>' . $XMLinfo['HTML'] : '') : '') . '
					</td>
				</tr>' . ($this->MOD_SETTINGS['set_details'] ? '
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usedby', TRUE) . ':</td>
					<td>' . $fRWTOUres['HTML'] . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('created', TRUE) . ':</td>
					<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($toObj->getCrdate()) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('byuser', TRUE) . ' [' . $toObj->getCruser() . ']</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('updated', TRUE) . ':</td>
					<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($toObj->getTstamp()) . '</td>
				</tr>' : '') . '
			</table>
			';
		} else {
			$content = '
			<table' . $tableAttribs . '>
				<tr class="bgColor4-20">
					<td colspan="3">' .
				$recordIcon .
				$toTitle .
				$editLink .
				'</td>
		</tr>
		<tr class="bgColor4">
			<td style="width:200px;">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('filereference', TRUE) . ':</td>
					<td>' . $fileRef . $fileMsg . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('mappingstatus', TRUE) . ':</td>
					<td>' . $mappingStatus . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('rendertype', TRUE) . ':</td>
					<td>' . $this->getProcessedValue('tx_templavoila_tmplobj', 'rendertype', $toObj->getRendertype()) . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('language', TRUE) . ':</td>
					<td>' . $this->getProcessedValue('tx_templavoila_tmplobj', 'sys_language_uid', $toObj->getSyslang()) . '</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('localprocessing_xml') . ':</td>
					<td>
						' . $lpXML . ($toObj->getLocalDataprotXML(TRUE) ?
					\TYPO3\CMS\Core\Utility\GeneralUtility::formatSize(strlen($toObj->getLocalDataprotXML(TRUE))) . ' bytes' .
					($this->MOD_SETTINGS['set_details'] ? '<hr/>' . $XMLinfo['HTML'] : '') : '') . '
					</td>
				</tr>' . ($this->MOD_SETTINGS['set_details'] ? '
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('created', TRUE) . ':</td>
					<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($toObj->getCrdate()) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('byuser', TRUE) . ' [' . $toObj->getCruser() . ']</td>
				</tr>
				<tr class="bgColor4">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('updated', TRUE) . ':</td>
					<td>' . \TYPO3\CMS\Backend\Utility\BackendUtility::datetime($toObj->getTstamp()) . '</td>
				</tr>' : '') . '
			</table>
			';
		}

		// Traverse template objects which are not children of anything:
		$toRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Extension\Templavoila\Domain\Repository\TemplateRepository::class);
		$toChildren = $toRepo->getTemplatesByParentTemplate($toObj);

		if (!$children && count($toChildren)) {
			$TOchildrenContent = '';
			foreach ($toChildren as $toChild) {
				$rTODres = $this->renderTODisplay($toChild, $scope, 1);
				$TOchildrenContent .= $rTODres['HTML'];
			}
			$content .= '<div style="margin-left: 102px;">' . $TOchildrenContent . '</div>';
		}

		// Return content
		return array('HTML' => $content, 'mappingStatus' => $mappingStatus_index, 'usage' => $fRWTOUres['usage']);
	}

	/**
	 * Creates listings of pages / content elements where template objects are used.
	 *
	 * @param \Extension\Templavoila\Domain\Model\Template $toObj Template Object record
	 * @param integer $scope Scope value. 1) page,  2) content elements
	 *
	 * @return string HTML table listing usages.
	 */
	public function findRecordsWhereTOUsed($toObj, $scope) {

		$output = array();

		switch ($scope) {
			case 1: // PAGES:
				// Header:
				$output[] = '
							<tr class="bgColor5 tableheader">
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_pid', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_title', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_path', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_workspace', TRUE) . ':</td>
							</tr>';

				// Main templates:
				$dsKey = $toObj->getDatastructure()->getKey();
				$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
					'uid,title,pid,t3ver_wsid,t3ver_id',
					'pages',
					'(
						(tx_templavoila_to=' . (int)$toObj->getKey() . ' AND tx_templavoila_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($dsKey, 'pages') . ') OR
						(tx_templavoila_next_to=' . (int)$toObj->getKey() . ' AND tx_templavoila_next_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($dsKey, 'pages') . ')
					)' .
					\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('pages')
				);

				while (FALSE !== ($pRow = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_assoc($res))) {
					$path = $this->findRecordsWhereUsed_pid($pRow['uid']);
					if ($path) {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[pages][' . $pRow['uid'] . ']=edit', $this->doc->backPath)) . '" title="Edit">' .
							htmlspecialchars($pRow['uid']) .
							'</a></td>
						<td nowrap="nowrap">' .
							htmlspecialchars($pRow['title']) .
							'</td>
						<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($pRow['uid'], $this->doc->backPath) . 'return false;') . '" title="View">' .
							htmlspecialchars($path) .
							'</a></td>
						<td nowrap="nowrap">' .
							htmlspecialchars($pRow['pid'] == -1 ? 'Offline version 1.' . $pRow['t3ver_id'] . ', WS: ' . $pRow['t3ver_wsid'] : 'LIVE!') .
							'</td>
					</tr>';
					} else {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							htmlspecialchars($pRow['uid']) .
							'</td>
						<td><em>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noaccess', TRUE) . '</em></td>
								<td>-</td>
								<td>-</td>
							</tr>';
					}
				}
				\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);
				break;
			case 2:

				// Select Flexible Content Elements:
				$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
					'uid,header,pid,t3ver_wsid,t3ver_id',
					'tt_content',
					'CType=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr('templavoila_pi1', 'tt_content') .
					' AND tx_templavoila_to=' . (int)$toObj->getKey() .
					' AND tx_templavoila_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($toObj->getDatastructure()->getKey(), 'tt_content') .
					\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
					'',
					'pid'
				);

				// Header:
				$output[] = '
							<tr class="bgColor5 tableheader">
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_uid', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_header', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_path', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_workspace', TRUE) . ':</td>
							</tr>';

				// Elements:
				while (FALSE !== ($pRow = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_assoc($res))) {
					$path = $this->findRecordsWhereUsed_pid($pRow['pid']);
					if ($path) {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[tt_content][' . $pRow['uid'] . ']=edit', $this->doc->backPath)) . '" title="Edit">' .
							htmlspecialchars($pRow['uid']) .
							'</a></td>
						<td nowrap="nowrap">' .
							htmlspecialchars($pRow['header']) .
							'</td>
						<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($pRow['pid'], $this->doc->backPath) . 'return false;') . '" title="View page">' .
							htmlspecialchars($path) .
							'</a></td>
						<td nowrap="nowrap">' .
							htmlspecialchars($pRow['pid'] == -1 ? 'Offline version 1.' . $pRow['t3ver_id'] . ', WS: ' . $pRow['t3ver_wsid'] : 'LIVE!') .
							'</td>
					</tr>';
					} else {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							htmlspecialchars($pRow['uid']) .
							'</td>
						<td><em>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noaccess', TRUE) . '</em></td>
								<td>-</td>
								<td>-</td>
							</tr>';
					}
				}
				\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);
				break;
		}

		// Create final output table:
		$outputString = '';
		if (count($output)) {
			if (count($output) > 1) {
				$outputString = sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_usedin', TRUE), count($output) - 1) . '
					<table border="0" cellspacing="1" cellpadding="1" class="lrPadding">'
					. implode('', $output) . '
				</table>';
			} else {
				$outputString = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-warning') . 'No usage!';
				$this->setErrorLog($scope, 'warning', sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('warning_mappingstatus', TRUE), $outputString, $toObj->getLabel()));
			}
		}

		return array('HTML' => $outputString, 'usage' => count($output) - 1);
	}

	/**
	 * Creates listings of pages / content elements where NO or WRONG template objects are used.
	 *
	 * @param \Extension\Templavoila\Domain\Model\AbstractDataStructure $dsObj Data Structure ID
	 * @param integer $scope Scope value. 1) page,  2) content elements
	 * @param array $toIdArray Array with numerical toIDs. Must be integers and never be empty. You can always put in "-1" as dummy element.
	 *
	 * @return string HTML table listing usages.
	 */
	public function findDSUsageWithImproperTOs($dsObj, $scope, $toIdArray) {

		$output = array();

		switch ($scope) {
			case 1: //
				// Header:
				$output[] = '
							<tr class="bgColor5 tableheader">
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_title', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_path', TRUE) . ':</td>
							</tr>';

				// Main templates:
				$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
					'uid,title,pid',
					'pages',
					'(
						(tx_templavoila_to NOT IN (' . implode(',', $toIdArray) . ') AND tx_templavoila_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($dsObj->getKey(), 'pages') . ') OR
						(tx_templavoila_next_to NOT IN (' . implode(',', $toIdArray) . ') AND tx_templavoila_next_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($dsObj->getKey(), 'pages') . ')
					)' .
					\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('pages')
				);

				while (FALSE !== ($pRow = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_assoc($res))) {
					$path = $this->findRecordsWhereUsed_pid($pRow['uid']);
					if ($path) {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[pages][' . $pRow['uid'] . ']=edit', $this->doc->backPath)) . '">' .
							htmlspecialchars($pRow['title']) .
							'</a></td>
						<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($pRow['uid'], $this->doc->backPath) . 'return false;') . '">' .
							htmlspecialchars($path) .
							'</a></td>
					</tr>';
					} else {
						$output[] = '
							<tr class="bgColor4-20">
								<td><em>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noaccess', TRUE) . '</em></td>
								<td>-</td>
							</tr>';
					}
				}
				\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);
				break;
			case 2:

				// Select Flexible Content Elements:
				$res = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTquery(
					'uid,header,pid',
					'tt_content',
					'CType=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr('templavoila_pi1', 'tt_content') .
					' AND tx_templavoila_to NOT IN (' . implode(',', $toIdArray) . ')' .
					' AND tx_templavoila_ds=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($dsObj->getKey(), 'tt_content') .
					\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
					'',
					'pid'
				);

				// Header:
				$output[] = '
							<tr class="bgColor5 tableheader">
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_header', TRUE) . ':</td>
								<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_path', TRUE) . ':</td>
							</tr>';

				// Elements:
				while (FALSE !== ($pRow = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_fetch_assoc($res))) {
					$path = $this->findRecordsWhereUsed_pid($pRow['pid']);
					if ($path) {
						$output[] = '
							<tr class="bgColor4-20">
								<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick('&edit[tt_content][' . $pRow['uid'] . ']=edit', $this->doc->backPath)) . '" title="Edit">' .
							htmlspecialchars($pRow['header']) .
							'</a></td>
						<td nowrap="nowrap">' .
							'<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($pRow['pid'], $this->doc->backPath) . 'return false;') . '" title="View page">' .
							htmlspecialchars($path) .
							'</a></td>
					</tr>';
					} else {
						$output[] = '
							<tr class="bgColor4-20">
								<td><em>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noaccess', TRUE) . '</em></td>
								<td>-</td>
							</tr>';
					}
				}
				\Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->sql_free_result($res);
				break;
		}

		// Create final output table:
		$outputString = '';
		if (count($output)) {
			if (count($output) > 1) {
				$outputString = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error') .
					sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('invalidtemplatevalues', TRUE), count($output) - 1);
				$this->setErrorLog($scope, 'fatal', $outputString);

				$outputString .= '<table border="0" cellspacing="1" cellpadding="1" class="lrPadding">' . implode('', $output) . '</table>';
			} else {
				$outputString = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok') .
					\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('noerrorsfound', TRUE);
			}
		}

		return $outputString;
	}

	/**
	 * Checks if a PID value is accessible and if so returns the path for the page.
	 * Processing is cached so many calls to the function are OK.
	 *
	 * @param integer $pid Page id for check
	 *
	 * @return string Page path of PID if accessible. otherwise zero.
	 */
	public function findRecordsWhereUsed_pid($pid) {
		if (!isset($this->pidCache[$pid])) {
			$this->pidCache[$pid] = array();

			$pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($pid, $this->perms_clause);
			$this->pidCache[$pid]['path'] = $pageinfo['_thePath'];
		}

		return $this->pidCache[$pid]['path'];
	}

	/**
	 * Creates a list of all template files used in TOs
	 *
	 * @return string HTML table
	 */
	public function completeTemplateFileList() {
		$output = '';
		if (is_array($this->tFileList)) {
			$output = '';

			// USED FILES:
			$tRows = array();
			$tRows[] = '
				<tr class="bgColor5 tableheader">
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('file', TRUE) . '</td>
					<td align="center">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usagecount', TRUE) . '</td>
					<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newdsto', TRUE) . '</td>
				</tr>';

			$i = 0;
			foreach ($this->tFileList as $tFile => $count) {
				$tRows[] = '
					<tr class="' . ($i++ % 2 == 0 ? 'bgColor4' : 'bgColor6') . '">
						<td>' .
					'<a href="' . htmlspecialchars($this->doc->backPath . '../' . substr($tFile, strlen(PATH_site))) . '" target="_blank">' .
					\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-view') . ' ' . htmlspecialchars(substr($tFile, strlen(PATH_site))) .
					'</a></td>
				<td align="center">' . $count . '</td>
						<td>' .
					'<a href="' . htmlspecialchars($this->cm1Link . '?id=' . $this->id . '&file=' . rawurlencode($tFile)) . '&mapElPath=%5BROOT%5D">' .
					\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-new') . ' ' . htmlspecialchars('Create...') .
					'</a></td>
			</tr>';
			}

			if (count($tRows) > 1) {
				$output .= '
				<h3>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usedfiles', TRUE) . ':</h3>
				<table border="0" cellpadding="1" cellspacing="1" class="typo3-dblist">
					' . implode('', $tRows) . '
				</table>
				';
			}

			$files = $this->getTemplateFiles();

			// TEMPLATE ARCHIVE:
			if (count($files)) {

				$tRows = array();
				$tRows[] = '
					<tr class="bgColor5 tableheader">
						<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('file', TRUE) . '</td>
						<td align="center">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usagecount', TRUE) . '</td>
						<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newdsto', TRUE) . '</td>
					</tr>';

				$i = 0;
				foreach ($files as $tFile) {
					$tRows[] = '
						<tr class="' . ($i++ % 2 == 0 ? 'bgColor4' : 'bgColor6') . '">
							<td>' .
						'<a href="' . htmlspecialchars($this->doc->backPath . '../' . substr($tFile, strlen(PATH_site))) . '" target="_blank">' .
						\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-view') . ' ' . htmlspecialchars(substr($tFile, strlen(PATH_site))) .
						'</a></td>
					<td align="center">' . ($this->tFileList[$tFile] ? $this->tFileList[$tFile] : '-') . '</td>
							<td>' .
						'<a href="' . htmlspecialchars($this->cm1Link . '?id=' . $this->id . '&file=' . rawurlencode($tFile)) . '&mapElPath=%5BROOT%5D">' .
						\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-new') . ' ' . htmlspecialchars('Create...') .
						'</a></td>
				</tr>';
				}

				if (count($tRows) > 1) {
					$output .= '
					<h3>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('templatearchive', TRUE) . ':</h3>
					<table border="0" cellpadding="1" cellspacing="1" class="typo3-dblist">
						' . implode('', $tRows) . '
					</table>
					';
				}
			}
		}

		return $output;
	}

	/**
	 * Get the processed value analog to \TYPO3\CMS\Backend\Utility\BackendUtility::getProcessedValue
	 * but take additional TSconfig values into account
	 *
	 * @param string $table
	 * @param string $typeField
	 * @param string $typeValue
	 *
	 * @return string
	 */
	protected function getProcessedValue($table, $typeField, $typeValue) {
		$value = \TYPO3\CMS\Backend\Utility\BackendUtility::getProcessedValue($table, $typeField, $typeValue);
		if (!$value) {
			$TSConfig = \TYPO3\CMS\Backend\Utility\BackendUtility::getPagesTSconfig($this->id);
			if (isset($TSConfig['TCEFORM.'][$table . '.'][$typeField . '.']['addItems.'][$typeValue])) {
				$value = $TSConfig['TCEFORM.'][$table . '.'][$typeField . '.']['addItems.'][$typeValue];
			}
		}

		return $value;
	}

	/**
	 * Stores errors/warnings inside the class.
	 *
	 * @param string $scope Scope string, 1=page, 2=ce, _ALL= all errors
	 * @param string $type "fatal" or "warning"
	 * @param string $HTML HTML content for the error.
	 *
	 * @return void
	 * @see getErrorLog()
	 */
	public function setErrorLog($scope, $type, $HTML) {
		$this->errorsWarnings['_ALL'][$type][] = $this->errorsWarnings[$scope][$type][] = $HTML;
	}

	/**
	 * Returns status for a single scope
	 *
	 * @param string $scope Scope string
	 *
	 * @return array Array with content
	 * @see setErrorLog()
	 */
	public function getErrorLog($scope) {
		$errStat = FALSE;
		if (is_array($this->errorsWarnings[$scope])) {
			$errStat = array();

			if (is_array($this->errorsWarnings[$scope]['warning'])) {
				$errStat['count'] = count($this->errorsWarnings[$scope]['warning']);
				$errStat['content'] = '<h3>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('warnings', TRUE) . '</h3>' . implode('<hr/>', $this->errorsWarnings[$scope]['warning']);
				$errStat['iconCode'] = 2;
			}

			if (is_array($this->errorsWarnings[$scope]['fatal'])) {
				$errStat['count'] = count($this->errorsWarnings[$scope]['fatal']) . ($errStat['count'] ? '/' . $errStat['count'] : '');
				$errStat['content'] .= '<h3>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('fatalerrors', TRUE) . '</h3>' . implode('<hr/>', $this->errorsWarnings[$scope]['fatal']);
				$errStat['iconCode'] = 3;
			}
		}

		return $errStat;
	}

	/**
	 * Shows a graphical summary of a array-tree, which suppose was a XML
	 * (but don't need to). This function works recursively.
	 *
	 * @param array $DStree an array holding the DSs defined structure
	 *
	 * @return string HTML showing an overview of the DS-structure
	 */
	public function renderDSdetails($DStree) {
		$HTML = '';

		if (is_array($DStree) && (count($DStree) > 0)) {
			$HTML .= '<dl class="DS-details">';

			foreach ($DStree as $elm => $def) {
				if (!is_array($def)) {
					$HTML .= '<p>' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error') . sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('invaliddatastructure_xmlbroken', TRUE), $elm) . '</p>';
					break;
				}

				$HTML .= '<dt>';
				$HTML .= ($elm == "meta" ? \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('configuration', TRUE) : $def['tx_templavoila']['title'] . ' (' . $elm . ')');
				$HTML .= '</dt>';
				$HTML .= '<dd>';

				/* this is the configuration-entry ------------------------------ */
				if ($elm == "meta") {
					/* The basic XML-structure of an meta-entry is:
					 *
					 * <meta>
					 * 	<langDisable>		-> no localization
					 * 	<langChildren>		-> no localization for children
					 * 	<sheetSelector>		-> a php-function for selecting "sDef"
					 * </meta>
					 */

					/* it would also be possible to use the 'list-style-image'-property
					 * for the flags, which would be more sensible to IE-bugs though
					 */
					$conf = '';
					if (isset($def['langDisable'])) {
						$conf .= '<li>' .
							(($def['langDisable'] == 1)
								? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
								: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
							) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('fceislocalized', TRUE) . '</li>';
					}
					if (isset($def['langChildren'])) {
						$conf .= '<li>' .
							(($def['langChildren'] == 1)
								? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
								: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
							) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('fceinlineislocalized', TRUE) . '</li>';
					}
					if (isset($def['sheetSelector'])) {
						$conf .= '<li>' .
							(($def['sheetSelector'] != '')
								? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
								: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
							) . ' custom sheet-selector' .
							(($def['sheetSelector'] != '')
								? ' [<em>' . $def['sheetSelector'] . '</em>]'
								: ''
							) . '</li>';
					}

					if ($conf != '') {
						$HTML .= '<ul class="DS-config">' . $conf . '</ul>';
					}
				} /* this a container for repetitive elements --------------------- */
				else if (isset($def['section']) && ($def['section'] == 1)) {
					$HTML .= '<p>[..., ..., ...]</p>';
				} /* this a container for cellections of elements ----------------- */
				else {
					if (isset($def['type']) && ($def['type'] == "array")) {
						$HTML .= '<p>[...]</p>';
					} /* this a regular entry ----------------------------------------- */
					else {
						$tco = TRUE;
						/* The basic XML-structure of an entry is:
						 *
						 * <element>
						 * 	<tx_templavoila>	-> entries with informational character belonging to this entry
						 * 	<TCEforms>		-> entries being used for TCE-construction
						 * 	<type + el + section>	-> subsequent hierarchical construction
						 *	<langOverlayMode>	-> ??? (is it the language-key?)
						 * </element>
						 */
						if (($tv = $def['tx_templavoila'])) {
							/* The basic XML-structure of an tx_templavoila-entry is:
							 *
							 * <tx_templavoila>
							 * 	<title>			-> Human readable title of the element
							 * 	<description>		-> A description explaining the elements function
							 * 	<sample_data>		-> Some sample-data (can't contain HTML)
							 * 	<eType>			-> The preset-type of the element, used to switch use/content of TCEforms/TypoScriptObjPath
							 * 	<oldStyleColumnNumber>	-> for distributing the fields across the tt_content column-positions
							 * 	<proc>			-> define post-processes for this element's value
							 *		<int>		-> this element's value will be cast to an integer (if exist)
							 *		<HSC>		-> this element's value will convert special chars to HTML-entities (if exist)
							 *		<stdWrap>	-> an implicit stdWrap for this element, "stdWrap { ...inside... }"
							 * 	</proc>
							 *	<TypoScript_constants>	-> an array of constants that will be substituted in the <TypoScript>-element
							 * 	<TypoScript>		->
							 * 	<TypoScriptObjPath>	->
							 * </tx_templavoila>
							 */

							if (isset($tv['description']) && ($tv['description'] != '')) {
								$HTML .= '<p>"' . $tv['description'] . '"</p>';
							}

							/* it would also be possible to use the 'list-style-image'-property
							 * for the flags, which would be more sensible to IE-bugs though
							 */
							$proc = '';
							if (isset($tv['proc']) && isset($tv['proc']['int'])) {
								$proc .= '<li>' .
									(($tv['proc']['int'] == 1)
										? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
										: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
									) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('casttointeger', TRUE) . '</li>';
							}
							if (isset($tv['proc']) && isset($tv['proc']['HSC'])) {
								$proc .= '<li>' .
									(($tv['proc']['HSC'] == 1)
										? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
										: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
									) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('hsced', TRUE) .
									(($tv['proc']['HSC'] == 1)
										? ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('hsc_on', TRUE)
										: ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('hsc_off', TRUE)
									) . '</li>';
							}
							if (isset($tv['proc']) && isset($tv['proc']['stdWrap'])) {
								$proc .= '<li>' .
									(($tv['proc']['stdWrap'] != '')
										? \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok')
										: \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error')
									) . ' ' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('stdwrap', TRUE) . '</li>';
							}

							if ($proc != '') {
								$HTML .= '<ul class="DS-proc">' . $proc . '</ul>';
							}
							//TODO: get the registered eTypes and use the labels
							switch ($tv['eType']) {
								case "input":
									$preset = 'Plain input field';
									$tco = FALSE;
									break;
								case "input_h":
									$preset = 'Header field';
									$tco = FALSE;
									break;
								case "input_g":
									$preset = 'Header field, Graphical';
									$tco = FALSE;
									break;
								case "text":
									$preset = 'Text area for bodytext';
									$tco = FALSE;
									break;
								case "rte":
									$preset = 'Rich text editor for bodytext';
									$tco = FALSE;
									break;
								case "link":
									$preset = 'Link field';
									$tco = FALSE;
									break;
								case "int":
									$preset = 'Integer value';
									$tco = FALSE;
									break;
								case "image":
									$preset = 'Image field';
									$tco = FALSE;
									break;
								case "imagefixed":
									$preset = 'Image field, fixed W+H';
									$tco = FALSE;
									break;
								case "select":
									$preset = 'Selector box';
									$tco = FALSE;
									break;
								case "ce":
									$preset = 'Content Elements';
									$tco = TRUE;
									break;
								case "TypoScriptObject":
									$preset = 'TypoScript Object Path';
									$tco = TRUE;
									break;

								case "none":
									$preset = 'None';
									$tco = TRUE;
									break;
								default:
									$preset = 'Custom [' . $tv['eType'] . ']';
									$tco = TRUE;
									break;
							}

							switch ($tv['oldStyleColumnNumber']) {
								case 0:
									$column = 'Normal [0]';
									break;
								case 1:
									$column = 'Left [1]';
									break;
								case 2:
									$column = 'Right [2]';
									break;
								case 3:
									$column = 'Border [3]';
									break;
								default:
									$column = 'Custom [' . $tv['oldStyleColumnNumber'] . ']';
									break;
							}

							$notes = '';
							if (($tv['eType'] != "TypoScriptObject") && isset($tv['TypoScriptObjPath'])) {
								$notes .= '<li>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('redundant', TRUE) . ' &lt;TypoScriptObjPath&gt;-entry</li>';
							}
							if (($tv['eType'] == "TypoScriptObject") && isset($tv['TypoScript'])) {
								$notes .= '<li>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('redundant', TRUE) . ' &lt;TypoScript&gt;-entry</li>';
							}
							if ((($tv['eType'] == "TypoScriptObject") || !isset($tv['TypoScript'])) && isset($tv['TypoScript_constants'])) {
								$notes .= '<li>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('redundant', TRUE) . ' &lt;TypoScript_constants&gt;-' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('entry', TRUE) . '</li>';
							}
							if (isset($tv['proc']) && isset($tv['proc']['int']) && ($tv['proc']['int'] == 1) && isset($tv['proc']['HSC'])) {
								$notes .= '<li>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('redundant', TRUE) . ' &lt;proc&gt;&lt;HSC&gt;-' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('redundant', TRUE) . '</li>';
							}
							if (isset($tv['TypoScriptObjPath']) && preg_match('/[^a-zA-Z0-9\.\:_]/', $tv['TypoScriptObjPath'])) {
								$notes .= '<li><strong>&lt;TypoScriptObjPath&gt;-' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('illegalcharacters', TRUE) . '</strong></li>';
							}

							$tsstats = '';
							if (isset($tv['TypoScript_constants'])) {
								$tsstats .= '<li>' . sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_tsconstants', TRUE), count($tv['TypoScript_constants'])) . '</li>';
							}
							if (isset($tv['TypoScript'])) {
								$tsstats .= '<li>' . sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_tslines', TRUE), (1 + strlen($tv['TypoScript']) - strlen(str_replace("\n", "", $tv['TypoScript'])))) . '</li>';
							}
							if (isset($tv['TypoScriptObjPath'])) {
								$tsstats .= '<li>' . sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_tsutilize', TRUE), '<em>' . $tv['TypoScriptObjPath'] . '</em>') . '</li>';
							}

							$HTML .= '<dl class="DS-infos">';
							$HTML .= '<dt>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_preset', TRUE) . ':</dt>';
							$HTML .= '<dd>' . $preset . '</dd>';
							$HTML .= '<dt>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_column', TRUE) . ':</dt>';
							$HTML .= '<dd>' . $column . '</dd>';
							if ($tsstats != '') {
								$HTML .= '<dt>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_ts', TRUE) . ':</dt>';
								$HTML .= '<dd><ul class="DS-stats">' . $tsstats . '</ul></dd>';
							}
							if ($notes != '') {
								$HTML .= '<dt>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_notes', TRUE) . ':</dt>';
								$HTML .= '<dd><ul class="DS-notes">' . $notes . '</ul></dd>';
							}
							$HTML .= '</dl>';
						} else {
							$HTML .= '<p>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_nobasicdefinitions', TRUE) . '</p>';
						}

						/* The basic XML-structure of an TCEforms-entry is:
						 *
						 * <TCEforms>
						 * 	<label>			-> TCE-label for the BE
						 * 	<config>		-> TCE-configuration array
						 * </TCEforms>
						 */
						if (!($def['TCEforms'])) {
							if (!$tco) {
								$HTML .= '<p>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('dsdetails_notceformdefinitions', TRUE) . '</p>';
							}
						}
					}
				}

				/* there are some childs to process ----------------------------- */
				if (isset($def['type']) && ($def['type'] == "array")) {

					if (isset($def['section']))
						;
					if (isset($def['el']))
						$HTML .= $this->renderDSdetails($def['el']);
				}

				$HTML .= '</dd>';
			}

			$HTML .= '</dl>';
		} else
			$HTML .= '<p>' . \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-warning') . ' The element has no children!</p>';

		return $HTML;
	}

	/**
	 * Show meta data part of Data Structure
	 *
	 * @param string $DSstring
	 *
	 * @return array
	 */
	public function DSdetails($DSstring) {
		$DScontent = (array) \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($DSstring);

		$inputFields = 0;
		$referenceFields = 0;
		$rootelements = 0;
		if (is_array($DScontent) && is_array($DScontent['ROOT']['el'])) {
			foreach ($DScontent['ROOT']['el'] as $elCfg) {
				$rootelements++;
				if (isset($elCfg['TCEforms'])) {

					// Assuming that a reference field for content elements is recognized like this, increment counter. Otherwise assume input field of some sort.
					if ($elCfg['TCEforms']['config']['type'] === 'group' && $elCfg['TCEforms']['config']['allowed'] === 'tt_content') {
						$referenceFields++;
					} else {
						$inputFields++;
					}
				}
				if (isset($elCfg['el']))
					$elCfg['el'] = '...';
				unset($elCfg['tx_templavoila']['sample_data']);
				unset($elCfg['tx_templavoila']['tags']);
				unset($elCfg['tx_templavoila']['eType']);
			}
		}

		/*	$DScontent = array('meta' => $DScontent['meta']);	*/

		$languageMode = '';
		if (is_array($DScontent['meta'])) {
			if ($DScontent['meta']['langDisable']) {
				$languageMode = 'Disabled';
			} elseif ($DScontent['meta']['langChildren']) {
				$languageMode = 'Inheritance';
			} else {
				$languageMode = 'Separate';
			}
		}

		return array(
			'HTML' => /*\TYPO3\CMS\Core\Utility\GeneralUtility::view_array($DScontent).'Language Mode => "'.$languageMode.'"<hr/>
						Root Elements = '.$rootelements.', hereof ref/input fields = '.($referenceFields.'/'.$inputFields).'<hr/>
						'.$rootElementsHTML*/
				$this->renderDSdetails($DScontent),
			'languageMode' => $languageMode,
			'rootelements' => $rootelements,
			'inputFields' => $inputFields,
			'referenceFields' => $referenceFields
		);
	}

	/******************************
	 *
	 * Wizard for new site
	 *
	 *****************************/

	/**
	 * Wizard overview page - before the wizard is started.
	 *
	 * @return void
	 */
	public function renderNewSiteWizard_overview() {
		if ($this->modTSconfig['properties']['hideNewSiteWizard']) {
			return;
		}

		if (\Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->isAdmin()) {

			// Introduction:
			$outputString = nl2br(sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_intro', TRUE), implode('", "', $this->getTemplatePaths(TRUE, FALSE))));

			// Checks:
			$missingExt = $this->wizard_checkMissingExtensions();
			$missingConf = $this->wizard_checkConfiguration();
			$missingDir = $this->wizard_checkDirectory();
			if (!$missingExt && !$missingConf) {
				$outputString .= '
				<br/>
				<br/>
				<input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_startnow', TRUE) . '" onclick="' . htmlspecialchars('document.location=\'index.php?SET[wiz_step]=1\'; return false;') . '" />';
			} else {
				$outputString .= '<br/><br/>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_problem');
			}

			// Add output:
			$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('wiz_title'), $outputString, 0, 1);

			// Missing extension warning:
			if ($missingExt) {
				$msg = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $missingExt, \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$this->content .= $msg->render();
			}

			// Missing configuration warning:
			if ($missingConf) {
				$msg = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingconf_description'), \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingconf'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$this->content .= $msg->render();
			}

			// Missing directory warning:
			if ($missingDir) {
				$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingdir'), $missingDir, 0, 1, 3);
			}
		}
	}

	/**
	 * Running the wizard. Basically branching out to sub functions.
	 * Also gets and saves session data in $this->wizardData
	 *
	 * @return void
	 */
	public function renderNewSiteWizard_run() {
		// Getting session data:
		$this->wizardData = \Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->getSessionData('tx_templavoila_wizard');

		if (\Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->isAdmin()) {

			$outputString = '';

			switch ($this->MOD_SETTINGS['wiz_step']) {
				case 1:
					$this->wizard_step1();
					break;
				case 2:
					$this->wizard_step2();
					break;
				case 3:
					$this->wizard_step3();
					break;
				case 4:
					$this->wizard_step4();
					break;
				case 5:
					$this->wizard_step5('field_menu');
					break;
				case 5.1:
					$this->wizard_step5('field_submenu');
					break;
				case 6:
					$this->wizard_step6();
					break;
			}

			$outputString .= '<hr/><input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_cancel', TRUE) . '" onclick="' . htmlspecialchars('document.location=\'index.php?SET[wiz_step]=0\'; return false;') . '" />';

			// Add output:
			$this->content .= $this->doc->section('', $outputString, 0, 1);
		}

		// Save session data:
		\Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->setAndSaveSessionData('tx_templavoila_wizard', $this->wizardData);
	}

	/**
	 * Pre-checking for extensions
	 *
	 * @return string If string is returned, an error occured.
	 */
	public function wizard_checkMissingExtensions() {

		$outputString = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext_description', TRUE);

		// Create extension status:
		$checkExtensions = explode(',', 'css_styled_content,impexp');
		$missingExtensions = FALSE;

		$tRows = array();
		$tRows[] = '<tr class="tableheader bgColor5">
			<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext_extkey', TRUE) . '</td>
			<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext_installed', TRUE) . '</td>
		</tr>';

		foreach ($checkExtensions as $extKey) {
			$tRows[] = '<tr class="bgColor4">
				<td>' . $extKey . '</td>
				<td align="center">' . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey) ? \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext_yes', TRUE) : '<span class="typo3-red">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingext_no', TRUE) . '</span>') . '</td>
			</tr>';

			if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey))
				$missingExtensions = TRUE;
		}

		$outputString .= '<table border="0" cellpadding="1" cellspacing="1">' . implode('', $tRows) . '</table>';

		// If no extensions are missing, simply go to step two:
		return ($missingExtensions) ? $outputString : '';
	}

	/**
	 * Pre-checking for TemplaVoila configuration
	 *
	 * @return boolean If string is returned, an error occured.
	 */
	public function wizard_checkConfiguration() {
		$TVconfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);

		return !is_array($TVconfig);
	}

	/**
	 * Pre-checking for directory of extensions.
	 *
	 * @return string If string is returned, an error occured.
	 */
	public function wizard_checkDirectory() {
		$paths = $this->getTemplatePaths(TRUE);
		if (empty($paths)) {
			return nl2br(sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_missingdir_instruction'), implode(' or ', $this->getTemplatePaths(TRUE, FALSE)), $GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir']));
		}

		return FALSE;
	}

	/**
	 * Wizard Step 1: Selecting template file.
	 *
	 * @return void
	 */
	public function wizard_step1() {
		$paths = $this->getTemplatePaths();
		$files = $this->getTemplateFiles();
		if (!empty($paths) && !empty($files)) {

			$this->wizardData = array();
			$pathArr = \TYPO3\CMS\Core\Utility\GeneralUtility::removePrefixPathFromList($paths, PATH_site);
			$outputString = sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_firststep'), implode('", "', $pathArr)) . '<br/>';

			// Get all HTML files:
			$fileArr = \TYPO3\CMS\Core\Utility\GeneralUtility::removePrefixPathFromList($files, PATH_site);

			// Prepare header:
			$tRows = array();
			$tRows[] = '<tr class="tableheader bgColor5">
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('toused_path', TRUE) . ':</td>
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('usage', TRUE) . ':</td>
				<td>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('action', TRUE) . ':</td>
			</tr>';

			// Traverse available template files:
			foreach ($fileArr as $file) {

				// Has been used:
				$tosForTemplate = \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->exec_SELECTgetRows(
					'uid',
					'tx_templavoila_tmplobj',
					'fileref=' . \Extension\Templavoila\Utility\GeneralUtility::getDatabaseConnection()->fullQuoteStr($file, 'tx_templavoila_tmplobj') .
					\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tx_templavoila_tmplobj')
				);

				// Preview link
				$onClick = 'vHWin=window.open(\'' . $this->doc->backPath . '../' . $file . '\',\'tvTemplatePreview\',\'status=1,menubar=1,scrollbars=1,location=1\');vHWin.focus();return false;';

				// Make row:
				$tRows[] = '<tr class="bgColor4">
					<td>' . htmlspecialchars($file) . '</td>
					<td>' . (count($tosForTemplate) ? sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_usedtimes', TRUE), count($tosForTemplate)) : \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_notused', TRUE)) . '</td>
					<td>' .
					'<a href="#" onclick="' . htmlspecialchars($onClick) . '">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_preview', TRUE) . '</a> ' .
					'<a href="' . htmlspecialchars('index.php?SET[wiz_step]=2&CFG[file]=' . rawurlencode($file)) . '">' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_choose', TRUE) . '</a> ' .
					'</td>
			</tr>';
			}
			$outputString .= '<table border="0" cellpadding="1" cellspacing="1" class="lrPadding">' . implode('', $tRows) . '</table>';

			// Refresh button:
			$outputString .= '<br/><input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('refresh', TRUE) . '" onclick="' . htmlspecialchars('document.location=\'index.php?SET[wiz_step]=1\'; return false;') . '" />';

			// Add output:
			$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_selecttemplate', TRUE), $outputString, 0, 1);
		} else {
			$this->content .= $this->doc->section('TemplaVoila wizard error', \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_errornodir', TRUE), 0, 1);
		}
	}

	/**
	 * Step 2: Enter default values:
	 *
	 * @return void
	 */
	public function wizard_step2() {

		// Save session data with filename:
		$cfg = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('CFG');
		if ($cfg['file'] && \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($cfg['file'])) {
			$this->wizardData['file'] = $cfg['file'];
		}

		// Show selected template file:
		if ($this->wizardData['file']) {
			$outputString = htmlspecialchars(sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_templateselected'), $this->wizardData['file']));
			$outputString .= '<br/><iframe src="' . htmlspecialchars($this->doc->backPath . '../' . $this->wizardData['file']) . '" width="640" height="300"></iframe>';

			// Enter default data:
			$outputString .= '
				<br/><br/><br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2next', TRUE) . '
				<br/>
	<br/>
				<b>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_name', TRUE) . ':</b><br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_required', TRUE) . '<br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_valuename', TRUE) . '<br/>
				<input type="text" name="CFG[sitetitle]" value="' . htmlspecialchars($this->wizardData['sitetitle']) . '" /><br/>
	<br/>
				<b>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_url', TRUE) . ':</b><br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_optional', TRUE) . '<br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_valueurl', TRUE) . '<br/>
				<input type="text" name="CFG[siteurl]" value="' . htmlspecialchars($this->wizardData['siteurl']) . '" /><br/>
	<br/>
				<b>' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_editor', TRUE) . ':</b><br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_required', TRUE) . '<br/>
				' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_username', TRUE) . '<br/>
				<input type="text" name="CFG[username]" value="' . htmlspecialchars($this->wizardData['username']) . '" /><br/>
	<br/>
				<input type="hidden" name="SET[wiz_step]" value="3" />
				<input type="submit" name="_create_site" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_createnewsite', TRUE) . '" />
			';
		} else {
			$outputString = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2_notemplatefound', TRUE);
		}

		// Add output:
		$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step2', TRUE), $outputString, 0, 1);
	}

	/**
	 * Step 3: Begin template mapping
	 *
	 * @return void
	 */
	public function wizard_step3() {

		// Save session data with filename:
		$cfg = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST('CFG');
		if (isset($cfg['sitetitle'])) {
			$this->wizardData['sitetitle'] = trim($cfg['sitetitle']);
		}
		if (isset($cfg['siteurl'])) {
			$this->wizardData['siteurl'] = trim($cfg['siteurl']);
		}
		if (isset($cfg['username'])) {
			$this->wizardData['username'] = trim($cfg['username']);
		}

		// If the create-site button WAS clicked:
		$outputString = '';
		if (\TYPO3\CMS\Core\Utility\GeneralUtility::_POST('_create_site')) {

			// Show selected template file:
			if ($this->wizardData['file'] && $this->wizardData['sitetitle'] && $this->wizardData['username']) {

				// DO import:
				$import = $this->getImportObj();
				if (isset($this->modTSconfig['properties']['newTvSiteFile'])) {
					$inFile = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->modTSconfig['properties']['newTVsiteTemplate']);
				} else {
					$inFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('templavoila') . 'mod2/new_tv_site.xml';
				}
				if (@is_file($inFile) && $import->loadFile($inFile, 1)) {

					$import->importData($this->importPageUid);

					// Update various fields (the index values, eg. the "1" in "$import->import_mapId['pages'][1]]..." are the UIDs of the original records from the import file!)
					$data = array();
					$data['pages'][\TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('pages', $import->import_mapId['pages'][1])]['title'] = $this->wizardData['sitetitle'];
					$data['sys_template'][\TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('sys_template', $import->import_mapId['sys_template'][1])]['title'] = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_maintemplate', TRUE) . ' ' . $this->wizardData['sitetitle'];
					$data['sys_template'][\TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('sys_template', $import->import_mapId['sys_template'][1])]['sitetitle'] = $this->wizardData['sitetitle'];
					$data['tx_templavoila_tmplobj'][\TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('tx_templavoila_tmplobj', $import->import_mapId['tx_templavoila_tmplobj'][1])]['fileref'] = $this->wizardData['file'];
					$data['tx_templavoila_tmplobj'][\TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('tx_templavoila_tmplobj', $import->import_mapId['tx_templavoila_tmplobj'][1])]['templatemapping'] = serialize(
						array(
							'MappingInfo' => array(
								'ROOT' => array(
									'MAP_EL' => 'body[1]/INNER'
								)
							),
							'MappingInfo_head' => array(
								'headElementPaths' => array('link[1]', 'link[2]', 'link[3]', 'style[1]', 'style[2]', 'style[3]'),
								'addBodyTag' => 1
							)
						)
					);

					// Update user settings
					$newUserID = \TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('be_users', $import->import_mapId['be_users'][2]);
					$newGroupID = \TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('be_groups', $import->import_mapId['be_groups'][1]);

					$data['be_users'][$newUserID]['username'] = $this->wizardData['username'];
					$data['be_groups'][$newGroupID]['title'] = $this->wizardData['username'];

					foreach ($import->import_mapId['pages'] as $newID) {
						$data['pages'][$newID]['perms_userid'] = $newUserID;
						$data['pages'][$newID]['perms_groupid'] = $newGroupID;
					}

					// Set URL if applicable:
					if (strlen($this->wizardData['siteurl'])) {
						$data['sys_domain']['NEW']['pid'] = \TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('pages', $import->import_mapId['pages'][1]);
						$data['sys_domain']['NEW']['domainName'] = $this->wizardData['siteurl'];
					}

					// Execute changes:
					$tce = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
					$tce->stripslashes_values = 0;
					$tce->dontProcessTransformations = 1;
					$tce->start($data, Array());
					$tce->process_datamap();

					// Setting environment:
					$this->wizardData['rootPageId'] = $import->import_mapId['pages'][1];
					$this->wizardData['templateObjectId'] = \TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('tx_templavoila_tmplobj', $import->import_mapId['tx_templavoila_tmplobj'][1]);
					$this->wizardData['typoScriptTemplateID'] = \TYPO3\CMS\Backend\Utility\BackendUtility::wsMapId('sys_template', $import->import_mapId['sys_template'][1]);

					\TYPO3\CMS\Backend\Utility\BackendUtility::setUpdateSignal('updatePageTree');

					$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_maintemplate', TRUE) . '<hr/>';
				}
			} else {
				$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_maintemplate', TRUE);
			}
		}

		// If a template Object id was found, continue with mapping:
		if ($this->wizardData['templateObjectId']) {
			$url = '../cm1/index.php?table=tx_templavoila_tmplobj&uid=' . $this->wizardData['templateObjectId'] . '&SET[selectHeaderContent]=0&_reload_from=1&id=' . $this->id . '&returnUrl=' . rawurlencode('../mod2/index.php?SET[wiz_step]=4');

			$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step3ready') . '
				<br/>
				<br/>
				<img src="mapbody_animation.gif" style="border: 2px black solid;" alt=""><br/>
				<br/>
				<br/><input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_startmapping', TRUE) . '" onclick="' . htmlspecialchars('document.location=\'' . $url . '\'; return false;') . '" />
			';
		}

		// Add output:
		$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_beginmapping', TRUE), $outputString, 0, 1);
	}

	/**
	 * Step 4: Select HTML header parts.
	 *
	 * @return void
	 */
	public function wizard_step4() {
		$url = '../cm1/index.php?table=tx_templavoila_tmplobj&uid=' . $this->wizardData['templateObjectId'] . '&SET[selectHeaderContent]=1&_reload_from=1&id=' . $this->id . '&returnUrl=' . rawurlencode('../mod2/index.php?SET[wiz_step]=5');
		$outputString = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_headerinclude') . '
			<br/>
			<img src="maphead_animation.gif" style="border: 2px black solid;" alt=""><br/>
			<br/>
			<br/><input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_headerselect') . '" onclick="' . htmlspecialchars('document.location=\'' . $url . '\'; return false;') . '" />
			';

		// Add output:
		$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step4'), $outputString, 0, 1);
	}

	/**
	 * Step 5: Create dynamic menu
	 *
	 * @param string $menuField Type of menu (main or sub), values: "field_menu" or "field_submenu"
	 *
	 * @return void
	 */
	public function wizard_step5($menuField) {

		$menuPart = $this->getMenuDefaultCode($menuField);
		$menuType = $menuField === 'field_menu' ? 'mainMenu' : 'subMenu';
		$menuTypeText = $menuField === 'field_menu' ? 'main menu' : 'sub menu';
		$menuTypeLetter = $menuField === 'field_menu' ? 'a' : 'b';
		$menuTypeNextStep = $menuField === 'field_menu' ? 5.1 : 6;
		$menuTypeEntryLevel = $menuField === 'field_menu' ? 0 : 1;

		$this->saveMenuCode();

		if (strlen($menuPart)) {

			// Main message:
			$outputString = sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_basicsshouldwork', TRUE), $menuTypeText, $menuType, $menuTypeText);

			// Start up HTML parser:
			$htmlParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Html\HtmlParser::class);

			// Parse into blocks
			$parts = $htmlParser->splitIntoBlock('td,tr,table,a,div,span,ol,ul,li,p,h1,h2,h3,h4,h5', $menuPart, 1);

			// If it turns out to be only a single large block we expect it to be a container for the menu item. Therefore we will parse the next level and expect that to be menu items:
			if (count($parts) == 3) {
				$totalWrap = array();
				$totalWrap['before'] = $parts[0] . $htmlParser->getFirstTag($parts[1]);
				$totalWrap['after'] = '</' . strtolower($htmlParser->getFirstTagName($parts[1])) . '>' . $parts[2];

				$parts = $htmlParser->splitIntoBlock('td,tr,table,a,div,span,ol,ul,li,p,h1,h2,h3,h4,h5', $htmlParser->removeFirstAndLastTag($parts[1]), 1);
			} else {
				$totalWrap = array();
			}

			$menuPart_HTML = trim($totalWrap['before']) . chr(10) . implode(chr(10), $parts) . chr(10) . trim($totalWrap['after']);

			// Traverse expected menu items:
			$menuWraps = array();
			$GMENU = FALSE;
			$mouseOver = FALSE;
			$key = '';

			foreach ($parts as $k => $value) {
				if ($k % 2) { // Only expecting inner elements to be of use:

					$linkTag = $htmlParser->splitIntoBlock('a', $value, 1);
					if ($linkTag[1]) {
						$newValue = array();
						$attribs = $htmlParser->get_tag_attributes($htmlParser->getFirstTag($linkTag[1]), 1);
						$newValue['A-class'] = $attribs[0]['class'];
						if ($attribs[0]['onmouseover'] && $attribs[0]['onmouseout'])
							$mouseOver = TRUE;

						// Check if the complete content is an image - then make GMENU!
						$linkContent = trim($htmlParser->removeFirstAndLastTag($linkTag[1]));
						if (preg_match('/^<img[^>]*>$/i', $linkContent)) {
							$GMENU = TRUE;
							$attribs = $htmlParser->get_tag_attributes($linkContent, 1);
							$newValue['I-class'] = $attribs[0]['class'];
							$newValue['I-width'] = $attribs[0]['width'];
							$newValue['I-height'] = $attribs[0]['height'];

							$filePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(\TYPO3\CMS\Core\Utility\GeneralUtility::resolveBackPath(PATH_site . $attribs[0]['src']));
							if (@is_file($filePath)) {
								$newValue['backColorGuess'] = $this->getBackgroundColor($filePath);
							} else $newValue['backColorGuess'] = '';

							if ($attribs[0]['onmouseover'] && $attribs[0]['onmouseout'])
								$mouseOver = TRUE;
						}

						$linkTag[1] = '|';
						$newValue['wrap'] = preg_replace('/[' . chr(10) . chr(13) . ']*/', '', implode('', $linkTag));

						$md5Base = $newValue;
						unset($md5Base['I-width']);
						unset($md5Base['I-height']);
						$md5Base = serialize($md5Base);
						$md5Base = preg_replace('/name=["\'][^"\']*["\']/', '', $md5Base);
						$md5Base = preg_replace('/id=["\'][^"\']*["\']/', '', $md5Base);
						$md5Base = preg_replace('/\s/', '', $md5Base);
						$key = md5($md5Base);

						if (!isset($menuWraps[$key])) { // Only if not yet set, set it (so it only gets set once and the first time!)
							$menuWraps[$key] = $newValue;
						} else { // To prevent from writing values in the "} elseif ($key) {" below, we clear the key:
							$key = '';
						}
					} elseif ($key) {

						// Add this to the previous wrap:
						$menuWraps[$key]['bulletwrap'] .= str_replace('|', '&#' . ord('|') . ';', preg_replace('/[' . chr(10) . chr(13) . ']*/', '', $value));
					}
				}
			}

			// Construct TypoScript for the menu:
			reset($menuWraps);
			if (count($menuWraps) == 1) {
				$menu_normal = current($menuWraps);
				$menu_active = next($menuWraps);
			} else { // If more than two, then the first is the active one.
				$menu_active = current($menuWraps);
				$menu_normal = next($menuWraps);
			}

			if ($GMENU) {
				$typoScript = '
lib.' . $menuType . ' = HMENU
lib.' . $menuType . '.entryLevel = ' . $menuTypeEntryLevel . '
' . (count($totalWrap) ? 'lib.' . $menuType . '.wrap = ' . preg_replace('/[' . chr(10) . chr(13) . ']/', '', implode('|', $totalWrap)) : '') . '
lib.' . $menuType . '.1 = GMENU
lib.' . $menuType . '.1.NO.wrap = ' . $this->makeWrap($menu_normal) .
					($menu_normal['I-class'] ? '
lib.' . $menuType . '.1.NO.imgParams = class="' . htmlspecialchars($menu_normal['I-class']) . '" ' : '') . '
lib.' . $menuType . '.1.NO {
	XY = ' . ($menu_normal['I-width'] ? $menu_normal['I-width'] : 150) . ',' . ($menu_normal['I-height'] ? $menu_normal['I-height'] : 25) . '
	backColor = ' . ($menu_normal['backColorGuess'] ? $menu_normal['backColorGuess'] : '#FFFFFF') . '
	10 = TEXT
	10.text.field = title // nav_title
	10.fontColor = #333333
	10.fontSize = 12
	10.offset = 15,15
	10.fontFace = typo3/sysext/core/Resources/Private/Font/nimbus.ttf
}
	';

				if ($mouseOver) {
					$typoScript .= '
lib.' . $menuType . '.1.RO < lib.' . $menuType . '.1.NO
lib.' . $menuType . '.1.RO = 1
lib.' . $menuType . '.1.RO {
	backColor = ' . \TYPO3\CMS\Core\Utility\GeneralUtility::modifyHTMLColorAll(($menu_normal['backColorGuess'] ? $menu_normal['backColorGuess'] : '#FFFFFF'), -20) . '
	10.fontColor = red
}
			';
				}
				if (is_array($menu_active)) {
					$typoScript .= '
lib.' . $menuType . '.1.ACT < lib.' . $menuType . '.1.NO
lib.' . $menuType . '.1.ACT = 1
lib.' . $menuType . '.1.ACT.wrap = ' . $this->makeWrap($menu_active) .
						($menu_active['I-class'] ? '
lib.' . $menuType . '.1.ACT.imgParams = class="' . htmlspecialchars($menu_active['I-class']) . '" ' : '') . '
lib.' . $menuType . '.1.ACT {
	backColor = ' . ($menu_active['backColorGuess'] ? $menu_active['backColorGuess'] : '#FFFFFF') . '
}
			';
				}
			} else {
				$typoScript = '
lib.' . $menuType . ' = HMENU
lib.' . $menuType . '.entryLevel = ' . $menuTypeEntryLevel . '
' . (count($totalWrap) ? 'lib.' . $menuType . '.wrap = ' . preg_replace('/[' . chr(10) . chr(13) . ']/', '', implode('|', $totalWrap)) : '') . '
lib.' . $menuType . '.1 = TMENU
lib.' . $menuType . '.1.NO {
	allWrap = ' . $this->makeWrap($menu_normal) .
					($menu_normal['A-class'] ? '
	ATagParams = class="' . htmlspecialchars($menu_normal['A-class']) . '"' : '') . '
}
	';

				if (is_array($menu_active)) {
					$typoScript .= '
lib.' . $menuType . '.1.ACT = 1
lib.' . $menuType . '.1.ACT {
	allWrap = ' . $this->makeWrap($menu_active) .
						($menu_active['A-class'] ? '
	ATagParams = class="' . htmlspecialchars($menu_active['A-class']) . '"' : '') . '
}
			';
				}
			}

			// Output:

			// HTML defaults:
			$outputString .= '
			<br/>
			<br/>
			' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuhtmlcode', TRUE) . '
			<hr/>
			<pre>' . htmlspecialchars($menuPart_HTML) . '</pre>
			<hr/>
			<br/>';

			if (trim($menu_normal['wrap']) != '|') {
				$outputString .= sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuenc', TRUE), htmlspecialchars(str_replace('|', ' ... ', $menu_normal['wrap'])));
			} else {
				$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menunoa', TRUE);
			}
			if (count($totalWrap)) {
				$outputString .= sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuwrap', TRUE), htmlspecialchars(str_replace('|', ' ... ', implode('|', $totalWrap))));
			}
			if ($menu_normal['bulletwrap']) {
				$outputString .= sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menudiv', TRUE), htmlspecialchars($menu_normal['bulletwrap']));
			}
			if ($GMENU) {
				$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuimg', TRUE);
			}
			if ($mouseOver) {
				$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menumouseover', TRUE);
			}

			$outputString .= '<br/><br/>';
			$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuts', TRUE) . '
			<br/><br/>';
			$outputString .= '<hr/>' . $this->syntaxHLTypoScript($typoScript) . '<hr/><br/>';

			$outputString .= \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menufinetune', TRUE);
			$outputString .= '<textarea name="CFG[menuCode]"' . $GLOBALS['TBE_TEMPLATE']->formWidthText() . ' rows="10">' . \TYPO3\CMS\Core\Utility\GeneralUtility::formatForTextarea($typoScript) . '</textarea><br/><br/>';
			$outputString .= '<input type="hidden" name="SET[wiz_step]" value="' . $menuTypeNextStep . '" />';
			$outputString .= '<input type="submit" name="_" value="' . sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menuwritets', TRUE), $menuTypeText) . '" />';
		} else {
			$outputString = sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menufinished', TRUE), $menuTypeText) . '<br />';
			$outputString .= '<input type="hidden" name="SET[wiz_step]" value="' . $menuTypeNextStep . '" />';
			$outputString .= '<input type="submit" name="_" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_menunext', TRUE) . '" />';
		}

		// Add output:
		$this->content .= $this->doc->section(sprintf(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_step5', TRUE), $menuTypeLetter), $outputString, 0, 1);
	}

	/**
	 * Step 6: Done.
	 *
	 * @return void
	 */
	public function wizard_step6() {

		$this->saveMenuCode();

		$outputString = \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_sitecreated') . '

		<br/>
		<br/>
		<input type="submit" value="' . \Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_finish', TRUE) . '" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($this->wizardData['rootPageId'], $this->doc->backPath) . 'document.location=\'index.php?SET[wiz_step]=0\'; return false;') . '" />
		';

		// Add output:
		$this->content .= $this->doc->section(\Extension\Templavoila\Utility\GeneralUtility::getLanguageService()->getLL('newsitewizard_done', TRUE), $outputString, 0, 1);
	}

	/**
	 * Initialize the import-engine
	 *
	 * @return \TYPO3\CMS\Impexp\ImportExport Returns object ready to import the import-file used to create the basic site!
	 */
	public function getImportObj() {
		/** @var \TYPO3\CMS\Impexp\ImportExport $import */
		$import = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\tx_impexp::class);
		$import->init(0, 'import');
		$import->enableLogging = TRUE;

		return $import;
	}

	/**
	 * Syntax Highlighting of TypoScript code
	 *
	 * @param string $v String of TypoScript code
	 *
	 * @return string HTML content with it highlighted.
	 */
	public function syntaxHLTypoScript($v) {
		$tsparser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
		$tsparser->lineNumberOffset = 0;
		$TScontent = $tsparser->doSyntaxHighlight(trim($v) . chr(10), '', 1);

		return $TScontent;
	}

	/**
	 * Produce WRAP value
	 *
	 * @param array $cfg menuItemSuggestion configuration
	 *
	 * @return string Wrap for TypoScript
	 */
	public function makeWrap($cfg) {
		if (!$cfg['bulletwrap']) {
			$wrap = $cfg['wrap'];
		} else {
			$wrap = $cfg['wrap'] . '  |*|  ' . $cfg['bulletwrap'] . $cfg['wrap'];
		}

		return preg_replace('/[' . chr(10) . chr(13) . chr(9) . ']/', '', $wrap);
	}

	/**
	 * Returns the code that the menu was mapped to in the HTML
	 *
	 * @param string $field "Field" from Data structure, either "field_menu" or "field_submenu"
	 *
	 * @return string
	 */
	public function getMenuDefaultCode($field) {
		// Select template record and extract menu HTML content
		$toRec = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordWSOL('tx_templavoila_tmplobj', $this->wizardData['templateObjectId']);
		$tMapping = unserialize($toRec['templatemapping']);

		return $tMapping['MappingData_cached']['cArray'][$field];
	}

	/**
	 * Saves the menu TypoScript code
	 *
	 * @return void
	 */
	public function saveMenuCode() {

		// Save menu code to template record:
		$cfg = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST('CFG');
		if (isset($cfg['menuCode'])) {

			// Get template record:
			$TSrecord = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('sys_template', $this->wizardData['typoScriptTemplateID']);
			if (is_array($TSrecord)) {
				$data = array();
				$data['sys_template'][$TSrecord['uid']]['config'] = '

## Menu [Begin]
' . trim($cfg['menuCode']) . '
## Menu [End]



' . $TSrecord['config'];

				// Execute changes:
				$tce = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
				$tce->stripslashes_values = 0;
				$tce->dontProcessTransformations = 1;
				$tce->start($data, Array());
				$tce->process_datamap();
			}
		}
	}

	/**
	 * Tries to fetch the background color of a GIF or PNG image.
	 *
	 * @param string $filePath Filepath (absolute) of the image (must exist)
	 *
	 * @return string HTML hex color code, if any.
	 */
	public function getBackgroundColor($filePath) {

		if (substr($filePath, -4) == '.gif' && function_exists('imagecreatefromgif')) {
			$im = @imagecreatefromgif($filePath);
		} elseif (substr($filePath, -4) == '.png' && function_exists('imagecreatefrompng')) {
			$im = @imagecreatefrompng($filePath);
		} else {
			$im = NULL;
		}

		if (is_resource($im)) {
			$values = imagecolorsforindex($im, imagecolorat($im, 3, 3));
			$color = '#' . substr('00' . dechex($values['red']), -2) .
				substr('00' . dechex($values['green']), -2) .
				substr('00' . dechex($values['blue']), -2);

			return $color;
		}

		return FALSE;
	}

	/**
	 * Find and check all template paths
	 *
	 * @param boolean $relative if true returned paths are relative
	 * @param boolean $check if true the patchs are checked
	 *
	 * @return array all relevant template paths
	 */
	protected function getTemplatePaths($relative = FALSE, $check = TRUE) {
		$templatePaths = array();
		if (strlen($this->modTSconfig['properties']['templatePath'])) {
			$paths = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->modTSconfig['properties']['templatePath'], TRUE);
		} else {
			$paths = array('templates');
		}

		$prefix = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir']);

		foreach (\Extension\Templavoila\Utility\GeneralUtility::getBackendUser()->getFileStorages() AS $driver) {
			/** @var TYPO3\CMS\Core\Resource\ResourceStorage $driver */
			$driverpath = $driver->getConfiguration();
			$driverpath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($driverpath['basePath']);
			foreach ($paths as $path) {
				if (\TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($prefix . $path, $driverpath) && is_dir($prefix . $path)) {
					$templatePaths[] = ($relative ? $GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir'] : $prefix) . $path;
				} else {
					if (!$check) {
						$templatePaths[] = ($relative ? $GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir'] : $prefix) . $path;
					}
				}
			}
		}

		return $templatePaths;
	}

	/**
	 * Find and check all templates within the template paths
	 *
	 * @return array all relevant templates
	 */
	protected function getTemplateFiles() {
		$paths = $this->getTemplatePaths();
		$files = array();
		foreach ($paths as $path) {
			$files = array_merge(\TYPO3\CMS\Core\Utility\GeneralUtility::getAllFilesAndFoldersInPath(array(), $path . ((substr($path, -1) != '/') ? '/' : ''), 'html,htm,tmpl', 0), $files);
		}

		return $files;
	}
}

if (!function_exists('md5_file')) {
	/**
	 * @param string $file
	 * @param boolean $raw
	 *
	 * @return string
	 */
	function md5_file($file, $raw = FALSE) {
		return md5(file_get_contents($file), $raw);
	}
}

// Make instance:
$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\tx_templavoila_module2::class);
$SOBE->init();
$SOBE->main();
$SOBE->printContent();
