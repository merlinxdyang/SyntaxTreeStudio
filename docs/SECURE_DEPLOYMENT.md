# 安全部署 / Secure deployment

## 已有网站 / Existing installations

Updating GitHub does not update the running VPS. Stop public access while applying these steps if exposure is suspected. Never reuse the previously published administrator password: it remains available in Git history and copies of the repository.

仅修改 GitHub 不会更新 VPS。若怀疑已经暴露，应在处理期间暂停公开访问。旧默认密码仍可能存在于历史提交或他人的副本中，必须作废。

1. Deploy the updated files, including hidden `.htaccess` files.
2. Reset every administrator account that used the old default password with `php scripts/set-admin-password.php USERNAME`, supplying the new password through standard input as shown in the README. Run this on the VPS against the production database, never a copied database. This command creates the account if absent and updates only that account's password if present.
3. Invalidate existing PHP sessions using your server's session management; changing passwords does not end existing administrator sessions.
4. Move SQLite outside **all** web document roots. Stop PHP workers before copying or moving the database, and preserve any SQLite journal/WAL/SHM files consistently. Use an owner-only directory (0700) and database files readable/writable only by the PHP service user (0600). Set `SYNTREE_DB_PATH` to the private absolute path in the PHP service environment. Do not commit the environment file.
5. Restart the service and verify existing users and records before deleting any old publicly reachable database and sidecar files. Keep backups outside web roots as well.

依次部署文件、重设管理员密码、清除旧会话、将数据库迁出所有网站公开目录并设置 `SYNTREE_DB_PATH`，确认原有记录完整后清除旧位置的数据库及附属文件。不要在运行中的数据库上直接复制文件。不要把密码作为命令参数、URL 参数或写入文件提交到 GitHub。

## Apache

The supplied root `.htaccess` blocks direct access to data, source, scripts, tests, hidden files, SQLite files and common database/backup extensions. `data/.htaccess` also denies access. The virtual host must enable overrides for these directives (Options, FileInfo, AuthConfig); otherwise put the same restrictions in the virtual-host configuration. Confirm the effective configuration before reopening access.

Apache 必须实际启用这些访问限制；忽略或未部署 `.htaccess` 时，仓库文件不能提供保护。

## Nginx

Nginx does not read `.htaccess`. Place these regular-expression locations **before** the generic PHP and other regex locations in the relevant server block. Adjust the URL prefix if deployed outside `/mss`:

```nginx
location ~* ^/mss/(data|src|scripts|tests)(/|$) { deny all; }
location ~ (^|/)\. { deny all; }
location ~* \.(sqlite3?|db)(-(wal|shm|journal))?$ { deny all; }
location ~* \.(bak|sql|dump|log|env)$ { deny all; }
```

These restrictions supplement private database storage; validate and reload the actual Nginx configuration. With a reverse proxy, administrator throttling counts both username and socket peer. Users sharing a proxy may share the source limit; never trust arbitrary forwarding headers to bypass it.

## Verify / 验证

- Confirm that `/mss/data/syntree.sqlite`, SQLite sidecars, `/mss/.env`, `/mss/src/config.php`, and `/mss/scripts/set-admin-password.php` return 403 or 404. A response body or a redirect to a successful page does not establish protection.
- Use a harmless temporary marker in the protected directory to check actual GET denial; never download the real database as a test. Remove the marker afterward.
- Confirm that administrator login works with the new password; the old credential must fail.
- Ten failed administrator sign-ins within 15 minutes block further attempts for that username or socket peer until the failures age out.
- Review access logs and administrator/user records if there is evidence of prior access.
- GitHub cannot establish whether the VPS has applied these protections. Historical removal cannot revoke a password; password replacement is required.
