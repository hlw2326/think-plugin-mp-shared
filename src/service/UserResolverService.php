<?php

declare(strict_types=1);

namespace hlw2326\mp\shared\service;

use hlw2326\mp\shared\contract\UserResolver;
use think\admin\Service;

/**
 * 用户解析器服务（共享组件）
 * 插件通过此服务获取当前登录用户 ID，具体实现由主应用注册
 * @class UserResolverService
 * @package hlw2326\mp\shared\service
 */
class UserResolverService extends Service
{
    /**
     * 已注册的解析器集合
     * @var array<string, callable|string>
     */
    private static array $resolvers = [];

    /**
     * 注册用户 ID 解析器
     * @param callable|string $resolver 支持两种形式：
     *   1. callable 函数，直接返回 user_id 或 null
     *   2. 类名全称字符串（含命名空间），该类必须实现 UserResolver 接口
     * @param string $appid 支持的 appid，'*' 表示通用（匹配所有）
     */
    public static function register(callable|string $resolver, string $appid = '*'): void
    {
        self::$resolvers[$appid] = $resolver;
    }

    /**
     * 获取当前登录用户 ID
     * 优先级：精确匹配 appid > 通配符 '*'
     * @param string $appid 小程序 appid
     * @return string|null 已登录返回用户ID，未登录返回 null
     */
    public static function getUserId(string $appid): ?string
    {
        if (isset(self::$resolvers[$appid])) {
            return self::doResolve(self::$resolvers[$appid], $appid);
        }
        if (isset(self::$resolvers['*'])) {
            return self::doResolve(self::$resolvers['*'], $appid);
        }
        return null;
    }

    /**
     * 执行解析
     */
    private static function doResolve(callable|string $resolver, string $appid): ?string
    {
        if (is_callable($resolver)) {
            return $resolver($appid);
        }
        if (is_string($resolver) && is_a($resolver, UserResolver::class, true)) {
            return $resolver::getUserId($appid);
        }
        return null;
    }

    /**
     * 清除所有已注册的解析器（测试用）
     */
    public static function clear(): void
    {
        self::$resolvers = [];
    }

    /**
     * 获取当前已注册的解析器（调试用）
     * @return array
     */
    public static function getResolvers(): array
    {
        return self::$resolvers;
    }
}
