<?php
declare( strict_types = 1 );

/**
 * Integration tests that hold the transient store of zone locks to the rules every store follows.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Infrastructure\WordPress\TransientLockStore;
use Automattic\Zoninator\Tests\LockStoreContract;

// The integration tests have no autoloader for the tests' own classes.
require_once dirname( __DIR__ ) . '/LockStoreContract.php';

final class TransientLockStoreTest extends TestCase {

	use LockStoreContract;

	protected function store(): LockStoreInterface {
		return zoninator_container()->get( TransientLockStore::class );
	}
}
