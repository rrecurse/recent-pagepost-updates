<?php
/*
Plugin Name: Recent Page/Post Updates
Description: A widget to show the most recently modified pages, posts or both, allowing visitors to review recently updated or uploaded pages and/or posts. Options to filter by Parent page, select limit of items returned and exclude items by ID.
Version: 1.0
Author: cdebellis
*/

if (!defined('ABSPATH')) { 
	die('Direct Access Forbidden!'); 
}

class recentUpdates extends WP_Widget {
	/**
	 * @internal
	 */
	function __construct() {
		$widget_ops = array( 'description' => 'Widget to display the most recent posts and page updates.' );
		parent::__construct( false, __( 'Recently Updated', 'recent-updates' ), $widget_ops );
	}
	/**
	 * @param array $args
	 * @param array $instance
	 */
	function widget( $args, $instance ) {
		extract( $args );
		$title		= esc_attr( $instance['title'] );
		$class		= esc_attr($instance['class']);
		$number		= esc_attr((int)$instance['number']);
		$content	= esc_attr($instance['content']);
		$exclude	= esc_attr($instance['exclude']);

        $hideTitle = !empty($instance['hideTitle']) ? true : false;
        $newWindow = !empty($instance['newWindow']) ? true : false;
        $filter = !empty($instance['filter']) ? true : false;

		if ($number < 1 || $number > 50) { 
			$number = 15; 
		} else {
			$number = $number; 
		}

		$content_sql = '';

		if($filter) { 

			$page = get_queried_object();
			$children = get_pages('child_of='.$page->post_parent);

			$content_sql .= "ID != '". $page->post_parent ."' AND post_parent = '". $page->post_parent ."' AND "; 

			// # don't show current page/post in list
			if($children->ID != get_the_ID()) {
				if(count($children) == 1) { 
					$content_sql .= "ID = '". $children->ID ."' AND "; 
				} else if(count($children) > 1) {
					foreach($children as $child) {
						// # don't show current page/post in list
						if($child->ID != get_the_ID()) {
							if ($child === end($children)) {
								$content_sql .= "ID = '". $child->ID ."' AND "; 
							} else {
								$content_sql .= "ID = '". $child->ID ."' OR "; 
							}
						}
					}
				}
			}
		}

		if($content == 'pages') { 

			$content_sql .= "post_type = 'page' AND post_status = 'publish'"; 

		} else if ($content == 'posts') { 

			$content_sql .= "post_type = 'post' AND post_status = 'publish'"; 

		} else { // # both

			$content_sql .= "post_type = 'page' AND post_status = 'publish' OR (post_type = 'post' AND post_status = 'publish')"; 
		}


		global $wpdb;

		$exclude = preg_replace('/[^0-9,]/', '', $exclude);
		
		$exclude_array = !empty($exclude) ? explode(',', $exclude) : array();

		// # add If Menu Plugin exclusions to exclude_array if If-Menu plugin is active.
    	if(is_plugin_active('if-menu/if-menu.php')) {

    		$allow = (!empty($_SESSION['internalIPs']) ? $_SESSION['internalIPs'] : array());

    		// # if IP is allowed, skip over the exclusion additions from If-Menu plugin (no need to exclude if allowed)
      		if(!in_array($_SERVER['REMOTE_ADDR'], $allow)) {
        		
        		$if_menu_rows = $wpdb->get_results("SELECT post_id FROM wp_postmeta WHERE meta_key = 'if_menu_enable' AND meta_value = 1");

				foreach($if_menu_rows as $ifm) {
        			array_push($exclude_array, $ifm->post_id);
    			}
      		}
		}

		if (!empty($exclude_array)) {
			foreach($exclude_array as $excluded) {
				$content_sql .= " AND ID != '". $excluded ."' "; 
			}
		}	

		$sql = "SELECT post_title, ID FROM ". $wpdb->posts ." WHERE ".$content_sql." ORDER BY post_modified DESC LIMIT ". $number;

		if (!$hideEmpty) {

			$output = '';

			$output .= '<div id="recent-updates-widget" class="widget'. ($class ? ' ' . $class : '').'">
							<div class="intern-padding">';

			if(!$hideTitle && $title) {
				// # $before_title = '<div class="intern-box box-title"><h3>';
				// # $after_title = '</h3></div>';
				$output .= $before_title.$title.$after_title;
            }

			$output .= '<ul>';

			$RecentPagePostUpdates = $wpdb->get_results($sql);

            $newWindow = ($newWindow) ? ' target="_blank" ' : '';

			// # added filter hook in case other plugins need to add exlusions (like roles)
			$updatedArray = apply_filters('recent-pagepost-extended-filter', $RecentPagePostUpdates, $updatedArray);
			if(is_array($updatedArray) && !empty($updatedArray)) {
				$RecentPagePostUpdates = $updatedArray;
			}

			foreach ($RecentPagePostUpdates as $recentUpdates) {

				if (!in_array($recentUpdates->ID, $exclude_array)) { 
					$url = get_permalink($recentUpdates->ID);
					$output .= '<li class="recent-item-'.$recentUpdates->ID.'"><a href="'.$url.'"'.$newWindow.'>'.$recentUpdates->post_title.'</a></li>'."\n";
				}
			}

			$output .= '</ul>
					</div>
				</div>';

			echo $output;
        }

		$wpdb->flush();
	}

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['class'] = strip_tags($new_instance['class']);
        $instance['content'] = strip_tags($new_instance['content']);
        $instance['number'] = (int)strip_tags($new_instance['number']);
        $instance['exclude'] = strip_tags($new_instance['exclude']);
        $instance['hideTitle'] = isset($new_instance['hideTitle']);
        $instance['hideEmpty'] = isset($new_instance['hideEmpty']);
        $instance['newWindow'] = isset($new_instance['newWindow']);
        $instance['filter'] = isset($new_instance['filter']);

        return $instance;
    }

	function form( $instance ) {

		$instance = wp_parse_args( (array) $instance, array(
            'title' => '',
            'content' => 'both',
			'class' => '',
    		'number' => 5,
    		'exclude' => ''
        ));

        $title = $instance['title'];

       	$content = attribute_escape($instance['content']);

		if ($content == 'pages') {
			$selectedpages = 'selected'; 
		} else if ($content == 'posts') {
			$selectedposts = 'selected'; 
		} else {
			$selectedboth = 'selected'; 
		}

        $class = attribute_escape($instance['class']);

		$number = ($instance['number'] != $number ? $instance['number'] : 5);

		$exclude = preg_replace('/[^0-9,]/', '', attribute_escape($instance['exclude']));


		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:'); ?> </label>
		<input type="text" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" id="<?php esc_attr( $this->get_field_id( 'title' ) ); ?>"/>
		</p>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'class' ) ); ?>"><?php _e( 'CSS Classes:'); ?> </label>
		<input type="text" name="<?php echo esc_attr( $this->get_field_name( 'class' ) ); ?>" value="<?php echo esc_attr( $instance['class'] ); ?>" class="widefat" id="<?php esc_attr( $this->get_field_id( 'class' ) ); ?>"/>
		</p>

		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'content' ) ); ?>"><?php _e( 'Content:'); ?> </label>
		<select name="<?php echo esc_attr( $this->get_field_name( 'content' ) ); ?>" class="widefat" id="<?php esc_attr( $this->get_field_id( 'content' ) ); ?>">
			<option value="pages"<?php echo $selectedpages; ?>>pages</option>
			<option value="posts"<?php echo $selectedposts; ?>>posts</option>
			<option value="both"<?php echo $selectedboth; ?>>both</option>
		</select>
		</p>

		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Limit # of pages:'); ?> </label>
		<input type="text" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" value="<?php echo esc_attr( $instance['number'] ); ?>" class="widefat" id="<?php esc_attr( $this->get_field_id( 'number' ) ); ?>"/><br />
		<small><?php _e('(at most 100)'); ?></small></p>
		</p>

		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'exclude' ) ); ?>"><?php _e( 'Exclude:'); ?> </label>
		<input type="text" name="<?php echo esc_attr( $this->get_field_name( 'exclude' ) ); ?>" value="<?php echo esc_attr( $instance['exclude'] ); ?>" class="widefat" id="<?php esc_attr( $this->get_field_id( 'exclude' ) ); ?>"/><br />
		<small><?php _e('page/post IDs, separated by commas.'); ?></small></p>

        <p>
            <input id="<?php echo $this->get_field_id('hideTitle'); ?>" name="<?php echo $this->get_field_name('hideTitle'); ?>" type="checkbox" <?php checked(isset($instance['hideTitle']) ? $instance['hideTitle'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('hideTitle'); ?>"><?php _e('Do not display the title', 'enhancedtext'); ?></label>
        </p>

        <p>
            <input id="<?php echo $this->get_field_id('hideEmpty'); ?>" name="<?php echo $this->get_field_name('hideEmpty'); ?>" type="checkbox" <?php checked(isset($instance['hideEmpty']) ? $instance['hideEmpty'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('hideEmpty'); ?>"><?php _e('Do not display empty widgets', 'enhancedtext'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo $this->get_field_id('newWindow'); ?>" name="<?php echo $this->get_field_name('newWindow'); ?>" <?php checked(isset($instance['newWindow']) ? $instance['newWindow'] : 0); ?> />
            <label for="<?php echo $this->get_field_id('newWindow'); ?>"><?php _e('Open the URLs in a new window', 'enhancedtext'); ?></label>
        </p>

        <p>
            <input id="<?php echo $this->get_field_id('filter'); ?>" name="<?php echo $this->get_field_name('filter'); ?>" type="checkbox" <?php checked(isset($instance['filter']) ? $instance['filter'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('filter'); ?>"><?php _e('Filter by parent  ID', 'enhancedtext'); ?></label>
        </p>

		<input type="hidden" id="submit" name="submit" value="1" />

		 <?php
	}

	function removeParentLinks($args='') {

		if(!empty($args)) {

			// # match args passed from above
			$args = wp_parse_args($args);

			$child_of = $args['child_of'];
			$depth = $args['depth'];
			$title_li = $args['title_li'];
			$echo = $args['echo'];
		
			$pages = wp_list_pages('child_of=' . $child_of .'&depth=' . $depth .'&title_li=' . $title_li .'&echo=' . $echo);
		} else {
			$pages = wp_list_pages('echo=0&amp;title_li=');
		}

		$pages = explode("</li>", $pages);
		$count = 0;

		foreach($pages as $page) {
			if(strstr($page,"<ul>")) {
				$page = explode('<ul>', $page);
				$page[0] = str_replace('</a>','',$page[0]);
				$page[0] = preg_replace('/\<a(.*)\>/','',$page[0]);
			
				if(count($page) == 3) {
					$page[1] = str_replace('</a>','',$page[1]);
					$page[1] = preg_replace('/\<a(.*)\>/','',$page[1]);                
				}
		
				$page = implode('<ul>', $page);
			}

			$pages[$count] = $page;
			$count++;
		}

		$pages = implode('</li>',$pages);
		return $pages;
	}

}


function init_recentUpdates() {
	//wp_register_sidebar_widget("Recent Updates", "widget_recentUpdates");
	register_widget('recentUpdates');
}

//add_action("plugins_loaded", "init_recentUpdates");
add_action( 'widgets_init', 'init_recentUpdates' );
?>