<?php
if(IN_MANAGER_MODE != "true") {
	die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}

switch($modx->manager->action) {
	case 16:
		if(!$modx->hasPermission('edit_template')) {
			$modx->webAlertAndQuit($_lang["error_no_privileges"]);
		}
		break;
	case 19:
		if(!$modx->hasPermission('new_template')) {
			$modx->webAlertAndQuit($_lang["error_no_privileges"]);
		}
		break;
	default:
		$modx->webAlertAndQuit($_lang["error_no_privileges"]);
}

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$tbl_site_templates = $modx->getFullTableName('site_templates');

// check to see the snippet editor isn't locked
if($lockedEl = $modx->elementIsLocked(1, $id)) {
	$modx->webAlertAndQuit(sprintf($_lang['lock_msg'], $lockedEl['username'], $_lang['template']));
}
// end check for lock

// Lock snippet for other users to edit
$modx->lockElement(1, $id);

$content = array();
if(!empty($id)) {
	$rs = $modx->db->select('*', $tbl_site_templates, "id='{$id}'");
	$content = $modx->db->getRow($rs);
	if(!$content) {
		$modx->webAlertAndQuit("No database record has been found for this template.");
	}

	$_SESSION['itemname'] = $content['templatename'];
	if($content['locked'] == 1 && $_SESSION['mgrRole'] != 1) {
		$modx->webAlertAndQuit($_lang["error_no_privileges"]);
	}
} else {
	$_SESSION['itemname'] = $_lang["new_template"];
	$content['category'] = intval($_REQUEST['catid']);
}

if($modx->manager->hasFormValues()) {
	$modx->manager->loadFormValues();
}

$content = array_merge($content, $_POST);
$selectable = $modx->manager->action == 19 ? 1 : $content['selectable'];

// Add lock-element JS-Script
$lockElementId = $id;
$lockElementType = 1;
require_once(MODX_MANAGER_PATH . 'includes/active_user_locks.inc.php');
?>
<script type="text/javascript">

	var actions = {
		save: function() {
			documentDirty = false;
			form_save = true;
			document.mutate.save.click();
			saveWait('mutate');
		},
		duplicate: function() {
			if(confirm("<?php echo $_lang['confirm_duplicate_record'] ?>") === true) {
				documentDirty = false;
				document.location.href = "index.php?id=<?php echo $_REQUEST['id']; ?>&a=96";
			}
		},
		delete: function() {
			if(confirm("<?php echo $_lang['confirm_delete_template']; ?>") === true) {
				documentDirty = false;
				document.location.href = "index.php?id=" + document.mutate.id.value + "&a=21";
			}
		},
		cancel: function() {
			documentDirty = false;
			document.location.href = 'index.php?a=76';
		}
	}

</script>

<form name="mutate" method="post" action="index.php">
	<?php
	// invoke OnTempFormPrerender event
	$evtOut = $modx->invokeEvent("OnTempFormPrerender", array("id" => $id));
	if(is_array($evtOut)) {
		echo implode("", $evtOut);
	}
	?>
	<input type="hidden" name="a" value="20">
	<input type="hidden" name="id" value="<?php echo $_REQUEST['id']; ?>">
	<input type="hidden" name="mode" value="<?php echo $modx->manager->action; ?>">

	<h1>
		<i class="fa fa-newspaper-o"></i><?php echo $_lang['template_title']; ?>
	</h1>

	<?php echo $_style['actionsbuttons']['dynamic']['element'] ?>


	<div class="tab-pane" id="templatesPane">
		<script type="text/javascript">
			tp = new WebFXTabPane(document.getElementById("templatesPane"), <?php echo $modx->config['remember_last_tab'] == 1 ? 'true' : 'false'; ?> );
		</script>

		<div class="tab-page" id="tabTemplate">
			<h2 class="tab"><?php echo $_lang["template_edit_tab"] ?></h2>
			<script type="text/javascript">tp.addTabPage(document.getElementById("tabTemplate"));</script>

			<p class="element-edit-message">
				<?php echo $_lang['template_msg']; ?>
			</p>

			<div class="form-group">
				<div class="row form-row">
					<label class="col-lg-2">
						<?php echo $_lang['template_name']; ?>
					</label>
					<div class="col-lg-10">
						<input name="templatename" type="text" maxlength="100" value="<?php echo $modx->htmlspecialchars($content['templatename']); ?>" class="form-control" onchange="documentDirty=true;"><span class="warning" id='savingMessage'></span>
						<?php if($id == $modx->config['default_template']) {
							echo ' <b>' . $_lang['defaulttemplate_title'] . '</b>';
						} ?>
						<script>document.getElementsByName("templatename")[0].focus();</script>
					</div>
				</div>
				<div class="row form-row">
					<label class="col-lg-2"><?php echo $_lang['template_desc']; ?></label>
					<div class="col-lg-10">
						<input name="description" type="text" maxlength="255" value="<?php echo $modx->htmlspecialchars($content['description']); ?>" class="form-control" onchange="documentDirty=true;">
					</div>
				</div>
				<div class="row form-row">
					<label class="col-lg-2"><?php echo $_lang['existing_category']; ?></label>
					<div class="col-lg-10">
						<select name="categoryid" class="form-control" onchange="documentDirty=true;">
							<option>&nbsp;</option>
							<?php
							include_once(MODX_MANAGER_PATH . 'includes/categories.inc.php');
							foreach(getCategories() as $n => $v) {
								echo "<option value='" . $v['id'] . "'" . ($content["category"] == $v["id"] ? " selected='selected'" : "") . ">" . $modx->htmlspecialchars($v["category"]) . "</option>";
							}
							?>
						</select>
					</div>
				</div>
				<div class="row form-row">
					<label class="col-lg-2"><?php echo $_lang['new_category']; ?></label>
					<div class="col-lg-10">
						<input name="newcategory" type="text" maxlength="45" value="<?php echo isset($content['newcategory']) ? $content['newcategory'] : '' ?>" class="form-control" onchange="documentDirty=true;">
					</div>
				</div>
			</div>
			<?php if($modx->hasPermission('save_role')): ?>
				<div class="form-group">
					<label class="form-check-label">
						<input name="locked" type="checkbox" <?php echo $content['locked'] == 1 ? "checked='checked'" : ""; ?> class="form-check-input"> <?php echo $_lang['lock_template']; ?></label>
					<small class="form-text text-muted">
						<?php echo $_lang['lock_template_msg']; ?>
					</small>
				</div>
				<div class="form-group">
					<label class="form-check-label">
						<input name="selectable" type="checkbox" <?php echo $selectable == 1 ? "checked='checked'" : ""; ?> class="form-check-input"> <?php echo $_lang['template_selectable']; ?></label>
				</div>
			<?php endif; ?>

			<!-- HTML text editor start -->
			<label><?php echo $_lang['template_code']; ?></label>
			<div class="row">
				<textarea dir="ltr" name="post" class="phptextarea" style="width:100%; height: 370px;" onChange="documentDirty=true;"><?php echo isset($content['post']) ? $modx->htmlspecialchars($content['post']) : $modx->htmlspecialchars($content['content']); ?></textarea>
			</div>
			<!-- HTML text editor end -->

			<input type="submit" name="save" style="display:none">

			<?php
			$selectedTvs = array();
			if(!isset($_POST['assignedTv'])) {
				$rs = $modx->db->select(sprintf("tv.name AS tvname, tv.id AS tvid, tr.templateid AS templateid, tv.description AS tvdescription, tv.caption AS tvcaption, tv.locked AS tvlocked, if(isnull(cat.category),'%s',cat.category) AS category", $_lang['no_category']), sprintf("%s tv
                LEFT JOIN %s tr ON tv.id=tr.tmplvarid
                LEFT JOIN %s cat ON tv.category=cat.id", $modx->getFullTableName('site_tmplvars'), $modx->getFullTableName('site_tmplvar_templates'), $modx->getFullTableName('categories')), "templateid='{$id}'", "tr.rank DESC, tv.rank DESC, tvcaption DESC, tvid DESC"     // workaround for correct sort of none-existing ranks
				);
				while($row = $modx->db->getRow($rs)) {
					$selectedTvs[$row['tvid']] = $row;
				}
				$selectedTvs = array_reverse($selectedTvs, true);       // reverse ORDERBY DESC
			}

			$unselectedTvs = array();
			$rs = $modx->db->select(sprintf("tv.name AS tvname, tv.id AS tvid, tr.templateid AS templateid, tv.description AS tvdescription, tv.caption AS tvcaption, tv.locked AS tvlocked, if(isnull(cat.category),'%s',cat.category) AS category, cat.id as catid", $_lang['no_category']), sprintf("%s tv
	    LEFT JOIN %s tr ON tv.id=tr.tmplvarid
	    LEFT JOIN %s cat ON tv.category=cat.id", $modx->getFullTableName('site_tmplvars'), $modx->getFullTableName('site_tmplvar_templates'), $modx->getFullTableName('categories')), "", "category, tvcaption");
			while($row = $modx->db->getRow($rs)) {
				$unselectedTvs[$row['tvid']] = $row;
			}

			// Catch checkboxes if form not validated
			if(isset($_POST['assignedTv'])) {
				$selectedTvs = array();
				foreach($_POST['assignedTv'] as $tvid) {
					if(isset($unselectedTvs[$tvid])) {
						$selectedTvs[$tvid] = $unselectedTvs[$tvid];
					}
				};
			}

			$total = count($selectedTvs);
			?>
		</div>
		<div class="tab-page" id="tabAssignedTVs">
			<h2 class="tab"><?php echo $_lang["template_assignedtv_tab"] ?></h2>
			<script type="text/javascript">tp.addTabPage(document.getElementById("tabAssignedTVs"));
			</script>
			<input type="hidden" name="tvsDirty" id="tvsDirty" value="0">
			<?php
			if($total > 0) {
				echo '<p>' . $_lang['template_tv_msg'] . '</p>';
			}
			if($modx->hasPermission('save_template') && $total > 1 && $id) {
				echo sprintf('<ul class="actionButtons"><li><a href="index.php?a=117&amp;id=%s">%s</a></li></ul>', $id, $_lang['template_tv_edit']);
			}

			// Selected TVs
			$tvList = '';
			if($total > 0) {
				$tvList .= '<ul>';
				foreach($selectedTvs as $row) {
					$desc = !empty($row['tvdescription']) ? '&nbsp;&nbsp;<small>(' . $row['tvdescription'] . ')</small>' : '';
					$locked = $row['tvlocked'] ? ' <em>(' . $_lang['locked'] . ')</em>' : "";
					$tvList .= sprintf('<li><label class="form-check-label"><input name="assignedTv[]" value="%s" type="checkbox" class="form-check-input" checked="checked" onchange="documentDirty=true;jQuery(\'#tvsDirty\').val(\'1\');">%s <small>(%s)</small> - %s%s</label>%s <a href="index.php?id=%s&a=301&or=%s&oid=%s">%s</a></li>', $row['tvid'], $row['tvname'], $row['tvid'], $row['tvcaption'], $desc, $locked, $row['tvid'], $modx->manager->action, $id, $_lang['edit']);
				}
				$tvList .= '</ul>';

			} else {
				echo $_lang['template_no_tv'];
			}
			echo $tvList;

			// Unselected TVs
			$tvList = '<hr/><p>' . $_lang['template_notassigned_tv'] . '</p><ul>';
			$preCat = '';
			$insideUl = 0;
			while($row = array_shift($unselectedTvs)) {
				if(isset($selectedTvs[$row['tvid']])) {
					continue;
				} // Skip selected
				$row['category'] = stripslashes($row['category']); //pixelchutes
				if($preCat !== $row['category']) {
					$tvList .= $insideUl ? '</ul>' : '';
					$tvList .= '<li><strong>' . $row['category'] . ($row['catid'] != '' ? ' <small>(' . $row['catid'] . ')</small>' : '') . '</strong><ul>';
					$insideUl = 1;
				}

				$desc = !empty($row['tvdescription']) ? '&nbsp;&nbsp;<small>(' . $row['tvdescription'] . ')</small>' : '';
				$locked = $row['tvlocked'] ? ' <em>(' . $_lang['locked'] . ')</em>' : "";
				$tvList .= sprintf('<li><label class="form-check-label"><input name="assignedTv[]" value="%s" type="checkbox" class="form-check-input" onchange="documentDirty=true;jQuery(\'#tvsDirty\').val(\'1\');">%s <small>(%s)</small> - %s%s</label>%s <a href="index.php?id=%s&a=301&or=%s">%s</a></li>', $row['tvid'], $row['tvname'], $row['tvid'], $row['tvcaption'], $desc, $locked, $row['tvid'], $modx->manager->action, $_lang['edit']);
				$tvList .= '</li>';

				$preCat = $row['category'];
			}
			$tvList .= $insideUl ? '</ul>' : '';
			$tvList .= '</ul>';
			echo $tvList;

			?></div>
		<?php
		// invoke OnTempFormRender event
		$evtOut = $modx->invokeEvent("OnTempFormRender", array("id" => $id));
		if(is_array($evtOut)) {
			echo implode("", $evtOut);
		}
		?>
	</div>
</form>