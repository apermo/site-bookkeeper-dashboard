<?php
/**
 * Minimal WP_List_Table stub for unit tests.
 *
 * Provides just enough surface to let ApiListTable and its subclasses
 * be instantiated and tested without loading WordPress core.
 *
 * phpcs:ignoreFile -- Stub mirrors WP core signatures; no doc/naming rules apply.
 */

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- WP core stub.
class WP_List_Table {

	public array $items = [];

	public array $_column_headers = [];

	public function __construct( $args = [] ) {
	}

	public function set_pagination_args( $args ): void {
	}

	public function display(): void {
	}

	public function single_row_columns( $item ): void {
	}

	public function get_columns(): array {
		return [];
	}

	public function get_sortable_columns(): array {
		return [];
	}

	public function search_box( $text, $input_id ): void {
	}
}
