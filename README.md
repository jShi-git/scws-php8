# scws-php8

基于 [`hightman/scws`](https://github.com/hightman/scws) 的 **PHP 扩展兼容分支**，目标是让 `scws` 能在 **PHP 8.0/8.1/8.2（及更高版本）** 上正常编译、加载和运行。

## 改动要点（相对上游）

- **PHP 8 统一使用 stub/arginfo**：`phpext/scws.stub.php` + `phpext/scws_arginfo.h`。
- **兼容 PHP 8.2 动态属性弃用**：`SimpleCWS::$handle` 按 stub 声明属性处理，扩展侧用 `zend_read_property / zend_update_property` 读写 `handle`，避免 PHP 8.2 的 deprecated 与兼容性问题。
- **修复部分旧式 zval 用法**：减少 PHP7/8 下潜在的内存/生命周期风险。

## 构建与安装（通用 Linux / Ubuntu）

> 注意：**不要**从 macOS 直接拷 `scws.so` 到 Linux；必须在目标机器上用对应 PHP 版本重新编译。

### 依赖

- 编译工具链：`gcc/clang + make + autoconf`
- PHP 开发包：`php8.2-dev`（或你自己编译的 PHP 对应的 `phpize/php-config`）

### 编译

```bash
cd scws-php8/phpext

# 关键：本仓库 libscws/ 在项目根目录，built-in 构建时需要它位于 phpext/libscws
rm -rf libscws
cp -R ../libscws ./libscws

phpize
./configure --with-scws=built-in
make -j"$(nproc)"
sudo make install
```

### 启用

将以下内容写入对应 PHP 的 ini（比如 `conf.d/scws.ini` 或 `php.ini`）：

```ini
extension=scws.so
```

重启 PHP-FPM 后验证：

```bash
php -m | grep -i scws
php -r 'echo scws_version(), PHP_EOL;'
```

## 宝塔面板（BT）PHP 8.2 编译提示

宝塔环境建议使用它自带的 `phpize/php-config`，并按其 `extension_dir` 安装：

- `php82 -i | grep extension_dir`
- `/www/server/php/82/bin/phpize`
- `/www/server/php/82/bin/php-config`

示例（路径按你的机器调整）：

```bash
cd /www/server/source/scws-php8/phpext
rm -rf libscws && cp -R ../libscws ./libscws

/www/server/php/82/bin/phpize
./configure --with-php-config=/www/server/php/82/bin/php-config --with-scws=built-in
make -j"$(nproc)"
cp -f modules/scws.so /www/server/php/82/lib/php/extensions/no-debug-non-zts-20220829/
```

然后在 `/www/server/php/82/etc/php.ini`（或对应 conf.d）加入：

```ini
extension=scws.so
```

并重启 php-fpm（例如 `/etc/init.d/php-fpm-82 restart`）。

## 相关文档

- 详细扩展 API 与用法：见 `phpext/README.md`
