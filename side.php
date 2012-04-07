<?php

define( 'ADMIN_BOX_PER_PAGE', 5 );

abstract class P2P_Side {

	public $query_vars;

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	function get_base_qv() {
		return $this->query_vars;
	}
}


class P2P_Side_Post extends P2P_Side {

	public $post_type = array();

	function __construct( $query_vars ) {
		parent::__construct( $query_vars );

		$this->post_type = $this->query_vars['post_type'];
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

	function abstract_query( $query ) {
		return (object) array(
			'items' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	private static $admin_box_qv = array(
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'post_status' => 'any',
	);

	function get_connections_qv() {
		return array_merge( self::$admin_box_qv, array(
			'nopaging' => true
		) );
	}

	function get_connectable_qv( $qv ) {
		return array_merge( $this->get_base_qv(), self::$admin_box_qv, $this->translate_qv( $qv ) );
	}

	function translate_qv( $qv ) {
		$map = array(
			'exclude' => 'post__not_in',
			'search' => 's',
			'page' => 'paged'
		);

		foreach ( $map as $old => $new )
			if ( isset( $qv["p2p:$old"] ) )
				$qv[$new] = _p2p_pluck( $qv, "p2p:$old" );

		return $qv;
	}

	/**
	 * @param mixed A post type, a post id, a post object, an array of post ids or of objects.
	 */
	function item_recognize( $arg ) {
		if ( is_array( $arg ) ) {
			$arg = reset( $arg );
		}

		if ( is_object( $arg ) ) {
			$post_type = $arg->post_type;
		} elseif ( $post_id = (int) $arg ) {
			$post = get_post( $post_id );
			if ( !$post )
				return false;
			$post_type = $post->post_type;
		} else {
			$post_type = $arg;
		}

		if ( !post_type_exists( $post_type ) )
			return false;

		return in_array( $post_type, $this->post_type );
	}

	protected function get_ptype() {
		return get_post_type_object( $this->post_type[0] );
	}

	function item_exists( $item_id ) {
		return (bool) get_post( $item_id );
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

	function abstract_query( $query ) {
		return (object) array(
			'items' => $query->get_results(),
			'current_page' => isset( $query->query_vars['p2p:page'] ) ? $query->query_vars['p2p:page'] : 1,
			'total_pages' => ceil( $query->get_total() / ADMIN_BOX_PER_PAGE )
		);
	}

	function get_connections_qv() {
		return array();
	}

	function get_connectable_qv( $qv ) {
		return array_merge( $this->get_base_qv(), $this->translate_qv( $qv ) );
	}

	function translate_qv( $qv ) {
		if ( isset( $qv['p2p:exclude'] ) )
			$qv['exclude'] = _p2p_pluck( $qv, 'p2p:exclude' );

		if ( isset( $qv['p2p:search'] ) && $qv['p2p:search'] )
			$qv['search'] = '*' . _p2p_pluck( $qv, 'p2p:search' ) . '*';

		if ( isset( $qv['p2p:page'] ) ) {
			$qv['number'] = ADMIN_BOX_PER_PAGE;
			$qv['offset'] = ADMIN_BOX_PER_PAGE * ( $qv['p2p:page'] - 1 );
		}

		return $qv;
	}

	function item_recognize( $arg ) {
		return false;
	}

	function item_exists( $item_id ) {
		return (bool) get_user_by( 'id', $item_id );
	}

	function item_title( $item ) {
		return $item->display_name;
	}
}

