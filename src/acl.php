<?php

/**
 * @deprecated 1.6.0
 */
function memberful_wp_current_user_products() {
	return memberful_wp_current_user_downloads();
}

/**
 * @deprecated 1.6.0
 */
function has_memberful_subscription( $slug, $user_id = NULL ) {
	return is_subscribed_to_memberful_plan( $slug, $user_id );
}

/**
 * @deprecated 1.6.0
 */
function has_memberful_product( $slug, $user_id = NULL ) {
	return has_memberful_download( $slug, $user_id );
}

/**
 * @deprecated 1.6.0
 */
function memberful_wp_user_products( $user_id ) {
	return memberful_wp_user_downloads( $user_id );
}

/**
 * @deprecated 1.6.0
 */
function memberful_wp_user_subscriptions( $user_id ) {
	return memberful_wp_user_plans_subscribed_to( $user_id );
}

/**
 * @deprecated 1.6.0
 */
function memberful_wp_user_has_products( $user_id, array $products ) {
	return memberful_wp_user_has_downloads( $user_id, $products );
}

/**
 * @deprecated 1.6.0
 */
function memberful_wp_user_has_subscriptions( $user_id, array $subscriptions ) {
	return memberful_wp_user_has_subscription_to_plans( $user_id, $subscriptions );
}

/**
 * Check that the current member has a subscription to at least least one of the required plans
 *
 * @param string|array $slug    Slug of the plan the user should have. Can pass an array of slugs
 * @param int          $user_id ID of the user who should have the subscription, defaults to current user
 * @return bool
 */
function is_subscribed_to_memberful_plan( $slug, $user_id = NULL ) {
	list( $required_plans , $user_id ) = memberful_wp_extract_slug_ids_and_user( func_get_args() );

	return memberful_wp_user_has_subscription_to_plans( $user_id, $required_plans );
}

/**
 * Check that the current member has at least one of the specified products
 *
 * @param string|array $slug    Slug of the product the user should have. Can pass an array of slugs
 * @param int          $user_id ID of the user who should have the product, defaults to current user
 * @return bool
 */
function has_memberful_download( $slug, $user_id = NULL ) {
	list( $required_downloads, $user_id ) = memberful_wp_extract_slug_ids_and_user( func_get_args() );

	return memberful_wp_user_has_downloads( $user_id, $required_downloads );
}

/**
 * Determines the set of post IDs that the current user cannot access
 *
 * If a page/post requires products a,b then the user will be granted access
 * to the content if they have bought either product a or b
 *
 * TODO: This is calculated on every page load, maybe use a cache?
 *
 * @return array Map of post ID => post ID
 */
function memberful_wp_user_disallowed_post_ids( $user_id ) {
	static $ids = array();

	if ( isset( $ids[$user_id] ) )
		return $ids[$user_id];

	$acl                     = get_option( 'memberful_acl', array() );
	$global_product_acl      = isset( $acl['product'] ) ? $acl['product'] : array();
	$global_subscription_acl = isset( $acl['subscription'] ) ? $acl['subscription'] : array();

	// Items the user has access to
	$user_products = memberful_wp_user_downloads( $user_id );
	$user_subs     = memberful_wp_user_plans_subscribed_to( $user_id );

	if ( ! empty( $user_subs ) )
		$user_subs     = array_filter( $user_subs, 'memberful_wp_filter_active_subscriptions' );

	// Work out the set of posts the user is and isn't allowed to access
	$user_product_acl      = memberful_wp_generate_user_specific_acl_from_global_acl( $user_products, $global_product_acl );
	$user_subscription_acl = memberful_wp_generate_user_specific_acl_from_global_acl( $user_subs, $global_subscription_acl );

	$user_allowed_posts    = array_merge( $user_product_acl['allowed'],    $user_subscription_acl['allowed'] );
	$user_restricted_posts = array_merge( $user_product_acl['restricted'], $user_subscription_acl['restricted'] );

	// Remove from the set of restricted posts the posts that the user is
	// definitely allowed to access
	$union = array_diff( $user_restricted_posts, $user_allowed_posts );

	return $ids[$user_id] = ( empty( $union ) ) ? array() : array_combine( $union, $union );
}

function memberful_wp_filter_active_subscriptions($subscription) {
	return empty($subscription['expires_at']) || $subscription['expires_at'] > time();
}

/**
 * Given a set of products/subscriptions that the member has, and the corresponding
 * product/subscription acl for the site, work out what posts they can view.
 *
 * @param  array $users_entities An array of ids (either product ids or subscription ids) in form id => id.
 * @param  array $acl            Global acl for the entity type.
 * @return
 */
function memberful_wp_generate_user_specific_acl_from_global_acl( $users_entities, $acl ) {
	if ( empty( $users_entities ) )
		$users_entities = array();

	$allowed_entities    = array_intersect_key( $acl, $users_entities );
	$restricted_entities = array_diff_key( $acl, $users_entities );

	$allowed_ids    = array();
	$restricted_ids = array();

	foreach ( $allowed_entities as $posts ) {
		$allowed_ids = array_merge( $allowed_ids, $posts );
	}

	foreach ( $restricted_entities as $posts ) {
		$restricted_ids = array_merge( $restricted_ids, $posts );
	}

	// array_merge doesn't preserve keys
	$allowed    = array_unique( $allowed_ids );
	$restricted = array_unique( $restricted_ids );

	return array( 'allowed' => $allowed, 'restricted' => $restricted );
}

/**
 * Gets the array of products the member with $member_id owns
 *
 * @return array member's products
 */
function memberful_wp_user_downloads( $user_id ) {
	return get_user_meta( $user_id, 'memberful_product', TRUE );
}

/**
 * Gets the plans that the member with $member_id is currently subscribed to
 * If the member had a subscription to a plan, but it has expired then it
 * is not included in this list.
 *
 * @return array member's subscriptions
 */
function memberful_wp_user_plans_subscribed_to( $user_id ) {
	return get_user_meta( $user_id, 'memberful_subscription', TRUE );
}

/**
 * Gets the download the current member has
 *
 * @return array current member's downloads
 */
function memberful_wp_current_user_downloads() {
	$current_user = wp_get_current_user();
	return memberful_wp_user_downloads( $current_user->ID );
}

/**
 * Check that the specified user is subscribed to at least one of the specified plans
 *
 * @param int   $user_id The id of the wordpress user
 * @param array $subscriptions Ids of the subscriptions to restrict access to
 * @return boolean
 */
function memberful_wp_user_has_subscription_to_plans( $user_id, array $required_plans ) {
	$plans_user_is_subscribed_to = memberful_wp_user_plans_subscribed_to( $user_id );

	foreach ( $required_plans as $plan ) {
		if ( isset( $plans_user_is_subscribed_to[ $plan ] ) ) {
			$subscription = $plans_user_is_subscribed_to[ $plan ];

			if ( empty( $subscription['expires_at'] ) || $subscription['expires_at'] > time() )
				return TRUE;
		}
	}

	return FALSE;
}

/**
 * Check that the specified user has at least one of a set of products
 *
 * @param int   $user_id   The id of the wordpress user
 * @param array $downloads Ids of the downloads to check the user has
 * @return boolean
 */
function memberful_wp_user_has_downloads( $user_id, $required_downloads ) {
	$downloads_user_has = memberful_wp_user_downloads( $user_id );

	foreach ( $required_downloads as $download ) {
		if ( isset( $downloads_user_has[ $download ] ) )
			return TRUE;
	}

	return FALSE;
}

/**
 * Extracts ids, and a user ID from the arguments passed to one of the
 * has_memberful_* helpers.
 *
 * @param array $args ALL arguments passed to the original helper
 * @return array      Array of IDs extract from the slugs as first element, user id as second
 */
function memberful_wp_extract_slug_ids_and_user($args) {
	$slugs = $args[0];
	$user  = empty($args[1]) ? NULL : $args[1];

	if ( $user === NULL )
		$user = wp_get_current_user()->ID;

	return array( memberful_wp_slugs_to_ids( $slugs ), $user );
}

/**
 * Checks that the user has permission to access the specified post
 *
 * @param integer $user_id ID of the user
 * @param integer $post_id ID of the post that should have access checked
 */
function memberful_can_user_access_post( $user, $post ) {
	$restricted_posts = memberful_wp_user_disallowed_post_ids( $user );

	return ! isset( $restricted_posts[$post] );
}
