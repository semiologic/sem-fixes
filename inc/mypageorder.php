<?php
/*
Author: froman118
Author URI: http://www.geekyweekly.com
Author Email: froman118@gmail.com
*/

function mypageorder_menu()
{   if (function_exists('add_submenu_page')) {
        add_submenu_page(mypageorder_getTarget(), 'My Page Order', __('My Page Order', 'sem-fixes'), 'edit_pages',"mypageorder",'mypageorder');
    }
}

function mypageorder_js_libs() {
	if ( !empty($_GET['page']) && $_GET['page'] == "mypageorder" ) {
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-sortable');
	}
}

//Switch page target depending on version
function mypageorder_getTarget() {
	return 'page-new.php';

	global $wp_version;
	switch (true) {
		case version_compare($wp_version, '3.0.0', '>='):
			return 'page-new.php';
		case version_compare($wp_version, '2.9.0', '>='):
			return 'edit-pages.php';
		case version_compare($wp_version, '2.6.5', '>'):
			return 'page-new.php';
		default:
			return 'edit.php';
	}
}

/**
 * get link base to the current page
 * 
 * @return string base-link, so to add additional query parameters at the end.
 */
function mypageorder_getLinkBase() {
	global $pagenow;
	global $typenow;
	
	$link = $pagenow . '?';
	if (isset($typenow))
		$link .= 'post_type=' . $typenow . '&';
		
	return $link;
}

add_action('admin_menu', 'mypageorder_menu');
add_action('admin_menu', 'mypageorder_js_libs'); 

function mypageorder()
{
global $wpdb;
$mode = "";
if (isset($_GET['mode']))
	$mode = $_GET['mode'];
$parentID = 0;
if (isset($_GET['parentID']))
	$parentID = (int) $_GET['parentID'];
$success = "";

if($mode == "act_OrderPages")
{  
	$idString = $_GET['idString'];
	$IDs = explode(",", $idString);
	$IDs = array_map('intval', $IDs);
	$result = count($IDs);

	for($i = 0; $i < $result; $i++)
	{
		$wpdb->query("UPDATE $wpdb->posts SET menu_order = '$i' WHERE id ='$IDs[$i]'");
    }
	do_action('flush_cache');
	$success = '<div id="message" class="updated fade"><p>'. __('Page order updated successfully.', 'sem-fixes').'</p></div>';
}

	$subPageStr = "";
	$results=$wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_parent = $parentID and post_type = 'page' AND post_status IN ( 'publish', 'private' ) ORDER BY menu_order ASC");
	foreach($results as $row)
	{
		$postCount=$wpdb->get_row("SELECT count(*) as postsCount FROM $wpdb->posts WHERE post_parent = $row->ID and post_type = 'page' AND post_status IN ( 'publish', 'private' )", ARRAY_N);
		if($postCount[0] > 0)
	    	$subPageStr = $subPageStr."<option value='$row->ID'>$row->post_title</option>";
	}
?>
<div class='wrap'>
	<h2><?php _e('My Page Order', 'sem-fixes') ?></h2>
<?php echo $success; ?>
	<p><?php _e('Choose a page from the drop down to order its subpages or order the pages on this level by dragging and dropping them into the desired order.', 'sem-fixes') ?></p>

<?php	
	if($parentID != 0)
	{
		$parentsParent = $wpdb->get_row("SELECT post_parent FROM $wpdb->posts WHERE ID = $parentID ", ARRAY_N);
		echo "<a href='". mypageorder_getTarget() . "?page=mypageorder&parentID=$parentsParent[0]'>" . __('Return to parent page', 'sem-fixes') . "</a>";
	}
 if($subPageStr != "") { ?>
	<h3><?php _e('Order Subpages', 'sem-fixes') ?></h3>
	<select id="pages" name="pages"><?php
		echo $subPageStr;
	?>
	</select>
	&nbsp;<input type="button" name="edit" Value="<?php _e('Order Subpages', 'sem-fixes') ?>" onClick="javascript:goEdit();">
<?php } ?>

	<h3><?php _e('Order Pages', 'sem-fixes') ?></h3>
	<ul id="order" style="width: 500px; margin:10px 10px 10px 0px; padding:10px; border:1px solid #B2B2B2; list-style:none;"><?php
	foreach($results as $row)
	{
		echo "<li id='$row->ID' class='lineitem'>$row->post_title</li>";
	}?>
	</ul>
	
	<input type="button" id="orderButton" Value="<?php _e('Click to Order Pages', 'sem-fixes') ?>" onclick="javascript:orderPages();">&nbsp;&nbsp;<strong id="updateText"></strong>

</div>

<style>
	li.lineitem {
		margin: 3px 0px;
		padding: 2px 5px 2px 5px;
		background-color: #F1F1F1;
		border:1px solid #B2B2B2;
		cursor: move;
		width: 490px;
	}
</style>

<script language="JavaScript">

	jQuery(document).ready(function(){
		jQuery("#order").sortable({ 
			placeholder: "ui-selected", 
			revert: false,
			tolerance: "pointer" 
		});
	});
	
	function orderPages() {
		jQuery("#orderButton").css("display", "none");
		jQuery("#updateText").html("<?php _e('Updating Page Order...', 'sem-fixes') ?>");

		idList = jQuery("#order").sortable("toArray");
		location.href = '<?php echo mypageorder_getLinkBase(); ?>page=mypageorder&mode=act_OrderPages&parentID=<?php echo $parentID; ?>&idString='+idList;
	}

	function goEdit () {
		if(jQuery("#pages").val() != "")
			location.href="<?php echo mypageorder_getLinkBase(); ?>page=mypageorder&mode=dsp_OrderPages&parentID="+jQuery("#pages").val();
	}
</script>
<?php
}
?>