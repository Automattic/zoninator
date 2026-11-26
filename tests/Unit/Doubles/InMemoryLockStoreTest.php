<?php
declare( strict_types = 1 );

/**
 * Unit tests that hold the stand-in for the lock store to the real store's rules.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Tests\LockStoreContract;
use PHPUnit\Framework\TestCase;

final class InMemoryLockStoreTest extends TestCase {

	use LockStoreContract;

	/**
	 * @var InMemoryLockStore
	 */
	private $store;

	protected function setUp(): void {
		$this->store = new InMemoryLockStore();
	}

	protected function store(): LockStoreInterface {
		return $this->store;
	}
}
