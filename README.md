# hlw2326/think-plugin-mp-shared

ThinkAdmin 小程序插件共享组件，提供用户解析器和通用服务，所有小程序相关插件统一依赖此包。

> **作者微信**：hlw2326

---

## 组件内容

| 文件 | 说明 |
|---|---|
| `src/contract/UserResolver.php` | 用户解析器接口 |
| `src/service/UserResolverService.php` | 用户解析器服务（静态单例） |

---

## 使用方式

### 各插件依赖此包

```json
{
  "require": {
    "hlw2326/think-plugin-mp-shared": "*"
  }
}
```

### 主应用注册解析器

在主应用初始化时一次性注册所有插件的用户解析器：

```php
<?php

declare(strict_types=1);

namespace app\mini\service;

use app\mini\model\MiniUser;
use hlw2326\mp\shared\service\UserResolverService;

/**
 * 插件用户解析器注册服务
 * 所有小程序插件通过 UserResolverService 获取当前登录用户 ID
 * @class PluginResolverService
 * @package app\mini\service
 */
class PluginResolverService extends \think\admin\Service
{
    protected function initialize(): void
    {
        // 注册通用解析器（'*' 匹配所有 appid）
        UserResolverService::register(function (string $appid): ?string {
            return MiniUser::getCurrentUserId($appid);
        });
    }
}
```

然后在 `app/mini/Service.php` 中引用或直接在各插件的 Service 中完成注册。

### 各插件 API 基类引用

```php
<?php

declare(strict_types=1);

namespace plugin\xxx\controller\api\v1;

use hlw2326\mp\shared\service\UserResolverService;
use think\admin\Controller;

abstract class Base extends Controller
{
    protected string $appid = '';
    protected ?string $userId = null;

    public function __construct()
    {
        parent::__construct();
        $this->appid = $this->request->header('appid', $this->request->get('appid', $this->request->post('appid', '')));
        $this->userId = UserResolverService::getUserId($this->appid);
    }
}
```

---

## 版本历史

- **v1.0.0**：初始版本，提供 UserResolver 接口和 UserResolverService
