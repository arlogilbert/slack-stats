<?php
require_once('app/common.php');
auth();
$users = getUsers();
if (empty($_REQUEST['user']) || !isset($users[$_REQUEST['user']]) || $_REQUEST['user'] == "guest" || $_REQUEST['user'] == "full") {
	$restrict = false;
	if ($_REQUEST['user'] == "guest" || $_REQUEST['user'] == "full") {
		$restrict = $_REQUEST['user'];
	}
	$result = getStatsAll($users, $restrict);
	echo $templates->renderTemplate(
		new League\Plates\Template('home', [
			'authed' => true,
			'startDate' => date('m/d/Y', strtotime($result['startDate'])),
			'endDate' => date('m/d/Y', strtotime($result['endDate'])),
			'users' => $users,
			'data' => $result['data']
		])
	);
} else {
	$result = getStats($users);
	echo $templates->renderTemplate(
		new League\Plates\Template('stats', [
			'authed' => true,
			'startDate' => date('m/d/Y', strtotime($result['startDate'])),
			'endDate' => date('m/d/Y', strtotime($result['endDate'])),
			'days' => $result['days'],
			'users' => $users,
			'data' => $result['data']
		])
	);
}
