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
        // 数组形式 ['user' => callable] 或 ['userId' => callable]
        if (is_array($resolver)) {
            if (isset($resolver['userId'])) {
                $result = $resolver['userId']($appid);
            } elseif (isset($resolver['user'])) {
                $result = $resolver['user']($appid);
            } else {
                $result = null;
            }
        }
        // 单个 callable：优先当做返回完整用户信息
        elseif (is_callable($resolver)) {
            $result = $resolver($appid);
        }
        // 接口类
        elseif (is_string($resolver) && is_a($resolver, UserResolver::class, true)) {
            $result = $resolver::getUserId($appid);
        } else {
            $result = null;
        }

        return self::extractUserId($result);
    }

    /**
     * 从各种返回值中提取 user_id 字符串
     * @param mixed $result
     * @return string|null
     */
    private static function extractUserId(mixed $result): ?string
    {
        if ($result === null) {
            return null;
        }
        // Model 对象
        if (is_object($result) && method_exists($result, 'toArray')) {
            $data = $result->toArray();
            return $data['user_id'] ?? $data['id'] ?? null;
        }
        // 数组
        if (is_array($result)) {
            return $result['user_id'] ?? $result['id'] ?? null;
        }
        // 字符串
        if (is_string($result)) {
            return $result ?: null;
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

    /**
     * 获取当前登录用户的完整信息
     * @param string $appid 小程序 appid
     * @return array|null 已登录返回完整用户信息数组，未登录返回 null
     */
    public static function getUser(string $appid): ?array
    {
        $result = null;

        if (isset(self::$resolvers[$appid])) {
            $result = self::callResolver(self::$resolvers[$appid], $appid);
        } elseif (isset(self::$resolvers['*'])) {
            $result = self::callResolver(self::$resolvers['*'], $appid);
        }

        if ($result !== null) {
            return self::normalizeUserResult($result, $appid);
        }

        return null;
    }

    /**
     * 调用解析器获取原始结果
     * @param mixed $resolver
     * @param string $appid
     * @return mixed
     */
    private static function callResolver(mixed $resolver, string $appid): mixed
    {
        // 数组形式 ['user' => callable]
        if (is_array($resolver)) {
            if (isset($resolver['user'])) {
                return $resolver['user']($appid);
            }
            if (isset($resolver['userId'])) {
                return $resolver['userId']($appid);
            }
        }
        // 单个 callable 或接口类
        if (is_callable($resolver)) {
            return $resolver($appid);
        }
        if (is_string($resolver) && is_a($resolver, UserResolver::class, true)) {
            return $resolver::getUser($appid);
        }
        return null;
    }

    /**
     * 规范化用户信息结果，统一返回完整用户信息数组
     * @param mixed $result 解析器返回的结果
     * @param string $appid 小程序 appid
     * @return array|null
     */
    private static function normalizeUserResult(mixed $result, string $appid): ?array
    {
        if ($result === null) {
            return null;
        }

        // 如果返回的是 Model 对象（ThinkAdmin Model 或 think\Model）
        if (is_object($result) && method_exists($result, 'toArray')) {
            $data = $result->toArray();
            // 确保有 user_id
            if (!isset($data['user_id']) && isset($data['id'])) {
                $data['user_id'] = (string)$data['id'];
            }
            // 确保 id 转为字符串（mini_user.id 为 varchar）
            if (isset($data['id'])) {
                $data['id'] = (string)$data['id'];
            }
            // 确保 score/vip_time 等数值字段有默认值
            $data['score']    = intval($data['score'] ?? 0);
            $data['vip_time'] = intval($data['vip_time'] ?? 0);
            $data['status']   = intval($data['status'] ?? 1);
            return $data;
        }

        // 如果返回的是字符串（user_id），尝试构建基本信息
        if (is_string($result)) {
            return [
                'user_id' => $result,
                'appid'   => $appid,
            ];
        }

        // 如果返回的是数组
        if (is_array($result)) {
            // 确保有 user_id
            if (!isset($result['user_id']) && isset($result['id'])) {
                $result['user_id'] = (string)$result['id'];
            }
            // 确保 id 转为字符串（mini_user.id 为 varchar）
            if (isset($result['id'])) {
                $result['id'] = (string)$result['id'];
            }
            // 确保 score/vip_time 等数值字段有默认值
            $result['score']    = intval($result['score'] ?? 0);
            $result['vip_time'] = intval($result['vip_time'] ?? 0);
            $result['status']   = intval($result['status'] ?? 1);
            return $result;
        }

        return null;
    }
}
