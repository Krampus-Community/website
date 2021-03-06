<?php

/* Hooks */

$plugins->add_hook("index_end", "recentthread_list_threads");
$plugins->add_hook("global_start", "recentthread_get_templates");
$plugins->add_hook("global_intermediate", "recentthread_global_intermediate");
$plugins->add_hook("xmlhttp", "recentthread_refresh_threads");
$plugins->add_hook("admin_config_plugins_begin", "recentthread_update");
$plugins->add_hook("admin_tools_adminlog_begin", "recentthread_admin_tools_adminlog_begin");
$plugins->add_hook("admin_tools_get_admin_log_action", "recenttthread_admin_tools_get_admin_log_action");
$plugins->add_hook("admin_style_templates", "recentthread_admin_style_templates");

function recentthread_info()
{
    $donationlink = "https://www.paypal.me/MarkJanssen";
    $updatelink = "index.php?module=config-plugins&action=update_recentthreads";
    return array(
        "name"		=> "Recent Threads",
        "description"		=> "A plug-in that shows the most recent threads on the index. <a href='$updatelink'>Run Update Script</a><br /><a href='$donationlink'>Donate to support</a>",
        "author"		=> "Mark Janssen",
        "version"		=> "14.0",
        "codename" 			=> "recentthreads",
        "compatibility"	=> "18*"
    );
}

function recentthread_install()
{
    global $db, $mybb;

    if($mybb->version_code < 1801)
    {
        flash_message("Sorry, but this plugin requires you to update to 1.8.1 or higher.", "error");
        admin_redirect("index.php?module=config-plugins");
    }

    // Add some settings
    $new_setting_group = array(
        "name" => "recentthreads",
        "title" => "Recent Threads Settings",
        "description" => "Customize various aspects of recent threads",
        "disporder" => 77,
        "isdefault" => 0
    );

    $gid = $db->insert_query("settinggroups", $new_setting_group);

    $new_setting[] = array(
        "name" => "recentthread_threadcount",
        "title" => "Number of Threads",
        "description" => "How many threads are shown.",
        "optionscode" => "numeric",
        "disporder" => 1,
        "value" => 15,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_threadavatar",
        "title" => $db->escape_string("Show thread starter's avatar"),
        "description" => $db->escape_string("If set to yes, the thread starter's avatar will be shown."),
        "optionscode" => "yesno",
        "disporder" => 2,
        "value" => 0,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_lastavatar",
        "title" => $db->escape_string("Show last poster's avatar"),
        "description" => $db->escape_string("If set to yes, the last poster's avatar will be shown."),
        "optionscode" => "yesno",
        "disporder" => 3,
        "value" => 0,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_forumskip",
        "title" => "Forums To Ignore",
        "description" => "The forums threads should not be pulled from.",
        "optionscode" => "forumselect",
        "disporder" => 4,
        "value" => "",
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_subject_length",
        "title" => "Max Title Length",
        "description" => "The amount of characters before the rest of the title is truncated. Enter 0 for no limit.",
        "optionscode" => "numeric",
        "disporder" => 5,
        "value" => 0,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_subject_breaker",
        "title" => "Word Breaking",
        "description" => "If selected, the title will be kept to full words only in cut off.",
        "optionscode" => "yesno",
        "disporder" => 6,
        "value" => 0,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_which_groups",
        "title" => "Permissions",
        "description" => "These groups cannot view the reccent threads on index.",
        "optionscode" => "groupselect",
        "disporder" => 7,
        "value" => 7,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_prefix",
        "title" => "Thread Prefix",
        "description" => "If set to yes, thread prefixes will be shown.",
        "optionscode" => "yesno",
        "disporder" => 8,
        "value" => 1,
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_prefix_only",
        "title" => "Which Prefix",
        "description" => "A thread must have one of these prefix ids to show, separate with a comma.  Leave blank to not restrict.",
        "optionscode" => "text",
        "disporder" => 9,
        "value" => "",
        "gid" => $gid
    );

    $new_setting[] = array(
        "name" => "recentthread_xthreads",
        "title" => "XThreads",
        "description" => "If set to yes, custom thread fields will be loaded.",
        "optionscode" => "yesno",
        "disporder" => 10,
        "value" => 1,
        "gid" => $gid
    );


    $db->insert_query_multiple("settings", $new_setting);
    rebuild_settings();
}

function recentthread_is_installed()
{
    global $db;
    $query = $db->simple_select("settinggroups", "*", "name='recentthreads'");
    if($db->num_rows($query))
    {
        return TRUE;
    }
    return FALSE;
}

function recentthread_activate()
{
    global $db;

    // First create a new template group
    $new_template_group = array(
        "prefix" => "recentthread",
        "title" => "<lang:recentthreads_template>",
        "isdefault" => 0
    );

    $db->insert_query("templategroups", $new_template_group);

    $new_template['recentthread'] = '<div class="scroll">
<div id="recentthreads">
<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="clear: both;max-height:300px">
<thead>
    <tr>
    <td class="thead{$expthead}" colspan="5" style="text-align:left; font-size: 10pt;"><div class="expcolimage"><img src="{$theme[\'imgdir\']}/collapse.png" id="cat_9999_img" class="expander" alt="{$expaltext}" title="{$expaltext}" /></div>
<div><b>~ {$lang->recentthreads_recentthreads} ~</b></div>
</td>
    </tr>
</thead>
<tbody style="{$expdisplay}" id="cat_9999_e">
    <tr>
    <td class="tcat" colspan="2"><strong>{$lang->recentthreads_thread} / {$lang->recentthreads_author}</strong></td>
    <td class="tcat"><strong>{$lang->recentthreads_forum}</strong></td>
    <td class="tcat"><strong>{$lang->recentthreads_posts}</strong></td>
    <td class="tcat"><strong>{$lang->recentthreads_last_post}</strong></td>
    </tr>
    {$recentthreads}
</tbody>
    </table>
</div>
    </div>';

    $new_template['recentthread_thread'] = '<tr>
    <td class="{$trow}" width="2%">{$icon}</td>
    <td class="{$trow}">{$recentprefix}<a href="{$mybb->settings[\'bburl\']}/showthread.php?tid={$thread[\'tid\']}">{$thread[\'subject\']}</a>&nbsp;{$arrow}&nbsp;{$thread[\'multipage\']}<br />{$thread[\'author\']}<br />{$posteravatar}</td>
    <td class="{$trow}"><a href="{$mybb->settings[\'bburl\']}/forumdisplay.php?fid={$thread[\'fid\']}">{$thread[\'forum\']}</a></td>
    <td class="{$trow}"><a href="javascript:MyBB.whoPosted({$thread[\'tid\']});">{$thread[\'replies\']}</a></td>
    <td class="{$trow}">{$lastposttimeago}<br />
    <a href="{$lastpostlink}">Last Post:</a> {$lastposterlink}<br />{$lastavatar}</td>
    </tr>';

    $new_template['recentthread_avatar'] = '<img src="{$avatarurl}" {$dimensions} alt="{$avatarurl}" />';

    $new_template['recentthread_headerinclude'] = '<script type="text/javascript">
  <!--
	var refresher = window.setInterval(function () {refresh_recent_threads()}, 30000);
    var stopper = window.setTimeout(function() { stop_recent_threads()}, 900000);
    $(document).ready(function() { refresh_recent_threads(); });
    function refresh_recent_threads()
    {
      	var xmlhttp;
		if(window.XMLHttpRequest)
  		{// code for IE7+, Firefox, Chrome, Opera, Safari
 			 xmlhttp=new XMLHttpRequest();
  		}
		else
 		 {// code for IE6, IE5
     		 xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
  		}
      xmlhttp.onreadystatechange=function()
      {
        if(xmlhttp.readyState==4 && xmlhttp.status==200)
    		{
   				 document.getElementById("recentthreads").innerHTML=xmlhttp.responseText;
    		}
      }
      	xmlhttp.open("GET","xmlhttp.php?action=recent_threads",true);
		xmlhttp.send();
    }
    function stop_recent_threads()
    {
      		clearInterval(refresher);
    }
  // -->
  </script>';

    // Now go through each of the themes
    $themequery = $db->simple_select("themes", "*");
    $sids = array();
    while($theme = $db->fetch_array($themequery))
    {
        $properties = unserialize($theme['properties']);
        $sid = $properties['templateset'];
        if(!in_array($sid, $sids))
        {
            array_push($sids, $sid);

            foreach ($new_template as $title => $template)
            {
                $my_template = array(
                    'title' => $db->escape_string($title),
                    'template' => $db->escape_string($template),
                    'sid' => '$sid',
                    'version' => '1800',
                    'dateline' => TIME_NOW);
                $db->insert_query('templates', $my_template);
            }
        }
    }
    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

    find_replace_templatesets('index', "#" . preg_quote('{$forums}') . "#i", '{$forums}<div id="recentthreads">{$recentthreadtable}</div>');
    find_replace_templatesets('index', "#" . preg_quote('{$headerinclude}') . "#i", '{$headerinclude}{$recentthread_headerinclude}');
}

function recentthread_deactivate()
{
    global $db;
    $db->delete_query("templates", "title IN('recentthread','recentthread_thread','recentthread_avatar','recentthread_headerinclude')");

    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

    find_replace_templatesets('index', "#" . preg_quote('{$recentthread_headerinclude}') . "#i", '');
    find_replace_templatesets('index', "#" . preg_quote('<div id="recentthreads">{$recentthreadtable}</div>') . "#i", '');
}

function recentthread_uninstall()
{
    global $db;
    $query = $db->simple_select("settinggroups", "gid", "name='recentthreads'");
    $gid = $db->fetch_field($query, "gid");
    if(!$gid)
    {
        return;
    }
    $db->delete_query("settinggroups", "name='recentthreads'");
    $db->delete_query("settings", "gid=$gid");
    rebuild_settings();
}

function recentthread_update()
{
    global $mybb, $db;
    if($mybb->input['action'] != "update_recentthreads")
    {
        return;
    }
    log_admin_action();
    // Check if they have the updated template group
    $query = $db->simple_select("templategroups", "*", "prefix='recentthread'");
    $data = $db->fetch_array($query);
    if(!$data['gid'])
    {
        $new_template_group = array(
            "prefix" => "recentthread",
            "title" => "<lang:recentthreads_template>",
            "isdefault" => 0
        );

        $db->insert_query("templategroups", $new_template_group);

        // Since they don't have the template group, it is safe to say they don't have it in each template set
        $templatedataquery = $db->simple_select("templates", "*", "title LIKE 'recentthread%'");
        while($mytemplate = $db->fetch_array($templatedataquery))
        {
            $new_template[$mytemplate['title']] = $mytemplate['template'];
        }

        $themequery = $db->simple_select("themes", "*");
        $sids = array();
        while($theme = $db->fetch_array($themequery))
        {
            $properties = my_unserialize($theme['properties']);
            $sid = $properties['templateset'];
            if(!in_array($sid, $sids)) // Prevent duplicate entries
            {
                array_push($sids, $sid);
                foreach ($new_template as $title => $template)
                {
                    $my_template = array(
                        'title' => $db->escape_string($title),
                        'template' => $db->escape_string($template),
                        'sid' => $sid,
                        'version' => '1810',
                        'dateline' => TIME_NOW);
                    $db->insert_query('templates', $my_template);
                }
            }
        }
    }
    if(array_key_exists("recentthread_prefix_only", $mybb->settings))
    {
        flash_message("You have the most current version of Recent Threads On Index.");
        admin_redirect("index.php?module=config-plugins");
    }
    else
    {
        $query = $db->simple_select("settinggroups", "*", "name='recentthreads'");
        $gid = $db->fetch_field($query, "gid");
        if(!array_key_exists("recentthread_xthreads", $mybb->settings))
        {
            $new_setting[0] = array(
                "name" => "recentthread_xthreads",
                "title" => "XThreads",
                "description" => "If set to yes, custom thread fields will be loaded.",
                "optionscode" => "yesno",
                "disporder" => 10,
                "value" => 1,
                "gid" => $gid
            );
            $db->insert_query("settings", $new_setting[0]);
        }

        if(!array_key_exists("recentthread_prefix_only", $mybb->settings))
        {
            $new_setting[1] = array(
                "name" => "recentthread_prefix_only",
                "title" => "Which Prefix",
                "description" => "A thread must have one of these prefix ids to show, separate with a comma.  Leave blank to not restrict.",
                "optionscode" => "text",
                "disporder" => 9,
                "value" => "",
                "gid" => $gid
            );
            $db->insert_query("settings", $new_setting[1]);
        }

        rebuild_settings();
        $plugin_info = recentthread_info();
        flash_message("Recent Threads On Index has now been updated to version " . $plugin_info['version'] . ".", "success");
        admin_redirect("index.php?module=config-plugins");
    }
}

function recentthread_list_threads($return=false)
{
    global $mybb, $db, $templates, $recentthreadtable, $recentthreads, $settings, $canviewrecentthreads, $cache, $theme, $lang, $threadfields, $xthreadfields;
    // First check permissions
    if(!recentthread_can_view())
    {
        return;
    }
    $lang->load("recentthreads");
    $lang->load("forumdisplay");
    $icons = $cache->read("posticons");
    require_once MYBB_ROOT."inc/functions_search.php";
    $threadlimit = (int) $mybb->settings['recentthread_threadcount'];
    if(!$threadlimit) // Provide a fallback
    {
        $threadlimit = 15;
    }
    $onlyusfids = array();
    $onlycanview = array();
    // Check group permissions if we can't view threads not started by us
    $group_permissions = forum_permissions();
    foreach($group_permissions as $fid => $forum_permissions)
    {
        if($forum_permissions['canonlyviewownthreads'] == 1)
        {
            $onlyusfids[] = $fid;
        }
        if ($forum_permissions['canview'] == 0)
        {
            $onlycanview[] = $fid;
        }
    }
    if(!empty($onlyusfids))
    {
        $where .= "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
    }
    if (!empty($onlycanview))
    {
        $where .= "AND (t.fid NOT IN(".implode(',', $onlycanview)."))";
    }
    $approved = 0;

    // Moderators can view unapproved threads
    if($mybb->usergroup['canmodcp']==1) {
        $approved = -1;
    }
    $unsearchableforums = get_unsearchable_forums();
    $unviewableforums = get_unviewable_forums();
    if($unsearchableforums && $unviewableforums)
    {
        $forumarray = explode(",", $unsearchableforums . "," . $unviewableforums);
        $newarray = array_unique($forumarray);
        $unsearchableforumssql = " AND t.fid NOT IN(" . implode(",", $newarray) . ") ";
    }
    // Take into account any ignored forums
    if($mybb->settings['recentthread_forumskip'])
    {
        $ignoreforums = " AND t.fid NOT IN(" . $mybb->settings['recentthread_forumskip'] . ") ";
    }
    $forums = $cache->read("forums");
    $prefixes = $cache->read("threadprefixes");

    // Are we only using certain prefixes?
    if($mybb->settings['recentthread_prefix_only'])
    {
        if(is_numeric($mybb->settings['recentthread_prefix_only']))
        {
            $prefixonly = " AND t.prefix = " . (int) $mybb->settings['recentthread_prefix_only'] . " ";
        }
        else
        {
            $prefixlist = explode(",", $mybb->settings['recentthread_prefix_only']);
            $newlist = array_map("intval", $prefixlist); /* Use this to stop any funny business. */
            $prefixonly = " AND t.prefix IN(" . implode(',', $newlist) . ") ";
        }
    }


    // Take XThreads into account
    if(function_exists("xthreads_get_threadfields") && $mybb->settings['recentthread_xthreads'] == 1)
    {
        $quickquery = $db->query("SELECT t.tid as threadid, t.username, t.fid, tf.*
                                    FROM " . TABLE_PREFIX . "threads t
                                    LEFT JOIN " . TABLE_PREFIX . "threadfields_data tf ON(t.tid=tf.tid)
                                    WHERE 1=1 $where $prefixonly AND t.visible > $approved $unsearchableforumssql $ignoreforums
                                    ORDER BY t.lastpost DESC
                                    LIMIT $threadlimit");

        while ($threadfields = $db->fetch_array($quickquery))
        {
            $threadfields_raw[$xthreadsfields['threadid']] = $xthreadsfields;
            if ($threadfields['fid'] == $GLOBALS['fid'])
            {
                // use global cache if we're referring to current forum
                $threadfield_cache =& $GLOBALS['threadfield_cache'];
            }
            if (!isset($threadfield_cache))
            {
                $threadfield_cache = xthreads_gettfcache((int)$threadfields['fid']);
            }
            if (!empty($threadfield_cache))
            {
                if (!isset($threadfields))
                {
                    $threadfields = array();
                }
                foreach ($threadfield_cache as $k => &$v)
                {
                    xthreads_get_xta_cache($v, $threadfields['threadid']);
                    xthreads_sanitize_disp($threadfields[$k], $v, $threadfields['username'], true);
                }
                $threadfields_formatted[$threadfields['threadid']] = $threadfields;
            }
        }
    }

    $query = $db->query("
			SELECT t.*, u.username AS userusername, u.usergroup, u.displaygroup, u.avatar as threadavatar, u.avatardimensions as threaddimensions, lp.usergroup AS lastusergroup, lp.avatar as lastavatar, lp.avatardimensions as lastdimensions, lp.displaygroup as lastdisplaygroup
			FROM " . TABLE_PREFIX . "threads t
			LEFT JOIN " . TABLE_PREFIX . "users u ON (u.uid=t.uid)
			LEFT JOIN " . TABLE_PREFIX . "users lp ON (t.lastposteruid=lp.uid)
			WHERE 1=1 $where $prefixonly AND t.visible > {$approved} {$unsearchableforumssql} {$ignoreforums}
			ORDER BY t.lastpost DESC
			LIMIT $threadlimit");

    while($thread = $db->fetch_array($query))
    {
        if(strpos($thread['closed'], "moved|") === 0)
        {
            $thread['tid'] = substr($thread['closed'], 6);
        }
        $tid = $thread['tid'];
        $trow = alt_trow();
        if($thread['visible'] == 0)
        {
            $trow = "trow_shaded";
        }
        $thread['forum'] = $forums[$thread['fid']]['name'];
        if($mybb->settings['recentthread_prefix'])
        {
            $recentprefix = $prefixes[$thread['prefix']]['displaystyle'];
        }
        if($thread['icon'])
        {
            $icon = "<img src='" . $icons[$thread['icon']]['path'] . "' alt='" . $icons[$thread['icon']]['name'] . "' />";
        }
        $threadlink = $thread['newpostlink'] = get_thread_link($tid, "", "newpost"); // Maintain for template compatibility
        eval("\$arrow =\"".$templates->get("forumdisplay_thread_gotounread")."\";");
        $lastpostlink = get_thread_link($tid, "", "lastpost");
        $lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
        $lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
        $lastposttimeago = my_date("relative", $thread['lastpost']);
        $lastposter = $thread['lastposter'];
        $lastposteruid = $thread['lastposteruid'];
        $thread['author'] = build_profile_link(format_name($thread['userusername'], $thread['usergroup'], $thread['displaygroup']), $thread['uid']);
        // Don't link to guest's profiles (they have no profile).
        if($lastposteruid == 0)
        {
            $lastposterlink = $lastposter;
        }
        else
        {
            $lastposterlink = build_profile_link(format_name($lastposter, $thread['lastusergroup'], $thread['lastdisplaygroup']), $lastposteruid);
        }
        if($mybb->settings['recentthread_threadavatar'])
        {
            $threadavatar = format_avatar($thread['threadavatar'], $thread['threaddimensions']);
            $avatarurl = $threadavatar['image'];
            $dimensions = $threadavatar['width_height'];
            eval("\$posteravatar = \"".$templates->get("recentthread_avatar")."\";");
        }
        if($mybb->settings['recentthread_lastavatar'])
        {
            $lastposteravatar = format_avatar($thread['lastavatar'], $thread['lastdimensions']);
            $avatarurl = $lastposteravatar['image'];
            $dimensions = $lastposteravatar['width_height'];
            eval("\$lastavatar = \"".$templates->get("recentthread_avatar")."\";");
        }
        // Now check the length of subjects
        $length = (int) $mybb->settings['recentthread_subject_length'];
        if(strlen($thread['subject']) > $length && $length != 0)
        {
            // Figure out if we need to split it up.
            $title = my_substr($thread['subject'], 0, $length);
            if($mybb->settings['recentthread_subject_breaker'])
            {
                $words = explode(" ", $title);
                $count = count($words) -1;
                $currenttitle = "";
                for($x = 0; $x < $count; $x++)
                {
                    $currenttitle .= $words[$x] . " ";
                }
                $thread['subject'] = $currenttitle . " ...";
            }
            if(!$mybb->settings['recentthread_subject_breaker'])
            {
                $thread['subject'] = $title . "...";
            }
        }
        $thread['subject'] = htmlspecialchars_uni($thread['subject']);
        // Moderator stuff baby!
        if(is_moderator($thread['fid']))
        {
            $ismod = TRUE;
            // fetch the inline mod column
        }
        else
        {
            $ismod = FALSE;
        }
        if(is_moderator($thread['fid'], "caneditposts") || $fpermissions['caneditposts'] == 1)
        {
            $can_edit_titles = 1;
        }
        else
        {
            $can_edit_titles = 0;
        }
        $inline_edit_class = '';
        if(($thread['uid'] == $mybb->user['uid'] && $thread['closed'] != 1 && $mybb->user['uid'] != 0 && $can_edit_titles == 1) || $ismod == true)
        {
            $inline_edit_class = "subject_editable";
        }

        // Multipage.  Code from forumdisplay.php
        $thread['posts'] = $thread['replies'] +1;
        if($thread['unapprovedposts'] > 0 && $ismod)
        {
            $thread['posts'] += $thread['unapprovedposts'] + $thread['deletedposts'];
        }
        if($thread['posts'] > $mybb->settings['postsperpage'])
        {
            $thread['pages'] = $thread['posts'] / $mybb->settings['postsperpage'];
            $thread['pages'] = ceil($thread['pages']);
            if($thread['pages'] > $mybb->settings['maxmultipagelinks'])
            {
                $pagesstop = $mybb->settings['maxmultipagelinks'] - 1;
                $page_link = get_thread_link($thread['tid'], $thread['pages']);
                eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
            }
            else
            {
                $pagesstop = $thread['pages'];
            }
            for($i = 1; $i <= $pagesstop; ++$i)
            {
                $page_link = get_thread_link($thread['tid'], $i);
                eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
            }
            eval("\$thread['multipage'] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
        }
        else
        {
            $threadpages = '';
            $morelink = '';
            $thread['multipage'] = '';
        }
        eval("\$recentthreads .= \"".$templates->get("recentthread_thread")."\";");
        unset($posteravatar);
        unset($lastavatar);
        unset($icon);
    }
    eval("\$recentthreadtable = \"".$templates->get("recentthread")."\";");
    if($return)
    {
        return $recentthreadtable;
    }
}

function recentthread_get_templates()
{
    global $templatelist;
    if(THIS_SCRIPT == "index.php")
    {
        $templatelist .= ",recentthread,recentthread_thread,recentthread_avatar,recentthread_headerinclude,forumdisplay_thread_gotounread";
        $templatelist .= ",forumdisplay_thread_multipage,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage_more";
    }
}

function recentthread_global_intermediate()
{
    global $templates, $recentthread_headerinclude;
    if(THIS_SCRIPT == "index.php" && recentthread_can_view())
    {
        eval("\$recentthread_headerinclude = \"".$templates->get("recentthread_headerinclude")."\";");
    }
}

function recentthread_refresh_threads()
{
    global $db, $mybb, $canviewrecentthreads;
    if($mybb->input['action'] == "recent_threads")
    {
        require_once MYBB_ROOT . "/inc/plugins/recentthread.php";
        if(recentthread_can_view())
        {
            echo(recentthread_list_threads(true));
        }
        die;
    }
}

function recentthread_can_view()
{
    global $mybb;
    if($mybb->settings['recentthread_which_groups'])
    {
        $disallowedgroups = explode(",", $mybb->settings['recentthread_which_groups']);
        $mygroups = $mybb->user['usergroup'];
        if($mybb->user['additionalgroups'])
        {
            $mygroups .= "," . $mybb->user['additionalgroups'];
        }
        $groups = explode(",", $mygroups);
        foreach($groups as $group)
        {
            if(in_array($group, $disallowedgroups))
            {
                return FALSE;
            }
        }
        return TRUE;
    }
    else
    {
        return TRUE;
    }
}

function recentthread_admin_style_templates()
{
    global $lang;
    $lang->load("recentthreads");
}

function recentthread_admin_tools_adminlog_begin()
{
    global $lang;
    $lang->load("recentthreads");
}

function recenttthread_admin_tools_get_admin_log_action(&$plugin_array)
{
    if($plugin_array['logitem']['module'] == "config-plugins"  && $plugin_array['logitem']['action'] == "update_recentthreads")
    {
        $plugin_array['lang_string'] = "admin_log_config_plugins_update_recentthreads";
    }
}