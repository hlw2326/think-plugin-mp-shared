<?php

declare(strict_types=1);

namespace hlw2326\mp\shared\contract;

/**
 * 用户解析器接口
 * 插件通过此接口获取当前登录用户信息
 * @interface UserResolver
 * @package hlw2326\mp\shared\contract
 */
interface UserResolver
{
    /**
     * 根据 appid 获取当前登录用户 ID
     * @param string $appid 小程序 appid
     * @return string|null 已登录返回用户ID，未登录返回 null
     */
    public static function getUserId(string $appid): ?string;

    /**
     * 根据 appid 获取当前登录用户的完整信息
     * @param string $appid 小程序 appid
     * @return array{user_id: string, nickname: string, avatar: string, ...}|null
     *   已登录返回用户信息数组，数组键：user_id / nickname / avatar / vip_time / score / phone 等
     *   未登录返回 null
     */
    public static function getUser(string $appid): ?array;
}
