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

if (!$auth_user["admin"] && !$auth_user["editor"]) {
	die("not an editor or admin");
}

print_header("Menu");
beg_main("dual_table");

writeln('<div class="dual_left">');

beg_tab();
print_row(array("caption" => "Poll", "description" => "Create a new poll", "icon" => "heart", "link" => "/poll/create"));
if ($auth_user["admin"]) {
	print_row(array("caption" => "Topics", "description" => "Add and remove topics", "icon" => "chat", "link" => "/topic/list"));
}
end_tab();

writeln('</div>');
writeln('<div class="dual_right">');

beg_tab();
print_row(array("caption" => "Pages", "description" => "Manage static pages", "icon" => "html", "link" => "/page/"));
if ($auth_user["admin"]) {
	print_row(array("caption" => "Links", "description" => "Manage server links", "icon" => "workgroup", "link" => "/link/"));
	print_row(array("caption" => "Settings", "description" => "Configure the server settings", "icon" => "tools", "link" => "settings"));
}
end_tab();

writeln('</div>');

end_main();
print_footer();
