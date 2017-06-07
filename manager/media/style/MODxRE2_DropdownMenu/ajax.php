<?php

define('MODX_API_MODE', true);

include_once("../../../../index.php");

$modx->db->connect();

if(empty ($modx->config)) {
	$modx->getSettings();
}

$modx->invokeEvent("OnWebPageInit");

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') || ($_SERVER['REQUEST_METHOD'] != 'POST')) {
	$modx->sendRedirect($modx->config['site_url']);
}

header('content-type: application/json');

$action = $_REQUEST['a'];

if(isset($action)) {
	switch($action) {
		case '76': {

			if(isset($_REQUEST['tab'])) {
				$sql = '';
				$a = '';

				if($_REQUEST['tab'] == 0) {
					$a = 16;
					$sql = $modx->db->query('SELECT t1.*, t1.templatename AS name
					FROM ' . $modx->getFullTableName('site_templates') . ' AS t1
					#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
					ORDER BY t1.templatename ASC');

				} else if($_REQUEST['tab'] == 1) {
					$a = 301;
					$sql = $modx->db->query('SELECT t1.*
					FROM ' . $modx->getFullTableName('site_tmplvars') . ' AS t1
					#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
					ORDER BY t1.name ASC');

				} else if($_REQUEST['tab'] == 2) {
					$a = 78;
					$sql = $modx->db->query('SELECT t1.*
					FROM ' . $modx->getFullTableName('site_htmlsnippets') . ' AS t1
					#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
					ORDER BY t1.name ASC');

				} else if($_REQUEST['tab'] == 3) {
					$a = 22;
					$sql = $modx->db->query('SELECT t1.*
					FROM ' . $modx->getFullTableName('site_snippets') . ' AS t1
					#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
					ORDER BY t1.name ASC');

				} else if($_REQUEST['tab'] == 4) {
					$a = 102;
					$sql = $modx->db->query('SELECT t1.*
					FROM ' . $modx->getFullTableName('site_plugins') . ' AS t1
					#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
					ORDER BY t1.name ASC');

				}

				if($modx->db->getRecordCount($sql)) {
					while($row = $modx->db->getRow($sql)) {
						echo '<li><a href="index.php?a=' . $a . '&id=' . $row['id'] . '" target="main">' . $row['name'] . ' <small>(' . $row['id'] . ')</small></a></li>';
					}
				}
			}

			break;
		}

		case '75': {
			$a = 12;

			$sql = $modx->db->query('SELECT t1.*, t1.username AS name
				FROM ' . $modx->getFullTableName('manager_users') . ' AS t1
				#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
				ORDER BY t1.username ASC');

			if($modx->db->getRecordCount($sql)) {
				while($row = $modx->db->getRow($sql)) {
					echo '<li><a href="index.php?a=' . $a . '&id=' . $row['id'] . '" target="main">' . $row['name'] . ' <small>(' . $row['id'] . ')</small></a></li>';
				}
			}

			break;
		}

		case '99': {
			$a = 88;

			$sql = $modx->db->query('SELECT t1.*, t1.username AS name
				FROM ' . $modx->getFullTableName('web_users') . ' AS t1
				#LEFT JOIN ' . $modx->getFullTableName('categories') . ' AS t2 ON t2.id=t1.category
				ORDER BY t1.username ASC');

			if($modx->db->getRecordCount($sql)) {
				while($row = $modx->db->getRow($sql)) {
					echo '<li><a href="index.php?a=' . $a . '&id=' . $row['id'] . '" target="main">' . $row['name'] . ' <small>(' . $row['id'] . ')</small></a></li>';
				}
			}

			break;
		}
	}
}
