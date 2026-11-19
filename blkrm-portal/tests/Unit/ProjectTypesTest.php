<?php
/**
 * Project type multi-select.
 *
 * @package BLKRM\Portal
 */

use BLKRM\Portal\Data\Projects;
use PHPUnit\Framework\TestCase;

final class ProjectTypesTest extends TestCase {

	public function test_parse_multiple_and_ignore_unknown() {
		$this->assertSame( array( 'print', 'illustration' ), Projects::parse_types( 'print, illustration,bogus' ) );
		$this->assertSame( array( 'branding', 'digital' ), Projects::parse_types( array( 'branding', 'digital', 'digital' ) ) );
	}

	public function test_not_sure_only_alone() {
		$this->assertSame( array( 'print' ), Projects::parse_types( array( 'unsure', 'print' ) ) );
		$this->assertSame( array( 'unsure' ), Projects::parse_types( '' ) );
	}

	public function test_labels_and_legacy_single_value() {
		$this->assertSame( 'Strategic Design · Digital Illustration + Art', Projects::type_labels( 'print,illustration' ) );
		$this->assertSame( 'Brand + Strategy', Projects::type_labels( 'branding' ) );
		$this->assertArrayHasKey( 'illustration', Projects::type_descriptions() );
	}
}
