<?php

namespace Tests\Feature\V5;

use App\Support\Concurrency\OnceGuard;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class OnceGuardTest extends TestCase
{
    private function guard(): OnceGuard
    {
        return new OnceGuard(30, 3600);
    }

    public function test_the_work_runs_once_and_the_second_call_is_a_duplicate(): void
    {
        $runs = 0;

        $first = $this->guard()->run('once:a', function () use (&$runs) {
            $runs++;
        });
        $second = $this->guard()->run('once:a', function () use (&$runs) {
            $runs++;
        });

        $this->assertSame(OnceGuard::EXECUTED, $first);
        $this->assertSame(OnceGuard::DUPLICATE, $second);
        $this->assertSame(1, $runs);
    }

    public function test_a_call_made_while_another_holds_the_lock_is_busy_and_does_not_run(): void
    {
        $holder = Cache::lock('once:b:lock', 30);
        $this->assertTrue($holder->get());

        $runs = 0;
        $outcome = $this->guard()->run('once:b', function () use (&$runs) {
            $runs++;
        });

        $this->assertSame(OnceGuard::BUSY, $outcome);
        $this->assertSame(0, $runs);

        $holder->release();

        $this->assertSame(OnceGuard::EXECUTED, $this->guard()->run('once:b', function () use (&$runs) {
            $runs++;
        }));
        $this->assertSame(1, $runs);
    }

    public function test_a_failed_run_is_not_remembered_and_can_be_retried(): void
    {
        try {
            $this->guard()->run('once:c', function () {
                throw new RuntimeException('smtp down');
            });
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertFalse(Cache::has('once:c'));
        $this->assertSame(OnceGuard::EXECUTED, $this->guard()->run('once:c', fn () => null));
    }

    public function test_the_lock_is_released_after_a_failure(): void
    {
        try {
            $this->guard()->run('once:d', function () {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        $lock = Cache::lock('once:d:lock', 5);

        $this->assertTrue($lock->get());
        $lock->release();
    }
}
