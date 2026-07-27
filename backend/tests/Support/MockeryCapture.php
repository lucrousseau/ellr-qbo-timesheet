<?php

namespace Tests\Support;

final class MockeryCapture
{
    /**
     * Return a value populated by Mockery::capture() for static analysis after the mocked call runs.
     *
     * @template T of object
     * @param  T|null  $captured
     * @return T
     */
    public static function unwrap(?object $captured): object
    {
        assert($captured !== null);

        return $captured;
    }
}
