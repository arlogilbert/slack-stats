<?php

ini_set('session.gc_maxlifetime', 1800);

use Medoo\Medoo;
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ObjsUser;
use JoliCode\Slack\Api\Model\ObjsUserProfile;
use JoliCode\Slack\ClientFactory;
use TheIconic\Tracking\GoogleAnalytics\Analytics;
use League\Plates\Engine;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

$SlackToken = getenv('SlackToken');
$secretusername = getenv('secretusername');
$secretpassword = getenv('secretpassword');

$templates = Engine::create(__DIR__ . '/templates')
    ->getContainer()
    ->get('renderTemplate');

$database = new Medoo([
    'database_type' => 'mysql',
    'database_name' => getenv('database_name'),
    'server' => getenv('server'),
    'username' => getenv('username'),
    'password' => getenv('password')
]);

function getUsers()
{
    global $database;
    $dates = getDates();
    $query = "SELECT userid, realName FROM stats GROUP BY userId ORDER BY realName ASC";
    $data = $database->query($query)->fetchAll();
    $users = array();
    foreach ($data as $k => $v) {
        $users[$v['userid']] =  $v['realName'];
    }
    return $users;
}

function genDate($offsetDays, $suffix)
{
    $today = date('Y-m-d');
    $time = strtotime($today . ' ' . $offsetDays . ' Days');
    return date('Y-m-d' . " " . $suffix, $time);
}

function getDates()
{
    $start = empty($_REQUEST['startDate']) ? genDate(-7, '00:00:00') : date('Y-m-d 00:00:00', strtotime(str_replace('-', '/', $_REQUEST['startDate'])));
    $end = empty($_REQUEST['startDate']) ? genDate(0, '23:59:59') : date('Y-m-d 23:59:59', strtotime(str_replace('-', '/', $_REQUEST['endDate'])));
    $days = round((strtotime($end) - strtotime($start)) / (60 * 60 * 24));
    return array('start' => $start, 'end' => $end, 'days' => $days);
}

function getStatsAll($users, $restrict = false)
{
    global $database;
    $dates = getDates();
    /* get most recent status */
    $query = "SELECT t1.userId, t1.presence
	FROM stats t1
	WHERE t1.time = (SELECT MAX(t2.time)
	FROM stats t2)";
    $data = $database->query($query)->fetchAll();
    $status = array();
    foreach ($data as $k => $v) {
        $status[$v['userId']] = $v['presence'];
    }
    $query = "SELECT userId, realName, presence, count(*) as counter 
	FROM stats 
	WHERE time >= '" . $dates['start'] . "'
	AND time <= '" . $dates['end'] . "'";
    if ($restrict == "guest") {
        $query .= " AND email NOT LIKE '%" . getenv('TeamEmailDomain') . "'";
    }
    if ($restrict == "full") {
        $query .= " AND email LIKE '%" . getenv('TeamEmailDomain') . "'";
    }
    $query .= " GROUP BY userId, presence";
    $data = $database->query($query)->fetchAll();
    $newData = array();
    foreach ($data as $k => $v) {
        $newData[$v['userId']]['realName'] = $v['realName'];
        if (!isset($newData[$v['userId']]['activeCount'])) {
            $newData[$v['userId']]['activeCount'] = 0;
        }
        if (!isset($newData[$v['userId']]['inactiveCount'])) {
            $newData[$v['userId']]['inactiveCount'] = 0;
        }
        if ($v['presence'] == 'active') {
            $newData[$v['userId']]['activeCount'] = $v['counter'];
        }
        if ($v['presence'] == 'away') {
            $newData[$v['userId']]['inactiveCount'] = $v['counter'];
        }
        if (isset($status[$v['userId']])) {
            $newData[$v['userId']]['status'] = $status[$v['userId']];
        } else {
            $newData[$v['userId']]['status'] = 'away';
        }
    }
    foreach ($newData as $k => $v) {
        if ($newData[$k]['activeCount'] == 0) {
            $newData[$k]['activePercent'] = 0;
        } else if ($newData[$k]['inactiveCount'] == 0 && $newData[$k]['activeCount'] >= 1) {
            $newData[$k]['activePercent'] = 100;
        } else {
            $newData[$k]['activePercent'] = round(($newData[$k]['activeCount'] * 100) / ($newData[$k]['activeCount'] + $newData[$k]['inactiveCount']));
        }
    }
    aasort($newData, 'activePercent');
    return array(
        'startDate' => $dates['start'],
        'endDate' => $dates['end'],
        'data' => array_reverse($newData)
    );
}

function getStats($users)
{
    global $database;
    $dates = getDates();
    $query = "SELECT time, userId, realName, presence 
	FROM stats 
	WHERE time >= '" . $dates['start'] . "'
	AND time <= '" . $dates['end'] . "'
	AND userId = '" . stripslashes($_REQUEST['user']) . "'
	GROUP BY UNIX_TIMESTAMP(time) DIV 300, userId";
    $data = $database->query($query)->fetchAll();
    return array(
        'startDate' => $dates['start'],
        'endDate' => $dates['end'],
        'days' => $dates['days'],
        'data' => $data,
    );
}

function auth()
{
    session_set_cookie_params(1800);
    session_start();
    global $secretpassword, $secretusername, $templates;

    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] == true) {
        return true;
    } else {
        $error = null;
        if (!empty($_POST)) {
            $username = empty($_POST['username']) ? null : $_POST['username'];
            $password = empty($_POST['password']) ? null : $_POST['password'];
            if ($username == $secretusername && $password == $secretpassword) {
                $_SESSION['authenticated'] = true;
                return true;
            }
        }
    }
    echo $templates->renderTemplate(
        new League\Plates\Template('login')
    );
    exit;
}


/*********** SLACK CONNECTION AND INSERTION **************/
function slack($SlackToken, $database)
{
    // Initialize
    $client = ClientFactory::create($SlackToken);
    $users = [];
    $cursor = '';
    do {
        // This method require your token to have the scope "users:read"
        $response = $client->usersList([
            'limit' => 200,
            'presence' => true,
            'cursor' => $cursor,
        ]);
        if ($response->getOk()) {
            $users = array_merge($users, $response->getMembers());
            $cursor = $response->getResponseMetadata() ? $response->getResponseMetadata()->getNextCursor() : '';
        } else {
            echo 'Could not retrieve the users list.', PHP_EOL;
        }
    } while (!empty($cursor));
    if ($users) {
        foreach ($users as $k => $u) {
            if ($u->getIsBot() === false && $u->getDeleted() === false && $u->getRealName() != "Slackbot") {
                $database->insert('stats', [
                    'userId' => $u->getId(),
                    'realName' => $u->getRealName(),
                    'email' => $u->getProfile()->getEmail(),
                    'presence' => $u->getPresence()
                ]);
            }
        }
    }
}


function aasort(&$array, $key)
{
    $sorter = array();
    $ret = array();
    reset($array);
    foreach ($array as $ii => $va) {
        $sorter[$ii] = $va[$key];
    }
    asort($sorter);
    foreach ($sorter as $ii => $va) {
        $ret[$ii] = $array[$ii];
    }
    $array = $ret;
}
