<?php


namespace App\Kernel\Fuse;


use Psr\Container\ContainerInterface;

class Fuse implements FuseInterface
{
    /**
     * @var State
     */
    public State $state;
    /**
     * @var string
     */
    private string $name;
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var float
     */
    private float $timestamp;

    /**
     * 失败次数
     *
     * @var int
     */
    private int $failCounter;

    /**
     * 最后一次执行结果
     *
     * @var array
     */
    private array $lastResult = [];

    /**
     * 成功次数
     *
     * @var int
     */
    private int $successCounter;

    public function __construct(ContainerInterface $container, string $name)
    {
        $this->container = $container;
        $this->name = $name;
        $this->state = make(State::class);
        $this->init();
    }

    /**
     * @return State
     */
    public function state(): State
    {
        return $this->state;
    }

    /**
     * 是否可重试
     *
     * @return bool
     */
    public function attempt(): bool
    {
        return rand(0, 100) >= 50;
    }

    /**
     * 打开
     */
    public function open()
    {
        $this->init();

        $this->state->open();
    }

    /**
     * 半开
     */
    public function halfOpen()
    {
        $this->init();
        $this->state->halfOpen();
    }

    /**
     * 关闭
     */
    public function close()
    {
        $this->init();

        $this->state->close();
    }

    /**
     * 失败次数自增
     *
     * @return int
     */
    public function incrFailCounter(): int
    {
        return ++ $this->failCounter;
    }

    /**
     * 成功新增
     *
     * @return int
     */
    public function incrSuccessCounter(): int
    {
        return ++ $this->successCounter;
    }

    /**
     * @return int
     */
    public function getFailCount(): int
    {
        return $this->failCounter;
    }

    /**
     * @return int
     */
    public function getSuccessCount(): int
    {
        return $this->successCounter;
    }

    /**
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * 间隔时间
     *
     * @return float
     */
    public function getDuration(): float
    {
        return microtime(true) - $this->timestamp;
    }

    /**
     *
     */
    public function init()
    {
        $this->timestamp = microtime(true);
        $this->failCounter = 0;
        $this->successCounter = 0;
//        $this->lastResult = [];
    }

    /**
     * 获取最后一次执行结果
     *
     * @return array
     */
    public function getLastResult(): array
    {
        return $this->lastResult ?? [];
    }

    /**
     * 设置最后一次执行结果
     *
     * @param array $data
     */
    public function setLastResult(array $data)
    {
        $this->lastResult = $data;
    }
}