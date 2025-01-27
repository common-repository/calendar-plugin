<?php
/*
Plugin Name: Calendar_plugin
Plugin URI: http://www.aieee-guru.com/wp-content/plugins/calendar_plugin
Description: This plugin integrates google calender api to wordpress admin page Author is Tanuj Makkar .
Version: 1.0
Author: Tanuj makkar
Author URI: www.aieee-guru.com
*/

//define('WP_DEBUG', true);
//error_reporting( E_ALL );
$google_path = "../wp-content/plugins/Google Calendar Wordpress/";
$google_default = false;
$google_installed = true;

add_action('init', 'google_calendar_init');

function google_calendar_init()
{		
	global $google_calendar_privileges, $table_prefix, $wpdb, $google_path, $google_default, $google_installed;
 	add_action('admin_menu', 'google_calendar_config_page');
 	
 	$google_default = false;
 	
	$google_calendar_privileges = (int)get_option("google_calendar_privileges");
}

function google_calendar_config_page() 
{
	global $google_calendar_privileges;
	if ( function_exists('add_submenu_page') )
	{
		add_menu_page('Google Calendar', 'Google Calendar', $google_calendar_privileges, __FILE__, 'google_calendar_main_page');
		add_submenu_page(__FILE__, 'Settings', 'Settings', $google_calendar_privileges, 'maintenance', 'google_calendar_manage_page');
	}
}

function google_calendar_main_page()
{
	global $google_default, $userdata, $table_prefix, $wpdb, $google_installed;
    get_currentuserinfo();
    
    if( !google_calendar_installed() )
		$google_installed = google_calendar_install();
    
    if( !$google_installed )
    {
		echo "PLUGIN NOT CORRECTLY INSTALLED, PLEASE CHECK ALL INSTALL PROCEDURE!";
		return;
	}
    
    $user_login = $userdata->user_login;
    if( isset( $_POST["name"]) )
    {
		 $query = "
			SELECT code AS code
			FROM ".$table_prefix."google_calendar
			WHERE (user_login = '$user_login' OR shared = 1)
			AND name = '".$_POST["name"]."'
		";
		$code = $wpdb->get_var( $query );
	}
	else
	{
	    $query = "
			SELECT code AS code
			FROM ".$table_prefix."google_calendar
			WHERE user_login = '$user_login'
			OR shared = 1
			LIMIT 1
		";
		$code = $wpdb->get_var( $query );
	}
	?>
	<div class="wrap">
	<?php
	if( $code === null )
	{
		echo '<h4>You don\'t have a Google Calendar, please set code in Settings menu.</h4>';
	}
	else
	{
		?>
		<h2>Google Calendar</h2>
		<form method="POST" action="<?PHP echo $_SERVER["PHP_SELF"]."?page=".$_GET["page"]; ?>">
		<b>Choose the calendar which you want view: </b><select name="name">
			<?php
				$query = "
					SELECT name AS name
					FROM ".$table_prefix."google_calendar
					WHERE user_login = '$user_login'
					OR shared = 1
				";
				$rows = $wpdb->get_results( $query );
				
				$calendars = count( $rows );

				foreach( $rows as $row )
					echo '<option value="'.$row->name.'">'.$row->name."</li>";
			?>
		</select><input type="submit" name="set" value="Change" />
		</form>
		<h2>The Calendar</h2>
		<?php if( $google_default )echo '<h4 align="center">WARNING, you not have select the privileges for use Google Calendar, please use Admin Settings menu for correct.</h4>'?>
		<center>
		<?php echo $code ?>
		</center>
		<?php
	}
	?>
	</div>
	<?php
}

function google_calendar_manage_page()
{
	global $google_default, $userdata, $table_prefix, $wpdb;
	get_currentuserinfo();
    $user_login = $userdata->user_login;
    ?>
	<div class="wrap">
	<?php
	$valid = true;
	if( isset($_POST["set"]) AND $_POST["set"] == "Set Values" )
	{
		$shared = 0;
		if( isset($_POST["shared"]) AND $_POST["shared"] == "true" )
			$shared = 1;
			
		if( !google_calendar_code( $_POST["code"] ) )
			$valid = false;
		else
		{
			$query ="
				INSERT INTO ".$table_prefix."google_calendar ( user_login, code, name, shared )
				VALUES ( '$user_login', '".$_POST["code"]."', '".$_POST["name"]."', $shared )
			";
			$wpdb->query( $query );
		}
	}
	?>
		<h2>Your calendar</h2>
		<?php
			// ONLY YOUR CALENDARS NOT SHARED BY AN ADMIN!
			$query = "
				SELECT name AS name
				FROM ".$table_prefix."google_calendar
				WHERE user_login = '$user_login'
			";
			$rows = $wpdb->get_results( $query );
			
			$calendars = count( $rows );
			if( $calendars != 0 )
			{
				echo "<ul>";
				foreach( $rows as $row )
					echo "<li>$row->name</li>";
				echo "</ul>";
			}
			else
				echo "<p>You don't have any calendars.</p>"
		?>
		<h2>Settings</h2>
		<?php
			if( !$valid )
				echo '<h4 align=\"center\">You must use the google calendar code!</h4>';
			elseif( $valid AND isset($_POST["set"]) )
				echo '<h4 align="center">Your calendar are now saved, go to Google Calendar section for view it!</h4>';
				
		?>
		<form action="<?php echo $_SERVER["PHP_SELF"]."?page=".$_GET["page"]; ?>" method="POST">
			<p><b>Google Calendar Name: </b><br /><input type="text" size="50" name="name" value="" /></p>
			<p><b>Google Calendar Code: </b><br /><textarea name="code" rows="5" cols="60"></textarea></p>
			<?php
				if( $userdata->user_level == 10 )
					echo '<p><b>Shared: </b><input type="checkbox" name="shared" value="true" /></p>';
			?>
			<p><input type="submit" name="set" value="Set Values" /></p>
		</form>
	</div>
	<?php	
}


function google_calendar_code( $code )
{
	if( strpos($code, "<iframe") === FALSE )
		return false;
	else
		return true;
}

function google_calendar_installed()
{
	global $table_prefix, $wpdb;
	
	$query = "
		SHOW TABLES LIKE '".$table_prefix."google_calendar'
	";
	$install = $wpdb->get_var( $query );

	if( $install === NULL )
		return false;
	else
		return true;
}

function google_calendar_install()
{
	global $table_prefix, $wpdb;
	
	$query = "
		CREATE TABLE ".$table_prefix."google_calendar (
			calendar_id INT(11) NOT NULL auto_increment,
			user_login VARCHAR(255) NOT NULL,
			name VARCHAR(255) NOT NULL,
			code TEXT NOT NULL,
			shared TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY( calendar_id )
		)
	";
	$wpdb->query( $query );

	//Using option for google calendar plugin!
	add_option( "google_calendar_privileges", "2" );

	if( !google_calendar_installed() )
		return false;
	else
		return true;
}
?>