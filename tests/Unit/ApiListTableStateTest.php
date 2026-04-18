<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\ApiListTable;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the shared state helpers on ApiListTable.
 *
 * Covers the 2x2 stale/overdue matrix (including the combined state),
 * emoji / label output, sort ranking, and the row CSS class mapping.
 */
class ApiListTableStateTest extends TestCase {

	/**
	 * Data provider: all 5 input variants for derive_state().
	 *
	 * @return array<string, array{0: array<string, bool>, 1: string}>
	 */
	public static function state_matrix(): array {
		return [
			'neither flag'   => [
				[
					'stale'   => false,
					'overdue' => false,
				],
				ApiListTable::STATE_FRESH,
			],
			'stale only'     => [
				[
					'stale'   => true,
					'overdue' => false,
				],
				ApiListTable::STATE_STALE,
			],
			'overdue only'   => [
				[
					'stale'   => false,
					'overdue' => true,
				],
				ApiListTable::STATE_OVERDUE,
			],
			'stale + overdue' => [
				[
					'stale'   => true,
					'overdue' => true,
				],
				ApiListTable::STATE_STALE_OVERDUE,
			],
			'missing flags'  => [ [], ApiListTable::STATE_FRESH ],
		];
	}

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs(
			[
				'__'       => static fn( string $text ): string => $text,
				'esc_attr' => static fn( string $text ): string => $text,
				'esc_html' => static fn( string $text ): string => $text,
			],
		);
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Derive state covers every (stale, overdue) combination.
	 *
	 * @dataProvider state_matrix
	 *
	 * @param array<string, bool> $item     Row flags.
	 * @param string              $expected Expected state.
	 */
	public function test_derive_state_covers_full_matrix( array $item, string $expected ): void {
		$this->assertSame( $expected, ApiListTable::derive_state( $item ) );
	}

	/**
	 * Rank must order fresh < stale < overdue < both so descending sort
	 * surfaces the worst rows first.
	 *
	 * @return void
	 */
	public function test_state_rank_orders_by_severity(): void {
		$fresh    = ApiListTable::state_rank( ApiListTable::STATE_FRESH );
		$stale    = ApiListTable::state_rank( ApiListTable::STATE_STALE );
		$overdue  = ApiListTable::state_rank( ApiListTable::STATE_OVERDUE );
		$combined = ApiListTable::state_rank( ApiListTable::STATE_STALE_OVERDUE );

		$this->assertLessThan( $stale, $fresh );
		$this->assertLessThan( $overdue, $stale );
		$this->assertLessThan( $combined, $overdue );
	}

	/**
	 * Unknown state should rank below all real states so callers notice.
	 *
	 * @return void
	 */
	public function test_state_rank_unknown_state_ranks_last(): void {
		$this->assertGreaterThan(
			ApiListTable::state_rank( ApiListTable::STATE_STALE_OVERDUE ),
			ApiListTable::state_rank( 'bogus' ),
		);
	}

	/**
	 * Each real state must map to a non-empty emoji; unknown states map
	 * to the empty string so no placeholder glyph leaks into the UI.
	 *
	 * @return void
	 */
	public function test_state_emoji_covers_all_states(): void {
		$this->assertNotSame( '', ApiListTable::state_emoji( ApiListTable::STATE_FRESH ) );
		$this->assertNotSame( '', ApiListTable::state_emoji( ApiListTable::STATE_STALE ) );
		$this->assertNotSame( '', ApiListTable::state_emoji( ApiListTable::STATE_OVERDUE ) );
		$this->assertNotSame( '', ApiListTable::state_emoji( ApiListTable::STATE_STALE_OVERDUE ) );
		$this->assertSame( '', ApiListTable::state_emoji( 'bogus' ) );
	}

	/**
	 * The combined state must render two distinct emojis so readers
	 * can see both issues at a glance.
	 *
	 * @return void
	 */
	public function test_combined_state_emoji_contains_both(): void {
		$combined = ApiListTable::state_emoji( ApiListTable::STATE_STALE_OVERDUE );

		$this->assertStringContainsString( ApiListTable::state_emoji( ApiListTable::STATE_STALE ), $combined );
		$this->assertStringContainsString( ApiListTable::state_emoji( ApiListTable::STATE_OVERDUE ), $combined );
	}

	/**
	 * Every real state must have a human-readable label.
	 *
	 * @return void
	 */
	public function test_state_label_covers_all_states(): void {
		$this->assertNotSame( '', ApiListTable::state_label( ApiListTable::STATE_FRESH ) );
		$this->assertNotSame( '', ApiListTable::state_label( ApiListTable::STATE_STALE ) );
		$this->assertNotSame( '', ApiListTable::state_label( ApiListTable::STATE_OVERDUE ) );
		$this->assertNotSame( '', ApiListTable::state_label( ApiListTable::STATE_STALE_OVERDUE ) );
		$this->assertSame( '', ApiListTable::state_label( 'bogus' ) );
	}

	/**
	 * Badge HTML wraps the emoji with the state-scoped CSS class and tooltip.
	 *
	 * @return void
	 */
	public function test_state_badge_html_wraps_emoji_with_state_class(): void {
		$html = ApiListTable::state_badge_html( ApiListTable::STATE_STALE );

		$this->assertStringContainsString( 'class="smd-state smd-state-stale"', $html );
		$this->assertStringContainsString( ApiListTable::state_emoji( ApiListTable::STATE_STALE ), $html );
		$this->assertStringContainsString( 'title=', $html );
		$this->assertStringContainsString( 'aria-label=', $html );
	}

	/**
	 * Unknown state must produce no markup at all so callers don't
	 * render an empty tooltip span.
	 *
	 * @return void
	 */
	public function test_state_badge_html_returns_empty_for_unknown_state(): void {
		$this->assertSame( '', ApiListTable::state_badge_html( 'bogus' ) );
	}

	/**
	 * Row class: stale gets its own class; overdue and the combined
	 * state share the more severe (red) class; fresh has no class.
	 *
	 * @return void
	 */
	public function test_state_row_class_mapping(): void {
		$this->assertSame( '', ApiListTable::state_row_class( ApiListTable::STATE_FRESH ) );
		$this->assertSame( 'smd-stale', ApiListTable::state_row_class( ApiListTable::STATE_STALE ) );
		$this->assertSame( 'smd-overdue', ApiListTable::state_row_class( ApiListTable::STATE_OVERDUE ) );
		$this->assertSame( 'smd-overdue', ApiListTable::state_row_class( ApiListTable::STATE_STALE_OVERDUE ) );
		$this->assertSame( '', ApiListTable::state_row_class( 'bogus' ) );
	}
}
