# 测试最佳实践（agent.test.md）

本文档约定本仓库测试的统一规范与执行方式。提交测试代码时应严格遵守，确保可维护、可复用、可在 CI 稳定运行。

## 目标与范围

-   优先覆盖：关键控制器 API 的功能测试；必要时再做集成/慢测。
-   不与生产外部依赖交互：网络、真实对象存储、第三方服务等一律 fake 或打桩。

## 策略与优先级

-   金字塔：单元 > 功能(Feature) > 集成/端到端。
-   小步快跑：每个新控制器/服务配最小可用测试（成功分支 + 校验失败/权限失败）。
-   可维护：为通用 REST 资源控制器抽通用测试基类/trait，减少重复。

## 运行环境与 .env.testing

-   建议使用 PostgreSQL 作为测试数据库（项目查询广泛使用 PG 语法）。
-   建议在项目根目录维护 `.env.testing`，示例：
    -   `APP_ENV=testing`
    -   `DB_CONNECTION=pgsql`
    -   `DB_HOST=127.0.0.1`
    -   `DB_PORT=5432`
    -   `DB_DATABASE=your_test_db`
    -   `DB_USERNAME=your_user`
    -   `DB_PASSWORD=your_pass`
    -   `QUEUE_CONNECTION=sync`
    -   `LOG_CHANNEL=null`
    -   `FILESYSTEM_DISK=local`
    -   `MOCK_ENABLE=false`（除非特意测试临时免登场景）
-   运行：`composer test` 或 `php artisan test [-–group slow] [-–parallel]`。

## 数据策略（默认）

-   默认使用“真实 PG 测试库 + DatabaseTransactions 回滚”。原因：业务查询依赖 PG 特性；`RefreshDatabase` 可能清空业务表，风险较高。
-   如果某些用例必须依赖业务表数据，请在用例开头检测环境/表存在性，不满足时 `markTestSkipped()`。

## 鉴权与权限

-   测试基座通过 `Tests\TestCase` 以超管身份登录；需先在该库中存在超管记录（命令：`php artisan _sys:super-user:create`）。
-   每个资源控制器应包含：
    -   未授权/无权限返回 403；
    -   超管或具备权限用户允许通过；
    -   新增/修改控制器后，请运行 `php artisan _sys:permission:import` 同步权限。

## 常用 Fake / 隔离

-   HTTP：`Http::fake()` 拦截外部请求（如 122.gov 接口等）。
-   文件系统：`Storage::fake('local')` / `Storage::fake('s3')`，避免真实 IO；断言写入路径与文件数。
-   队列/事件：`Queue::fake()`、`Event::fake()`；必要时 `Event::assertDispatched()`。
-   日志与请求日志：将日志通道设为 `null`；对不关心请求日志的用例可 `withoutMiddleware(App\Http\Middleware\LogRequests::class)`。

## 控制器响应规范断言

-   统一断言 `ResponseBuilder` 结构：`data` / `message` / `messages` / `extra` / `lang` / `option` / `meta`。
-   分页列表：断言分页/集合的关键字段与大小；必要时校验排序/过滤参数生效。
-   导出 Excel：请求参数带 `output=excel`；使用 `Storage::fake('local')` 断言导出产物与返回的临时签名结构（`url`、`expiration`），无需真实下载。
-   参数校验：对缺失/类型错误断言 422；资源不存在 404；权限不足 403。

## 服务层测试

-   Uploader：`Storage::fake('s3'|'local')` + 伪造上传文件，断言返回 JSON 中包含 `filepath`（以及 OCR 字段可打桩最小假数据）。
-   PageExcel：构造最小查询集，断言导出文件写入 fake 磁盘与签名返回结构。
-   DocTplService（较慢）：通过 fake S3 + 本地模板最小替换验证 Happy Path；此类标记为慢测（见“慢测与分组”）。

## 控制台命令测试

-   `_sys:super-user:create`：断言幂等创建/更新超管与角色同步。
-   `_sys:permission:import`：路由/控制器变更后断言权限表同步（新增/删除）。
-   122 相关命令：使用 `Http::fake()`、`Log::spy()`；仅验证流程与持久化，不触达真实网络。

## 时间与并行

-   固定时间：`travelTo()` / `Carbon::setTestNow()` 避免时间不确定性。
-   并行：可开启 `php artisan test --parallel`；确保每个用例自包含且事务回滚。

## 结构与命名

-   目录：`tests/Unit` 放纯 PHP 与小服务；`tests/Feature` 放路由/控制器/API；按业务域分子目录，与 `app/` 结构对应。
-   命名：`testStoreCreates...`、`testUpdateValidationErrors` 等，表达清晰、与现有风格一致。
-   复用：抽取 `tests/Feature/Support/ResourceCrudTestTrait.php`（或基类）统一覆盖 index/show/store/update/destroy 标准断言。

## 慢测与分组

-   将涉及大 IO/模板处理/批量导出的测试标注 `@group slow`。
-   CI 默认不跑 `slow`；本地或专用任务通过 `php artisan test --group slow` 或设 `RUN_SLOW_TESTS=1` 触发。
-   需要在用例中根据 `env('RUN_SLOW_TESTS')` 条件 `markTestSkipped()` 控制执行。

## CI 与稳定性

-   测试必须可重复、无顺序依赖、无外网依赖、无真实外部副作用。
-   对依赖业务表的数据用例：在缺表/无数据时 `markTestSkipped()`，避免红灯；推荐提供测试库快照或准备脚本。

## 约定默认值（可按项目需要调整）

1. 测试数据库：默认使用真实 PG 测试库 + 事务回滚；不使用 `RefreshDatabase` 清库。
2. 导出/文档处理：包含 Excel 导出用例；Doc 模板处理标记 `@group slow` 并默认跳过，需显式开启。
3. 权限覆盖：每个资源控制器至少覆盖“无权限 403”和“超管放行 200/2xx”。
4. 通用基座：为 REST 资源控制器提供通用测试 trait/基类以批量覆盖 CRUD 契约。

—— 如需调整本规范，请在 PR 中同步更新本文档并在变更说明中明确理由。
