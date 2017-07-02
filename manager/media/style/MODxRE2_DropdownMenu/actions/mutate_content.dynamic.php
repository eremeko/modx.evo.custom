<?php
if(IN_MANAGER_MODE != "true") {
	die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}

/********************/
$sd = isset($_REQUEST['dir']) ? '&dir=' . $_REQUEST['dir'] : '&dir=DESC';
$sb = isset($_REQUEST['sort']) ? '&sort=' . $_REQUEST['sort'] : '&sort=createdon';
$pg = isset($_REQUEST['page']) ? '&page=' . (int) $_REQUEST['page'] : '';
$add_path = $sd . $sb . $pg;
/*******************/

// check permissions
switch($modx->manager->action) {
	case 27:
		if(!$modx->hasPermission('edit_document')) {
			$modx->webAlertAndQuit($_lang["error_no_privileges"]);
		}
		break;
	case 85:
	case 72:
	case 4:
		if(!$modx->hasPermission('new_document')) {
			$modx->webAlertAndQuit($_lang["error_no_privileges"]);
		} elseif(isset($_REQUEST['pid']) && $_REQUEST['pid'] != '0') {
			// check user has permissions for parent
			include_once(MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
			$udperms = new udperms();
			$udperms->user = $modx->getLoginUserID();
			$udperms->document = empty($_REQUEST['pid']) ? 0 : $_REQUEST['pid'];
			$udperms->role = $_SESSION['mgrRole'];
			if(!$udperms->checkPermissions()) {
				$modx->webAlertAndQuit($_lang["access_permission_denied"]);
			}
		}
		break;
	default:
		$modx->webAlertAndQuit($_lang["error_no_privileges"]);
}

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

// Get table names (alphabetical)
$tbl_categories = $modx->getFullTableName('categories');
$tbl_document_group_names = $modx->getFullTableName('documentgroup_names');
$tbl_member_groups = $modx->getFullTableName('member_groups');
$tbl_membergroup_access = $modx->getFullTableName('membergroup_access');
$tbl_document_groups = $modx->getFullTableName('document_groups');
$tbl_keyword_xref = $modx->getFullTableName('keyword_xref');
$tbl_site_content = $modx->getFullTableName('site_content');
$tbl_site_content_metatags = $modx->getFullTableName('site_content_metatags');
$tbl_site_keywords = $modx->getFullTableName('site_keywords');
$tbl_site_metatags = $modx->getFullTableName('site_metatags');
$tbl_site_templates = $modx->getFullTableName('site_templates');
$tbl_site_tmplvar_access = $modx->getFullTableName('site_tmplvar_access');
$tbl_site_tmplvar_contentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
$tbl_site_tmplvar_templates = $modx->getFullTableName('site_tmplvar_templates');
$tbl_site_tmplvars = $modx->getFullTableName('site_tmplvars');

if($modx->manager->action == 27) {
	//editing an existing document
	// check permissions on the document
	include_once(MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
	$udperms = new udperms();
	$udperms->user = $modx->getLoginUserID();
	$udperms->document = $id;
	$udperms->role = $_SESSION['mgrRole'];

	if(!$udperms->checkPermissions()) {
		$modx->webAlertAndQuit($_lang["access_permission_denied"]);
	}
}

// check to see if resource isn't locked
if($lockedEl = $modx->elementIsLocked(7, $id)) {
	$modx->webAlertAndQuit(sprintf($_lang['lock_msg'], $lockedEl['username'], $_lang['resource']));
}
// end check for lock

// Lock resource for other users to edit
$modx->lockElement(7, $id);

// get document groups for current user
if($_SESSION['mgrDocgroups']) {
	$docgrp = implode(',', $_SESSION['mgrDocgroups']);
}

if(!empty ($id)) {
	$access = sprintf("1='%s' OR sc.privatemgr=0", $_SESSION['mgrRole']);
	if($docgrp) {
		$access .= " OR dg.document_group IN ({$docgrp})";
	}
	$rs = $modx->db->select('sc.*', "{$tbl_site_content} AS sc LEFT JOIN {$tbl_document_groups} AS dg ON dg.document=sc.id", "sc.id='{$id}' AND ({$access})");
	$content = array();
	$content = $modx->db->getRow($rs);
	$modx->documentObject = &$content;
	if(!$content) {
		$modx->webAlertAndQuit($_lang["access_permission_denied"]);
	}
	$_SESSION['itemname'] = $content['pagetitle'];
} else {
	$content = array();

	if(isset($_REQUEST['newtemplate'])) {
		$content['template'] = $_REQUEST['newtemplate'];
	} else {
		$content['template'] = getDefaultTemplate();
	}

	$_SESSION['itemname'] = $_lang["new_resource"];
}

// restore saved form
$formRestored = $modx->manager->loadFormValues();
if(isset($_REQUEST['newtemplate'])) {
	$formRestored = true;
}

// retain form values if template was changed
// edited to convert pub_date and unpub_date
// sottwell 02-09-2006
if($formRestored == true) {
	$content = array_merge($content, $_POST);
	$content['content'] = $_POST['ta'];
	if(empty ($content['pub_date'])) {
		unset ($content['pub_date']);
	} else {
		$content['pub_date'] = $modx->toTimeStamp($content['pub_date']);
	}
	if(empty ($content['unpub_date'])) {
		unset ($content['unpub_date']);
	} else {
		$content['unpub_date'] = $modx->toTimeStamp($content['unpub_date']);
	}
}

// increase menu index if this is a new document
if(!isset ($_REQUEST['id'])) {
	if(!isset ($modx->config['auto_menuindex'])) {
		$modx->config['auto_menuindex'] = 1;
	}
	if($modx->config['auto_menuindex']) {
		$pid = intval($_REQUEST['pid']);
		$rs = $modx->db->select('count(*)', $tbl_site_content, "parent='{$pid}'");
		$content['menuindex'] = $modx->db->getValue($rs);
	} else {
		$content['menuindex'] = 0;
	}
}

if(isset ($_POST['which_editor'])) {
	$modx->config['which_editor'] = $_POST['which_editor'];
}

// Add lock-element JS-Script
$lockElementId = $id;
$lockElementType = 7;
require_once(MODX_MANAGER_PATH . 'includes/active_user_locks.inc.php');
?>
	<script type="text/javascript">
		/* <![CDATA[ */

		// save tree folder state
		if(parent.tree) parent.tree.saveFolderState();

		function changestate(el) {
			if(parseInt(el.value) === 1) {
				el.value = 0;
			} else {
				el.value = 1;
			}
			documentDirty = true;
		}

		var actions = {
			save: function() {
				documentDirty = false;
				form_save = true;
				document.mutate.save.click();
			},
			delete: function() {
				if(confirm("<?php echo $_lang['confirm_delete_resource']?>") === true) {
					document.location.href = "index.php?id=" + document.mutate.id.value + "&a=6<?php echo $add_path; ?>";
				}
			},
			cancel: function() {
				documentDirty = false;
				document.location.href = 'index.php?<?php echo($id == 0 ? 'a=2' : 'a=3&r=1&id=' . $id . $add_path) ?>';
			},
			duplicate: function() {
				if(confirm("<?php echo $_lang['confirm_resource_duplicate']?>") === true) {
					document.location.href = "index.php?id=<?php echo $_REQUEST['id'] ?>&a=94<?php echo $add_path; ?>";
				}
			},
			view: function() {
				window.open('<?php echo ($modx->config['friendly_urls'] == '1') ? $modx->makeUrl($id) : $modx->config['site_url'] . 'index.php?id=' . $id ?>', 'previeWin');
			}
		};

		var allowParentSelection = false;
		var allowLinkSelection = false;

		function enableLinkSelection(b) {
			parent.tree.ca = "link";
			var llock = document.getElementById('llock');
			if(b) {
				llock.className = "<?php echo $_style["actions_chain_broken"] ?>";
				allowLinkSelection = true;
			}
			else {
				llock.className = "<?php echo $_style["actions_chain"] ?>";
				allowLinkSelection = false;
			}
		}

		function setLink(lId) {
			if(!allowLinkSelection) {
				window.location.href = "index.php?a=3&id=" + lId + "<?php echo $add_path; ?>";
			}
			else {
				documentDirty = true;
				document.mutate.ta.value = lId;
			}
		}

		function enableParentSelection(b) {
			parent.tree.ca = "parent";
			var plock = document.getElementById('plock');
			if(b) {
				plock.className = "<?php echo $_style["actions_folder_open"] ?>";
				allowParentSelection = true;
			}
			else {
				plock.className = "<?php echo $_style["actions_folder"] ?>";
				allowParentSelection = false;
			}
		}

		function setParent(pId, pName) {
			if(!allowParentSelection) {
				window.location.href = "index.php?a=3&id=" + pId + "<?php echo $add_path; ?>";
			}
			else {
				if(pId === 0 || checkParentChildRelation(pId, pName)) {
					documentDirty = true;
					document.mutate.parent.value = pId;
					var elm = document.getElementById('parentName');
					if(elm) {
						elm.innerHTML = (pId + " (" + pName + ")");
					}
				}
			}
		}

		// check if the selected parent is a child of this document
		function checkParentChildRelation(pId, pName) {
			var sp;
			var id = document.mutate.id.value;
			var tdoc = parent.tree.document;
			var pn = (tdoc.getElementById) ? tdoc.getElementById("node" + pId) : tdoc.all["node" + pId];
			if(!pn) return;
			if(pn.id.substr(4) === id) {
				alert("<?php echo $_lang['illegal_parent_self']?>");
				return;
			}
			else {
				while(pn.getAttribute("p") > 0) {
					pId = pn.getAttribute("p");
					pn = (tdoc.getElementById) ? tdoc.getElementById("node" + pId) : tdoc.all["node" + pId];
					if(pn.id.substr(4) === id) {
						alert("<?php echo $_lang['illegal_parent_child']?>");
						return;
					}
				}
			}
			return true;
		}

		function clearKeywordSelection() {
			var opt = document.mutate.elements["keywords[]"].options;
			for(var i = 0; i < opt.length; i++) {
				opt[i].selected = false;
			}
		}

		function clearMetatagSelection() {
			var opt = document.mutate.elements["metatags[]"].options;
			for(var i = 0; i < opt.length; i++) {
				opt[i].selected = false;
			}
		}

		var curTemplate = -1;
		var curTemplateIndex = 0;

		function storeCurTemplate() {
			var dropTemplate = document.getElementById('template');
			if(dropTemplate) {
				for(var i = 0; i < dropTemplate.length; i++) {
					if(dropTemplate[i].selected) {
						curTemplate = dropTemplate[i].value;
						curTemplateIndex = i;
					}
				}
			}
		}

		var newTemplate;

		function templateWarning() {
			var dropTemplate = document.getElementById('template');
			if(dropTemplate) {
				for(var i = 0; i < dropTemplate.length; i++) {
					if(dropTemplate[i].selected) {
						newTemplate = dropTemplate[i].value;
						break;
					}
				}
			}
			if(curTemplate === newTemplate) {
				return;
			}

			if(documentDirty === true) {
				if(confirm('<?php echo $_lang['tmplvar_change_template_msg']?>')) {
					documentDirty = false;
					document.mutate.a.value = <?php echo $modx->manager->action; ?>;
					document.mutate.newtemplate.value = newTemplate;
					document.mutate.submit();
				} else {
					dropTemplate[curTemplateIndex].selected = true;
				}
			}
			else {
				document.mutate.a.value = <?php echo $modx->manager->action; ?>;
				document.mutate.newtemplate.value = newTemplate;
				document.mutate.submit();
			}
		}

		// Added for RTE selection
		function changeRTE() {
			var whichEditor = document.getElementById('which_editor'),
				newEditor,
				i;
			if(whichEditor) {
				for(i = 0; i < whichEditor.length; i++) {
					if(whichEditor[i].selected) {
						newEditor = whichEditor[i].value;
						break;
					}
				}
			}
			var dropTemplate = document.getElementById('template');
			if(dropTemplate) {
				for(i = 0; i < dropTemplate.length; i++) {
					if(dropTemplate[i].selected) {
						newTemplate = dropTemplate[i].value;
						break;
					}
				}
			}

			documentDirty = false;
			document.mutate.a.value = <?php echo $modx->manager->action; ?>;
			document.mutate.newtemplate.value = newTemplate;
			document.mutate.which_editor.value = newEditor;
			document.mutate.submit();
		}

		/**
		 * Snippet properties
		 */

		var snippetParams = {};     // Snippet Params
		var currentParams = {};     // Current Params
		var lastsp, lastmod = {};

		function showParameters(ctrl) {
			var c, p, df, cp, ar, desc, value, key, dt, f;

			cp = {};
			currentParams = {}; // reset;

			if(ctrl) {
				f = ctrl.form;
			} else {
				f = document.forms['mutate'];
				ctrl = f.snippetlist;
			}

			// get display format
			df = "";//lastsp = ctrl.options[ctrl.selectedIndex].value;

			// load last modified param values
			if(lastmod[df]) cp = lastmod[df].split("&");
			for(p = 0; p < cp.length; p++) {
				cp[p] = (cp[p] + '').replace(/^\s|\s$/, ""); // trim
				ar = cp[p].split("=");
				currentParams[ar[0]] = ar[1];
			}

			// setup parameters
			var t, dp = (snippetParams[df]) ? snippetParams[df].split("&") : [""];
			if(dp) {
				t = '<table width="100%" class="displayparams"><thead><tr><td width="50%"><?php echo $_lang['parameter']?><\/td><td width="50%"><?php echo $_lang['value']?><\/td><\/tr><\/thead>';
				for(p = 0; p < dp.length; p++) {
					dp[p] = (dp[p] + '').replace(/^\s|\s$/, ""); // trim
					ar = dp[p].split("=");
					key = ar[0];     // param
					ar = (ar[1] + '').split(";");
					desc = ar[0];   // description
					dt = ar[1];     // data type
					value = decode((currentParams[key]) ? currentParams[key] : (dt == 'list') ? ar[3] : (ar[2]) ? ar[2] : '');
					if(value !== currentParams[key]) currentParams[key] = value;
					value = (value + '').replace(/^\s|\s$/, ""); // trim
					if(dt) {
						switch(dt) {
							case 'int':
								c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" \/>';
								break;
							case 'list':
								c = '<select name="prop_' + key + '" height="1" style="width:168px" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">';
								var ls = (ar[2] + '').split(",");
								if(currentParams[key] === ar[2]) currentParams[key] = ls[0]; // use first list item as default
								for(var i = 0; i < ls.length; i++) {
									c += '<option value="' + ls[i] + '"' + ((ls[i] === value) ? ' selected="selected"' : '') + '>' + ls[i] + '<\/option>';
								}
								c += '<\/select>';
								break;
							default:  // string
								c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" \/>';
								break;

						}
						t += '<tr><td bgcolor="#FFFFFF" width="50%">' + desc + '<\/td><td bgcolor="#FFFFFF" width="50%">' + c + '<\/td><\/tr>';
					}
				}
				t += '<\/table>';
				var td = (document.getElementById) ? document.getElementById('snippetparams') : document.all['snippetparams'];
				td.innerHTML = t;
			}
			implodeParameters();
		}

		function setParameter(key, dt, ctrl) {
			var v;
			if(!ctrl) return null;
			switch(dt) {
				case 'int':
					ctrl.value = parseInt(ctrl.value);
					if(isNaN(ctrl.value)) ctrl.value = 0;
					v = ctrl.value;
					break;
				case 'list':
					v = ctrl.options[ctrl.selectedIndex].value;
					break;
				default:
					v = ctrl.value + '';
					break;
			}
			currentParams[key] = v;
			implodeParameters();
		}

		function resetParameters() {
			document.mutate.params.value = "";
			lastmod[lastsp] = "";
			showParameters();
		}

		// implode parameters
		function implodeParameters() {
			var v, p, s = '';
			for(p in currentParams) {
				v = currentParams[p];
				if(v) s += '&' + p + '=' + encode(v);
			}
			//document.forms['mutate'].params.value = s;
			if(lastsp) lastmod[lastsp] = s;
		}

		function encode(s) {
			s = s + '';
			s = s.replace(/\=/g, '%3D'); // =
			s = s.replace(/\&/g, '%26'); // &
			return s;
		}

		function decode(s) {
			s = s + '';
			s = s.replace(/\%3D/g, '='); // =
			s = s.replace(/\%26/g, '&'); // &
			return s;
		}

		function setLastClickedElement(type, id) {
			localStorage.setItem('MODX_lastClickedElement', '[' + type + ',' + id + ']');
		}

		<?php if ($content['type'] == 'reference' || $modx->manager->action == '72') { // Web Link specific ?>
		var lastImageCtrl;
		var lastFileCtrl;

		function OpenServerBrowser(url, width, height) {
			var iLeft = (screen.width - width) / 2;
			var iTop = (screen.height - height) / 2;

			var sOptions = 'toolbar=no,status=no,resizable=yes,dependent=yes';
			sOptions += ',width=' + width;
			sOptions += ',height=' + height;
			sOptions += ',left=' + iLeft;
			sOptions += ',top=' + iTop;

			var oWindow = window.open(url, 'FCKBrowseWindow', sOptions);
		}

		function BrowseServer(ctrl) {
			lastImageCtrl = ctrl;
			var w = screen.width * 0.5;
			var h = screen.height * 0.5;
			OpenServerBrowser('<?php echo MODX_MANAGER_URL?>media/browser/<?php echo $which_browser?>/browser.php?Type=images', w, h);
		}

		function BrowseFileServer(ctrl) {
			lastFileCtrl = ctrl;
			var w = screen.width * 0.5;
			var h = screen.height * 0.5;
			OpenServerBrowser('<?php echo MODX_MANAGER_URL?>media/browser/<?php echo $which_browser?>/browser.php?Type=files', w, h);
		}

		function SetUrlChange(el) {
			if('createEvent' in document) {
				var evt = document.createEvent('HTMLEvents');
				evt.initEvent('change', false, true);
				el.dispatchEvent(evt);
			} else {
				el.fireEvent('onchange');
			}
		}

		function SetUrl(url, width, height, alt) {
			if(lastFileCtrl) {
				var c = document.getElementById(lastFileCtrl);
				if(c && c.value !== url) {
					c.value = url;
					SetUrlChange(c);
				}
				lastFileCtrl = '';
			} else if(lastImageCtrl) {
				var c = document.getElementById(lastImageCtrl);
				if(c && c.value !== url) {
					c.value = url;
					SetUrlChange(c);
				}
				lastImageCtrl = '';
			} else {

			}
		}

		<?php $ResourceManagerLoaded = true; } ?>
		/* ]]> */
	</script>

	<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php" onsubmit="documentDirty=false;">
		<?php
		// invoke OnDocFormPrerender event
		$evtOut = $modx->invokeEvent('OnDocFormPrerender', array(
			'id' => $id,
			'template' => $content['template']
		));

		if(is_array($evtOut)) {
			echo implode('', $evtOut);
		}

		/*************************/
		$dir = isset($_REQUEST['dir']) ? $_REQUEST['dir'] : '';
		$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'createdon';
		$page = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : '';
		/*************************/

		?>
		<input type="hidden" name="a" value="5" />
		<input type="hidden" name="id" value="<?php echo $content['id'] ?>" />
		<input type="hidden" name="mode" value="<?php echo $modx->manager->action; ?>" />
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo isset($modx->config['upload_maxsize']) ? $modx->config['upload_maxsize'] : 1048576 ?>" />
		<input type="hidden" name="refresh_preview" value="0" />
		<input type="hidden" name="newtemplate" value="" />
		<input type="hidden" name="dir" value="<?php echo $dir; ?>" />
		<input type="hidden" name="sort" value="<?php echo $sort; ?>" />
		<input type="hidden" name="page" value="<?php echo $page; ?>" />

		<fieldset id="create_edit">

			<h1>
				<i class="fa fa-pencil-square-o"></i><?php if(isset($_REQUEST['id'])) {
					echo iconv_substr($content['pagetitle'], 0, 50, $modx->config['modx_charset']) . (iconv_strlen($content['pagetitle'], $modx->config['modx_charset']) > 50 ? '...' : '') . '<small>(' . $_REQUEST['id'] . ')</small>';
				} else {
					echo $_lang['create_resource_title'];
				} ?>
			</h1>

			<?php echo $_style['actionsbuttons']['dynamic']['document'] ?>

			<?php
			// breadcrumbs
			if($modx->config['use_breadcrumbs']) {
				$temp = array();
				$title = isset($content['pagetitle']) ? $content['pagetitle'] : $_lang['create_resource_title'];

				if(isset($_REQUEST['id']) && $content['parent'] != 0) {
					$bID = (int) $_REQUEST['id'];
					$temp = $modx->getParentIds($bID);
				} else if(isset($_REQUEST['pid'])) {
					$bID = (int) $_REQUEST['pid'];
					$temp = $modx->getParentIds($bID);
					array_unshift($temp, $bID);
				}

				if($temp) {
					$parents = implode(',', $temp);

					if(!empty($parents)) {
						$where = "FIND_IN_SET(id,'{$parents}') DESC";
						$rs = $modx->db->select('id, pagetitle', $tbl_site_content, "id IN ({$parents})", $where);
						while($row = $modx->db->getRow($rs)) {
							$out .= '<li class="breadcrumbs__li">
                                <a href="index.php?a=27&id=' . $row['id'] . '" class="breadcrumbs__a">' . htmlspecialchars($row['pagetitle'], ENT_QUOTES, $modx->config['modx_charset']) . '</a>
                                <span class="breadcrumbs__sep">&gt;</span>
                            </li>';
						}
					}
				}

				$out .= '<li class="breadcrumbs__li breadcrumbs__li_current">' . $title . '</li>';
				echo '<ul class="breadcrumbs">' . $out . '</ul>';
			}
			?>

			<!-- start main wrapper -->
			<div class="sectionBody">

				<div class="tab-pane" id="documentPane">
					<script type="text/javascript">
						tpSettings = new WebFXTabPane(document.getElementById("documentPane"), <?php echo $modx->config['remember_last_tab'] == 1 ? 'true' : 'false'; ?> );
					</script>

					<!-- General -->
					<?php
					$evtOut = $modx->invokeEvent('OnDocFormTemplateRender', array(
						'id' => $id
					));
					if(is_array($evtOut)) {
						echo implode('', $evtOut);
					} else {
						?>
						<div class="tab-page" id="tabGeneral">
							<h2 class="tab"><?php echo $_lang['settings_general'] ?></h2>
							<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tabGeneral"));</script>

							<table>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_title'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_title_help'] ?>"></i>
									</td>
									<td>
										<input name="pagetitle" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars(stripslashes($content['pagetitle'])) ?>" class="inputBox" onchange="documentDirty=true;" spellcheck="true" />
										<script>document.getElementsByName("pagetitle")[0].focus();</script>
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['long_title'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_long_title_help'] ?>"></i>
									</td>
									<td>
										<input name="longtitle" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars(stripslashes($content['longtitle'])) ?>" class="inputBox" onchange="documentDirty=true;" spellcheck="true" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_description'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_description_help'] ?>"></i>
									</td>
									<td>
										<input name="description" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars(stripslashes($content['description'])) ?>" class="inputBox" onchange="documentDirty=true;" spellcheck="true" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_alias'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_alias_help'] ?>"></i>
									</td>
									<td>
										<input name="alias" type="text" maxlength="100" value="<?php echo stripslashes($content['alias']) ?>" class="inputBox" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['link_attributes'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['link_attributes_help'] ?>"></i>
									</td>
									<td>
										<input name="link_attributes" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars(stripslashes($content['link_attributes'])) ?>" class="inputBox" onchange="documentDirty=true;" />
									</td>
								</tr>

								<?php if($content['type'] == 'reference' || $modx->manager->action == '72') { // Web Link specific ?>

									<tr>
										<td><span class="warning"><?php echo $_lang['weblink'] ?></span>
											<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_weblink_help'] ?>"></i>
										</td>
										<td>
											<i id="llock" class="<?php echo $_style["actions_chain"] ?>" onclick="enableLinkSelection(!allowLinkSelection);"></i>
											<input name="ta" id="ta" type="text" maxlength="255" value="<?php echo !empty($content['content']) ? stripslashes($content['content']) : 'http://'; ?>" class="inputBox" onchange="documentDirty=true;" /><input type="button" value="<?php echo $_lang['insert'] ?>" onclick="BrowseFileServer('ta')" />
										</td>
									</tr>

								<?php } ?>

								<tr>
									<td valign="top">
										<span class="warning"><?php echo $_lang['resource_summary'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_summary_help'] ?>" spellcheck="true"></i>
									</td>
									<td valign="top">
										<textarea id="introtext" name="introtext" class="inputBox" rows="3" cols="" onchange="documentDirty=true;"><?php echo $modx->htmlspecialchars(stripslashes($content['introtext'])) ?></textarea>
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['page_data_template'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_template_help'] ?>"></i>
									</td>
									<td>
										<select id="template" name="template" class="inputBox" onchange="templateWarning();">
											<option value="0">(blank)</option>
											<?php
											$field = "t.templatename, t.selectable, t.id, c.category";
											$from = "{$tbl_site_templates} AS t LEFT JOIN {$tbl_categories} AS c ON t.category = c.id";
											$rs = $modx->db->select($field, $from, '', 'c.category, t.templatename ASC');
											$currentCategory = '';
											while($row = $modx->db->getRow($rs)) {
												if($row['selectable'] != 1 && $row['id'] != $content['template']) {
													continue;
												};
												// Skip if not selectable but show if selected!
												$thisCategory = $row['category'];
												if($thisCategory == null) {
													$thisCategory = $_lang["no_category"];
												}
												if($thisCategory != $currentCategory) {
													if($closeOptGroup) {
														echo "\t\t\t\t\t</optgroup>\n";
													}
													echo "\t\t\t\t\t<optgroup label=\"$thisCategory\">\n";
													$closeOptGroup = true;
												}

												$selectedtext = ($row['id'] == $content['template']) ? ' selected="selected"' : '';

												echo "\t\t\t\t\t" . '<option value="' . $row['id'] . '"' . $selectedtext . '>' . $row['templatename'] . "</option>\n";
												$currentCategory = $thisCategory;
											}
											if($thisCategory != '') {
												echo "\t\t\t\t\t</optgroup>\n";
											}
											?>
										</select>
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_menu_title'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_menu_title_help'] ?>"></i>
									</td>
									<td>
										<input name="menutitle" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars(stripslashes($content['menutitle'])) ?>" class="inputBox" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_menu_index'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_menu_index_help'] ?>"></i>
									</td>
									<td>
										<input name="menuindex" type="text" maxlength="6" value="<?php echo $content['menuindex'] ?>" class="inputBox" onchange="documentDirty=true;" />
										<a href="javascript:;" class="btn btn-secondary" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;"><i class="<?php echo $_style['actions_angle_left'] ?>"></i></a>
										<a href="javascript:;" class="btn btn-secondary" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;"><i class="<?php echo $_style['actions_angle_right'] ?>"></i></a>
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_show_menu'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_show_menu_help'] ?>"></i>
									</td>
									<td>
										<input name="hidemenucheck" type="checkbox" class="checkbox" <?php echo $content['hidemenu'] != 1 ? 'checked="checked"' : '' ?> onclick="changestate(document.mutate.hidemenu);" /><input type="hidden" name="hidemenu" class="hidden" value="<?php echo ($content['hidemenu'] == 1) ? 1 : 0 ?>" />
									</td>
								</tr>
								<tr>
									<td valign="top">
										<span class="warning"><?php echo $_lang['resource_parent'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_parent_help'] ?>"></i>
									</td>
									<td valign="top">
										<?php
										$parentlookup = false;
										if(isset ($_REQUEST['id'])) {
											if($content['parent'] == 0) {
												$parentname = $site_name;
											} else {
												$parentlookup = $content['parent'];
											}
										} elseif(isset ($_REQUEST['pid'])) {
											if($_REQUEST['pid'] == 0) {
												$parentname = $site_name;
											} else {
												$parentlookup = $_REQUEST['pid'];
											}
										} elseif(isset($_POST['parent'])) {
											if($_POST['parent'] == 0) {
												$parentname = $site_name;
											} else {
												$parentlookup = $_POST['parent'];
											}
										} else {
											$parentname = $site_name;
											$content['parent'] = 0;
										}
										if($parentlookup !== false && is_numeric($parentlookup)) {
											$rs = $modx->db->select('pagetitle', $tbl_site_content, "id='{$parentlookup}'");
											$parentname = $modx->db->getValue($rs);
											if(!$parentname) {
												$modx->webAlertAndQuit($_lang["error_no_parent"]);
											}
										}
										?>
										<i id="plock" class="<?php echo $_style["actions_folder"] ?>" onclick="enableParentSelection(!allowParentSelection);"></i>
										<b><span id="parentName"><?php echo isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent'] ?> (<?php echo $parentname ?>)</span></b>
										<input type="hidden" name="parent" value="<?php echo isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent'] ?>" onchange="documentDirty=true;" />
									</td>
								</tr>
								<?php
								/*
								if($content['type'] == 'reference' || $modx->manager->action == '72') {
									?>
									<tr>
										<td colspan="2">
											<div class="split"></div>
										</td>
									</tr>
									<tr>
										<td>
											<span class="warning"><?php echo $_lang['which_editor_title'] ?></span></td>
										<td>
											<select id="which_editor" name="which_editor" onchange="changeRTE();">
												<?php
												// invoke OnRichTextEditorRegister event
												$evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
												if(is_array($evtOut)) {
													for($i = 0; $i < count($evtOut); $i++) {
														$editor = $evtOut[$i];
														echo "\t\t\t", '<option value="', $editor, '"', ($modx->config['which_editor'] == $editor ? ' selected="selected"' : ''), '>', $editor, "</option>\n";
													}
												}
												?>
											</select>
										</td>
									</tr>
									<?php
								}*/
								?>

								<?php if($content['type'] == 'document' || $modx->manager->action == '4') { ?>
									<tr>
										<td colspan="2">
											<hr>
											<!-- Content -->
											<label id="content_header"><?php echo $_lang['resource_content'] ?></label>
											<div id="content_body">
												<?php
												if(($content['richtext'] == 1 || $modx->manager->action == '4') && $use_editor == 1) {
													$htmlContent = $content['content'];
													?>
													<div>
														<textarea id="ta" name="ta" onchange="documentDirty=true;"><?php echo $modx->htmlspecialchars($htmlContent) ?></textarea>
														<span class="warning"><?php echo $_lang['which_editor_title'] ?></span>

														<select id="which_editor" name="which_editor" onchange="changeRTE();">
															<option value="none"><?php echo $_lang['none'] ?></option>
															<?php
															// invoke OnRichTextEditorRegister event
															$evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
															if(is_array($evtOut)) {
																for($i = 0; $i < count($evtOut); $i++) {
																	$editor = $evtOut[$i];
																	echo "\t\t\t", '<option value="', $editor, '"', ($modx->config['which_editor'] == $editor ? ' selected="selected"' : ''), '>', $editor, "</option>\n";
																}
															}
															?>
														</select>
													</div>
													<?php
													// Richtext-[*content*]
													$richtexteditorIds = array();
													$richtexteditorOptions = array();
													$richtexteditorIds[$modx->config['which_editor']][] = 'ta';
													$richtexteditorOptions[$modx->config['which_editor']]['ta'] = '';
												} else {
													echo "\t" . '<div><textarea class="phptextarea" id="ta" name="ta" onchange="documentDirty=true;">', $modx->htmlspecialchars($content['content']), '</textarea></div>' . "\n";
												}
												?>
											</div>
										</td>
									</tr>
									<!-- end .sectionBody -->
								<?php } ?>
							</table>

							<?php if(($content['type'] == 'document' || $modx->manager->action == '4') || ($content['type'] == 'reference' || $modx->manager->action == 72)) { ?>
								<?php
								$template = $default_template;
								if(isset ($_REQUEST['newtemplate'])) {
									$template = $_REQUEST['newtemplate'];
								} else {
									if(isset ($content['template'])) {
										$template = $content['template'];
									}
								}

								$field = "DISTINCT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value, tvtpl.rank as tvrank";
								$vs = array(
									$tbl_site_tmplvars,
									$tbl_site_tmplvar_templates,
									$tbl_site_tmplvar_contentvalues,
									$id,
									$tbl_site_tmplvar_access
								);
								$from = vsprintf("%s AS tv INNER JOIN %s AS tvtpl ON tvtpl.tmplvarid = tv.id
                         LEFT JOIN %s AS tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='%s'
                         LEFT JOIN %s AS tva ON tva.tmplvarid=tv.id", $vs);
								$dgs = $docgrp ? " OR tva.documentgroup IN ({$docgrp})" : '';
								$vs = array(
									$template,
									$_SESSION['mgrRole'],
									$dgs
								);
								$where = vsprintf("tvtpl.templateid='%s' AND (1='%s' OR ISNULL(tva.documentgroup) %s)", $vs);
								$rs = $modx->db->select($field, $from, $where, 'tvtpl.rank,tv.rank, tv.id');
								$limit = $modx->db->getRecordCount($rs);
								if($limit > 0) {
									?>
									<!-- Template Variables -->
									<div class="sectionHeader" id="tv_header"><?php echo $_lang['settings_templvars'] ?></div>
									<div class="sectionBody tmplvars" id="tv_body">
										<?php

										$tvsArray = $modx->db->makeArray($rs, 'name');
										echo "\t" . '<table>' . "\n";
										require_once(MODX_MANAGER_PATH . 'includes/tmplvars.inc.php');
										require_once(MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php');
										$i = 0;
										foreach($tvsArray as $row) {
											// Go through and display all Template Variables
											if($row['type'] == 'richtext' || $row['type'] == 'htmlarea') {
												// determine TV-options
												$tvOptions = $modx->parseProperties($row['elements']);
												if(!empty($tvOptions)) {
													// Allow different Editor with TV-option {"editor":"CKEditor4"} or &editor=Editor;text;CKEditor4
													$editor = isset($tvOptions['editor']) ? $tvOptions['editor'] : $modx->config['which_editor'];
												};
												// Add richtext editor to the list
												$richtexteditorIds[$editor][] = "tv" . $row['id'];
												$richtexteditorOptions[$editor]["tv" . $row['id']] = $tvOptions;
											}
											// splitter
											if($i++ > 0) {
												echo "\t\t", '<tr><td colspan="2"><div class="split"></div></td></tr>', "\n";
											}

											// post back value
											if(array_key_exists('tv' . $row['id'], $_POST)) {
												if(is_array($_POST['tv' . $row['id']])) {
													$tvPBV = implode('||', $_POST['tv' . $row['id']]);
												} else {
													$tvPBV = $_POST['tv' . $row['id']];
												}
											} else {
												$tvPBV = $row['value'];
											}

											$tvDescription = (!empty($row['description'])) ? '<br /><span class="comment">' . $row['description'] . '</span>' : '';
											$tvInherited = (substr($tvPBV, 0, 8) == '@INHERIT') ? '<br /><span class="comment inherited">(' . $_lang['tmplvars_inherited'] . ')</span>' : '';
											$tvName = $modx->hasPermission('edit_template') ? '<br/><small class="protectedNode">[*' . $row['name'] . '*]</small>' : '';

											echo "\t\t", '<tr><td><span class="warning">', $row['caption'] . $tvName, "</span>\n", "\t\t\t", $tvDescription, $tvInherited, "</td>\n", "\t\t\t", '<td><div style="position:relative;' . ($row['type'] == 'date' ? '' : '') . '">' . "\n", "\t\t\t" . renderFormElement($row['type'], $row['id'], $row['default_text'], $row['elements'], $tvPBV, '', $row, $tvsArray) . "\n" . "\t\t</div></td></tr>\n";
										}
										echo "\t</table>\n";
										?>
									</div>
									<!-- end .sectionBody .tmplvars -->

									<?php
								} else {
									// There aren't any Template Variables
									//echo "\t<p>" . $_lang['tmplvars_novars'] . "</p>\n";
								}
								?>
							<?php } ?>

						</div>
						<!-- end #tabGeneral -->

						<!-- Settings -->
						<div class="tab-page" id="tabSettings">
							<h2 class="tab"><?php echo $_lang['settings_page_settings'] ?></h2>
							<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tabSettings"));</script>

							<table>
								<?php $mx_can_pub = $modx->hasPermission('publish_document') ? '' : 'disabled="disabled" '; ?>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_published'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_published_help'] ?>"></i>
									</td>
									<td>
										<input <?php echo $mx_can_pub ?>name="publishedcheck" type="checkbox" class="checkbox" <?php echo (isset($content['published']) && $content['published'] == 1) || (!isset($content['published']) && $publish_default == 1) ? "checked" : '' ?> onclick="changestate(document.mutate.published);" />
										<input type="hidden" name="published" value="<?php echo (isset($content['published']) && $content['published'] == 1) || (!isset($content['published']) && $publish_default == 1) ? 1 : 0 ?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['page_data_publishdate'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_publishdate_help'] ?>"></i>
									</td>
									<td>
										<input type="text" id="pub_date" <?php echo $mx_can_pub ?>name="pub_date" class="DatePicker" value="<?php echo $content['pub_date'] == "0" || !isset($content['pub_date']) ? '' : $modx->toDateFormat($content['pub_date']) ?>" onblur="documentDirty=true;" />
										<a href="javascript:" onclick="document.mutate.pub_date.value=''; return true;" onmouseover="window.status='<?php echo $_lang['remove_date'] ?>'; return true;" onmouseout="window.status=''; return true;">
											<i class="<?php echo $_style["actions_calendar"] ?>" title="<?php echo $_lang['remove_date'] ?>"></i></a>
									</td>
								</tr>
								<tr>
									<td></td>
									<td>
										<em> <?php echo $modx->config['datetime_format']; ?> HH:MM:SS</em></td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['page_data_unpublishdate'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_unpublishdate_help'] ?>"></i>
									</td>
									<td>
										<input type="text" id="unpub_date" <?php echo $mx_can_pub ?>name="unpub_date" class="DatePicker" value="<?php echo $content['unpub_date'] == "0" || !isset($content['unpub_date']) ? '' : $modx->toDateFormat($content['unpub_date']) ?>" onblur="documentDirty=true;" />
										<a href="javascript:" onclick="document.mutate.unpub_date.value=''; return true;" onmouseover="window.status='<?php echo $_lang['remove_date'] ?>'; return true;" onmouseout="window.status=''; return true;">
											<i class="<?php echo $_style["actions_calendar"] ?>" title="<?php echo $_lang['remove_date'] ?>"></i></a>
									</td>
								</tr>
								<tr>
									<td></td>
									<td>
										<em> <?php echo $modx->config['datetime_format']; ?> HH:MM:SS</em>
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<div class='split'></div>
									</td>
								</tr>

								<?php

								if($_SESSION['mgrRole'] == 1 || $modx->manager->action != '27' || $_SESSION['mgrInternalKey'] == $content['createdby'] || $modx->hasPermission('change_resourcetype')) {
									?>
									<tr>
										<td>
											<span class="warning"><?php echo $_lang['resource_type'] ?></span>
											<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_type_message'] ?>"></i>
										</td>
										<td>
											<select name="type" class="inputBox" onchange="documentDirty=true;">
												<option value="document"<?php echo(($content['type'] == "document" || $modx->manager->action == '85' || $modx->manager->action == '4') ? ' selected="selected"' : ""); ?> ><?php echo $_lang["resource_type_webpage"]; ?></option>
												<option value="reference"<?php echo(($content['type'] == "reference" || $modx->manager->action == '72') ? ' selected="selected"' : ""); ?> ><?php echo $_lang["resource_type_weblink"]; ?></option>
											</select>
										</td>
									</tr>

									<tr>
										<td>
											<span class="warning"><?php echo $_lang['page_data_contentType'] ?></span>
											<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_contentType_help'] ?>"></i>
										</td>
										<td>
											<select name="contentType" class="inputBox" onchange="documentDirty=true;">
												<?php
												if(!$content['contentType']) {
													$content['contentType'] = 'text/html';
												}
												$custom_contenttype = (isset ($custom_contenttype) ? $custom_contenttype : "text/html,text/plain,text/xml");
												$ct = explode(",", $custom_contenttype);
												for($i = 0; $i < count($ct); $i++) {
													echo "\t\t\t\t\t" . '<option value="' . $ct[$i] . '"' . ($content['contentType'] == $ct[$i] ? ' selected="selected"' : '') . '>' . $ct[$i] . "</option>\n";
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td>
											<span class="warning"><?php echo $_lang['resource_opt_contentdispo'] ?></span>
											<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_contentdispo_help'] ?>"></i>
										</td>
										<td>
											<select name="content_dispo" class="inputBox" size="1" onchange="documentDirty=true;">
												<option value="0"<?php echo !$content['content_dispo'] ? ' selected="selected"' : '' ?>><?php echo $_lang['inline'] ?></option>
												<option value="1"<?php echo $content['content_dispo'] == 1 ? ' selected="selected"' : '' ?>><?php echo $_lang['attachment'] ?></option>
											</select>
										</td>
									</tr>

									<tr>
										<td colspan="2">
											<div class='split'></div>
										</td>
									</tr>
									<?php
								} else {
									if($content['type'] != 'reference' && $modx->manager->action != '72') {
										// non-admin managers creating or editing a document resource
										?>
										<input type="hidden" name="contentType" value="<?php echo isset($content['contentType']) ? $content['contentType'] : "text/html" ?>" />
										<input type="hidden" name="type" value="document" />
										<input type="hidden" name="content_dispo" value="<?php echo isset($content['content_dispo']) ? $content['content_dispo'] : '0' ?>" />
										<?php
									} else {
										// non-admin managers creating or editing a reference (weblink) resource
										?>
										<input type="hidden" name="type" value="reference" />
										<input type="hidden" name="contentType" value="text/html" />
										<?php
									}
								}//if mgrRole
								?>

								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_folder'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_folder_help'] ?>"></i>
									</td>
									<td>
										<input name="isfoldercheck" type="checkbox" class="checkbox" <?php echo ($content['isfolder'] == 1 || $modx->manager->action == '85') ? "checked" : '' ?> onclick="changestate(document.mutate.isfolder);" />
										<input type="hidden" name="isfolder" value="<?php echo ($content['isfolder'] == 1 || $modx->manager->action == '85') ? 1 : 0 ?>" onchange="documentDirty=true;" />
									</td>
								</tr>

								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_alvisibled'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_alvisibled_help'] ?>"></i>
									</td>
									<td>
										<input name="alias_visible_check" type="checkbox" class="checkbox" <?php echo (!isset($content['alias_visible']) || $content['alias_visible'] == 1) ? "checked" : '' ?> onclick="changestate(document.mutate.alias_visible);" /><input type="hidden" name="alias_visible" value="<?php echo (!isset($content['alias_visible']) || $content['alias_visible'] == 1) ? 1 : 0 ?>" />
									</td>
								</tr>

								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_richtext'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_richtext_help'] ?>"></i>
									</td>
									<td>
										<input name="richtextcheck" type="checkbox" class="checkbox" <?php echo $content['richtext'] == 0 && $modx->manager->action == '27' ? '' : "checked" ?> onclick="changestate(document.mutate.richtext);" />
										<input type="hidden" name="richtext" value="<?php echo $content['richtext'] == 0 && $modx->manager->action == '27' ? 0 : 1 ?>" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['track_visitors_title'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_trackvisit_help'] ?>"></i>
									</td>
									<td>
										<input name="donthitcheck" type="checkbox" class="checkbox" <?php echo ($content['donthit'] != 1) ? 'checked="checked"' : '' ?> onclick="changestate(document.mutate.donthit);" /><input type="hidden" name="donthit" value="<?php echo ($content['donthit'] == 1) ? 1 : 0 ?>" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['page_data_searchable'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_searchable_help'] ?>"></i>
									</td>
									<td>
										<input name="searchablecheck" type="checkbox" class="checkbox" <?php echo (isset($content['searchable']) && $content['searchable'] == 1) || (!isset($content['searchable']) && $search_default == 1) ? "checked" : '' ?> onclick="changestate(document.mutate.searchable);" /><input type="hidden" name="searchable" value="<?php echo (isset($content['searchable']) && $content['searchable'] == 1) || (!isset($content['searchable']) && $search_default == 1) ? 1 : 0 ?>" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['page_data_cacheable'] ?></span>
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['page_data_cacheable_help'] ?>"></i>
									</td>
									<td>
										<input name="cacheablecheck" type="checkbox" class="checkbox" <?php echo (isset($content['cacheable']) && $content['cacheable'] == 1) || (!isset($content['cacheable']) && $cache_default == 1) ? "checked" : '' ?> onclick="changestate(document.mutate.cacheable);" />
										<input type="hidden" name="cacheable" value="<?php echo (isset($content['cacheable']) && $content['cacheable'] == 1) || (!isset($content['cacheable']) && $cache_default == 1) ? 1 : 0 ?>" onchange="documentDirty=true;" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="warning"><?php echo $_lang['resource_opt_emptycache'] ?></span>
										<input type="hidden" name="syncsite" value="1" />
										<i class="<?php echo $_style["icons_tooltip"] ?>" data-tooltip="<?php echo $_lang['resource_opt_emptycache_help'] ?>"></i>
									</td>
									<td>
										<input name="syncsitecheck" type="checkbox" class="checkbox" checked="checked" onclick="changestate(document.mutate.syncsite);" />
									</td>
								</tr>
							</table>
						</div><!-- end #tabSettings -->
					<?php } ?>

					<!-- :TODO  tab META deleted in the future-->

					<?php if($modx->hasPermission('edit_doc_metatags') && $modx->config['show_meta']) {
						// get list of site keywords
						$keywords = array();
						$ds = $modx->db->select('id, keyword', $tbl_site_keywords, '', 'keyword ASC');
						while($row = $modx->db->getRow($ds)) {
							$keywords[$row['id']] = $row['keyword'];
						}
						// get selected keywords using document's id
						if(isset ($content['id']) && count($keywords) > 0) {
							$keywords_selected = array();
							$ds = $modx->db->select('keyword_id', $tbl_keyword_xref, "content_id='{$content['id']}'");
							while($row = $modx->db->getRow($ds)) {
								$keywords_selected[$row['keyword_id']] = ' selected="selected"';
							}
						}

						// get list of site META tags
						$metatags = array();
						$ds = $modx->db->select('id, name', $tbl_site_metatags);
						while($row = $modx->db->getRow($ds)) {
							$metatags[$row['id']] = $row['name'];
						}
						// get selected META tags using document's id
						if(isset ($content['id']) && count($metatags) > 0) {
							$metatags_selected = array();
							$ds = $modx->db->select('metatag_id', $tbl_site_content_metatags, "content_id='{$content['id']}'");
							while($row = $modx->db->getRow($ds)) {
								$metatags_selected[$row['metatag_id']] = ' selected="selected"';
							}
						}
						?>
						<!-- META Keywords -->
						<div class="tab-page" id="tabMeta">
							<h2 class="tab"><?php echo $_lang['meta_keywords'] ?></h2>
							<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tabMeta"));</script>

							<table>
								<tr>
									<td><?php echo $_lang['resource_metatag_help'] ?><br /><br />
										<table>
											<tr>
												<td><span class="warning"><?php echo $_lang['keywords'] ?></span><br />
													<select name="keywords[]" multiple="multiple" size="16" class="inputBox" onchange="documentDirty=true;">
														<?php
														foreach($keywords as $key => $value) {
															$selected = $keywords_selected[$key];
															echo "\t\t\t\t" . '<option value="' . $key . '"' . $selected . '>' . $value . "</option>\n";
														}
														?>
													</select>
													<br />
													<input type="button" value="<?php echo $_lang['deselect_keywords'] ?>" onclick="clearKeywordSelection();" />
												</td>
												<td><span class="warning"><?php echo $_lang['metatags'] ?></span><br />
													<select name="metatags[]" multiple="multiple" size="16" class="inputBox" onchange="documentDirty=true;">
														<?php
														foreach($metatags as $key => $value) {
															$selected = $metatags_selected[$key];
															echo "\t\t\t\t" . '<option value="' . $key . '"' . $selected . '>' . $value . "</option>\n";
														}
														?>
													</select>
													<br />
													<input type="button" value="<?php echo $_lang['deselect_metatags'] ?>" onclick="clearMetatagSelection();" />
												</td>
										</table>
									</td>
								</tr>
							</table>
						</div><!-- end #tabMeta -->

						<?php
					}

					/*******************************
					 * Document Access Permissions */
					if($use_udperms == 1) {
						$groupsarray = array();
						$sql = '';

						$documentId = ($modx->manager->action == '27' ? $id : (!empty($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent']));
						if($documentId > 0) {
							// Load up, the permissions from the parent (if new document) or existing document
							$rs = $modx->db->select('id, document_group', $tbl_document_groups, "document='{$documentId}'");
							while($currentgroup = $modx->db->getRow($rs)) $groupsarray[] = $currentgroup['document_group'] . ',' . $currentgroup['id'];

							// Load up the current permissions and names
							$vs = array(
								$tbl_document_group_names,
								$tbl_document_groups,
								$documentId
							);
							$from = vsprintf("%s AS dgn LEFT JOIN %s AS groups ON groups.document_group=dgn.id AND groups.document='%s'", $vs);
							$rs = $modx->db->select('dgn.*, groups.id AS link_id', $from, '', 'name');
						} else {
							// Just load up the names, we're starting clean
							$rs = $modx->db->select('*, NULL AS link_id', $tbl_document_group_names, '', 'name');
						}

						// retain selected doc groups between post
						if(isset($_POST['docgroups'])) {
							$groupsarray = array_merge($groupsarray, $_POST['docgroups']);
						}

						$isManager = $modx->hasPermission('access_permissions');
						$isWeb = $modx->hasPermission('web_access_permissions');

						// Setup Basic attributes for each Input box
						$inputAttributes = array(
							'type' => 'checkbox',
							'class' => 'checkbox',
							'name' => 'docgroups[]',
							'onclick' => 'makePublic(false);',
						);
						$permissions = array(); // New Permissions array list (this contains the HTML)
						$permissions_yes = 0; // count permissions the current mgr user has
						$permissions_no = 0; // count permissions the current mgr user doesn't have

						// Loop through the permissions list
						while($row = $modx->db->getRow($rs)) {

							// Create an inputValue pair (group ID and group link (if it exists))
							$inputValue = $row['id'] . ',' . ($row['link_id'] ? $row['link_id'] : 'new');
							$inputId = 'group-' . $row['id'];

							$checked = in_array($inputValue, $groupsarray);
							if($checked) {
								$notPublic = true;
							} // Mark as private access (either web or manager)

							// Skip the access permission if the user doesn't have access...
							if((!$isManager && $row['private_memgroup'] == '1') || (!$isWeb && $row['private_webgroup'] == '1')) {
								continue;
							}

							// Setup attributes for this Input box
							$inputAttributes['id'] = $inputId;
							$inputAttributes['value'] = $inputValue;
							if($checked) {
								$inputAttributes['checked'] = 'checked';
							} else {
								unset($inputAttributes['checked']);
							}

							// Create attribute string list
							$inputString = array();
							foreach($inputAttributes as $k => $v) $inputString[] = $k . '="' . $v . '"';

							// Make the <input> HTML
							$inputHTML = '<input ' . implode(' ', $inputString) . ' />';

							// does user have this permission?
							$from = "{$tbl_membergroup_access} AS mga, {$tbl_member_groups} AS mg";
							$vs = array(
								$row['id'],
								$_SESSION['mgrInternalKey']
							);
							$where = vsprintf("mga.membergroup=mg.user_group AND mga.documentgroup=%s AND mg.member=%s", $vs);
							$rsp = $modx->db->select('COUNT(mg.id)', $from, $where);
							$count = $modx->db->getValue($rsp);
							if($count > 0) {
								++$permissions_yes;
							} else {
								++$permissions_no;
							}
							$permissions[] = "\t\t" . '<li>' . $inputHTML . '<label for="' . $inputId . '">' . $row['name'] . '</label></li>';
						}
						// if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
						if($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0)) {
							$permissions = array();
						}

						// See if the Access Permissions section is worth displaying...
						if(!empty($permissions)) {
							// Add the "All Document Groups" item if we have rights in both contexts
							if($isManager && $isWeb) {
								array_unshift($permissions, "\t\t" . '<li><input type="checkbox" class="checkbox" name="chkalldocs" id="groupall"' . (!$notPublic ? ' checked="checked"' : '') . ' onclick="makePublic(true);" /><label for="groupall" class="warning">' . $_lang['all_doc_groups'] . '</label></li>');
							}
							// Output the permissions list...
							?>
							<!-- Access Permissions -->
							<div class="tab-page" id="tabAccess">
								<h2 class="tab" id="tab_access_header"><?php echo $_lang['access_permissions'] ?></h2>
								<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tabAccess"));</script>
								<script type="text/javascript">
									/* <![CDATA[ */
									function makePublic(b) {
										var notPublic = false;
										var f = document.forms['mutate'];
										var chkpub = f['chkalldocs'];
										var chks = f['docgroups[]'];
										if(!chks && chkpub) {
											chkpub.checked = true;
											return false;
										} else if(!b && chkpub) {
											if(!chks.length) notPublic = chks.checked;
											else for(var i = 0; i < chks.length; i++) if(chks[i].checked) notPublic = true;
											chkpub.checked = !notPublic;
										} else {
											if(!chks.length) chks.checked = (b) ? false : chks.checked;
											else for(var i = 0; i < chks.length; i++) if(b) chks[i].checked = false;
											chkpub.checked = true;
										}
									}

									/* ]]> */
								</script>
								<p><?php echo $_lang['access_permissions_docs_message'] ?></p>
								<ul>
									<?php echo implode("\n", $permissions) . "\n"; ?>
								</ul>
							</div><!--div class="tab-page" id="tabAccess"-->
							<?php
						} // !empty($permissions)
						elseif($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0) && ($_SESSION['mgrPermissions']['access_permissions'] == 1 || $_SESSION['mgrPermissions']['web_access_permissions'] == 1)) {
							?>
							<p><?php echo $_lang["access_permissions_docs_collision"]; ?></p>
							<?php

						}
					}
					/* End Document Access Permissions *
					 ***********************************/
					?>

					<input type="submit" name="save" style="display:none" />
					<?php

					// invoke OnDocFormRender event
					$evtOut = $modx->invokeEvent('OnDocFormRender', array(
						'id' => $id,
						'template' => $content['template']
					));

					if(is_array($evtOut)) {
						echo implode('', $evtOut);
					}
					?>
				</div><!--div class="tab-pane" id="documentPane"-->
			</div><!--div class="sectionBody"-->
		</fieldset>
	</form>

	<script type="text/javascript">
		storeCurTemplate();
	</script>
<?php
if(($content['richtext'] == 1 || $modx->manager->action == '4' || $modx->manager->action == '72') && $use_editor == 1) {
	if(is_array($richtexteditorIds)) {
		foreach($richtexteditorIds as $editor => $elements) {
			// invoke OnRichTextEditorInit event
			$evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
				'editor' => $editor,
				'elements' => $elements,
				'options' => $richtexteditorOptions[$editor]
			));
			if(is_array($evtOut)) {
				echo implode('', $evtOut);
			}
		}
	}
}

function getDefaultTemplate() {
	global $modx;

	switch($modx->config['auto_template_logic']) {
		case 'sibling':
			if(!isset($_GET['pid']) || empty($_GET['pid'])) {
				$site_start = $modx->config['site_start'];
				$where = "sc.isfolder=0 AND sc.id!='{$site_start}'";
				$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', $where, 'menuindex', 'ASC', 1);
				if(isset($sibl[0]['template']) && $sibl[0]['template'] !== '') {
					$default_template = $sibl[0]['template'];
				}
			} else {
				$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
				if(isset($sibl[0]['template']) && $sibl[0]['template'] !== '') {
					$default_template = $sibl[0]['template'];
				} else {
					$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 0, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
					if(isset($sibl[0]['template']) && $sibl[0]['template'] !== '') {
						$default_template = $sibl[0]['template'];
					}
				}
			}
			if(isset($default_template)) {
				break;
			} // If $default_template could not be determined, fall back / through to "parent"-mode
		case 'parent':
			if(isset($_REQUEST['pid']) && !empty($_REQUEST['pid'])) {
				$parent = $modx->getPageInfo($_REQUEST['pid'], 0, 'template');
				if(isset($parent['template'])) {
					$default_template = $parent['template'];
				}
			}
			break;
		case 'system':
		default: // default_template is already set
			$default_template = $modx->config['default_template'];
	}
	if(!isset($default_template)) {
		$default_template = $modx->config['default_template'];
	} // default_template is already set

	return $default_template;
}
