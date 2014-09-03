<?
//
// Pipecode - distributed social network
// Copyright (C) 2014 Bryan Beicker <bryan@pipedot.org>
//
// This file is part of Pipecode.
//
// Pipecode is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Pipecode is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Pipecode.  If not, see <http://www.gnu.org/licenses/>.
//

$row = sql("select sum(value) as karma from comment inner join comment_vote on comment.comment_id = comment_vote.comment_id where comment.zid = ?", $zid);
$karma = $row[0]["karma"];
$description = karma_description($karma);

$page = http_get_int("page", array("default" => 1, "required" => false));
$icon = "karma-" . strtolower($description) . "-32.png";
//$rows_per_page = 10;
//$row = sql("select count(zid) as row_count from karma_log where zid = ?", $zid);
//$row = sql("select count(*) as row_count from comment inner join comment_vote on comment.comment_id = comment_vote.comment_id where comment.zid = ? and value <> 0", $zid);
//$row_count = (int) $row[0]["row_count"];
//$pages_count = ceil($row_count / $rows_per_page);
//$row_start = ($page - 1) * $rows_per_page;
$items_per_page = 100;
list($item_start, $page_footer) = page_footer("select count(*) as item_count from comment inner join comment_vote on comment.comment_id = comment_vote.comment_id where comment.zid = ? and value <> 0", $items_per_page, $zid);

print_header("Karma");
print_left_bar("user", "karma");
beg_main("cell");

writeln('<h1>Karma</h1>');
writeln('<table>');
writeln('	<tr>');
writeln('		<td><img alt="Karma Face" src="/images/' . $icon . '"/></td>');
writeln('		<td>' . $description . ' (' . $karma . ')</td>');
writeln('	</tr>');
writeln('</table>');

//$row = sql("select time, karma_log.value, karma_log.type_id, type, id from karma_log inner join karma_type on karma_log.type_id = karma_type.type_id where zid = ? order by time desc limit $row_start, $rows_per_page", $zid);
$row = sql("select comment_vote.time, value, comment.comment_id, short_id, comment_vote.zid from comment inner join comment_vote on comment.comment_id = comment_vote.comment_id where comment.zid = ? and value <> 0 order by comment_vote.time desc limit $item_start, $items_per_page", $zid);
writeln('<h1>Log</h1>');
writeln('<table class="zebra">');
writeln('	<tr>');
writeln('		<th>Time</th>');
writeln('		<th class="center">Points</th>');
writeln('		<th>Comment</th>');
writeln('		<th>Voter</th>');
writeln('	</tr>');
if (count($row) == 0) {
	writeln('	<tr>');
	writeln('		<td colspan="3">(none)</td>');
	writeln('	</tr>');
}
for ($i = 0; $i < count($row); $i++) {
	//if ($row[$i]["type_id"] == 1 || $row[$i]["type_id"] == 2) {
	//	$link = " (<a href=\"http://$server_name/comment/" . $row[$i]["id"] . '">#' . $row[$i]["comment_id"] . '</a>';
	//} else {
	//	$link = " (<a href=\"http://$server_name/pipe/" . $row[$i]["id"] . '">#' . $row[$i]["comment_id"] . '</a>';
	//}
	$short_code = crypt_crockford_encode($row[$i]["short_id"]);
	$link = item_link("comment", $row[$i]["comment_id"]);
	$value = (int) $row[$i]["value"];
	if ($value > 0) {
		$value = "+$value";
	}
	$voter = user_page_link($row[$i]["zid"], true);
	writeln('	<tr>');
	writeln('		<td>' . gmdate("Y-m-d H:i", $row[$i]["time"]) . '</td>');
	writeln('		<td class="center">' . $value . '</td>');
	writeln('		<td><a href="' . $protocol . '://' . $server_name . '/' . $short_code . '">#' . $short_code . '</a></td>');
	writeln('		<td>' . $voter . '</td>');
	writeln('	</tr>');
}
end_tab();

//$s = "";
//for ($i = 1; $i <= $pages_count; $i++) {
//	if ($i == $page) {
//		$s .= "$i ";
//	} else {
//		$s .= "<a href=\"?page=$i\">$i</a> ";
//	}
//}
//writeln('<div style="text-align: center">' . trim($s) . '</div>');

writeln($page_footer);

end_main();
print_footer();