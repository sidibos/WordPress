<?php
/**
 * Extending the Nav_Menu walker to override some class names and add some customizations.
 *
 * @see Walker class (classes.php)
 * @see Walker_Nav_Menu class (nav-menu-template)
 */
class Assanka_Walker_Nav_Menu extends Walker_Nav_Menu {
	// Rewriting the sub menu CSS class.
	function start_lvl(&$output, $depth) {
		$indent = str_repeat("\t", $depth);
		$output .= "\n$indent<ul class=\"subnav\">\n"; // Was: sub-menu
		
		// If this is the active submenu, insert the rss-feed <li>. 
		global $rss_inserted;
		if($rss_inserted == false && strstr($output,'on active')){
			$url_slug = assanka_get_url_slug();
			$href = '/' . $url_slug . '/feed/';
			$output .= '<li class="nosub rss-feed">';
			$output .= '<a title="Syndicate this site using RSS" href="' . $href . '">';
			$output .= '<span><img alt="RSS" src="http://im.media.ft.com/m/img/nav/rss_link_nav.gif"> RSS</span>';
			$output .= '</a>';
			$output .= '</li>';
			$rss_inserted = true;
						
			// Insert the tools menu while we are here.
			$output .= '<li class="tools"><a href="http://www.ft.com/servicestools" style="visibility: visible;">Tools</a> <ul class="subnav">
			<li class="dummy-child"><a href="http://www.ft.com/servicestools" style="visibility: visible;">Tools</a><div class="tl"></div><div class="tr"></div></li> <li class="first-child ">
			<a href="http://markets.ft.com/portfolio/all.asp" style="visibility: visible;">Portfolio</a> </li>
			<li class=""><a href="http://clippings.ft.com/" style="visibility: visible;">FT clippings</a> </li>
			<li class=""><a href="http://markets.ft.com/alerts/keyword.asp" style="visibility: visible;">Alerts hub</a> </li>
			<li class=""><a href="http://nbe.ft.com/nbe/profile.cfm" style="visibility: visible;">Email briefings</a> </li>
			<li class=""><a href="http://rankings.ft.com/businessschoolrankings/global-mba-rankings" style="visibility: visible;">MBA rankings</a> </li>
			<li class=""><a href="http://www.ft.com/multimedia" style="visibility: visible;">Interactive</a> </li>
			<li class=""><a href="http://markets.ft.com/ft/markets/currencies.asp" style="visibility: visible;">Currency converter</a> </li>
			<li class=""><a href="http://www.ft.com/FTePaper" style="visibility: visible;">ePaper</a> </li>
			<li class=""><a href="http://presscuttings.ft.com/presscuttings/search.htm" style="visibility: visible;">FT press cuttings</a> </li>
			<li class="last-child "><a href="http://privilege.ft.com/" style="visibility: visible;">Privilege Club</a> <div class="bl"></div><div class="br"></div></li></ul>
			<div class="bl"></div><div class="br"></div></li>';
		}
	}
	
	// Fixing the "has_children" functionality (doesn't seem to be working properly in WP 3.0.1. So we're overwriting it here. )
	function display_element( $element, &$children_elements, $max_depth, $depth=0, $args, &$output ) {
		if ( !$element )
			return;

		$id_field = $this->db_fields['id'];
		$id = $element->$id_field;

		$element->has_children = false;
		if ( isset( $args[0] ) && ($max_depth == 0 || $max_depth > $depth+1 ) && isset( $children_elements[$id]) ) {
			$element->has_children = true;
		}

		$cb_args = array_merge( array(&$output, $element, $depth), $args);
		call_user_func_array(array(&$this, 'start_el'), $cb_args);
		
		// descend only when the depth is right and there are children for this element
		if ( ($max_depth == 0 || $max_depth > $depth+1 ) && isset( $children_elements[$id]) ) {
			$i = 1;			
			foreach( $children_elements[ $id ] as $child ){
				if ( !isset($newlevel) ) {
					$newlevel = true;
					//start the child delimiter
					$cb_args = array_merge( array(&$output, $depth), $args);
					call_user_func_array(array(&$this, 'start_lvl'), $cb_args);
				}
				$child->is_last_child = false;
				if ($i++ == count($children_elements[ $id ])){
					$child->is_last_child = true;
				}
				$this->display_element( $child, $children_elements, $max_depth, $depth + 1, $args, $output );
			}
			unset( $children_elements[ $id ] );
		}

		if ( isset($newlevel) && $newlevel ){
			//end the child delimiter
			$cb_args = array_merge( array(&$output, $depth), $args);
			call_user_func_array(array(&$this, 'end_lvl'), $cb_args);
		}

		//end this element
		$cb_args = array_merge( array(&$output, $element, $depth), $args);
		call_user_func_array(array(&$this, 'end_el'), $cb_args);
	}
	
	
	function start_el(&$output, $item, $depth, $args) {
		global $wp_query;
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$class_names = $value = '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		// Activate any applicable items (apply the "on" class.)
		if(!empty($args->active_nav_menu_items) ) {
			if( preg_grep( "/".urlencode($item->post_title)."/i" , $args->active_nav_menu_items ) ) {
				$classes[] = 'nosub on active';
			}		
		} elseif($item->menu_order === 1){
			// If no items are manually set, automatically activate the very first menu item.
			$classes[] = 'nosub on active';
		}

		// Add the "nosub" class to any element with no children.
		if($item->has_children != true){
			$classes[] = 'nosub';
		}

		if($item->is_last_child){
			$classes[] = 'last-child';
		}
		
		// Was: $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item ) );
		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $depth, $args ) );
		$class_names = ' class="' . esc_attr( $class_names ) . '"';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
		$id = strlen( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';

		$output .= $indent . '<li' . $id . $value . $class_names .'>';

		$attributes  = ! empty( $item->attr_title ) ? ' title="'  . esc_attr( $item->attr_title ) .'"' : '';
		$attributes .= ! empty( $item->target )     ? ' target="' . esc_attr( $item->target     ) .'"' : '';
		$attributes .= ! empty( $item->xfn )        ? ' rel="'    . esc_attr( $item->xfn        ) .'"' : '';
		$attributes .= ! empty( $item->url )        ? ' href="'   . esc_attr( $item->url        ) .'"' : '';

		$item_output = $args->before;
		$item_output .= '<a'. $attributes .'>';
		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
					
		// Add "<em>&nbsp;</em>" to all top-level elements.
		if($depth === 0){
			$item_output .= '<em>&nbsp;</em>';
		}	
		$item_output .= '</a>';
		$item_output .= $args->after;

		// Change the classes of the top-level <a> items
		if($depth === 0){
			// Activate any applicable items (apply the "nosub on" class.)
			if(!empty($args->active_nav_menu_items) ) {
				if( preg_grep( "/".urlencode($item->post_title)."/i" , $args->active_nav_menu_items ) ) {
					$item_output = str_replace('<a','<a class="nosub on" ',$item_output);
				}	
			} elseif($item->menu_order === 1) {
				// If no items are manually set, automatically activate the first menu item (apply the "nosub on" class.)
				$item_output = str_replace('<a','<a class="nosub on" ',$item_output);	
			}
		}
		
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}
