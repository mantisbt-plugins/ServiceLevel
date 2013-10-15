<?php
# MantisBT - a php based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

	/**
	 * @package MantisBT
	 * @copyright Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
	 * @copyright Copyright (C) 2002 - 2009  MantisBT Team - mantisbt-dev@lists.sourceforge.net
	 * @link http://www.mantisbt.org
	 */
	 /**
	  * MantisBT Core API's
	  */
	require_once( 'core.php' );

	require_once( 'summary_api.php' );
	require_once( 'evaluation_api.php' );
	require_once( 'date_api.php' );

	access_ensure_project_level( config_get( 'view_summary_threshold' ) );

	$f_project_id = gpc_get_int( 'project_id', helper_get_current_project() );

	# Date range
	$t_from_date = gpc_get_string( 'from_date', null );
	$t_until_date = gpc_get_string( 'until_date', null );
	
	$day = 24 * 60 * 60;
	if ( empty ( $t_from_date ) ) {
		$t_from_date = ( time() - 30 * $day );
	} else {
		$t_from_date = strtotime( $t_from_date );
	}		
		if ( empty ( $t_until_date ) ) {
		$t_until_date = time();
	} else {
		$t_until_date = strtotime( $t_until_date );
	}		
	

	evaluation_create_views();

	# Override the current page to make sure we get the appropriate project-specific configuration
	$g_project_override = $f_project_id;

	$t_user_id = auth_get_current_user_id();

	$t_project_ids = user_get_all_accessible_projects( $t_user_id, $f_project_id);
	$specific_where = helper_project_specific_where( $f_project_id, $t_user_id);

	$t_bug_table = db_get_table( 'mantis_bug_table' );
	$t_history_table = db_get_table( 'mantis_bug_history_table' );

	$t_resolved = config_get( 'bug_resolved_status_threshold' );
	# the issue may have passed through the status we consider resolved
	#  (e.g., bug is CLOSED, not RESOLVED). The linkage to the history field
	#  will look up the most recent 'resolved' status change and return it as well
	$query = "SELECT b.id, b.date_submitted, b.last_updated, MAX(h.date_modified) as hist_update, b.status
        FROM $t_bug_table b LEFT JOIN $t_history_table h
            ON b.id = h.bug_id  AND h.type=0 AND h.field_name='status' AND h.new_value=" . db_param() . "
            WHERE b.status >=" . db_param() . " AND $specific_where
            GROUP BY b.id, b.status, b.date_submitted, b.last_updated
            ORDER BY b.id ASC";
	$result = db_query_bound( $query, Array( $t_resolved, $t_resolved ) );
	$bug_count = db_num_rows( $result );

	$t_bug_id       = 0;
	$t_largest_diff = 0;
	$t_total_time   = 0;
	for ($i=0;$i<$bug_count;$i++) {
		$row = db_fetch_array( $result );
		$t_date_submitted = $row['date_submitted'];
		$t_id = $row['id'];
		$t_status = $row['status'];
		if ( $row['hist_update'] !== NULL ) {
            $t_last_updated   = $row['hist_update'];
        } else {
        	$t_last_updated   = $row['last_updated'];
        }

		if ($t_last_updated < $t_date_submitted) {
			$t_last_updated   = 0;
			$t_date_submitted = 0;
		}

		$t_diff = $t_last_updated - $t_date_submitted;
		$t_total_time = $t_total_time + $t_diff;
		if ( $t_diff > $t_largest_diff ) {
			$t_largest_diff = $t_diff;
			$t_bug_id = $row['id'];
		}
	}
	if ( $bug_count < 1 ) {
		$bug_count = 1;
	}
	$t_average_time 	= $t_total_time / $bug_count;

	$t_largest_diff 	= number_format( $t_largest_diff / SECONDS_PER_DAY, 2 );
	$t_total_time		= number_format( $t_total_time / SECONDS_PER_DAY, 2 );
	$t_average_time 	= number_format( $t_average_time / SECONDS_PER_DAY, 2 );

	$t_orct_arr = preg_split( '/[\)\/\(]/', plugin_lang_get( 'scrc' ), -1, PREG_SPLIT_NO_EMPTY );

	$t_orcttab = "";
	foreach ( $t_orct_arr as $t_orct_s ) {
		$t_orcttab .= '<td class="right">';
		$t_orcttab .= $t_orct_s;
		$t_orcttab .= '</td>';
	}

	
	/* Statistical Information 
	 * @author alexander.menk@gtz.de
	 * 
	 * 
	 */
	
	 
	// there is a quick fix to not consider bugs with
// for all the bugs (resolved, otherwise use  acknowledged)
//	$query = "SELECT b.id, (status_update_time - date_submitted) as diff 
//				FROM mantis_bug_table as b
//				LEFT JOIN view_resolved sv ON (b.id = sv.bug_id)  WHERE status_update_time IS NOT NULL and status_update_time >= date_submitted ";
	
	
	function evaluation_helper_print_cols($p_label,$cols) {
		printf( '<tr %s>', helper_alternate_class() );
		printf( '<td width="50%%">%s</td>',  string_display_line( $p_label ) );
		$width = round(50 / count($cols));
		foreach ($cols as $col) {
			printf( '<td width="'.$width.'%%" class="right">%s</td>', (isset($col) ? $col : 0) );
		}
		print( '</tr>' );
	}
	
	function get_median($field, $query_after_select) {
		$query = "SELECT COUNT(*) as count $query_after_select";
		$result = db_query( $query );
		$row = db_fetch_array($result);
		$count=$row["count"];
		
		if (($count % 2)== 0) {
			$m = $count/2;
			$n= $m+1;
		} else {
			$m = ceil($count/2);
			$n = $m;
		}
		$query = "SELECT AVG(val) as median FROM (SELECT $field as val $query_after_select ORDER BY val LIMIT $m,$n) as tablex";
		$result = db_query( $query );
		
		$row = db_fetch_array($result);
		return round($row["median"]);		
	}
			
				
	
	/**
	 * Issues to exclude in the time analysis
	 * @param $asString true = text, false = sql
	 * @return string where query part
	 */
	function get_time_exclude($asString = false) {
		$exclude_categories = array(69,82);
		$exclude_projects = array(1);
		
		$result = array();
		
		foreach($exclude_categories as $id) {
			if ($asString) $result[] = 'Category "'.category_get_name($id).'"';
				else
					$result[] = 'category_id = '.$id;
		}

		foreach($exclude_projects as $id) {
			if ($asString) $result[] = 'Project "'.project_get_name($id).'"';
				else
					$result[] = 'project_id = '.$id;
		}
				
		if ($asString)
			return implode(', ', $result);
		else
			return 'NOT ('. implode(' OR ',$result) . ')';
			
	}
	
				
	function print_time_analysis($view , $p_enum = "priority") {
		global $t_from_date, $t_until_date;
		
		$t_project_id = helper_get_current_project();
		$t_project_filter = helper_project_specific_where( $t_project_id );
	
		
		$exclude = get_time_exclude();
		
		$query = "SELECT b.$p_enum as field,
			MIN(status_update_time - date_submitted) as min,
			MAX(status_update_time - date_submitted) as max,
			AVG(status_update_time - date_submitted) as avg
					FROM mantis_bug_table as b
					LEFT JOIN $view sv ON (b.id = sv.bug_id)  
					WHERE status_update_time IS NOT NULL and status_update_time >= date_submitted 
						AND date_submitted BETWEEN $t_from_date AND $t_until_date
						AND ($t_project_filter) AND ($exclude) GROUP BY b.$p_enum";
		
		evaluation_helper_print_cols("Client Group", array( "min" , "avg" , "median" , "max" ) );
		$result = db_query( $query );
		
		while($stats = db_fetch_array($result)) {
			
				$median = get_median("(status_update_time - date_submitted)", "FROM mantis_bug_table as b
					LEFT JOIN $view sv ON (b.id = sv.bug_id)  
					WHERE status_update_time IS NOT NULL and status_update_time >= date_submitted 
						AND date_submitted BETWEEN $t_from_date AND $t_until_date
						AND ($t_project_filter) AND ($exclude) AND b.$p_enum = " . $stats["field"]);				

				evaluation_helper_print_cols( get_enum_element( $p_enum, $stats["field"] ) , array( human_time($stats["min"]) , human_time($stats["avg"]) , human_time($median), human_time($stats["max"]) ) );
			
		}
		
		
		
		// Median, from comment on http://dev.mysql.com/doc/refman/5.0/en/group-by-functions.html
//		$query = "SELECT x.val FROM view_resolved_values x, view_resolved_values y GROUP BY x.val HAVING SUM(SIGN(1-SIGN(y.val-x.val))) = (COUNT(*)+1)/2";
//		$result = db_query( $query );
		//var_dump($result);
		return $stats;
		
	}
	
	function time_stats_list($view) {
		global $t_from_date, $t_until_date;
		
		$t_project_id = helper_get_current_project();
		$t_project_filter = helper_project_specific_where( $t_project_id );

		$exclude = get_time_exclude();
		
		$query = "
			SELECT 
				b.priority as priority, b.id as bug_id, status_update_time - date_submitted as diff
			FROM mantis_bug_table as b
			LEFT JOIN view_status_times sv ON (b.id = sv.bug_id)  
			WHERE 
				updated_to_status IN (52,54) 
				AND date_submitted BETWEEN $t_from_date AND $t_until_date 
				AND ($t_project_filter) 
				AND ($exclude) 
			ORDER BY diff DESC";
		
		echo '"bug_id";"client_group";"seconds"'."\n";
		$result = db_query( $query );
		while ($row = db_fetch_array($result)) {
			//echo "<a href=\"".$row["bug_id"]."\" target=\"_blank\">".$row["bug_id"]."</a> - ".human_time($row["diff"])."<br>";
			echo $row["bug_id"].";\"".get_enum_element( "priority", $row["priority"])."\";"; 
			echo $row["diff"]."\n";
		}
			
	}
		
	function human_time($t) {
		if ($t < 3*60) return number_format($t,1)." s";
		 $t/=60;
		 if ($t < 3*60) return number_format($t,1)." m";
		 $t/=60;
		 if ($t < 3*60) return number_format($t,1)." h";
		 $t/=24;
		 return number_format($t,1)." d";
	}
	
	


	if ( isset( $_GET["list"] ) ) {
		// The views that are allowed (to avoid SQL injection)
		$views = array("view_acknowledged","view_resolved");
		if (!in_array($_GET["list"],$views)) die("view not in the list of allowed views");
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"data.csv\"");
		time_stats_list($_GET["list"]);
		exit;
	}
		
	html_page_top( plugin_lang_get( 'link' ) );
?>


<script language="Javascript">

function ToggleCats( master, detail ) {

	var idlist=detail.split(","); 

	for(i=0;i<idlist.length;i++) {
		document.getElementById("dynrow"+idlist[i]).style.display="table-row";
	}

}

</script>
<br />
[ <a href="admin_fix_times.php">Admin: Update timestamps using manually specified dates</a> ]
<br />
<br />
<table class="width100" cellspacing="1">
<tr>
	<td class="form-title" colspan="1">
		<?php echo plugin_lang_get( 'title' ) ?>
	</td>
	<td class="right">
<?php
		if ( !date_is_null( $t_from_date ) ) {
			$t_from_date_to_display = date( config_get( 'calendar_date_format' ), $t_from_date );
		} else 
			$t_from_date_to_display = "";

		if ( !date_is_null( $t_until_date ) ) {
			$t_until_date_to_display = date( config_get( 'calendar_date_format' ), $t_until_date );
		} else 
			$t_until_date_to_display = "";

?>
Date range: 
<form name="date_range_form" method="POST" action="<?php echo plugin_page('evaluation_page') ?>">
	<input class="small" <?php echo helper_get_tab_index() ?> type="text" name="from_date" id="from_date" size="7" maxlength="16" value="<?= $t_from_date_to_display?>" /> 


<?php
	date_print_calendar("trigger_from", TRUE); 
	date_finish_calendar( 'from_date', 'trigger_from');
?>
-
	<input class="small" <?php echo helper_get_tab_index() ?> type="text" name="until_date" id="until_date" size="7" maxlength="16" value="<?= $t_until_date_to_display?>" />

<? date_print_calendar("trigger_until", FALSE); 
		date_finish_calendar( 'until_date', 'trigger_until');
?>
	<input class="button-small" type="submit" value="Apply Range">
</form>

	</td>
</tr>
<tr valign="top">
	<td width="50%">
		<table class="width100" cellspacing="1">
		<tr>
			<td class="form-title" colspan="1">
				<?php echo lang_get( 'by_severity' ) ?>
			</td>
			<?php echo $t_orcttab ?>
		</tr>
		<?php evaluation_print_by_enum( 'severity' , $t_from_date, $t_until_date) ?>
		</table>

		<br />
	
		<table class="width100">
		<tr>
			<td class="form-title" colspan="5">
				Reaction Time Analysis* (New -> first acknowledged) <a href="<?php 
					echo plugin_page('evaluation_page')
				?>&list=view_acknowledged&from_date=<?php 
					echo $t_from_date_to_display 
				?>&until_date=<?php echo $t_until_date_to_display  ?>" target="_blank">CSV</a>
			</td>
		</tr>
<?php 
	print_time_analysis("view_acknowledged")
?>		
		</table>

		<br />

		<table class="width100">
		<tr>
			<td class="form-title" colspan="5">
				Resolution Time Analysis* (New -> last resolution) <a href="<?php 
					echo plugin_page('evaluation_page')
				?>&list=view_resolved&from_date=<?php 
					echo $t_from_date_to_display 
				?>&until_date=<?php  echo $t_until_date_to_display  ?>">CSV</a>
			</td>
		</tr>
<?php 
	print_time_analysis("view_resolved")
?>		
		</table>
	
		<br />
	

		<?php # PROJECT # 
			if ( 1 < count( $t_project_ids ) ) { ?>
		<table class="width100" cellspacing="1">
		<tr>
			<td class="form-title" colspan="1">
				<?php echo lang_get( 'by_project' ) ?>
			</td>
			<?php echo $t_orcttab ?>
		</tr>
		<?php evaluation_print_by_project(null,0,null,$t_from_date, $t_until_date); ?>
		</table>

		<br />
		<?php } ?>

		<br />

	</td>



	<td width="50%">

		<table class="width100" cellspacing="1">
		<tr>
			<td class="form-title" colspan="1">
				<?php echo lang_get( 'by_priority' ) ?>
			</td>
			<?php echo $t_orcttab ?>
		</tr>
		<?php evaluation_print_by_enum( 'priority' , $t_from_date, $t_until_date) ?>
		</table>

		<br />

		<table class="width100" cellspacing="1">
		<tr>
			<td class="form-title" colspan="1">
				<?php echo lang_get( 'by_category' ) ?>
			</td>
			<?php echo $t_orcttab ?>
		</tr>
		<?php evaluation_print_by_category($t_from_date, $t_until_date) ?>
		</table>

		<br />
	</td>
</tr>

</table>

*Excluded: <?php echo get_time_exclude(true) ?>
<?php
	html_page_bottom( __FILE__ );
