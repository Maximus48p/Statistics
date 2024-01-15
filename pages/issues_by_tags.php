<?php
# Statistics - a statistics plugin for MantisBT
#

require_once 'statistics_api.php';

layout_page_header();
layout_page_begin( 'plugin.php?page=Statistics/start_page' );

access_ensure_global_level( config_get( 'tag_view_threshold' ) );

$project_id                 = helper_get_current_project();
$specific_where             = helper_project_specific_where( $project_id );
$resolved_status_threshold  = config_get( 'bug_resolved_status_threshold' );
$status_enum_string         = lang_get( 'status_enum_string' );
$status_values              = MantisEnum::getValues( $status_enum_string );
$count_states               = count_states();
$tag_names                  = tag_names();

$start  = strtotime( cleanDates( 'date-from', $dateFrom ) . " 00:00:00" );
$end = strtotime( cleanDates( 'date-to', $dateTo ) . " 23:59:59" );

// get data
$issues_fetch_from_db = array();

$query = "SELECT count(*) as the_count, mbtt.tag_id, mbt.status
        FROM {bug} mbt
        JOIN {bug_tag} mbtt on mbt.id = mbtt.bug_id
        WHERE $specific_where
         AND mbt.date_submitted >= " . $start . "
        AND mbt.date_submitted <= " . $end . "
        GROUP BY mbtt.tag_id, mbt.status
        ";
$result = db_query( $query );

foreach ( $result as $row ) {
    $issues_fetch_from_db[$row['tag_id']][$row['status']] = $row['the_count'];
}

unset ( $result );


// build tables and charts
function tables ( $type ) {

	global $resolved_status_threshold, $status_values, $status_enum_string, $tag_names, $issues_fetch_from_db, $maxResultsInTables;

	$data_table = $data_table_totals = array();

	foreach ( $issues_fetch_from_db as $key => $val ) {

        if ( !isset( $data_table_totals[$key] ) ) { $data_table_totals[$key] = 0; }

        foreach ( $status_values as $k => $v ) {
            if ( ( $v < $resolved_status_threshold and $type == "open" ) || ( $v >= $resolved_status_threshold and $type == "resolved" ) ) {
                if ( isset( $issues_fetch_from_db[$key][$v] ) ) {
            		$data_table[$key][$v] = $issues_fetch_from_db[$key][$v];
                    $data_table_totals[$key] = $data_table_totals[$key] + $issues_fetch_from_db[$key][$v];
                } else {
                    $data_table[$key][$v] = 0;
                }
            }
        }
    }


    // build table header
    $data_table_print = "
         <thead>
        <tr class='tblheader'>
            <td width='100%'>" . lang_get( 'tags' ) . "</td>";

    foreach ( $status_values as $key => $val ) {
        if ( ( $type == 'open' and $val >= $resolved_status_threshold ) || ( $type == 'resolved' and $val < $resolved_status_threshold ) ) { continue; }
        $data_table_print .= "<td class='dt-right nowrap'>" . MantisEnum::getLabel( $status_enum_string, $val ) . "&nbsp;</td>";
    }

    $data_table_print .= "
            <td class='dt-right'>" . lang_get( 'plugin_Statistics_total' ) . "</td>
        </tr>
        </thead>";


    // build table body
    $i = 0;

    arsort( $data_table_totals );

    foreach ( $data_table_totals as $key => $val ) {

        $i++;
        if ( $val == 0 || ( $i > $maxResultsInTables and $maxResultsInTables != 0 ) ) { break; }

		$data_table_print .= "
        <tr>
            <td>" . $tag_names[$key] . "</td>";

        foreach ( $status_values as $k => $v ) {
            if ( ( $type == 'open' and $v >= $resolved_status_threshold ) || ( $type == 'resolved' and $v < $resolved_status_threshold ) ) { continue; }
            $data_table_print .= "
            <td class='dt-right' nowrap>" . number_format( $data_table[$key][$v] ) . "&nbsp;</td>";
        }
        $data_table_print .= "
            <td class='dt-right'>" . number_format( $val ) . "</td>
        </tr>";
	}


return $data_table_print;

}

?>


<script type='text/javascript' src="<?php echo plugin_page( 'csp_support&r=ibtag' ); ?>"></script>

                
<div id="wrapper">

        <div id="logo">
			<a href="https://github.com/mantisbt-plugins/Statistics" target="_blank"><img src="<?php echo plugin_file( 'statistics.png' ); ?>" width='70' height='70' alt='Statistics' /></a>
        </div>

        <?php echo $whichReport; ?>

        <p class='space20Before' />

        <div id="titleText">
            <div id="scope"><?php echo lang_get( 'plugin_Statistics_project' ); ?>: <?php echo project_get_name( $project_id ); ?></div><div id="sup"><?php if ( $project_id == ALL_PROJECTS ) { echo "<sup>&dagger;</sup>"; } ?></div>
        </div>

        <p class="clear" />


        <div id="filter">
            <strong><?php echo lang_get( 'plugin_Statistics_timeframe' ); ?></strong>

            <form method="get">
                <input type="hidden" name="page" value="Statistics/issues_by_status" />
                <?php echo form_security_field( 'date_picker' ) ?>

                <div>
                    <div>
                        <input type="text" name="date-from" id="from" value="<?php echo cleanDates('date-from', $dateFrom); ?>" />
                        -
                        <input type="text" name="date-to" id="to"  value="<?php echo cleanDates('date-to', $dateTo); ?>" />
                    </div>
                </div>
                <div>
                    <input type="submit" id="displaysubmit" value=<?php echo lang_get( 'plugin_Statistics_display' ); ?> class="button" />
                </div>
            </form>
        </div>

<table>
<tr>

<td><img src="plugin.php?page=Statistics/tags-graph.php&start=<?php echo $start ?>&end=<?php echo $end ?>&set=0 "></td>
<td><img src="plugin.php?page=Statistics/tags-graph.php&start=<?php echo $start ?>&end=<?php echo $end ?>&set=1 "></td>
<td><img src="plugin.php?page=Statistics/tags-graph.php&start=<?php echo $start ?>&end=<?php echo $end ?>&set=2 "></td>
</tr>
</table>

<table>
<tr><td> <strong>&raquo; <?php echo lang_get( 'plugin_Statistics_open_issues' ); ?></strong></td></tr>
</table><table>
<?php echo tables( "open" ); ?>
</table>

<table>
<tr><td><strong>&raquo; <?php echo lang_get( 'plugin_Statistics_resolved_issues' ); ?></strong></td></tr>
</table><table>
<?php echo tables( "resolved" ); ?>
</table>

<p class="space40Before" />
<?php if ( $project_id == ALL_PROJECTS ) { echo "<p />&dagger; " . lang_get( 'plugin_Statistics_priv_proj_skip' ) . "<br />"; } ?>
<strong>&raquo;</strong> <?php printf( lang_get( 'plugin_Statistics_charts_maxdisp' ), MAX_LINES_IN_BAR_CHARTS ); ?>
<?php if ( $showRuntime == 1 ) { printf( "<p class='graycolor'>" . lang_get( 'plugin_Statistics_runtime_string' ) . "</p>", round(microtime(true) - $starttime, 5) ); } ?>
</div>
<?php 
layout_page_end();