<?php


namespace App\Kernel\Fuse;

use Hyperf\CircuitBreaker\CircuitBreakerInterface;
use Psr\Container\ContainerInterface;

/**
 * 熔断工厂
 *
 * Class FuseFactory
 * @package App\Kernel\Fuse
 */
class FuseFactory
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var array
     */
    protected array $point = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(string $name): ?FuseInterface
    {
        return $this->point[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->point[$name]);
    }

    public function set(string $name, FuseInterface $storage): FuseInterface
    {
        return $this->point[$name] = $storage;
    }
}