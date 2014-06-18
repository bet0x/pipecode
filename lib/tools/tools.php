<?
//
// tools - general utility functions
// Copyright (C) 1998-2014 Bryan Beicker <bryan@beicker.com>
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

function auth_check($sign_in_page = "/sign_in")
{
	global $AUTH_KEY;
	global $auth_user_id;

	$auth = @$_COOKIE["auth"];
	$map = map_from_url_string($auth);

	$exp = @$map["exp"];
	$exp = mktime(substr($exp, 8, 2), substr($exp, 10, 2), substr($exp, 12, 2), substr($exp, 4, 2), substr($exp, 6, 2), substr($exp, 0, 4));
	if (time() > $exp) {
		header("Location: $sign_in_page");
		die();
	}

	$auth_user_id = @$map["user"];
	if (!string_uses($auth_user_id, "[0-9]")) {
		die("invalid user [$auth_user_id]");
	}
	$auth_user_id = (int) $auth_user_id;

	$hash = @$map["hash"];
	$test = crypt_sha256($AUTH_KEY . "exp=" . date("YmdHis", $exp) . "&user=$auth_user_id");
	if ($hash != $test) {
		header("Location: $sign_in_page");
		die();
	}
}


function auth_sign_in($next_page = "/")
{
	global $AUTH_EXP;
	global $AUTH_KEY;

	$username = http_post_string("username", array("len" => 50, "valid" => "[a-z][0-9]"));
	$password = http_post_string("password", array("len" => 50));

	if (!string_uses(substr($username, 0, 1), "[a-z]")) {
		die("invalid username [$username]");
	}
	$row = run_sql("select user_id, password, salt from auth.user_list where username = ?", array($username));
	if (count($row) == 0) {
		die("no such user [$username]");
	}
	if (crypt_sha256($password . $row[0]["salt"]) != $row[0]["password"]) {
		die("wrong password");
	}

	$expire = time() + $AUTH_EXP;
	$cookie = "exp=" . date("YmdHis", $expire) . "&user=" . $row[0]["user_id"];
	$cookie .= "&hash=" . crypt_sha256($AUTH_KEY . $cookie);
	setcookie("auth", $cookie, $expire);
	header("Location: $next_page");
	die();
}


function auth_sign_out($next_page = "/")
{
	setcookie("auth", "", time() - (5 * 365 * 24 * 60 * 60));
	header("Expires: Thur, 28 Aug 1980 10:00:00 GMT");
	header("Location: $next_page");
}


function beg_form($action = "", $method = "post")
{
	writeln('<form' . ($action == '' ? '' : ' action="' . $action . '"' ) . ($method == 'post' || $method == 'file' ? ' method="post"' : '' ) . ($method == 'file' ? ' enctype="multipart/form-data"' : '') . '>');
}


function beg_tab($caption = "", $a = array())
{
	$s = '<table';
	if (array_key_exists("id", $a)) {
		$s .= ' id="' . $a["id"] . '"';
	}
	$s .= ' class="zebra"';
	if (array_key_exists("visible", $a)) {
		if (!$a["visible"] || $a["visible"] == 0) {
			$s .= ' style="display: none"';
		}
	}
	$s .= '>';
	writeln($s);
	if ($caption != "") {
		writeln('	<tr>');
		if (array_key_exists("colspan", $a)) {
			writeln('		<th colspan="' . $a["colspan"] . '">' . $caption . '</th>');
		} else {
			writeln('		<th>' . $caption . '</th>');
		}
		writeln('	</tr>');
	}
}


function cache_delete($key)
{
	global $cache_enabled;
	global $apc_enabled;
	global $memcache;
	global $memcache_open;

	if (!$cache_enabled) {
		return;
	}

	if ($apc_enabled) {
		apc_delete($key);
		return;
	}

	if (!$memcache_open) {
		cache_open();
	}

	$memcache->delete($key);
}


function cache_get($key)
{
	global $cache_enabled;
	global $apc_enabled;
	global $memcache;
	global $memcache_open;

	if (!$cache_enabled) {
		return false;
	}

	if ($apc_enabled) {
		return apc_fetch($key);
	}

	if (!$memcache_open) {
		cache_open();
	}

	return $memcache->get($key);
}


function cache_has($key)
{
	global $cache_enabled;

	if (!$cache_enabled) {
		return false;
	}

	$s = cache_get($key);
	if ($s === false) {
		return false;
	}

	return true;
}


function cache_open()
{
	global $cache_enabled;
	global $apc_enabled;
	global $memcache;
	global $memcache_open;
	global $memcache_server;

	if (!$cache_enabled) {
		return;
	}

	if ($apc_enabled || $memcache_open) {
		return;
	}

	$memcache = new Memcache;
	$memcache->connect($memcache_server, 11211);
	$memcache_open = true;
}


function cache_set($key, $data = NULL, $expire = -1)
{
	global $cache_enabled;
	global $apc_enabled;
	global $memcache;
	global $memcache_open;
	global $cache_expire;

	if (!$cache_enabled) {
		return;
	}

	if ($expire == -1) {
		if (empty($cache_expire)) {
			$expire = 60;
		} else {
			$expire = $cache_expire;
		}
	}

	if ($apc_enabled) {
		apc_store($key, $data, $expire);
		return;
	}

	if (!$memcache_open) {
		cache_open();
	}

	$memcache->set($key, $data, false, $expire);
}


function crypt_base64_decode($src)
{
	return base64_decode($src);
}


function crypt_base64_encode($src)
{
	return base64_encode($src);
}


function crypt_binary_decode($binary)
{
	$s = "";

	for ($i = 0; $i < strlen($binary); $i += 8) {
		$n = substr($binary, $i + 7, 1);
		$n = $n + substr($binary, $i + 6, 1) * 2;
		$n = $n + substr($binary, $i + 5, 1) * 4;
		$n = $n + substr($binary, $i + 4, 1) * 8;
		$n = $n + substr($binary, $i + 3, 1) * 16;
		$n = $n + substr($binary, $i + 2, 1) * 32;
		$n = $n + substr($binary, $i + 1, 1) * 64;
		$n = $n + substr($binary, $i, 1) * 128;

		$s .= Chr($n);
	}

	return $s;
}


function crypt_binary_encode($data)
{
	$s = "";

	for ($i = 0; $i < strlen($data); $i++) {
		$n = ord(substr($data, $i, 1));

		$b = $n % 2;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;
		$n = floor($n / 2);
		$b = $n % 2 . $b;

		$s .= $b;
	}

	return $s;
}


function crypt_compress($data)
{
	if ($data == "") {
		return $data;
	}

	return @gzcompress($data);
}


function crypt_crc32($src)
{
	return string_pad(dechex(crc32($src)), 8);
}


function crypt_crc32_file($path)
{
	return string_pad(@hash_file("crc32b", $path), 8);
}


function crypt_escape($src)
{
	$s = str_replace("\r", "\\r", $src);
	$s = str_replace("\n", "\\n", $s);
	$s = str_replace("\\", "\\\\", $s);
	$s = str_replace(":", "\\:", $s);

	return $s;
}


function crypt_hex_decode($data)
{
	$s = "";
	$i = 0;

	if ($data == "") {
		return $s;
	}
	if (strlen($data) % 2 == 1) {
		return "";
	}
	do {
		$s .= chr(hexdec($data[$i] . $data[($i + 1)]));
		$i += 2;
	} while ($i < strlen($data));

	return $s;
}


function crypt_hex_encode($data)
{
	return bin2hex($data);
}


function crypt_md5($data)
{
	return md5($data);
}


function crypt_md5_file($path)
{
	return @md5_file($path);
}


function crypt_rc4($data, $pwd)
{
	$key = array();
	$box = array();
	$cipher = "";

	$pwd_length = strlen($pwd);
	$data_length = strlen($data);

	for ($i = 0; $i < 256; $i++) {
		$key[$i] = ord($pwd[$i % $pwd_length]);
		$box[$i] = $i;
	}
	for ($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $key[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for ($a = $j = $i = 0; $i < $data_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$k = $box[(($box[$a] + $box[$j]) % 256)];
		$cipher .= chr(ord($data[$i]) ^ $k);
	}

	return $cipher;
}


function crypt_sha256($data)
{
	return hash("sha256", $data);
}


function crypt_sha256_file($path)
{
	return @hash_file("sha256", $path);
}


function crypt_span($src, $size = 76)
{
	$a = array();
	for ($i = 0; $i < strlen($src); $i += $size) {
		$a[] = substr($src, $i, $size);
	}

	return implode("\r\n", $a);
}


function crypt_tag_decode($src)
{
	$s = str_replace("&" . "quot;", "\"", $src);
	$s = str_replace("&cr;", "\r", $s);
	$s = str_replace("&lf;", "\n", $s);
	$s = str_replace("&" . "amp;", "&", $s);

	return $s;
}


function crypt_tag_encode($src)
{
	$s = str_replace("&", "&" . "amp;", $src);
	$s = str_replace("\"", "&" . "quot;", $s);
	$s = str_replace("\r", "&cr;", $s);
	$s = str_replace("\n", "&lf;", $s);

	return $s;
}


function crypt_uncompress($data, $uncompressed_size)
{
	if ($data == "") {
		return $data;
	}

	return @gzuncompress($data, $uncompressed_size);
}


function db_del_rec($table, $id)
{
	global $db_table;

	if (!array_key_exists($table, $db_table)) {
		die("unknown table [$table]");
	}
	$key = $db_table[$table]["key"];
	if (is_array($key)) {
		if (!is_array($id)) {
			die("error [id is not the full key] function [db_del_rec] table [$table] id [$id]");
		}
		$sql = "delete from $table where ";
		$a = array();
		for ($i = 0; $i < count($key); $i++) {
			$sql .= $key[$i] . " = ? and ";
			$a[] = $id[$key[$i]];
		}
		$sql = substr($sql, 0, -5);
		run_sql($sql, $a);
	} else {
		run_sql("delete from $table where $key = ?", array($id));
	}
}


function db_get_conf($table, $id = false)
{
	global $db_table;
	global $cache_enabled;

	if ($id !== false) {
		$key = $db_table[$table]["key"];
	}

	if ($cache_enabled) {
		if ($id === false) {
			$cache_key = "$table.conf";
		} else {
			$cache_key = "$table.conf.$id";
		}
		$s = cache_get($cache_key);
		if ($s !== false) {
			return map_from_conf_string($s);
		}
	}

	$map = array();
	$row = run_sql("select name, value from default_conf where conf = ?", array($table));
	for ($i = 0; $i < count($row); $i++) {
		$map[$row[$i]["name"]] = $row[$i]["value"];
	}

	if ($id === false) {
		$row = run_sql("select name, value from $table");
	} else {
		$row = run_sql("select name, value from $table where $key = ?", array($id));
	}
	for ($i = 0; $i < count($row); $i++) {
		$map[$row[$i]["name"]] = $row[$i]["value"];
	}

	ksort($map);
	if ($cache_enabled) {
		cache_set($cache_key, map_to_conf_string($map));
	}

	return $map;
}


function db_get_list($table, $order = "", $where = array())
{
	global $db_table;

	if (!array_key_exists($table, $db_table)) {
		die("unknown table [$table]");
	}
	$key = $db_table[$table]["key"];
	$col = $db_table[$table]["col"];

	$a = array();
	if (count($where) > 0) {
		$w = " where ";
		$k = array_keys($where);
		for ($i = 0; $i < count($where); $i++) {
			$w .= $k[$i] . " = ? and ";
			$a[] = $where[$k[$i]];
		}
		$w = substr($w, 0, -5);
	} else {
		$w = "";
	}

	if ($order != "") {
		$o = " order by $order";
	} else {
		$o = "";
	}

	$row = run_sql("select * from $table$w$o", $a);
	$a = array();
	for ($i = 0; $i < count($row); $i++) {
		if (is_array($key)) {
			$b = array();
			for ($j = 0; $j < count($key); $j++) {
				$b[] = $row[$i][$key[$j]];
			}
			$n = implode(".", $b);
		} else {
			$n = $row[$i][$key];
		}
		$b = array();
		for ($j = 0; $j < count($col); $j++) {
			$b[$col[$j]] = $row[$i][$col[$j]];
		}
		$a[$n] = $b;
	}

	return $a;
}


function db_get_rec($table, $id)
{
	global $db_table;
	global $cache_enabled;

	if ($cache_enabled && !is_array($id)) {
		$cache_key = "$table.rec.$id";
		$s = cache_get($cache_key);
		if ($s !== false) {
			return map_from_conf_string($s);
		}
	}

	if (!array_key_exists($table, $db_table)) {
		die("unknown table [$table]");
	}
	$key = $db_table[$table]["key"];
	$col = $db_table[$table]["col"];

	if (is_array($id)) {
		$k = array_keys($id);
		$a = array();
		$sql = "select * from $table where ";
		for ($i = 0; $i < count($id); $i++) {
			$sql .= $k[$i] . ' = ? and ';
			$a[] = $id[$k[$i]];
		}
		$sql = substr($sql, 0, -5);
		$row = run_sql($sql, $a);
	} else {
		$row = run_sql("select * from $table where $key = ?", array($id));
	}
	if (count($row) == 0) {
		if (is_array($id)) {
			die("record not found - table [$table] id [" . map_to_tag_string($id) . "]");
		} else {
			die("record not found - table [$table] id [$id]");
		}
	}
	$rec = array();
	for ($i = 0; $i < count($col); $i++) {
		$rec[$col[$i]] = $row[0][$col[$i]];
	}

	if ($cache_enabled && !is_array($id)) {
		cache_set($cache_key, map_to_conf_string($rec));
	}

	return $rec;
}


function db_has_database($database)
{
	$row = run_sql("show databases like '$database'");
	if (count($row) == 0) {
		return false;
	}
	return true;
}


function db_has_rec($table, $id)
{
	global $db_table;
	global $cache_enabled;

	if ($cache_enabled && !is_array($id)) {
		$cache_key = "$table.rec.$id";
		if (cache_has($cache_key)) {
			return true;
		}
	}

	if (!array_key_exists($table, $db_table)) {
		die("unknown table [$table]");
	}
	$key = $db_table[$table]["key"];
	if (is_array($id)) {
		$k = array_keys($id);
		$a = array();
		$sql = "select * from $table where ";
		for ($i = 0; $i < count($id); $i++) {
			$sql .= $k[$i] . ' = ? and ';
			$a[] = $id[$k[$i]];
		}
		$sql = substr($sql, 0, -5);
		$row = run_sql($sql, $a);
	} else {
		$row = run_sql("select * from $table where $key = ?", array($id));
	}
	if (count($row) == 0) {
		return false;
	}
	return true;
}


function db_set_conf($table, $map, $id = false)
{
	global $db_table;
	global $cache_enabled;

	if ($id !== false) {
		$key = $db_table[$table]["key"];
	}
	$current = array();
	$default = array();

	if ($id === false) {
		$row = run_sql("select name, value from $table");
	} else {
		$row = run_sql("select name, value from $table where $key = ?", array($id));
	}
	for ($i = 0; $i < count($row); $i++) {
		$current[$row[$i]["name"]] = $row[$i]["value"];
	}

	$row = run_sql("select name, value from default_conf where conf = ?", array($table));
	for ($i = 0; $i < count($row); $i++) {
		$default[$row[$i]["name"]] = $row[$i]["value"];
	}

	$k = array_keys($map);
	for ($i = 0; $i < count($k); $i++) {
		$new_name = $k[$i];
		$new_value = $map[$new_name];

		if (array_key_exists($new_name, $current)) {
			if (array_key_exists($new_name, $default) && $new_value == $default[$new_name]) {
				if ($id === false) {
					run_sql("delete from $table where name = ?", array($new_name));
				} else {
					run_sql("delete from $table where $key = ? and name = ?", array($id, $new_name));
				}
			} else if ($current[$new_name] != $new_value) {
				if ($id === false) {
					run_sql("update $table set value = ? where name = ?", array($new_value, $new_name));
				} else {
					run_sql("update $table set value = ? where $key = ? and name = ?", array($new_value, $id, $new_name));
				}
			}
		} else {
			$insert = true;
			if (array_key_exists($new_name, $default)) {
				if ($new_value == $default[$new_name]) {
					$insert = false;
				}
			}
			if ($insert) {
				if ($id === false) {
					run_sql("insert into $table (name, value) values (?, ?)", array($new_name, $new_value));
				} else {
					run_sql("insert into $table ($key, name, value) values (?, ?, ?)", array($id, $new_name, $new_value));
				}
			}
		}
	}

	if ($cache_enabled) {
		if ($id === false) {
			$cache_key = "$table.conf";
		} else {
			$cache_key = "$table.conf.$id";
		}
		cache_set($cache_key, map_to_conf_string($map));
	}
}


function db_set_rec($table, $rec)
{
	global $db_table;
	global $cache_enabled;

	if (!array_key_exists($table, $db_table)) {
		die("unknown table [$table]");
	}
	$key = $db_table[$table]["key"];
	$col = $db_table[$table]["col"];
	if (is_array($key)) {
		$id = array();
		for ($i = 0; $i < count($key); $i++) {
			$id[$key[$i]] = $rec[$key[$i]];
		}
	} else {
		$id = $rec[$key];
	}

	$insert = true;
	$auto = false;
	if ($id === 0 && array_key_exists("auto", $db_table[$table])) {
		$auto = true;
	} else if (db_has_rec($table, $id)) {
		$insert = false;
	}

	$a = array();
	if ($insert) {
		$sql = "insert into $table (";
		for ($i = 0; $i < count($col); $i++) {
			if (!$auto || $col[$i] != $key) {
				$sql .= $col[$i] . ", ";
				$a[] = $rec[$col[$i]];
			}
		}
		if ($auto) {
			$count = count($col) - 2;
		} else {
			$count = count($col) - 1;
		}
		$sql = substr($sql, 0, -2) . ") values (" . str_repeat("?, ", $count) . "?)";
		run_sql($sql, $a);
	} else {
		$sql = "update $table set ";
		for ($i = 0; $i < count($col); $i++) {
			$is_key = false;
			if (is_array($key)) {
				if (in_array($col[$i], $key)) {
					$is_key = true;
				}
			} else {
				if ($col[$i] == $key) {
					$is_key = true;
				}
			}
			if (!$is_key) {
				$sql .= $col[$i] . " = ?, ";
				$a[] = $rec[$col[$i]];
			}
		}
		$sql = substr($sql, 0, -2) . " where ";
		if (is_array($key)) {
			for ($i = 0; $i < count($key); $i++) {
				$sql .= $key[$i] . " = ? and ";
				$a[] = $rec[$key[$i]];
			}
			$sql = substr($sql, 0, -5);
		} else {
			$sql .= "$key = ?";
			$a[] = $id;
		}
		run_sql($sql, $a);
	}

	if ($cache_enabled) {
		$cache_key = "$table.rec.$id";
		cache_set($cache_key, map_to_conf_string($rec));
	}
}


function default_error($text)
{
	if (defined("FATAL_ERROR")) {
		fatal_error($text);
	}
	die("error: $text");
}


function end_form()
{
	writeln('</form>');
}


function end_tab()
{
	writeln('</table>');
}


function fs_append($path, $data)
{
	$f = fopen($path, "a");
	if ($f === false) {
		return false;
	}
	$bytes = fwrite($f, $data);
	fclose($f);

	if ($bytes == strlen($data)) {
		return true;
	}

	return false;
}


function fs_base_name($path)
{
	if (substr($path, -1) == "/") {
		return "";
	}

	$pos = strrpos($path, "/");
	if ($pos === false) {
		return $path;
	}

	return substr($path, $pos + 1);
}


function fs_dir($path)
{
	$d = @opendir($path);
	if (!$d) {
		return array();
	}

	$list = array();
	while (($f = readdir($d)) !== false) {
		if ($f != "." && $f != "..") {
			$list[] = $f;
		}
	}
	closedir($d);
	sort($list);

	return $list;
}


function fs_dir_name($path)
{
	$pos = strrpos($path, "/");
	if ($pos === false) {
		return "";
	}
	if ($pos == 0) {
		return "/";
	}

	return substr($path, 0, $pos);
}


function fs_ext($path)
{
	if (strtolower(substr($path, 0, 4)) == "http") {
		$pos = strpos($path, "?");
		if ($pos !== false) {
			$path = substr($path, 0, $pos);
		}
		$pos = strpos($path, "#");
		if ($pos !== false) {
			$path = substr($path, 0, $pos);
		}
	}

	$pos = strrpos($path, ".");
	if ($pos === false) {
		return "";
	}

	return strtolower(substr($path, $pos + 1));
}


function fs_is_dir($path)
{
	return @is_dir($path);
}


function fs_is_file($path)
{
	return @is_file($path);
}


function fs_make_dir($path)
{
	return mkdir($path, 0777, true);
}


function fs_remove($path)
{
	if (fs_is_dir($path)) {
		$a = fs_dir($path);
		$rc = true;
		for ($i = 0; $i < count($a); $i++) {
			$rc = $rc && fs_remove($path . "/" . $a[$i]);
		}
		return $rc && fs_remove_dir($path);
	} else {
		return fs_unlink($path);
	}
}


function fs_remove_dir($path)
{
	return @rmdir($path);
}


function fs_rename($old_path, $new_path)
{
	return rename($old_path, $new_path);
}


function fs_size($path)
{
	$size = @filesize($path);
	if ($size === false) {
		return 0;
	}

	return $size;
}


function fs_slap($path, $body)
{
	if (@file_put_contents($path, $body) == strlen($body)) {
		return true;
	} else {
		return false;
	}
}


function fs_slurp($path)
{
	return @file_get_contents($path);
}


function fs_time($path)
{
	$time = @filemtime($path);
	if ($time === false) {
		return 0;
	}

	return $time;
}


function fs_touch($path, $time = -1)
{
	if ($time == -1) {
		return @touch($path);
	} else {
		return @touch($path, $time);
	}
}


function fs_unlink($path)
{
	return @unlink($path);
}


function header_expires()
{
	header("Expires: Thur, 28 Aug 1980 10:00:00 GMT");
}


function http_cookie_bool($name, $arg = array())
{
	return http_test_bool($name, "cookie", $arg);
}


function http_cookie_date($name, $arg = array())
{
	return http_test_date($name, "cookie", $arg);
}


function http_cookie_int($name, $arg = array())
{
	return http_test_int($name, "cookie", $arg);
}


function http_cookie_string($name, $arg = array())
{
	return http_test_string($name, "cookie", $arg);
}


function http_get($name)
{
	$request_uri = $_SERVER["REQUEST_URI"];
	$pos = strpos($request_uri, "?");
	if ($pos === false) {
		return "";
	}
	$query_string = substr($request_uri, $pos + 1);
	$map = map_from_url_string($query_string);
	if (!array_key_exists($name, $map)) {
		return "";
	}
	return $map[$name];
}


function http_get_bool($name, $arg = array())
{
	return http_test_bool($name, "get", $arg);
}


function http_get_date($name, $arg = array())
{
	return http_test_date($name, "get", $arg);
}


function http_get_int($name, $arg = array())
{
	return http_test_int($name, "get", $arg);
}


function http_get_string($name, $arg = array())
{
	return http_test_string($name, "get", $arg);
}


function http_post($submit = "")
{
	if ($submit == "") {
		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			return true;
		}
	} else {
		if (array_key_exists($submit, $_POST)) {
			return true;
		}
	}

	return false;
}


function http_post_bool($name, $arg = array())
{
	return http_test_bool($name, "post", $arg);
}


function http_post_date($name, $arg = array())
{
	return http_test_date($name, "post", $arg);
}


function http_post_int($name, $arg = array())
{
	return http_test_int($name, "post", $arg);
}


function http_post_string($name, $arg = array())
{
	return http_test_string($name, "post", $arg);
}


function http_slap($url, $data, $timeout = 5)
{
	if (function_exists("curl_init")) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}

	if (ini_get("allow_url_fopen")) {
		$context = stream_context_create(array('http' => array(
		    'method' => "POST",
		    'header' => "Content-Type: application/x-www-form-urlencoded",
		    'timeout' => 14 * 60,
		    'content' => $data
		)));

		return @file_get_contents($url, false, $context);
	}

	die("unable to slap - url [$url] allow_url_fopen [disabled] curl [disabled]");
}


function http_slurp($url, $timeout = 5)
{
	if (function_exists("curl_init")) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}

	if (ini_get("allow_url_fopen")) {
		return @file_get_contents($url);
	}

	die("unable to slurp - url [$url] allow_url_fopen [disabled] curl [disabled]");
}


function http_test_bool($name, $method, $arg = array())
{
	if ($method == "get") {
		$value = @$_GET[$name];
		if ($value == "") {
			$value = http_get($name);
		}
	} else if ($method == "post") {
		$value = @$_POST[$name];
	} else if ($method == "cookie") {
		$value = @$_COOKIE[$name];
	} else {
		$value = $name;
	}
	if (array_key_exists("numeric", $arg)) {
		$numeric = $arg["numeric"];
	} else {
		$numeric = false;
	}
	if ($value == 1 || $value == "1" || $value == "on" || $value == "true") {
		if ($numeric) {
			return 1;
		} else {
			return true;
		}
	}
	if ($numeric) {
		return 0;
	}

	return false;
}


function http_test_date($name, $method, $arg = array())
{
	if ($method == "get") {
		$value = @$_GET[$name];
		if ($value == "") {
			$value = http_get($name);
		}
	} else if ($method == "post") {
		$value = @$_POST[$name];
	} else if ($method == "cookie") {
		$value = @$_COOKIE[$name];
	} else {
		$value = $name;
	}
	// 1262423045
	if (strlen($value) <= 10) {
		if (string_uses($value, "[0-9]")) {
			return (int) $value;
		}
	}
	// 20100102030405
	if (strlen($value) == 14) {
		if (string_uses($value, "[0-9]")) {
			$time = strtotime(substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2) . " " . substr($value, 8, 2) . ":" . substr($value, 10, 2) . ":" . substr($value, 12, 2));
			if (!($time === false)) {
				return $time;
			}
		}
	}
	// 2010-01-02 03:04:05
	if (strlen($value) == 19) {
		if (string_uses($value, "[0-9]- ")) {
			$time = strtotime($value);
			if (!($time === false)) {
				return $time;
			}
		}
	}
	$time = strtotime($value);
	if (!($time === false)) {
		return $time;
	}
	if (array_key_exists("required", $arg)) {
		$required = $arg["required"];
	} else {
		$required = true;
	}
	if (array_key_exists("default", $arg)) {
			return $arg["default"];
	} else if ($required) {
		default_error("value not found - method [$method] type [date] name [$name]");
	}

	return 0;
}


function http_test_int($name, $method, $arg = array())
{
	if ($method == "get") {
		$value = @$_GET[$name];
		if ($value == "") {
			$value = http_get($name);
		}
	} else if ($method == "post") {
		$value = @$_POST[$name];
	} else if ($method == "cookie") {
		$value = @$_COOKIE[$name];
	} else {
		$value = $name;
	}
	if (array_key_exists("required", $arg)) {
		$required = $arg["required"];
	} else {
		$required = true;
	}
	if ($value == "") {
		if (array_key_exists("default", $arg)) {
			return $arg["default"];
		} else if ($required) {
			default_error("value not found - method [$method] type [int] name [$name]");
		} else {
			return 0;
		}
	}
	if (!string_uses($value, "[0-9]-")) {
		default_error("invalid value - method [$method] type [int] name [$name] value [$value]");
	}
	if (strlen($value) == 1) {
		if ($value == "-") {
			default_error("invalid value - method [$method] type [int] name [$name] value [$value]");
		}
	} else if (strlen($value) > 1) {
		if (!string_uses(substr($value, 1), "[0-9]")) {
			default_error("invalid value - method [$method] type [int] name [$name] value [$value]");
		}
	}

	return (int) $value;
}


function http_test_string($name, $method, $arg = array())
{
	if ($method == "get") {
		$value = @$_GET[$name];
		if ($value == "") {
			$value = http_get($name);
		}
	} else if ($method == "post") {
		$value = @$_POST[$name];
	} else if ($method == "cookie") {
		$value = @$_COOKIE[$name];
	} else {
		$value = $name;
	}
	$value = trim($value);
	if (array_key_exists("len", $arg)) {
		$len = $arg["len"];
	} else {
		$len = 0;
	}
	if (array_key_exists("required", $arg)) {
		$required = $arg["required"];
	} else {
		$required = true;
	}
	if (array_key_exists("valid", $arg)) {
		$valid = $arg["valid"];
	} else {
		$valid = "[a-z][A-Z][0-9]`~!@#\$%^&*()_+-=[]\\{}|;':\",./<>? ";
	}
	if ($value == "") {
		if (array_key_exists("default", $arg)) {
			return $arg["default"];
		} else if ($required) {
			default_error("value not found - method [$method] type [text] name [$name]");
		} else {
			return "";
		}
	}
	if (!string_uses($value, $valid)) {
		default_error("invalid value - method [$method] type [string] name [$name] value [$value]");
	}
	if ($len > 0 && strlen($value) > $len) {
		return substr($value, 0, $len);
	}

	return $value;
}


function map_from_attribute_string($s)
{
	$map = array();

	$a = explode(";", $s);
	for ($i = 0; $i < count($a); $i++) {
		$value = $a[$i];
		$name = trim(string_next($value, ":"));
		$value = trim($value);
		if ($name != "") {
			$map[$name] = $value;
		}
	}

	return $map;
}


function map_from_conf_string($s)
{
	$map = array();

	$s = str_replace("\r\n", "\n", $s);
	$a = explode("\n", $s);
	for ($i = 0; $i < count($a); $i++) {
		$p = strpos($a[$i], ":");
		if ($p > 0) {
			$name = substr($a[$i], 0, $p);
			$value = trim(substr($a[$i], $p + 1));
			$map[$name] = $value;
		}
	}

	return $map;
}


function map_from_tag_string($s)
{
	$map = array();

	$a = explode("\"", $s);
	if (count($a) % 2 == 0) return array();

	for ($i = 0; $i < count($a) - 1; $i += 2) {
		$name = trim($a[$i]);
		$name = substr($name, 0, -1);
		$value = crypt_tag_decode(trim($a[$i + 1]));

		$map[$name] = $value;
	}

	return $map;
}


function map_from_url_string($s)
{
	$map = array();

	$a = explode("&", $s);
	for ($i = 0; $i < count($a); $i++) {
		$b = explode("=", $a[$i]);
		if (count($b) == 2) {
			$map[$b[0]] = $b[1];
		}
	}

	return $map;
}


function map_has($haystack, $needle)
{
	return array_key_exists($needle, $haystack);
}


function map_to_attribute_string($map)
{
	$s = "";

	$k = @array_keys($map);
	for ($i = 0; $i < count($map); $i++) {
		$s .= $k[$i] . ": " . $map[$k[$i]] . "; ";
	}

	if (substr($s, -2) == "; ") {
		return substr($s, 0, -2);
	} else {
		return $s;
	}
}


function map_to_conf_string($map)
{
	$s = "";
	$k = @array_keys($map);
	for ($i = 0; $i < count($map); $i++) {
		$s .= $k[$i] . ": " . $map[$k[$i]] . "\r\n";
	}

	return $s;
}


function map_to_tag_string($map)
{
	$s = "";
	$k = @array_keys($map);
	for ($i = 0; $i < count($map); $i++) {
		$s .= $k[$i] . '="' . crypt_tag_encode($map[$k[$i]]) . '" ';
	}

	return trim($s);
}


function map_to_url_string($map)
{
	$s = "";
	$k = @array_keys($map);
	for ($i = 0; $i < count($map); $i++) {
		$s .= $k[$i] . "=" . urlencode($map[$k[$i]]);
		if ($i < count($map) - 1) {
			$s .= "&";
		}
	}

	return $s;
}


function open_database()
{
	global $sql_server;
	global $sql_user;
	global $sql_pass;
	global $sql_dbh;
	global $sql_open;

	$sql_open = true;

	try {
		$sql_dbh = new PDO($sql_server, $sql_user, $sql_pass);
	} catch (PDOException $exception) {
		default_error($exception->getMessage());
	}

	$sql_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}


function print_row($a)
{
	global $r;

	if (array_key_exists("check_key", $a) || array_key_exists("link", $a)) {
		$hover = true;
		if (@$a["checked"] == "1" || @$a["checked"]) {
			$checked = true;
		} else {
			$checked = false;
		}
	} else {
		$hover = false;
	}
	if (array_key_exists("indent_width", $a)) {
		$indent_width = ' style="width: ' . $a["indent_width"] . 'px !important"';
	} else {
		$indent_width = "";
	}

	writeln('	<tr>');
	if ($hover) {
		writeln('		<td class="hover">');
	} else {
		writeln('		<td>');
	}

	if (array_key_exists("text_key", $a)) {
		writeln('			<div class="row_tab">');
		writeln('				<div' . $indent_width . ' class="row_caption">' . $a["caption"] . '</div>');
		writeln('				<div><div class="row_outline"><input id="' . $a["text_key"] . '" name="' . $a["text_key"] . '" type="text" value="' . @$a["text_value"] . '"/></div></div>');
		if (array_key_exists("text_default", $a)) {
			writeln('				<div style="width: 20px"><div class="row_button" style="background-image: url(/images/undo-16.png)" title="Reset" onclick="$(\'#' . $a["text_key"] . '\').val(\'' . addcslashes($a["text_default"], "\\") .'\')"></div></div>');
		}
		if (array_key_exists("text_browse", $a)) {
			writeln('				<div style="width: 20px"><div class="row_button" style="background-image: url(/images/folder.png)" title="Browse" onclick="$( \'#' . $a["text_key"] . '_dialog\' ).dialog( \'open\' );"></div></div>');
		}
		writeln('			</div>');
	} else if (array_key_exists("password_key", $a)) {
		writeln('			<div class="row_tab">');
		writeln('				<div' . $indent_width . ' class="row_caption">' . $a["caption"] . '</div>');
		writeln('				<div><div class="row_outline"><input id="' . $a["password_key"] . '" name="' . $a["password_key"] . '" type="password" value="' . @$a["password_value"] . '"/></div></div>');
		writeln('			</div>');
	} else if (array_key_exists("textarea_key", $a)) {
		if (array_key_exists("textarea_height", $a)) {
			$height = $a["textarea_height"];
		} else {
			$height = 100;
		}
		writeln('			<div class="row_tab">');
		writeln('				<div' . $indent_width . ' class="row_caption">' . $a["caption"] . '</div>');
		writeln('				<' . 'textarea name="' . $a["textarea_key"] . '" style="height: ' . $height . 'px">' . @$a["textarea_value"] . '<' . '/textarea>');
		writeln('			</div>');
	} else if (array_key_exists("option_key", $a)) {
		if (array_key_exists("option_change", $a)) {
			$event = ' onchange="' . $a["option_change"] . '"';
		} else {
			$event = '';
		}
		writeln('			<div class="row_tab">');
		writeln('				<div class="row_caption">' . $a["caption"] . '</div>');
		writeln('				<select name="' . $a["option_key"] . '"' . $event . '>');
		for ($i = 0; $i < count($a["option_list"]); $i++) {
			if (array_key_exists("option_keys", $a)) {
				if ($a["option_keys"][$i] == @$a["option_value"]) {
					writeln('					<option selected="selected" value="' . $a["option_keys"][$i] . '">' . $a["option_list"][$i] . '</option>');
				} else {
					writeln('					<option value="' . $a["option_keys"][$i] . '">' . $a["option_list"][$i] . '</option>');
				}
			} else {
				if ($a["option_list"][$i] == @$a["option_value"]) {
					writeln('					<option selected="selected">' . $a["option_list"][$i] . '</option>');
				} else {
					writeln('					<option>' . $a["option_list"][$i] . '</option>');
				}
			}
		}
		writeln('				</select>');
		writeln('			</div>');
	} else if (array_key_exists("link", $a)) {
		if (array_key_exists("description", $a)) {
			writeln('			<a href="' . $a["link"] . '">');
			writeln('			<dl class="icon_' . $a["icon"] . '_32">');
			writeln('				<dt>' . $a["caption"] . '</dt>');
			writeln('				<dd>' . $a["description"] . '</dd>');
			writeln('			</dl>');
			writeln('			</a>');
		} else {
			writeln('			<a href="' . $a["link"] . '"><div class="icon_16" style="background-image: url(/images/' . $a["icon"] . '-16.png); color: #000000">' . $a["caption"] . '</div></a>');
		}
	} else if (array_key_exists("icon_32", $a)) {
		if (array_key_exists("description", $a)) {
			writeln('			<dl class="icon_' . $a["icon"] . '_32">');
			writeln('				<dt>' . $a["caption"] . '</dt>');
			writeln('				<dd>' . $a["description"] . '</dd>');
			writeln('			</dl>');
		} else {
			writeln('			<div class="icon_32" style="background-image: url(/images/' . $a["icon_32"] . '-32.png)"><h1>' . $a["caption"] . '</h1></div>');
		}
	} else {
		if (array_key_exists("check_key", $a)) {
			if (array_key_exists("check_show", $a) || array_key_exists("check_hide", $a)) {
				//$on_click = ' onchange="alert(this.checked)" onclick="this.focus(); document.getElementById(\'location\').focus()"';
				$show_id = @$a["check_show"];
				$hide_id = @$a["check_hide"];
				if (ie()) {
					$event = ' onclick="check_click(this, \'' . $show_id . '\', \'' . $hide_id . '\')"';
				} else {
					$event = ' onchange="check_change(this, \'' . $show_id . '\', \'' . $hide_id . '\')"';
				}
			} else {
				$event = '';
			}
			if (array_key_exists("check_value", $a)) {
				$check_value = ' value="' . $a["check_value"] . '"';
			} else {
				$check_value = '';
			}
			writeln('			<input name="' . $a["check_key"] . '" class="row_check" type="checkbox"' . $check_value . ( $checked ? ' checked="true"' : '' ) . $event . '/>');
		}
		if (array_key_exists("icon", $a)) {
			writeln('			<img src="/images/' . $a["icon"] . '.png" style="vertical-align: middle; margin-right: 8px"/>');
		}
		if (array_key_exists("caption", $a)) {
			writeln('			' . $a["caption"]);
		}
	}
	writeln('		</td>');
	writeln('	</tr>');
}


function random_hash()
{
	return crypt_sha256(time() . getmypid() . rand());
}


function right_box($buttons, $style = "")
{
	if ($style == "") {
		writeln('<div class="right_box">');
	} else {
		writeln('<div class="right_box" style="' . $style . '">');
	}
	if (string_has($buttons, "<")) {
		writeln($buttons);
	} else {
		$a = explode(",", $buttons);
		for ($i = 0; $i < count($a); $i++) {
			$value = trim($a[$i]);
			$name = strtolower($value);
			$name = str_replace(" ", "_", $name);
			writeln('<input type="submit" name="' . $name . '" value="' . $value .'"/>');
		}
	}
	writeln('</div>');
}


function run_sql($sql, $arg = array(), $fatal = true)
{
	global $sql_open;
	global $sql_dbh;
	global $sql_server;
	global $sql_error;

	if (!$sql_open) {
		open_database();
	}
	$sth = $sql_dbh->prepare($sql);

	try {
		$sth->execute($arg);
		if ($sth->columnCount() == 0) {
			return;
		}
		$row = $sth->fetchAll();
	} catch (PDOException $exception) {
		$msg = $exception->getMessage();
		$sql_error = "sql [$sql] arg [" . implode(", ", $arg) . "] msg [$msg]";
		if ($fatal) {
			default_error($sql_error);
		}
		return false;
	}

	return $row;
}


function run_sql_file($path)
{
	$lines = file($path);
	$sql = "";
	for ($i = 0; $i < count($lines); $i++) {
		if ($lines[$i] != "" && substr($lines[$i], 0, 2) != "--") {
			$sql .= $lines[$i];
			if (substr(trim($lines[$i]), -1, 1) == ';') {
				run_sql($sql);
				$sql = "";
			}
		}
	}
}


function string_clean($test, $valid, $length = -1)
{
	$v = str_replace("[a-z]", "abcdefghijklmnopqrstuvwxyz", $valid);
	$v = str_replace("[A-Z]", "ABCDEFGHIJKLMNOPQRSTUVWXYZ", $v);
	$v = str_replace("[0-9]", "0123456789", $v);

	$s = trim($test);
	$t = "";
	for ($i = 0; $i < strlen($s); $i++) {
		$c = substr($s, $i, 1);
		if (strpos($v, $c) !== false) {
			$t .= $c;
		}
	}

	if ($length != -1 && strlen($t) > $length) {
		return substr($t, 0, $length);
	}
	return $t;
}


function string_has($haystack, $needle)
{
	$pos = strpos($haystack, $needle);
	if ($pos === false) {
		return false;
	}

	return true;
}


function string_next(&$src, $sep)
{
	$pos = strpos($src, $sep);
	if ($pos === false) {
		$tmp = $src;
		$src = "";
		return $tmp;
	}

	$tmp = substr($src, 0, $pos);
	$src = substr($src, $pos + strlen($sep));

	return $tmp;
}


function string_pad($src, $length, $char = "0") {
	return str_pad($src, $length, $char, STR_PAD_LEFT);
}


function string_replace_all($search, $replacement, $source)
{
	while (string_has($source, $search)) {
		$source = str_replace($search, $replacement, $source);
	}

	return $source;
}


function string_uses($src, $uses)
{
	// force src to be a string
	$s = "$src";

	// never allow blanks
	if ($s == "") {
		return false;
	}

	if ($uses == "[ALL]") {
		return true;
	}

	$chars = str_replace("[A-Z]", "ABCDEFGHIJKLMNOPQRSTUVWXYZ", $uses);
	$chars = str_replace("[a-z]", "abcdefghijklmnopqrstuvwxyz", $chars);
	$chars = str_replace("[0-9]", "0123456789", $chars);
	$chars = str_replace("[KEYBOARD]", "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789`~!@#\$%^&*()_+-=[]\\{}|;':\",./<>? ", $chars);

	for ($i = 0; $i < strlen($s); $i++) {
		$c = substr($s, $i, 1);
		if (strpos($chars, $c) === false) {
			return false;
		}
	}

	return true;
}


function sys_format_size($bytes, $binary = false)
{
	if ($binary) {
		$d = 1024;
		// NOTE: the SI symbol for kibi has an uppercase K
		$units = array("B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB");
	} else {
		$d = 1000;
		// NOTE: the SI symbol for kilo has a lowercase k
		$units = array("B", "kB", "MB", "GB", "TB", "PB", "EB");
	}

	$n = $bytes;
	for ($i = 0; $i <= 4; $i++) {
		if ($n < $d) {
			break;
		}
		$n = $n / $d;
	}

	if ($i == 0) {
		return $n . " " . $units[$i];
	} else {
		return round($n, 2) . " " . $units[$i];
	}
}


function writeln($s = "")
{
	if (defined("DOS_NEW_LINES")) {
		print $s . "\r\n";
	} else {
		print $s . "\n";
	}
}
