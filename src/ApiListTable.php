<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

use WP_List_Table;

/**
 * Abstract base for list tables backed by the hub API.
 *
 * Handles shared patterns: data fetching, sorting, pagination,
 * row classes, error display, and the "Last checked" line.
 *
 * Subclasses implement fetch_data() and define columns/renderers.
 */
abstract class ApiListTable extends WP_List_Table {

	/**
	 * API error response, if any.
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $api_error = null;

	/**
	 * Items per page.
	 *
	 * @var int
	 */
	protected int $per_page = 20;

	/**
	 * Check if any string value in an item contains the search needle.
	 *
	 * @param array<string, mixed> $item   Row data.
	 * @param string               $needle Lowercase search term.
	 *
	 * @return bool
	 */
	private static function item_matches_search( array $item, string $needle ): bool {
		foreach ( $item as $value ) {
			if ( \is_string( $value ) && \str_contains( \mb_strtolower( $value ), $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetch data from the hub API.
	 *
	 * @return array<int, array<string, mixed>> Items array or error data.
	 */
	abstract protected function fetch_data(): array;

	/**
	 * Return a CSS class for a table row.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string CSS class or empty string.
	 */
	protected function get_row_class( array $item ): string {
		return '';
	}

	/**
	 * Whether the current user can view this table.
	 *
	 * @return bool
	 */
	public function ajax_user_can(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Fetch, sort, and paginate items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];

		$items = $this->fetch_data();

		if ( $items === [] && $this->api_error !== null ) {
			$this->items = [];
			return;
		}

		$items = $this->search_items( $items );
		$items = $this->sort_fetched_items( $items );
		$total = \count( $items );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only pagination param.
		$paged = isset( $_GET['paged'] ) ? \max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable

		$this->items = \array_slice( $items, ( $paged - 1 ) * $this->per_page, $this->per_page );

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $this->per_page,
				'total_pages' => (int) \ceil( $total / $this->per_page ),
			],
		);
	}

	/**
	 * Default column renderer — escapes and returns the value.
	 *
	 * @param mixed  $item        Row data.
	 * @param string $column_name Column key.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- WP_List_Table override.
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Override single_row to support custom row classes.
	 *
	 * @param mixed $item Row data.
	 *
	 * @return void
	 */
	public function single_row( $item ): void { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- WP_List_Table override.
		$class = $this->get_row_class( $item );
		echo '<tr class="' . esc_attr( $class ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Display the table with error handling and last-checked line.
	 *
	 * @return void
	 */
	public function display(): void {
		if ( $this->api_error !== null ) {
			\printf(
				'<div class="notice notice-error"><p>%s: %s</p></div>',
				esc_html( (string) ( $this->api_error['error'] ?? 'error' ) ),
				esc_html( (string) ( $this->api_error['message'] ?? 'Unknown error.' ) ),
			);
			return;
		}

		$this->render_last_checked();

		$form_page = $this->get_form_page();
		if ( $form_page !== '' ) {
			echo '<form method="get">';
			\printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $form_page ) );
			$this->render_hidden_filters();
			$this->search_box( __( 'Search', 'site-bookkeeper-dashboard' ), 'sbd_search' );
		}

		parent::display();

		if ( $form_page !== '' ) {
			echo '</form>';
		}
	}

	/**
	 * Return the admin page slug to wrap the table in a filter form.
	 *
	 * Override in subclasses that need filter forms. Return empty
	 * string to skip the form wrapper.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return '';
	}

	/**
	 * Render hidden form fields to preserve filter state.
	 *
	 * Override in subclasses that need to preserve extra params.
	 *
	 * @return void
	 */
	protected function render_hidden_filters(): void {
	}

	/**
	 * Format a site label as "Name (domain)" for display in dropdowns.
	 *
	 * @param string $label    Site label or name.
	 * @param string $site_url Full site URL.
	 *
	 * @return string Formatted label.
	 */
	protected function format_site_label( string $label, string $site_url ): string {
		$domain = (string) \preg_replace( '#^https?://#', '', $site_url );
		$name = $label !== '' ? $label : $domain;

		return $name . ' (' . $domain . ')';
	}

	/**
	 * Render the "Last checked / Check again" line.
	 *
	 * @return void
	 */
	protected function render_last_checked(): void {
		$flush_url = wp_nonce_url(
			add_query_arg( 'sbd_flush', '1' ),
			'sbd_flush_cache',
		);

		$last_checked = ApiClient::get_last_checked();
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		if ( $last_checked !== null ) {
			$date_string = wp_date( $format, $last_checked );
			\printf(
				'<p class="smd-last-checked">%s <a href="%s">%s</a></p>',
				esc_html(
					\sprintf(
						/* translators: %s: formatted date/time */
						__( 'Last checked on %s.', 'site-bookkeeper-dashboard' ),
						$date_string,
					),
				),
				esc_url( $flush_url ),
				esc_html__( 'Check again.', 'site-bookkeeper-dashboard' ),
			);
		} else {
			\printf(
				'<p class="smd-last-checked"><a href="%s">%s</a></p>',
				esc_url( $flush_url ),
				esc_html__( 'Check now.', 'site-bookkeeper-dashboard' ),
			);
		}
	}

	/**
	 * Sort items by the requested column.
	 *
	 * @param array<int, array<string, mixed>> $items Items to sort.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Filter items by the search term from the search box.
	 *
	 * Matches any string value in the item against the search term.
	 *
	 * @param array<int, array<string, mixed>> $items Items to filter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function search_items( array $items ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search param.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( $search === '' ) {
			return $items;
		}

		$needle = \mb_strtolower( $search );

		return \array_values(
			\array_filter(
				$items,
				static fn( array $item ): bool => self::item_matches_search( $item, $needle ),
			),
		);
	}

	/**
	 * Sort items by the requested column.
	 *
	 * @param array<int, array<string, mixed>> $items Items to sort.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_fetched_items( array $items ): array {
		$sortable = $this->get_sortable_columns();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sort params.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
		// phpcs:enable

		if ( $orderby === '' || ! isset( $sortable[ $orderby ] ) ) {
			$first = \array_key_first( $sortable );
			$orderby = $first ?? '';
		}

		if ( $orderby === '' ) {
			return $items;
		}

		\usort(
			$items,
			static function ( array $left, array $right ) use ( $orderby, $order ): int {
				$val_a = (string) ( $left[ $orderby ] ?? '' );
				$val_b = (string) ( $right[ $orderby ] ?? '' );
				$result = \strnatcasecmp( $val_a, $val_b );

				return $order === 'desc' ? -$result : $result;
			},
		);

		return $items;
	}
}
