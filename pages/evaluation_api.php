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
 * @package CoreAPI
 * @subpackage SummaryAPI
 * @copyright Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright (C) 2002 - 2009  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

/**
 * requires config_filter_defaults_include
 */
require_once( $g_absolute_path . 'config_filter_defaults_inc.php' );


function evaluation_helper_print_row( $p_label, $p_open, $p_resolved, $p_closed, $p_total, $hidden_id = FALSE , $detail_ids = FALSE) {
	if ($hidden_id) {
		$hide = " style=\"display: none\" id=\"dynrow".$hidden_id."\"";

	} else $hide = "";
	if ($detail_ids) {
		$plus = '<a href="#" onclick="ToggleCats( \'bugnotes\', \''.$detail_ids.'\' ); return false;"><img border="0" src="images/plus.png" alt="+" /></a>';
	} else $plus ="";


	printf( '<tr %s'.$hide.'>', helper_alternate_class() );
	printf( '<td width="50%%">%s</td>',  $plus . string_display_line( $p_label ) );
	printf( '<td width="12%%" class="right">%s</td>', (isset($p_open) ? $p_open : 0) );
	printf( '<td width="12%%" class="right">%s</td>', (isset($p_resolved) ? $p_resolved : 0)  );
	printf( '<td width="12%%" class="right">%s</td>', (isset($p_closed) ? $p_closed : 0) );
	printf( '<td width="12%%" class="right">%s</td>', (isset($p_total) ? $p_total : 0)  );
	print( '</tr>' );
}

/**
 * Creates (or overwrites) a view in the database for an easier timestamp based evaluation of bug status changes
 * 
 * 
 * @author alexander.menk@gtz.de
 * @param string $view_name
 * @param integer $threshold
 * @param string (LAST or FIRST) $order
 */
function evaluation_create_status_view($view_name, $threshold , $order = "LAST") {
	$t_bug_table = db_get_table( 'mantis_bug_table' );
	$t_history_table = db_get_table( 'mantis_bug_history_table' );
	
	// LAST => means to get the maximum timestamp value
	$minmax = ($order == "LAST") ? "MAX" : "MIN";
//	$query = "CREATE OR REPLACE VIEW $view_name AS
//	SELECT 
//		b.id as bug_id,
//		IFNULL($minmax( h.date_modified ) , b.last_updated ) AS status_update_time,
//		b.status as updated_to_status
//        FROM $t_bug_table b LEFT JOIN $t_history_table h
//            ON b.id = h.bug_id 
//            WHERE b.status >=" . db_param() . "  AND h.type=0 AND h.field_name='status' AND h.new_value=" . db_param() . "
//            GROUP BY b.id, b.status, b.date_submitted, b.last_updated
//            ORDER BY b.id ASC";
	
	$query = "CREATE OR REPLACE VIEW $view_name AS
	SELECT 
		b.id as bug_id,
		$minmax( h.date_modified ) AS status_update_time,
		h.new_value as updated_to_status
        FROM $t_bug_table b LEFT JOIN $t_history_table h
            ON b.id = h.bug_id 
            WHERE b.status >=" . db_param() . "  AND h.type=0 AND h.field_name='status' AND h.new_value=" . db_param() . "
            GROUP BY b.id, h.new_value
            ORDER BY b.id ASC";	
	
	$result = db_query_bound( $query, Array( $threshold, $threshold ) );
}

/*
SELECT
b.id as bug_id,
h.date_modified AS status_update_time,
h.new_value as updated_to_status
FROM mantis_bug_table b 
LEFT JOIN mantis_bug_history_table h
ON b.id = h.bug_id
WHERE h.field_name = 'status'
*/


/*
 * Create MySQL views that are required for the analysis
 * Views are documented in the code below.
 */

function evaluation_create_views() {
/*
 * view_statuschange_xxx
 * 
 * submitted time, status_updated_to_this_status_time, current 
 * view_resolved
 * status_update: when was the status updated to resolved (first or last) ??
 */	
	# is the date the first or the last occurance of the resolution ??
	evaluation_create_status_view("view_resolved", config_get( 'bug_resolved_status_threshold' ) , "LAST" );
	// holds the timestamp of the last
	evaluation_create_status_view("view_closed", config_get( 'bug_closed_status_threshold' ) , "LAST" );
	// holds the timestamp of the first confirmation
	
	
	$confirmed = 54; //handler_confirmed
	evaluation_create_status_view("view_confirmed", $confirmed , "FIRST");

	// holds the timestamp of the first change to ACKNOWLEDGED
	$acknowledged = 52; // handler_acknowledged
	evaluation_create_status_view("view_acknowledged", $acknowledged , "FIRST");
	
//	$query = "
//	CREATE OR REPLACE VIEW view_resolved_values AS
//	SELECT (status_update_time - date_submitted) as val
//				FROM mantis_bug_table as b
//				LEFT JOIN view_resolved sv ON (b.id = sv.bug_id)
//		WHERE status_update_time IS NOT NULL and status_update_time >= date_submitted
//		";
//	$result = db_query( $query);
//	
//		
//	$query = "
//	CREATE OR REPLACE VIEW view_acknowledged_values AS
//	SELECT (status_update_time - date_submitted) as val
//				FROM mantis_bug_table as b
//				LEFT JOIN view_acknowledged sv ON (b.id = sv.bug_id)
//		WHERE status_update_time IS NOT NULL and status_update_time >= date_submitted
//		";
//	$result = db_query( $query);
	// open bugs: use date_submitted, b.status <= $t_resolved


	//evaluation_create_view("view_resolved", 


}


# print a bug count per category
function evaluation_print_by_category($from, $until) {
	$t_mantis_bug_table = db_get_table( 'mantis_bug_table' );
	$t_mantis_category_table = db_get_table( 'mantis_category_table' );
	$t_mantis_project_table = db_get_table( 'mantis_project_table' );
	$t_mantis_history_table = db_get_table( 'mantis_bug_history_table' );

	$t_summary_category_include_project = config_get( 'summary_category_include_project' );


	$t_project_id = helper_get_current_project();
	$t_user_id = auth_get_current_user_id();

	$specific_where = trim( helper_project_specific_where( $t_project_id ) );
	if( '1<>1' == $specific_where ) {
		return;
	}


	$query_submitted = "SELECT COUNT(b.id) as bugcount, c.name AS category_name, category_id, b.status
				FROM $t_mantis_bug_table b
				JOIN $t_mantis_category_table AS c ON b.category_id=c.id
				WHERE b.$specific_where AND b.date_submitted BETWEEN $from AND $until
				GROUP BY category_id, c.name
				ORDER BY c.name, b.status";

	$query_byview = "SELECT COUNT(b.id) as bugcount, c.name AS category_name, category_id, b.status
				FROM $t_mantis_bug_table b
				JOIN $t_mantis_category_table AS c ON b.category_id=c.id
				JOIN %VIEW% ON b.id = %VIEW%.bug_id
				WHERE b.$specific_where AND %VIEW%.status_update_time BETWEEN $from AND $until
				GROUP BY category_id, c.name
				ORDER BY c.name, b.status";

	### calculate statistics
	$statistics = array();
	# key = catgeory_id
	# contains: array
	#  name
	#  other columns
	$result = db_query($query_submitted );
	while( $row = db_fetch_array( $result ) ) {
		if (!isset( $statistics[$row["category_id"]]) ) 
			$statistics[$row["category_id"]] = array("name" => $row["category_name"]);
		$statistics[$row["category_id"]]["submitted"] = $row["bugcount"];
	}

	foreach (array("resolved", "closed", "confirmed") as $state) {
		$result = db_query(str_replace("%VIEW%", "view_$state", $query_byview)  );
		while( $row = db_fetch_array( $result ) ) {
			if (! isset($statistics[$row["category_id"]]))
				$statistics[$row["category_id"]] = array("name" => $row["category_name"]);
			$statistics[$row["category_id"]][$state] = $row["bugcount"];
		}
	}

	$masterdetail = array();
	$subcategories = array(); // list of sub-cateogory IDs

	foreach($statistics as $id => $stat_row) {
			list($master,$sub) = explode(" > ",$stat_row["name"]);
			if (! isset( $masterdetail[$master]) ) {
				$masterdetail[$master] = array();
				$subcategories[$master] = array();
			}
			foreach ( array("submitted", "confirmed", "resolved", "closed ") as $status ) {
				if ( ! isset($masterdetail[$master][$status]) )
					$masterdetail[$master][$status] = 0;
				$masterdetail[$master][$status] += ( isset($stat_row[$status]) ? $stat_row[$status] : 0 );
			}
			$subcategories[$master][] = $id;
	}

	### grouping by master category
	$old_master ="";
	foreach($statistics as $id=>$stat_row) {
			list($master,$sub) = explode(" > ",$stat_row["name"]);
			// master catergory
			if ($old_master != $master) {
				$old_master = $master;
				evaluation_helper_print_row( "<b>".$master."</b>",
					@$masterdetail[$master]["submitted"] ,
					@$masterdetail[$master]["confirmed"] ,
					@$masterdetail[$master]["resolved"] ,
					@$masterdetail[$master]["closed"],
					FALSE,  // not hidden
					implode(",",$subcategories[$master]) // provide functions to show stuff
				);
			}

			// sub category
			evaluation_helper_print_row( @$stat_row["name"], @$stat_row["submitted"] , @$stat_row["confirmed"], @$stat_row["resolved"], @$stat_row["closed"], $id);
			echo "</div>";
	}

#echo "<pre>";
#
#	var_dump($statistics);
#echo "</pre>";
}


# print bug counts by project
function evaluation_print_by_project( $p_projects = null, $p_level = 0, $p_cache = null , $from = null, $until = null) {
	$t_mantis_bug_table = db_get_table( 'mantis_bug_table' );
	$t_mantis_project_table = db_get_table( 'mantis_project_table' );

	$t_project_id = helper_get_current_project();

	if( null == $p_projects ) {
		if( ALL_PROJECTS == $t_project_id ) {
			$p_projects = current_user_get_accessible_projects();
		} else {
			$p_projects = Array(
				$t_project_id,
			);
		}
	}

	# Retrieve statistics one time to improve performance.
	if( null === $p_cache ) {
		if (null === $from) die("from and until need to be defined on top level of recursion");

		$query_submitted = "SELECT b.project_id as project_id, COUNT(b.status) as bugcount
					FROM $t_mantis_bug_table as b
					WHERE b.date_submitted BETWEEN $from AND $until
					GROUP BY b.project_id";

		$query_byview = "SELECT b.project_id as project_id, COUNT(b.status) as bugcount
					FROM $t_mantis_bug_table b
					JOIN %VIEW% ON b.id = %VIEW%.bug_id
					WHERE %VIEW%.status_update_time BETWEEN $from AND $until
					GROUP BY project_id";

		### calculate statistics
		$statistics = array();
		# key = project_id
		# contains: array
		#  name
		#  other columns
		$result = db_query($query_submitted );
		while( $row = db_fetch_array( $result ) ) {
			if (!$statistics[$row["project_id"]]) $statistics[$row["project_id"]] = array("name" => project_get_name($row["project_id"]));
			$statistics[$row["project_id"]]["submitted"] = $row["bugcount"];
		}

		foreach (array("resolved", "closed", "confirmed") as $state) {
			$result = db_query(str_replace("%VIEW%", "view_$state", $query_byview)  );
			while( $row = db_fetch_array( $result ) ) {
				if (!$statistics[$row["project_id"]]) $statistics[$row["project_id"]] = array("name" => $row["category_name"]);
				$statistics[$row["project_id"]][$state] = $row["bugcount"];
			}
		}

		$p_cache = $statistics;
	}

	foreach( $p_projects as $t_project ) {
		$t_name = str_repeat( "&raquo; ", $p_level ) . project_get_name( $t_project );

		$stat_row = isset( $p_cache[$t_project] ) ? $p_cache[$t_project] :
			array('submitted' => 0, 'confirmed' => 0, 'resolved' => 0 , 'closed' => 0);


		if ( count( project_hierarchy_get_subprojects ( $t_project ) ) > 0 ) {
			$t_subprojects = current_user_get_accessible_subprojects( $t_project );
			$total_sum = $stat_row;
			evaluation_helper_summarize_all_sub_projects($t_project, $p_cache, $total_sum);

			// total sum
			evaluation_helper_print_row( "<b>".$t_name."</b>", $total_sum["submitted"] , $total_sum["confirmed"],
				$total_sum["resolved"], $total_sum["closed"]);
			// the project itself
			evaluation_helper_print_row( $t_name, $stat_row["submitted"] , $stat_row["confirmed"],
				$stat_row["resolved"], $stat_row["closed"]);
			// subprojects
			if( count( $t_subprojects ) > 0 ) {
				evaluation_print_by_project( $t_subprojects, $p_level + 1, $p_cache );
			}

		} else {
			evaluation_helper_print_row( $t_name, $stat_row["submitted"] , $stat_row["confirmed"],
				$stat_row["resolved"], $stat_row["closed"]);
		}
	}
}

function evaluation_helper_summarize_all_sub_projects($t_project, $p_cache, &$total_sum) {
#	echo "enter: $t_project ".project_get_name($t_project)."<br>";
	if ( count( project_hierarchy_get_subprojects ( $t_project ) ) > 0 ) {
		$t_subprojects = current_user_get_accessible_subprojects( $t_project );
#		echo "Subproj: "; echo implode(",",$t_subprojects); echo "<br>";
#		echo "total sum"; var_dump($total_sum); echo "<br>";
		foreach($t_subprojects as $subproj) {
#				echo "sub of $t_project: ".project_get_name($subproj)."<br>";
				// add counts of this subproject to the total sum

				$stat_row = isset( $p_cache[$subproj] ) ? $p_cache[$subproj] :
					array('submitted' => 0, 'confirmed' => 0, 'resolved' => 0 , 'closed' => 0);
				foreach(array_keys($total_sum) as $key)
					$total_sum[$key] += $stat_row[$key];

#				echo "total sum"; var_dump($total_sum); echo "<br>";
				// dive into recursion to add sub-sub-projects ...
				evaluation_helper_summarize_all_sub_projects( $subproj, $p_cache, $total_sum );
		}
	}
}



# Used in summary reports
# this function prints out the summary for the given enum setting
# The enum field name is passed in through $p_enum
function evaluation_print_by_enum( $p_enum , $from , $until) {

	$t_project_id = helper_get_current_project();
	$t_user_id = auth_get_current_user_id();

	$t_project_filter = helper_project_specific_where( $t_project_id );
	if( ' 1<>1' == $t_project_filter ) {
		return;
	}


	$t_mantis_bug_table = db_get_table( 'mantis_bug_table' );


	$query_submitted = "SELECT COUNT(b.id) as bugcount, b.$p_enum as field
				FROM $t_mantis_bug_table as b
				WHERE $t_project_filter AND
					b.date_submitted BETWEEN $from AND $until
				GROUP BY b.$p_enum";

	$query_byview = "SELECT COUNT(b.id) as bugcount, b.$p_enum as field
				FROM $t_mantis_bug_table b
				JOIN %VIEW% ON b.id = %VIEW%.bug_id
				WHERE  $t_project_filter AND %VIEW%.status_update_time BETWEEN $from AND $until
				GROUP BY b.$p_enum";

	### calculate statistics
	$statistics = array();

	$result = db_query($query_submitted );
	while( $row = db_fetch_array( $result ) ) {
		if (!isset($statistics[$row["field"]]))
			$statistics[$row["field"]] = 
				array("name" => get_enum_element( $p_enum, $row["field"]));
		$statistics[$row["field"]]["submitted"] = $row["bugcount"];
	}

	foreach (array("resolved", "closed", "confirmed") as $state) {
		$result = db_query(str_replace("%VIEW%", "view_$state", $query_byview)  );
		while( $row = db_fetch_array( $result ) ) {
			if (!isset($statistics[$row["field"]]))
				$statistics[$row["field"]] = 
					array("name" => get_enum_element( $p_enum, $row["field"]));
			$statistics[$row["field"]][$state] = $row["bugcount"];
		}
	}

	foreach($statistics as $stat_row) {
		evaluation_helper_print_row( $stat_row["name"], @$stat_row["submitted"] , @$stat_row["confirmed"], @$stat_row["resolved"], @$stat_row["closed"]);
	}

}

?>
