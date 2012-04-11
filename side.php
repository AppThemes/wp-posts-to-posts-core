<?php

abstract class P2P_Side {

	public $query_vars;

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	function get_base_qv() {
		return $this->query_vars;
	}

	function abstract_query( $query ) {
		$class = str_replace( 'P2P_Side_', 'P2P_List_', get_class( $this ) );
		return new $class( $query );
	}
}


class P2P_Side_Post extends P2P_Side {

	public $post_type = array();

	function __construct( $query_vars ) {
		parent::__construct( $query_vars );

		$this->post_type = $this->query_vars['post_type'];
	}

	private function get_ptype() {
		return get_post_type_object( $this->post_type[0] );
	}

	function get_base_qv() {
		return array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	function get_desc() {
		return implode( ', ', array_map( array( $this, 'post_type_label' ), $this->post_type ) );
	}

	private function post_type_label( $post_type ) {
		$cpt = get_post_type_object( $post_type );
		return $cpt ? $cpt->label : $post_type;
	}

	function get_title() {
		return $this->get_ptype()->labels->name;
	}

	function get_labels() {
		return $this->get_ptype()->labels;
	}

	function check_capability() {
		return current_user_can( $this->get_ptype()->cap->edit_posts );
	}

	function do_query( $args ) {
		return new WP_Query( $args );
	}

	function translate_qv( $qv ) {
		$map = array(
			'exclude' => 'post__not_in',
			'search' => 's',
			'page' => 'paged',
			'per_page' => 'posts_per_page'
		);

		foreach ( $map as $old => $new )
			if ( isset( $qv["p2p:$old"] ) )
				$qv[$new] = _p2p_pluck( $qv, "p2p:$old" );

		return $qv;
	}

	function item_recognize( $arg ) {
		if ( is_object( $arg ) ) {
			if ( !isset( $arg->post_type ) )
				return false;
			$post_type = $arg->post_type;
		} elseif ( $post_id = (int) $arg ) {
			$post_type = get_post_type( $post_id );
		} else {
			$post_type = $arg;
		}

		if ( !post_type_exists( $post_type ) )
			return false;

		return in_array( $post_type, $this->post_type );
	}

	function item_id( $arg ) {
		$post = get_post( $arg );
		if ( $post )
			return $post->ID;

		return false;
	}

	function item_title( $item ) {
		return $item->post_title;
	}
}


class P2P_Side_Attachment extends P2P_Side_Post {

	function __construct( $query_vars ) {
		P2P_Side::__construct( $query_vars );

		$this->post_type = array( 'attachment' );
	}

	function get_base_qv() {
		return array_merge( parent::get_base_qv(), array(
			'post_status' => 'inherit'
		) );
	}
}


class P2P_Side_User extends P2P_Side {

	function get_desc() {
		return __( 'Users', P2P_TEXTDOMAIN );
	}

	function get_title() {
		return $this->get_desc();
	}

	function get_labels() {
		return (object) array(
			'singular_name' => __( 'User', P2P_TEXTDOMAIN ),
			'search_items' => __( 'Search Users', P2P_TEXTDOMAIN ),
			'not_found' => __( 'No users found.', P2P_TEXTDOMAIN ),
		);
	}

	function check_capability() {
		return current_user_can( 'list_users' );
	}

	function do_query( $args ) {
		return new WP_User_Query( $args );
	}

	function translate_qv( $qv ) {
		if ( isset( $qv['p2p:exclude'] ) )
			$qv['exclude'] = _p2p_pluck( $qv, 'p2p:exclude' );

		if ( isset( $qv['p2p:search'] ) && $qv['p2p:search'] )
			$qv['search'] = '*' . _p2p_pluck( $qv, 'p2p:search' ) . '*';

		if ( isset( $qv['p2p:page'] ) && $qv['p2p:page'] > 0 ) {
			$qv['number'] = $qv['p2p:per_page'];
			$qv['offset'] = $qv['p2p:per_page'] * ( $qv['p2p:page'] - 1 );
		}

		return $qv;
	}

	function item_recognize( $arg ) {
		return is_a( $arg, 'WP_User' );
	}

	function item_id( $arg ) {
		if ( $this->item_recognize( $arg ) )
			return $arg->ID;

		$user = get_user_by( 'id', $arg );
		if ( $user )
			return $user->ID;

		return false;
	}

	function item_title( $item ) {
		return $item->display_name;
	}
}

