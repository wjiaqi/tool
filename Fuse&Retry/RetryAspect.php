<?php
/**
 * @copyright SecXun
 * @version 1.0.0
 * @author xiaoqi
 */

namespace App\Aspect;


use App\Annotation\RateLimiterAnnotation;
use App\Annotation\RetryAnnotation;
use App\Annotation\RpcRetryAnnotation;
use App\Exception\LogicException;
use App\Kernel\Fuse\Fuse;
use App\Kernel\Fuse\FuseFactory;
use App\Kernel\Fuse\FuseInterface;
use GuzzleHttp\Exception\ConnectException;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\RpcClient\Exception\RequestException;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\System;

/**
 * 重试&熔断
 *
 * @Aspect()
 * Class RetryAspect
 * @package App\Aspect
 */
class RetryAspect extends AbstractAspect
{
    /**
     * 切入的注解
     *
     * @var string[]
     */
    public $annotations = [
        RpcRetryAnnotation::class
    ];

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var RpcRetryAnnotation $annotation */
        $annotation = $proceedingJoinPoint->getAnnotationMetadata()->method[RpcRetryAnnotation::class] ?? new RpcRetryAnnotation();

        Context::set('arguments', $proceedingJoinPoint->getArguments());
        Context::set('pipe', $proceedingJoinPoint->pipe);

        return $this->serviceFuse($proceedingJoinPoint, $annotation);
    }

    /**
     * 服务熔断
     */
    public function serviceFuse(ProceedingJoinPoint $proceedingJoinPoint, RpcRetryAnnotation $annotation)
    {
        // 获取熔断类
        $name = sprintf('%s::%s', $proceedingJoinPoint->className, $proceedingJoinPoint->methodName);
        $fuse = $this->container->get(FuseFactory::class)->get($name);
        if (!$fuse instanceof FuseInterface) {
            $fuse = make(Fuse::class, ['name' => $name]);
            $this->container->get(FuseFactory::class)->set($name, $fuse);
        }

        $stat = $fuse->state();
        // 如果熔断开启,直接执行默认返回
        if ($stat->isOpen()) {
            $this->switch($fuse, $annotation, false);
            return $this->fuseFallback($annotation, $fuse->getLastResult());
        }
        // 半开
        if ($stat->isHalfOpen()) {
            if ($fuse->attempt()) {
                return $this->retry($proceedingJoinPoint, $fuse, $annotation);
            } else {
                return $this->fuseFallback($annotation, $fuse->getLastResult());
            }
        }
        return $this->retry($proceedingJoinPoint, $fuse, $annotation);
    }

    /**
     * 重试
     *
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @param Fuse $fuse
     * @param RpcRetryAnnotation $annotation
     * @return array|false|mixed
     */
    public function retry(ProceedingJoinPoint $proceedingJoinPoint, Fuse $fuse, RpcRetryAnnotation $annotation)
    {
        // 当前重试次数
        $count = 1;
        attempt:
        try {
            // 如果熔断开启,直接执行默认返回
            if ($fuse->state()->isOpen()) {
                goto end;
            }
            $proceedingJoinPoint->pipe = Context::get('pipe');
            $ret = $proceedingJoinPoint->process();

            $fuse->incrSuccessCounter();
            $this->switch($fuse, $annotation, true);
        } catch (\Exception $exception) {
            Context::set('lastThrowable', [
                $exception->getMessage(),
                $exception->getCode(),
            ]);

            $fuse->incrFailCounter();
            $this->switch($fuse, $annotation, false);
        }
        if (isset($ret['code'])) {
            goto end;
        }
        $count++;
        if ($this->carRetry($count, $annotation->maxAttempt)) {
            $this->sleep($annotation->interval);
            goto attempt;
        } else {
            goto end;
        }
        end:
        if (!empty($ret)) {
            $fuse->incrSuccessCounter();
            return $ret;
        }
        $lastThrowable = (array)Context::get('lastThrowable');
        // 逻辑异常
        if (!empty($lastThrowable)) {
            // 不使用默认方法
            if (!$annotation->useFallback) {
                // 设置最后一次查询结果
                $fuse->setLastResult($lastThrowable);
                throw new LogicException($lastThrowable[0], $lastThrowable[1]);
            } else {
                return $this->fallback($annotation->fallback);
            }
        }
        throw new LogicException('未知错误-retry', 1009);
    }

    /**
     * 是否可重试
     *
     * @param int $count
     * @param int $maxCount
     * @return bool
     */
    public function carRetry(int $count, int $maxCount): bool
    {
        return $count <= $maxCount;
    }

    /**
     *  睡眠重试
     */
    public function sleep(int $time = 0)
    {
        if ($time == 0) {
            return;
        }
        System::sleep($time);
    }

    /**
     * 执行默认方法
     *
     * @return false|mixed
     */
    public function fallback(string $method)
    {
        if (empty($method)) {
            return $this->defaultFallback();
        }
        [$className, $methodName] = explode('::', $method);
        try {
            return $this->container->get($className)->{$methodName}();
        } catch (\Throwable $throwable) {
            Context::set('lastThrowable', [$throwable->getMessage(), $throwable->getCode()]);
        }
    }

    /**
     * 触发熔断的回调
     *
     * @param RpcRetryAnnotation $annotation
     * @param array $result
     * @return array|false|mixed
     */
    public function fuseFallback(RpcRetryAnnotation $annotation, array $result)
    {
        // 逻辑异常
        if (!empty($result) && !$annotation->useFallback) {
            throw new LogicException($result[0], $result[1]);
        }
        return $this->fallback($annotation->fallback);
    }

    /**
     * 默认方法
     *
     * @return array
     */
    public function defaultFallback(): array
    {
        // 执行系统默认方法
        return [];
    }

    /**
     * 熔断状态切换
     *
     * @param Fuse $fuse
     * @param RpcRetryAnnotation $annotation
     * @param bool $status
     */
    protected function switch(Fuse $fuse, RpcRetryAnnotation $annotation, bool $status)
    {
        $state = $fuse->state();
        if ($state->isClose()) {
            if ($fuse->getDuration() > $annotation->duration) {
                return $fuse->close();
            }
            if (!$status && $fuse->getFailCount() >= $annotation->failCount) {
                return $fuse->open();
            }
            return;
        }

        if ($state->isHalfOpen()) {
            if (!$status && $fuse->getFailCount() >= $annotation->failCount) {
                return $fuse->open();
            }
            if ($status && $fuse->getSuccessCount() >= $annotation->successCount) {
                return $fuse->close();
            }
            return;
        }

        if ($state->isOpen()) {
            if ($fuse->getDuration() > $annotation->duration) {
                return $fuse->halfOpen();
            }
            return;
        }
    }
}