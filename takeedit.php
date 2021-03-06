<?php
require_once("include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

$action = $_POST['action'];

function bark($msg) {
	global $lang_takeedit;
	stderr($lang_takeedit['std_edit_failed'], $msg);
}

if (!mkglobal("id:name:descr:type")){
	global $lang_takeedit;
	bark($lang_takeedit['std_missing_form_data']);
}

$id = 0 + $id;
if (!$id)
	die();


$res = sql_query("SELECT category, owner, filename, save_as, anonymous, picktype, picktime, added FROM torrents WHERE id = ?", [$id]);
$row = _mysql_fetch_array($res);
$torrentAddedTimeString = $row['added'];
if (!$row)
	die();

if ($CURUSER["id"] != $row["owner"] && get_user_class() < $torrentmanage_class)
	bark($lang_takeedit['std_not_owner']);
$oldcatmode = get_single_value("categories","mode","WHERE id=".sqlesc($row['category']));

$updateset = array();
$args = [];

//$fname = $row["filename"];
//preg_match('/^(.+)\.torrent$/si', $fname, $matches);
//$shortfname = $matches[1];
//$dname = $row["save_as"];

$dl_url = trim($_POST['dl-url']);
if($dl_url && !filter_var($dl_url, FILTER_VALIDATE_URL)) {
  bark('无效的下载链接');
}
$updateset[] = 'dl_url = ' . sqlesc($dl_url);

$url = parse_imdb_id($_POST['url']);

if ($enablenfo_main=='yes'){
$nfoaction = $_POST['nfoaction'];
if ($nfoaction == "update")
{
	$nfofile = $_FILES['nfo'];
	if (!$nfofile) die("No data " . var_dump($_FILES));
	if ($nfofile['size'] > 65535)
		bark($lang_takeedit['std_nfo_too_big']);
	$nfofilename = $nfofile['tmp_name'];
	if (@is_uploaded_file($nfofilename) && @filesize($nfofilename) > 0)
		$updateset[] = "nfo = " . sqlesc(str_replace("\x0d\x0d\x0a", "\x0d\x0a", file_get_contents($nfofilename)));
	$Cache->delete_value('nfo_block_torrent_id_'.$id);
}
elseif ($nfoaction == "remove"){
	$updateset[] = "nfo = ''";
	$Cache->delete_value('nfo_block_torrent_id_'.$id);
}
}

$catid = (0 + $type);
if (!is_valid_id($catid))
bark($lang_takeedit['std_missing_form_data']);
if (!$name || !$descr) {
  bark($lang_takeedit['std_missing_form_data']);
}
require_once('HTML/BBCodePreparser.php');
$preparser = new BBCodePreparser($descr);
$descr = $preparser->getText();

$newcatmode = get_single_value("categories","mode","WHERE id=".sqlesc($catid));
if ($enablespecial == 'yes' && get_user_class() >= $movetorrent_class)
	$allowmove = true; //enable moving torrent to other section
else $allowmove = false;
if ($oldcatmode != $newcatmode && !$allowmove)
	bark($lang_takeedit['std_cannot_move_torrent']);
$updateset[] = "anonymous = '" . ($_POST["anonymous"] ? "yes" : "no") . "'";
$updateset[] = "name = ?";
$args[]= add_space_between_words($name);
$updateset[] = "descr = " . sqlesc($descr);
$updateset[] = "url = " . sqlesc($url);
$updateset[] = "small_descr = ?";
$args[]= add_space_between_words($_POST["small_descr"]);
//$updateset[] = "ori_descr = " . sqlesc($descr);
$updateset[] = "category = " . sqlesc($catid);
$updateset[] = "source = " . sqlesc(0 + $_POST["source_sel"]);
$updateset[] = "medium = " . sqlesc(0 + $_POST["medium_sel"]);
$updateset[] = "codec = " . sqlesc(0 + $_POST["codec_sel"]);
$updateset[] = "standard = " . sqlesc(0 + $_POST["standard_sel"]);
$updateset[] = "processing = " . sqlesc(0 + $_POST["processing_sel"]);
$updateset[] = "team = " . sqlesc(0 + $_POST["team_sel"]);
$updateset[] = "audiocodec = " . sqlesc(0 + $_POST["audiocodec_sel"]);
//Added by bluemonster 20111026
if (checkPrivilege(['Torrent', 'oday'])) {
  $updateset[] = "oday = '" . ($_POST["sel_oday"] ? "yes" : "no") . "'";
}
$destoring = false;
if (permissionAuth("setstoring",$CURUSER['usergroups'],$CURUSER['class'])) {
  $updateset[] = "storing = '" . ($_POST["sel_storing"] ? "1" : "0") . "'";
  $destoring = $_POST["sel_storing"]? false: true;
}

if (get_user_class() >= $torrentmanage_class) {
	if ($_POST["banned"]) {
		$updateset[] = "banned = 'yes'";
		$_POST["visible"] = 0;
	}
	else
		$updateset[] = "banned = 'no'";
}
$updateset[] = "visible = '" . ($_POST["visible"] ? "yes" : "no") . "'";
if (checkPrivilege(['Torrent', 'pr'])) {
	if(!isset($_POST["sel_spstate"]) || $_POST["sel_spstate"] == 1)
		$updateset[] = "sp_state = 1";
	elseif((0 + $_POST["sel_spstate"]) == 2)
		$updateset[] = "sp_state = 2";
	elseif((0 + $_POST["sel_spstate"]) == 3)
		$updateset[] = "sp_state = 3";
	elseif((0 + $_POST["sel_spstate"]) == 4)
		$updateset[] = "sp_state = 4";
	elseif((0 + $_POST["sel_spstate"]) == 5)
		$updateset[] = "sp_state = 5";
	elseif((0 + $_POST["sel_spstate"]) == 6)
		$updateset[] = "sp_state = 6";
	elseif((0 + $_POST["sel_spstate"]) == 7)
		$updateset[] = "sp_state = 7";

	//promotion expiration type
	if(!isset($_POST["promotion_time_type"]) || $_POST["promotion_time_type"] == 0) {
		$updateset[] = "promotion_time_type = 0";
		$updateset[] = "promotion_until = '0000-00-00 00:00:00'";
	} elseif ($_POST["promotion_time_type"] == 1) {
		$updateset[] = "promotion_time_type = 1";
		$updateset[] = "promotion_until = '0000-00-00 00:00:00'";
	} elseif ($_POST["promotion_time_type"] == 2) {
		if ($_POST["promotionuntil"] && strtotime($torrentAddedTimeString) <= strtotime($_POST["promotionuntil"])) {
			$updateset[] = "promotion_time_type = 2";
			$updateset[] = "promotion_until = ".sqlesc($_POST["promotionuntil"]);
		} else {
			$updateset[] = "promotion_time_type = 0";
			$updateset[] = "promotion_until = '0000-00-00 00:00:00'";
		}
	}
}
if (checkPrivilege(['Torrent', 'sticky'])) {
	if(($_POST["sel_posstate"]) == 'normal'){
		$updateset[] = "pos_state = 'normal'";
		$updateset[] = "pos_state_until = '0000-00-00 00:00:00'";
	}
	else
	{
		if ($_POST["posstateuntil"] && strtotime($torrentAddedTimeString) <= strtotime($_POST["posstateuntil"])) {
			if(($_POST["sel_posstate"]) == 'sticky'){
				$updateset[] = "pos_state = 'sticky'";
			}
			else{
				$updateset[] = "pos_state = 'random'";
				}
			$updateset[] = "pos_state_until = ".sqlesc($_POST["posstateuntil"]);
		} 
		else {
			$updateset[] = "pos_state = 'normal'";
			$updateset[] = "pos_state_until = '0000-00-00 00:00:00'";
		}
	}
}

$pick_info = "";
if(get_user_class()>=$torrentmanage_class && $CURUSER['picker'] == 'yes')
{
	if((0 + $_POST["sel_recmovie"]) == 0)
	{
		if($row["picktype"] != 'normal')
			$pick_info = ", recomendation canceled!";
		$updateset[] = "picktype = 'normal'";
		$updateset[] = "picktime = '0000-00-00 00:00:00'";
	}
	elseif((0 + $_POST["sel_recmovie"]) == 1)
	{
		if($row["picktype"] != 'hot')
			$pick_info = ", recommend as hot movie";
		$updateset[] = "picktype = 'hot'";
		$updateset[] = "picktime = ". sqlesc(date("Y-m-d H:i:s"));
	}
	elseif((0 + $_POST["sel_recmovie"]) == 2)
	{
		if($row["picktype"] != 'classic')
			$pick_info = ", recommend as classic movie";
		$updateset[] = "picktype = 'classic'";
		$updateset[] = "picktime = ". sqlesc(date("Y-m-d H:i:s"));
	}
	elseif((0 + $_POST["sel_recmovie"]) == 3)
	{
		if($row["picktype"] != 'recommended')
			$pick_info = ", recommend as recommended movie";
		$updateset[] = "picktype = 'recommended'";
		$updateset[] = "picktime = ". sqlesc(date("Y-m-d H:i:s"));
	}
}

$args[]= $id;
sql_query("UPDATE torrents SET " . join(",", $updateset) . " WHERE id = ?", $args);
if($destoring===true){
	$updateList_res = sql_query("SELECT keeper_id FROM storing_records WHERE torrent_id = $id AND checkout = 0") or sqlerr(__FILE__,__LINE__);
	if(_mysql_num_rows($updateList_res)!=0){
		while($updateList = _mysql_fetch_assoc($updateList_res)){
			$outtime_res = sql_query("SELECT seedtime FROM snatched WHERE torrentid = $id AND userid = $updateList[keeper_id]") or sqlerr(__FILE__,__LINE__);
			$outtime = _mysql_fetch_assoc($outtime_res);
			
			if($outtime['seedtime']){
				$subject = $lang_takeedit['sbj_storing_canceled'];
				$msg = $lang_takeedit['txt_your_torrent']."[torrent=$id]".$lang_takeedit['txt_has_been']."[user=$CURUSER[id]]".$lang_takeedit['txt_storing_canceled'];

				$keeper_id = $updateList['keeper_id'];
				sql_query("UPDATE storing_records SET out_seedtime = $outtime[seedtime], out_date = NOW(), checkout = 1 WHERE torrent_id = $id AND keeper_id = $keeper_id AND checkout = 0") or sqlerr(__FILE__,__LINE__);
				if(_mysql_affected_rows()){
					send_pm(0,$keeper_id,$subject,$msg);
				}
			}
		}
	}
}
if($CURUSER["id"] == $row["owner"])
{
	if ($row["anonymous"]=='yes')
	{
		write_log("Torrent $id ($name) was edited by Anonymous" . $pick_info);
	}
	else
	{
		write_log("Torrent $id ($name) was edited by $CURUSER[username]" . $pick_info );
	}
}
else
{
	write_log("Torrent $id ($name) was edited by $CURUSER[username], Mod Edit" . $pick_info );
}
$returl = "details.php?id=$id&edited=1";
if (isset($_POST["returnto"]))
	$returl = $_POST["returnto"];
header("Refresh: 0; url=$returl");
