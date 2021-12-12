<?php
/**
 * @copyright SecXun
 * @version 1.0.0
 * @author xiaoqi
 */

namespace App\Annotation;


use Hyperf\Di\Annotation\AbstractAnnotation;

// 调用方法 @RpcRetryAnnotation(useFallback=false, failCount=4, successCount=6, maxAttempt=4, useFullback=true, fallback="App\JsonRpc\Caller\RolePowerPlusCaller::defaultMethod")
/**
 * rpc 重试
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 * Class RetryAnnotation
 * @package App\Annotation
 */
class RpcRetryAnnotation extends AbstractAnnotation
{
    /**
     * 是否使用失败回调方法
     *
     * @var bool
     */
    public bool $useFallback = false;

    /**
     * 失败回退方法 class::method
     *
     * @var string
     */
    public string $fallback = '';

    /**
     * 重试次数
     *
     * @var int
     */
    public int $maxAttempt = 2;

    /**
     * 重试间隔
     *
     * @var int
     */
    public int $interval = 1;

    /**
     * 熔断重新开放时间
     * @var float
     */
    public float $duration = 10;

    /**
     * 失败次数
     * @var int
     */
    public int $failCount = 4;

    /**
     * 成功次数
     * @var int
     */
    public int $successCount = 5;
}