<?php


namespace App\Kernel\Fuse;

/**
 * 状态
 *
 * Class State
 * @package App\Kernel\Fuse
 */
class State
{
    const CLOSE = 0;

    const HALF_OPEN = 1;

    const OPEN = 2;

    protected int $state;

    public function __construct()
    {
        $this->state = self::CLOSE;
    }

    public function open()
    {
        $this->state = self::OPEN;
    }

    public function close()
    {
        $this->state = self::CLOSE;
    }

    public function getState(): int
    {
        return $this->state;
    }
    public function halfOpen()
    {
        $this->state = self::HALF_OPEN;
    }

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }

    public function isClose(): bool
    {
        return $this->state === self::CLOSE;
    }

    public function isHalfOpen(): bool
    {
        return $this->state === self::HALF_OPEN;
    }
}