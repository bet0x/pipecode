<?
//
// Pipecode - distributed social network
// Copyright (C) 2014 Bryan Beicker <bryan@pipedot.org>
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

if (!$auth_user["editor"]) {
	die("you are not an editor");
}

$reason = http_post_string("reason", array("len" => 50, "valid" => "[a-z][A-Z][0-9]~!@#$%^*()_+-=[]\{}|;',./? "));

$pipe = find_rec("pipe");
$pipe["closed"] = 1;
$pipe["edit_zid"] = $auth_zid;
$pipe["reason"] = $reason;
db_set_rec("pipe", $pipe);

header("Location: /pipe/{$pipe["short_code"]}");
