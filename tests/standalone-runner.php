<?php
/**
 * Minimal PHPUnit-compatible runner for environments without Composer.
 *
 * @package SMS_Aero_Elementor
 */

namespace PHPUnit\Framework {
	class AssertionFailedError extends \Exception {}

	class TestCase {
		private $expected_exception;

		protected function setUp(): void {}
		protected function tearDown(): void {}

		public function expectException( $class ) {
			$this->expected_exception = $class;
		}

		public function assertContains( $needle, $haystack, $message = '' ) {
			if ( false === strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: "String does not contain expected value {$needle}." );
			}
		}

		public function assertNotSame( $expected, $actual, $message = '' ) {
			if ( $expected === $actual ) {
				$this->fail( $message ?: 'Values are unexpectedly identical.' );
			}
		}

		public function assertSame( $expected, $actual, $message = '' ) {
			if ( $expected !== $actual ) {
				$this->fail( $message ?: 'Values are not identical.' . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) );
			}
		}

		public function assertTrue( $value, $message = '' ) {
			if ( true !== $value ) {
				$this->fail( $message ?: 'Value is not true.' );
			}
		}

		public function assertFalse( $value, $message = '' ) {
			if ( false !== $value ) {
				$this->fail( $message ?: 'Value is not false.' );
			}
		}

		public function assertNull( $value, $message = '' ) {
			if ( null !== $value ) {
				$this->fail( $message ?: 'Value is not null.' );
			}
		}

		public function assertCount( $expected, $actual, $message = '' ) {
			$count = is_countable( $actual ) ? count( $actual ) : -1;
			if ( $expected !== $count ) {
				$this->fail( $message ?: "Expected count {$expected}, got {$count}." );
			}
		}

		public function assertStringNotContainsString( $needle, $haystack, $message = '' ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: "String contains unexpected value {$needle}." );
			}
		}

		public function fail( $message ) {
			throw new AssertionFailedError( $message );
		}
	}
}

namespace {
	$root = dirname( __DIR__ );
	require_once $root . '/tests/bootstrap.php';

	$test_files = glob( $root . '/tests/*Test.php' );
	$tests       = array();
	foreach ( $test_files as $test_file ) {
		require_once $test_file;
	}

	foreach ( get_declared_classes() as $class ) {
		if ( is_subclass_of( $class, 'PHPUnit\\Framework\\TestCase' ) && 'PHPUnit\\Framework\\TestCase' !== $class ) {
			$reflection = new ReflectionClass( $class );
			foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
				if ( 0 === strpos( $method->name, 'test' ) ) {
					$tests[] = array( $class, $method->name );
				}
			}
		}
	}

	$passed = 0;
	$failed = 0;
	foreach ( $tests as $test ) {
		list( $class, $method ) = $test;
		$case = new $class();
		try {
			$set_up = new ReflectionMethod( $case, 'setUp' );
			$set_up->setAccessible( true );
			$set_up->invoke( $case );
			$case->{$method}();
			$tear_down = new ReflectionMethod( $case, 'tearDown' );
			$tear_down->setAccessible( true );
			$tear_down->invoke( $case );
			$passed++;
			printf( ". %s::%s\n", $class, $method );
		} catch ( Throwable $error ) {
			$failed++;
			printf( "F %s::%s\n%s\n", $class, $method, $error->getMessage() );
		}
	}

	printf( "\n%d tests passed, %d failed.\n", $passed, $failed );
	exit( $failed ? 1 : 0 );
}
