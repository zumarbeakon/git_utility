<?php
/**
 *
 * @package Advanced OpenDiscovery
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author Salesagility Ltd <support@salesagility.com>
 */
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $current_user, $sugar_config;
global $mod_strings;
global $app_list_strings;
global $app_strings;
global $theme;

if (!is_admin($current_user)) sugar_die("Unauthorized access to administration.");

require_once('modules/Configurator/Configurator.php');
$cfg = new Configurator();
$sugar_smarty = new Sugar_Smarty();
$errors = array();
if (!array_key_exists('git', $cfg->config)) {
    $cfg->config['git'] = array(
        'enable_git' => '',
    );
}
require_once('custom/include/gitphp/Git.php');
// Open GIT Repository
$repo = Git::open($sugar_config['gitrepo']);
$status = $repo->status();
$modifiedpath = array();
$deletedpath = array();
$git_status = explode("\n", $status);
$git_status = array_filter($git_status);
foreach ($git_status as $statuspath) {
    if (preg_match('/modified:/', $statuspath, $matches, PREG_OFFSET_CAPTURE)) {
        $statuspath = str_replace("#", "", $statuspath);
        $statuspath = str_replace("modified:", "", $statuspath);
        $statuspath = trim(preg_replace('/\s+/', ' ', $statuspath));
        $modifiedpath[] = $statuspath;
    }
    if (preg_match('/deleted:/', $statuspath, $matches, PREG_OFFSET_CAPTURE)) {
        $statuspath = str_replace("#", "", $statuspath);
        $statuspath = str_replace("deleted:", "", $statuspath);
        $statuspath = trim(preg_replace('/\s+/', ' ', $statuspath));
        $deletedpath[] = $statuspath;
    }   
}
$untrackedPaths = [];
$untrackedstatus = preg_split("/Untracked files:/", $status);
if (isset($untrackedstatus[1])) {
    $untrackedLines = explode("\n", trim($untrackedstatus[1]));
    foreach ($untrackedLines as $line) {
        $line = str_replace("#", "", $line);
        if (!empty($line) && strpos($line, ')') == 0) {
            $untrackedPaths[] = trim($line);
        }
    }
}
$statuspaths = array_merge($modifiedpath, $untrackedPaths);
$sugar_smarty->assign('untrackedPaths', $untrackedPaths);
$sugar_smarty->assign('modifiedpath', $modifiedpath);
$sugar_smarty->assign('deletedpath', $deletedpath);
$sugar_smarty->display('custom/modules/Administration/tpls/gitCommit.tpl');
exit;
?>
