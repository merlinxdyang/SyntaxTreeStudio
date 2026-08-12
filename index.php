<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

const SYNTREE_VERSION = '0.2.2';
const SYNTREE_UPDATED_AT = '2026-07-17';
const APP_BRAND = 'MerlinSyntaxStudio';

$action = active_action();
$user = current_user();
init_language();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        handle_email_login();
    }
    if ($action === 'register') {
        handle_register();
    }
    if ($action === 'logout') {
        require_csrf();
        logout_user();
        redirect('index.php');
    }
    if ($action === 'save_history') {
        handle_save_history();
    }
    if ($action === 'record_generation') {
        handle_record_generation();
    }
    if ($action === 'feedback_submit') {
        handle_feedback_submit();
    }
    if ($action === 'admin_user') {
        http_response_code(404);
        exit('Not found');
    }
}

if ($action === 'oauth_start') {
    oauth_start((string) ($_GET['provider'] ?? ''));
}
if ($action === 'oauth_callback') {
    oauth_callback((string) ($_GET['provider'] ?? ''));
}

if ($action === 'admin') {
    http_response_code(404);
    exit('Not found');
}

if ($action === 'admin_user') {
    http_response_code(404);
    exit('Not found');
}

if ($action === 'workspace') {
    render_workspace();
    exit;
}

if ($action === 'about') {
    redirect(page_url());
}

if ($action === 'help') {
    render_help_page();
    exit;
}

if ($action === 'feedback') {
    render_feedback_page();
    exit;
}

render_landing($action);

function handle_save_history(): void
{
    require_csrf();
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Login required.'], 401);
    }
    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON.'], 400);
    }
    $source = trim((string) ($payload['source'] ?? ''));
    $latex = trim((string) ($payload['latex'] ?? ''));
    $nodeCount = max(0, (int) ($payload['node_count'] ?? 0));
    $movementCount = max(0, (int) ($payload['movement_count'] ?? 0));
    if ($source === '' || $latex === '' || mb_strlen($source) > 20000 || mb_strlen($latex) > 40000) {
        json_response(['ok' => false, 'error' => 'Nothing valid to save.'], 422);
    }
    save_tree_record((int) $user['id'], $source, $latex, $nodeCount, $movementCount);
    json_response(['ok' => true]);
}

function handle_record_generation(): void
{
    require_csrf();
    json_response([
        'ok' => true,
        'count' => increment_generation_count(),
    ]);
}

function handle_feedback_submit(): void
{
    require_csrf();

    $returnTo = 'feedback';
    $user = require_login();
    $formToken = (string) ($_POST['feedback_form_token'] ?? '');
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($honeypot !== '' || !consume_feedback_form_token($formToken)) {
        flash('error', t('feedback_error_form'));
        redirect(page_url($returnTo));
    }

    if ($message === '' || mb_strlen($message) > 10000) {
        flash('error', t('feedback_error_message'));
        redirect(page_url($returnTo));
    }
    if (feedback_external_link_count($message) > 3) {
        flash('error', t('feedback_error_links'));
        redirect(page_url($returnTo));
    }

    $ipAddress = client_ip_address();
    $violation = feedback_submission_violation((int) $user['id'], $ipAddress, $message);
    if ($violation !== null) {
        $key = match ($violation) {
            'duplicate' => 'feedback_error_duplicate',
            'cooldown' => 'feedback_error_cooldown',
            default => 'feedback_error_daily',
        };
        flash('error', t($key));
        redirect(page_url($returnTo));
    }

    $attachment = handle_feedback_upload($returnTo);
    $status = feedback_status_for_new_message((int) $user['id']);
    save_feedback_message([
        'user_id' => (int) $user['id'],
        'message' => $message,
        'format' => 'markdown',
        'status' => $status,
        'attachment_path' => $attachment['path'] ?? null,
        'attachment_name' => $attachment['name'] ?? null,
        'ip_address' => $ipAddress,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    flash('success', t($status === 'pending' ? 'feedback_success_pending' : 'feedback_success_published'));
    redirect(page_url($returnTo));
}

function feedback_return_action(string $action): string
{
    return in_array($action, ['home', 'workspace', 'about', 'help', 'feedback'], true) ? $action : 'feedback';
}

function handle_feedback_upload(string $returnTo): array
{
    $file = $_FILES['attachment'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        flash('error', t('feedback_error_file_size'));
        redirect(page_url($returnTo));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $info = $tmp !== '' ? @getimagesize($tmp) : false;
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        flash('error', t('feedback_error_file_type'));
        redirect(page_url($returnTo));
    }

    $uploadDir = __DIR__ . '/uploads/feedback';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = gmdate('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        flash('error', t('feedback_error_upload'));
        redirect(page_url($returnTo));
    }

    return [
        'path' => 'uploads/feedback/' . $filename,
        'name' => mb_substr((string) ($file['name'] ?? $filename), 0, 180),
    ];
}

function init_language(): void
{
    $allowed = enabled_language_codes();
    $lang = $_GET['lang'] ?? null;
    if (is_string($lang) && in_array($lang, $allowed, true)) {
        $_SESSION['lang'] = $lang;
    }
    if (empty($_SESSION['lang']) || !in_array((string) $_SESSION['lang'], $allowed, true)) {
        $_SESSION['lang'] = $allowed[0] ?? 'en';
    }
}

function current_lang(): string
{
    return (string) ($_SESSION['lang'] ?? 'en');
}

function t(string $key): string
{
    $dict = [
        'en' => [
            'syntax_tree_generator' => "Merlin's Syntax Studio",
            'nav_how' => 'How It Works',
            'nav_about' => 'About',
            'start_creating' => 'Start Creating',
            'continue_guest' => 'Use without account',
            'hero_eyebrow' => APP_BRAND,
            'hero_title' => 'Turn bracket notation into clean syntax trees',
            'hero_copy' => 'Create phrase-structure trees, movement links, transparent PNGs, SVGs, and Forest LaTeX in one quiet workspace.',
            'hero_note_1' => 'Free to start',
            'hero_note_2' => 'No account required',
            'hero_note_3' => 'History for signed-in users',
            'trees_generated' => '{count} trees generated',
            'latest_update_title' => 'Latest update',
            'latest_update_copy' => 'Version {version} · Updated {date}',
            'latest_update_1' => 'Use | for a line break inside one node. Literal square brackets must be escaped as \\[ and \\], for example T0|\\[+PST\\].',
            'latest_update_2' => 'Use [] for an empty node and [_i] or [_z2] for an indexed empty movement position.',
            'latest_update_3' => 'Each movement link can use its own solid or dashed style, and any selected branch can be hidden and restored.',
            'latest_update_4' => 'Input undo/redo, trackpad pinch zoom, annotation colors, and a complete syntax guide have been added.',
            'latest_update_5' => 'Forest keeps the structure editable; visual TikZ preserves manual geometry, hidden branches, styles, colors, annotations, and curves.',
            'release_syntax_title' => 'Complete syntax reference for this version',
            'login' => 'Login',
            'register' => 'Register',
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'create_account' => 'Create account',
            'continue_with' => 'Continue with',
            'not_configured' => 'not configured',
            'oauth_hint' => 'Registered users can keep the most recent 20 syntax trees.',
            'workspace' => 'Workspace',
            'about' => 'About',
            'feedback' => 'Feedback BBS',
            'feedback_title' => 'Feedback BBS',
            'feedback_intro' => 'Read public problem reports and feature requests here. Registered users can post; email addresses are never displayed publicly.',
            'feedback_open_page' => 'Open full page',
            'feedback_name_optional' => 'Name (optional)',
            'feedback_email_optional' => 'Email (optional)',
            'feedback_format' => 'Format',
            'feedback_markdown' => 'Markdown',
            'feedback_html' => 'HTML',
            'feedback_message' => 'Feedback or problem report',
            'feedback_message_placeholder' => 'Describe the problem you found or the feature you would like to see.',
            'feedback_attachment' => 'Image attachment',
            'feedback_attachment_hint' => 'Optional. JPG, PNG, GIF, or WebP only. Maximum 5 MB.',
            'feedback_submit' => 'Submit feedback',
            'feedback_close' => 'Close',
            'feedback_success' => 'Feedback received. Thank you.',
            'feedback_error_email' => 'Please enter a valid email address.',
            'feedback_error_message' => 'Please enter feedback under 10,000 characters.',
            'feedback_error_file_size' => 'The image must be 5 MB or smaller.',
            'feedback_error_file_type' => 'Please upload a JPG, PNG, GIF, or WebP image.',
            'feedback_error_upload' => 'The image could not be uploaded. Please try again.',
            'feedback_public_board' => 'Public feedback board',
            'feedback_board_heading' => 'Problems, requests, and official replies',
            'feedback_login_notice' => 'Everyone can read this board. Sign in or register to post a new message.',
            'feedback_new_topic' => 'Post new feedback',
            'feedback_posting_as' => 'Posting publicly as {name}',
            'feedback_first_post_notice' => 'Your first message will be reviewed before it becomes public. Later messages can appear immediately after your first message is approved.',
            'feedback_topics' => 'Public messages',
            'feedback_topic_count' => '{count} messages visible to you',
            'feedback_user' => 'Registered user',
            'feedback_pending' => 'Pending review',
            'feedback_pending_note' => 'Only you and the administrator can see this message until it is approved.',
            'feedback_answered' => 'Answered',
            'feedback_admin_reply' => 'Official administrator reply',
            'feedback_admin_edited' => 'Edited by the administrator on {time}',
            'feedback_attachment_preview' => 'Feedback image attachment',
            'feedback_empty_title' => 'No public messages yet',
            'feedback_empty_copy' => 'The first approved message will appear here.',
            'feedback_per_page' => 'Per page',
            'feedback_pagination' => 'Feedback pages',
            'feedback_previous' => 'Previous',
            'feedback_next' => 'Next',
            'feedback_page_status' => 'Page {page} of {pages}',
            'feedback_success_pending' => 'Your message was received and is waiting for administrator review.',
            'feedback_success_published' => 'Your message was published.',
            'feedback_error_form' => 'The form expired or could not be verified. Reload the page and try again.',
            'feedback_error_links' => 'A message can contain at most three external links.',
            'feedback_error_duplicate' => 'You have already submitted the same message recently.',
            'feedback_error_cooldown' => 'Please wait at least 60 seconds before posting again.',
            'feedback_error_daily' => 'The daily posting limit has been reached. Please try again tomorrow.',
            'feedback_public_name_notice' => 'This name will be displayed publicly when you post on the Feedback BBS. You may use a nickname.',
            'editor_bold' => 'Bold',
            'editor_italic' => 'Italic',
            'editor_link' => 'Link',
            'editor_quote' => 'Quote',
            'editor_code' => 'Code',
            'editor_list' => 'List',
            'about_title' => 'About ' . APP_BRAND,
            'about_maker' => 'Creator',
            'about_version' => 'Version',
            'about_intro' => APP_BRAND . ' is a lightweight syntax tree generator for linguistics teaching, analysis, and quick publication drafting.',
            'about_feature_title' => 'What it does',
            'about_feature_1' => 'Turns bracket expressions into readable phrase-structure trees in real time.',
            'about_feature_2' => 'Supports movement links, traces, triangle roofs, strikethrough, italics, outline text, subscripts, superscripts, and Greek letters.',
            'about_feature_3' => 'Exports SVG, transparent PNG, white-background PNG, editable Forest LaTeX, and geometry-preserving visual TikZ.',
            'about_privacy_title' => 'Accounts and records',
            'about_privacy_copy' => 'You can use the workspace as a guest without saving anything. Signed-in users can keep the most recent 20 generated records for reuse.',
            'about_contact_title' => 'Contact',
            'about_contact_copy' => 'If you find any problem while using this tool, please contact me.',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => 'If this tool helps you, you can buy me a double-shot Americano.',
            'about_alipay_button' => 'Support with Alipay',
            'about_alipay_qr_title' => 'Alipay QR code',
            'about_alipay_qr_copy' => 'Scan this QR code with Alipay.',
            'dialog_close' => 'Close',
            'admin' => 'Admin',
            'sign_out' => 'Sign out',
            'bracket_expression' => 'Bracket expression',
            'undo' => 'Undo',
            'redo' => 'Redo',
            'input_hint' => 'Paste or type a bracket expression. The preview updates immediately.',
            'enter_expression' => 'Enter a bracket expression.',
            'found_stats' => 'Found {nodes} nodes and {links} movement links.',
            'branch_style' => 'Branch style',
            'uniform_branch_angles' => 'Equal branch angles',
            'movement_style' => 'Movement style',
            'show_movement' => 'Show movement links',
            'show_movement_one' => 'Show movement link ({label})',
            'empty_movement_position' => 'empty position ({index})',
            'hide_branch' => 'Hide branch',
            'hidden_branches' => 'Hidden branches',
            'restore_branch' => 'Restore',
            'restore_all_branches' => 'Restore all',
            'free_drawing_tools' => 'Extra drawing',
            'add_annotation' => 'Add annotation',
            'add_segment_curve' => 'Add curve',
            'curve_style' => 'Curve style',
            'curve_weight' => 'Curve weight',
            'regular' => 'Regular',
            'bold' => 'Bold',
            'delete_selected_extra' => 'Delete selected note/curve',
            'delete_extra_short' => 'Delete',
            'annotation_prompt' => 'Annotation text',
            'annotation_color' => 'Annotation color',
            'default_annotation_text' => '(note)',
            'solid' => 'Solid',
            'dashed' => 'Dashed',
            'load_sample' => 'Load sample',
            'white_png' => 'White PNG',
            'transparent_png' => 'Transparent PNG',
            'forest_latex' => 'Forest LaTeX',
            'tikz_latex' => 'Visual TikZ',
            'save_account' => 'Save to account',
            'saving' => 'Saving...',
            'saved' => 'Saved',
            'typesetting_code' => 'LaTeX Code',
            'syntax_reference_title' => 'Syntax Reference',
            'syntax_example_col' => 'Syntax example',
            'syntax_effect_col' => 'Display effect',
            'syntax_effect_empty' => 'branch, no label',
            'syntax_effect_silent' => 'hidden, no branch',
            'syntax_action_annotation' => 'Add annotation',
            'syntax_action_curve' => 'Add curve',
            'syntax_effect_curve' => 'three-point arc',
            'syntax_action_drag' => 'Drag element',
            'syntax_effect_drag' => 'labels / branches / triangles / links',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'preview' => 'Preview',
            'preview_hint' => 'Use _i for visible indices, _z1/_z2 for hidden movement indices, =word= for strikethrough, *word* for italics, @word@ for outline text, and alpha/beta/gamma/phi for Greek letters.',
            'help' => 'Guide',
            'help_title' => 'Guide',
            'help_close' => 'Close',
            'help_syntax' => 'Syntax reference',
            'help_examples' => 'Examples',
            'help_notes' => 'Workspace notes',
            'signed_in' => 'Signed in',
            'recent_records' => 'Recent 20 Records',
            'no_saved' => 'No saved trees yet.',
            'guest_mode' => 'Guest mode',
            'guest_note' => 'You can generate and export trees now. Sign in only if you want the last 20 generated trees saved.',
            'language' => 'English',
        ],
        'zh' => [
            'syntax_tree_generator' => 'Merlin的句法树形图生成器',
            'nav_how' => '使用方式',
            'nav_about' => '关于',
            'start_creating' => '开始生成',
            'continue_guest' => '不注册直接使用',
            'hero_eyebrow' => APP_BRAND,
            'hero_title' => '把括号表达式转换成清晰的树形图',
            'hero_copy' => '在一个简洁页面中生成短语结构树形图、移位线、透明 PNG、SVG 和 Forest LaTeX。',
            'hero_note_1' => '免费开始',
            'hero_note_2' => '无需账户',
            'hero_note_3' => '登录后保存历史',
            'trees_generated' => '已生成 {count} 个树形图',
            'latest_update_title' => '最新更新',
            'latest_update_copy' => '版本 {version} · 更新日期 {date}',
            'latest_update_1' => '同一节点内用 | 换行；作为文字的方括号必须写成 \\[ 和 \\]，例如 T0|\\[+PST\\]。',
            'latest_update_2' => '使用 [] 创建空节点，使用 [_i] 或 [_z2] 创建带索引的空移位位置。',
            'latest_update_3' => '每条移位线可以分别使用实线或虚线；选中任意树枝后可以隐藏，并可在控制栏恢复。',
            'latest_update_4' => '新增输入撤销/恢复、触控板双指缩放、注释颜色和完整语法说明。',
            'latest_update_5' => 'Forest 保留可编辑结构；视觉 TikZ 保留手动布局、隐藏树枝、线型、颜色、注释和曲线。',
            'release_syntax_title' => '本版本完整语法说明',
            'login' => '登入',
            'register' => '注册',
            'name' => '姓名',
            'email' => '电子邮件',
            'password' => '密码',
            'create_account' => '创建账户',
            'continue_with' => '通过登录',
            'not_configured' => '未配置',
            'oauth_hint' => '注册用户可以记录最近20个树形图。',
            'workspace' => '工作台',
            'about' => '关于',
            'feedback' => '意见留言板',
            'feedback_title' => '意见留言板',
            'feedback_intro' => '这里公开显示问题反馈、功能建议和管理员回复。注册用户可以留言，电子邮件绝不会公开显示。',
            'feedback_open_page' => '打开完整页面',
            'feedback_name_optional' => '姓名（选填）',
            'feedback_email_optional' => '邮件（选填）',
            'feedback_format' => '格式',
            'feedback_markdown' => 'Markdown',
            'feedback_html' => 'HTML',
            'feedback_message' => '反馈意见或问题汇报',
            'feedback_message_placeholder' => '请描述您遇到的问题，或希望增加的功能。',
            'feedback_attachment' => '图片附件',
            'feedback_attachment_hint' => '选填。仅支持 JPG、PNG、GIF 或 WebP，最大 5 MB。',
            'feedback_submit' => '提交反馈',
            'feedback_close' => '关闭',
            'feedback_success' => '反馈已收到，谢谢。',
            'feedback_error_email' => '请输入有效的邮件地址。',
            'feedback_error_message' => '请输入 10,000 字以内的反馈内容。',
            'feedback_error_file_size' => '图片不能超过 5 MB。',
            'feedback_error_file_type' => '请上传 JPG、PNG、GIF 或 WebP 图片。',
            'feedback_error_upload' => '图片上传失败，请重试。',
            'feedback_public_board' => '公开意见留言板',
            'feedback_board_heading' => '问题、建议与管理员回复',
            'feedback_login_notice' => '任何人都可以查看留言。登录或注册后才可以发布新留言。',
            'feedback_new_topic' => '发布新留言',
            'feedback_posting_as' => '将以“{name}”公开留言',
            'feedback_first_post_notice' => '您的第一条留言需要管理员审核；首条通过后，后续留言可以直接公开。',
            'feedback_topics' => '公开留言',
            'feedback_topic_count' => '您当前可以看到 {count} 条留言',
            'feedback_user' => '注册用户',
            'feedback_pending' => '等待审核',
            'feedback_pending_note' => '审核通过前，只有您本人和管理员能够看到这条留言。',
            'feedback_answered' => '已回复',
            'feedback_admin_reply' => '管理员正式回复',
            'feedback_admin_edited' => '管理员于 {time} 编辑',
            'feedback_attachment_preview' => '反馈图片附件',
            'feedback_empty_title' => '还没有公开留言',
            'feedback_empty_copy' => '第一条审核通过的留言将显示在这里。',
            'feedback_per_page' => '每页显示',
            'feedback_pagination' => '留言分页',
            'feedback_previous' => '上一页',
            'feedback_next' => '下一页',
            'feedback_page_status' => '第 {page} 页，共 {pages} 页',
            'feedback_success_pending' => '留言已收到，正在等待管理员审核。',
            'feedback_success_published' => '留言已公开发布。',
            'feedback_error_form' => '表单已过期或无法验证，请刷新页面后重试。',
            'feedback_error_links' => '一条留言最多只能包含 3 个外部链接。',
            'feedback_error_duplicate' => '您最近已经提交过相同内容。',
            'feedback_error_cooldown' => '两次留言至少需要间隔 60 秒。',
            'feedback_error_daily' => '今天的留言次数已达到上限，请明天再试。',
            'feedback_public_name_notice' => '在意见留言板发帖时，这个姓名会公开显示；您可以使用昵称。',
            'editor_bold' => '加粗',
            'editor_italic' => '斜体',
            'editor_link' => '链接',
            'editor_quote' => '引用',
            'editor_code' => '代码',
            'editor_list' => '列表',
            'about_title' => '关于 ' . APP_BRAND,
            'about_maker' => '制作人',
            'about_version' => '版本号',
            'about_intro' => APP_BRAND . ' 是一个轻量级句法树/树形图生成工具，面向语言学教学、句法分析、课堂展示和论文写作草稿。',
            'about_feature_title' => '主要功能',
            'about_feature_1' => '把括号表达式实时转换为可读的短语结构树形图。',
            'about_feature_2' => '支持移位线、trace、三角形 roof、删除线、斜体、空心字、下标、上标和希腊字母。',
            'about_feature_3' => '支持导出 SVG、透明 PNG、白底 PNG、可编辑的 Forest LaTeX，以及保留手动布局的视觉 TikZ。',
            'about_privacy_title' => '账户与记录',
            'about_privacy_copy' => '不注册也可以直接使用工作台，游客模式不会保存生成历史。登录用户可以保留最近 20 条生成记录，方便之后继续编辑或复用。',
            'about_contact_title' => '联系',
            'about_contact_copy' => '如果您在使用中发现任何问题，请跟我联系。',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => '如果您觉得这个工具对您有帮助，可以给我买一杯加浓美式。',
            'about_alipay_button' => '支付宝',
            'about_alipay_qr_title' => '支付宝二维码',
            'about_alipay_qr_copy' => '请使用支付宝扫描二维码。',
            'dialog_close' => '关闭',
            'admin' => '后台',
            'sign_out' => '退出',
            'bracket_expression' => '括号表达式',
            'undo' => '撤销',
            'redo' => '恢复',
            'input_hint' => '粘贴或输入括号表达式，预览会立即更新。',
            'enter_expression' => '请输入括号表达式。',
            'found_stats' => '找到 {nodes} 个节点和 {links} 条移位线。',
            'branch_style' => '树枝线型',
            'uniform_branch_angles' => '统一树杈开口角度',
            'movement_style' => '移位线型',
            'show_movement' => '显示移位线',
            'show_movement_one' => '显示移位线（{label}）',
            'empty_movement_position' => '空位置（{index}）',
            'hide_branch' => '隐藏树枝',
            'hidden_branches' => '已隐藏的树枝',
            'restore_branch' => '恢复',
            'restore_all_branches' => '全部恢复',
            'free_drawing_tools' => '额外标注',
            'add_annotation' => '添加注释',
            'add_segment_curve' => '添加曲线',
            'curve_style' => '曲线线型',
            'curve_weight' => '曲线粗细',
            'regular' => '普通',
            'bold' => '加粗',
            'delete_selected_extra' => '删除选中注释/曲线',
            'delete_extra_short' => '删除',
            'annotation_prompt' => '注释文字',
            'annotation_color' => '注释颜色',
            'default_annotation_text' => '（注释）',
            'solid' => '实线',
            'dashed' => '虚线',
            'load_sample' => '载入示例',
            'white_png' => '白底 PNG',
            'transparent_png' => '透明 PNG',
            'forest_latex' => 'Forest LaTeX',
            'tikz_latex' => '视觉 TikZ',
            'save_account' => '保存到账户',
            'saving' => '保存中...',
            'saved' => '已保存',
            'typesetting_code' => 'LaTeX 代码',
            'syntax_reference_title' => '常见语法表',
            'syntax_example_col' => '语法示例',
            'syntax_effect_col' => '实际显示效果',
            'syntax_effect_empty' => '保留树枝，无文字',
            'syntax_effect_silent' => '隐藏，不画树枝',
            'syntax_action_annotation' => '添加注释',
            'syntax_action_curve' => '添加弧线',
            'syntax_effect_curve' => '三点弧线',
            'syntax_action_drag' => '拖动元素',
            'syntax_effect_drag' => 'label / 树枝 / 三角形 / 移位线',
            'copy' => '复制',
            'copied' => '已复制',
            'preview' => '预览',
            'preview_hint' => '使用 _i 显示下标，_z1/_z2 隐藏下标但保留不同移位匹配，=word= 标记删除线，*word* 标记斜体，@word@ 标记空心字，alpha/beta/gamma/phi 显示希腊字母。',
            'help' => '使用说明',
            'help_title' => '使用说明',
            'help_close' => '关闭',
            'help_syntax' => '语法表',
            'help_examples' => '示例',
            'help_notes' => '工作区说明',
            'signed_in' => '已登录',
            'recent_records' => '最近 20 条记录',
            'no_saved' => '还没有保存的树图。',
            'guest_mode' => '游客模式',
            'guest_note' => '现在即可生成和导出树图。只有登录后才会保存最近 20 条生成记录。',
            'language' => '中文',
        ],
        'es' => [
            'syntax_tree_generator' => 'Estudio de sintaxis de Merlin',
            'nav_how' => 'Cómo funciona',
            'nav_about' => 'Acerca de',
            'start_creating' => 'Empezar a crear',
            'continue_guest' => 'Usar sin cuenta',
            'hero_eyebrow' => APP_BRAND,
            'hero_title' => 'Convierte la notación con corchetes en árboles sintácticos claros',
            'hero_copy' => 'Crea árboles de estructura sintagmática, líneas de movimiento, PNG transparentes, SVG y código Forest para LaTeX en un único espacio de trabajo.',
            'hero_note_1' => 'Empieza gratis',
            'hero_note_2' => 'No se requiere cuenta',
            'hero_note_3' => 'Historial para usuarios registrados',
            'trees_generated' => '{count} árboles generados',
            'latest_update_title' => 'Última actualización',
            'latest_update_copy' => 'Versión {version} · Actualizado el {date}',
            'latest_update_1' => 'Usa | para una nueva línea dentro del mismo nodo. Los corchetes literales deben escribirse como \\[ y \\], por ejemplo T0|\\[+PST\\].',
            'latest_update_2' => 'Usa [] para un nodo vacío y [_i] o [_z2] para una posición vacía indexada.',
            'latest_update_3' => 'Cada línea de movimiento puede ser continua o discontinua; cualquier rama seleccionada puede ocultarse y restaurarse.',
            'latest_update_4' => 'Se añadieron deshacer/rehacer, zoom con gesto de pellizco, colores de anotación y una guía de sintaxis completa.',
            'latest_update_5' => 'Forest conserva la estructura editable; TikZ visual conserva geometría, ramas ocultas, estilos, colores, anotaciones y curvas.',
            'release_syntax_title' => 'Referencia completa de sintaxis de esta versión',
            'login' => 'Iniciar sesión',
            'register' => 'Registrarse',
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'password' => 'Contraseña',
            'create_account' => 'Crear cuenta',
            'continue_with' => 'Continuar con',
            'not_configured' => 'no configurado',
            'oauth_hint' => 'Los usuarios registrados pueden guardar los 20 árboles sintácticos más recientes.',
            'workspace' => 'Espacio de trabajo',
            'about' => 'Acerca de',
            'feedback' => 'Foro de comentarios',
            'feedback_title' => 'Foro de comentarios',
            'feedback_intro' => 'Aquí se muestran públicamente problemas, solicitudes y respuestas del administrador. Los usuarios registrados pueden publicar; los correos electrónicos nunca se muestran.',
            'feedback_open_page' => 'Abrir página completa',
            'feedback_name_optional' => 'Nombre (opcional)',
            'feedback_email_optional' => 'Correo electrónico (opcional)',
            'feedback_format' => 'Formato',
            'feedback_markdown' => 'Markdown',
            'feedback_html' => 'HTML',
            'feedback_message' => 'Comentario o informe de problema',
            'feedback_message_placeholder' => 'Describe el problema que encontraste o la función que te gustaría añadir.',
            'feedback_attachment' => 'Imagen adjunta',
            'feedback_attachment_hint' => 'Opcional. Solo JPG, PNG, GIF o WebP. Máximo 5 MB.',
            'feedback_submit' => 'Enviar comentarios',
            'feedback_close' => 'Cerrar',
            'feedback_success' => 'Hemos recibido tus comentarios. Gracias.',
            'feedback_error_email' => 'Introduce una dirección de correo electrónico válida.',
            'feedback_error_message' => 'Introduce un comentario de menos de 10 000 caracteres.',
            'feedback_error_file_size' => 'La imagen debe tener un tamaño máximo de 5 MB.',
            'feedback_error_file_type' => 'Sube una imagen JPG, PNG, GIF o WebP.',
            'feedback_error_upload' => 'No se pudo subir la imagen. Inténtalo de nuevo.',
            'feedback_public_board' => 'Foro público de comentarios',
            'feedback_board_heading' => 'Problemas, solicitudes y respuestas oficiales',
            'feedback_login_notice' => 'Todo el mundo puede leer este foro. Inicia sesión o regístrate para publicar un mensaje.',
            'feedback_new_topic' => 'Publicar un comentario',
            'feedback_posting_as' => 'Publicarás como {name}',
            'feedback_first_post_notice' => 'Tu primer mensaje será revisado antes de hacerse público. Los siguientes podrán aparecer inmediatamente tras la aprobación del primero.',
            'feedback_topics' => 'Mensajes públicos',
            'feedback_topic_count' => 'Puedes ver {count} mensajes',
            'feedback_user' => 'Usuario registrado',
            'feedback_pending' => 'Pendiente de revisión',
            'feedback_pending_note' => 'Hasta su aprobación, solo tú y el administrador podéis ver este mensaje.',
            'feedback_answered' => 'Respondido',
            'feedback_admin_reply' => 'Respuesta oficial del administrador',
            'feedback_admin_edited' => 'Editado por el administrador el {time}',
            'feedback_attachment_preview' => 'Imagen adjunta al comentario',
            'feedback_empty_title' => 'Todavía no hay mensajes públicos',
            'feedback_empty_copy' => 'El primer mensaje aprobado aparecerá aquí.',
            'feedback_per_page' => 'Por página',
            'feedback_pagination' => 'Páginas de comentarios',
            'feedback_previous' => 'Anterior',
            'feedback_next' => 'Siguiente',
            'feedback_page_status' => 'Página {page} de {pages}',
            'feedback_success_pending' => 'Hemos recibido tu mensaje y está pendiente de revisión.',
            'feedback_success_published' => 'Tu mensaje se ha publicado.',
            'feedback_error_form' => 'El formulario ha caducado o no pudo verificarse. Recarga la página e inténtalo de nuevo.',
            'feedback_error_links' => 'Un mensaje puede contener como máximo tres enlaces externos.',
            'feedback_error_duplicate' => 'Ya has enviado recientemente el mismo mensaje.',
            'feedback_error_cooldown' => 'Espera al menos 60 segundos antes de volver a publicar.',
            'feedback_error_daily' => 'Se alcanzó el límite diario de publicaciones. Inténtalo mañana.',
            'feedback_public_name_notice' => 'Este nombre se mostrará públicamente cuando publiques en el foro. Puedes utilizar un seudónimo.',
            'editor_bold' => 'Negrita',
            'editor_italic' => 'Cursiva',
            'editor_link' => 'Enlace',
            'editor_quote' => 'Cita',
            'editor_code' => 'Código',
            'editor_list' => 'Lista',
            'about_title' => 'Acerca de ' . APP_BRAND,
            'about_maker' => 'Creador',
            'about_version' => 'Versión',
            'about_intro' => APP_BRAND . ' es un generador ligero de árboles sintácticos para la enseñanza de la lingüística, el análisis y la preparación rápida de borradores para publicación.',
            'about_feature_title' => 'Qué permite hacer',
            'about_feature_1' => 'Convierte expresiones con corchetes en árboles legibles de estructura sintagmática en tiempo real.',
            'about_feature_2' => 'Admite líneas de movimiento, huellas, triángulos, texto tachado, cursiva, texto con contorno, subíndices, superíndices y letras griegas.',
            'about_feature_3' => 'Exporta SVG, PNG transparente, PNG con fondo blanco, Forest LaTeX editable y TikZ visual que conserva la geometría.',
            'about_privacy_title' => 'Cuentas y registros',
            'about_privacy_copy' => 'Puedes usar el espacio de trabajo como invitado sin guardar nada. Los usuarios registrados pueden conservar los 20 árboles generados más recientes para reutilizarlos.',
            'about_contact_title' => 'Contacto',
            'about_contact_copy' => 'Si encuentras algún problema al utilizar esta herramienta, ponte en contacto conmigo.',
            'about_coffee_title' => 'Invítame a un café',
            'about_coffee_copy' => 'Si esta herramienta te resulta útil, puedes invitarme a un americano doble.',
            'about_alipay_button' => 'Apoyar con Alipay',
            'about_alipay_qr_title' => 'Código QR de Alipay',
            'about_alipay_qr_copy' => 'Escanea este código QR con Alipay.',
            'dialog_close' => 'Cerrar',
            'admin' => 'Administración',
            'sign_out' => 'Cerrar sesión',
            'bracket_expression' => 'Expresión con corchetes',
            'undo' => 'Deshacer',
            'redo' => 'Rehacer',
            'input_hint' => 'Pega o escribe una expresión con corchetes. La vista previa se actualiza de inmediato.',
            'enter_expression' => 'Introduce una expresión con corchetes.',
            'found_stats' => 'Se encontraron {nodes} nodos y {links} líneas de movimiento.',
            'branch_style' => 'Estilo de las ramas',
            'uniform_branch_angles' => 'Ángulos de rama iguales',
            'movement_style' => 'Estilo del movimiento',
            'show_movement' => 'Mostrar líneas de movimiento',
            'show_movement_one' => 'Mostrar línea de movimiento ({label})',
            'empty_movement_position' => 'posición vacía ({index})',
            'hide_branch' => 'Ocultar rama',
            'hidden_branches' => 'Ramas ocultas',
            'restore_branch' => 'Restaurar',
            'restore_all_branches' => 'Restaurar todas',
            'free_drawing_tools' => 'Dibujo adicional',
            'add_annotation' => 'Añadir anotación',
            'add_segment_curve' => 'Añadir curva',
            'curve_style' => 'Estilo de la curva',
            'curve_weight' => 'Grosor de la curva',
            'regular' => 'Normal',
            'bold' => 'Negrita',
            'delete_selected_extra' => 'Eliminar la nota o curva seleccionada',
            'delete_extra_short' => 'Eliminar',
            'annotation_prompt' => 'Texto de la anotación',
            'annotation_color' => 'Color de la anotación',
            'default_annotation_text' => '(nota)',
            'solid' => 'Continua',
            'dashed' => 'Discontinua',
            'load_sample' => 'Cargar ejemplo',
            'white_png' => 'PNG con fondo blanco',
            'transparent_png' => 'PNG transparente',
            'forest_latex' => 'Forest LaTeX',
            'tikz_latex' => 'TikZ visual',
            'save_account' => 'Guardar en la cuenta',
            'saving' => 'Guardando...',
            'saved' => 'Guardado',
            'typesetting_code' => 'Código LaTeX',
            'syntax_reference_title' => 'Referencia de sintaxis',
            'syntax_example_col' => 'Ejemplo de sintaxis',
            'syntax_effect_col' => 'Efecto visual',
            'syntax_effect_empty' => 'rama sin etiqueta',
            'syntax_effect_silent' => 'oculto y sin rama',
            'syntax_action_annotation' => 'Añadir anotación',
            'syntax_action_curve' => 'Añadir curva',
            'syntax_effect_curve' => 'arco de tres puntos',
            'syntax_action_drag' => 'Arrastrar elemento',
            'syntax_effect_drag' => 'etiquetas, ramas, triángulos y líneas',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
            'preview' => 'Vista previa',
            'preview_hint' => 'Usa _i para índices visibles, _z1/_z2 para índices de movimiento ocultos, =palabra= para tachado, *palabra* para cursiva, @palabra@ para texto con contorno y alpha/beta/gamma/phi para letras griegas.',
            'help' => 'Guía',
            'help_title' => 'Guía',
            'help_close' => 'Cerrar',
            'help_syntax' => 'Referencia de sintaxis',
            'help_examples' => 'Ejemplos',
            'help_notes' => 'Notas del espacio de trabajo',
            'signed_in' => 'Sesión iniciada',
            'recent_records' => '20 registros recientes',
            'no_saved' => 'Todavía no hay árboles guardados.',
            'guest_mode' => 'Modo invitado',
            'guest_note' => 'Puedes generar y exportar árboles ahora. Inicia sesión solo si quieres guardar los últimos 20 árboles generados.',
            'language' => 'Español',
        ],
        'ja' => [
            'syntax_tree_generator' => 'Merlinの統語樹ジェネレーター',
            'nav_how' => '使い方',
            'nav_about' => '概要',
            'start_creating' => '作成を開始',
            'continue_guest' => '登録せずに使う',
            'hero_eyebrow' => APP_BRAND,
            'hero_title' => '括弧表記を見やすい統語樹に変換',
            'hero_copy' => '句構造木、移動リンク、透過 PNG、SVG、Forest LaTeX を一つの画面で作成できます。',
            'hero_note_1' => '無料で開始',
            'hero_note_2' => 'アカウント不要',
            'hero_note_3' => 'ログインで履歴保存',
            'trees_generated' => '生成済みの統語樹：{count}',
            'latest_update_title' => '最新更新',
            'latest_update_copy' => 'バージョン {version} · 更新日 {date}',
            'latest_update_1' => '同じノード内の改行には | を使います。文字としての角括弧は \\[ と \\] で入力します（例：T0|\\[+PST\\]）。',
            'latest_update_2' => '[] は空ノード、[_i] または [_z2] はインデックス付き空移動位置を作成します。',
            'latest_update_3' => '移動線ごとに実線・破線を選べ、選択した任意の枝を非表示にして復元できます。',
            'latest_update_4' => '入力の元に戻す/やり直し、ピンチズーム、注記色、完全な構文ガイドを追加しました。',
            'latest_update_5' => 'Forest は編集可能な構造を保ち、視覚 TikZ は手動配置、非表示の枝、線種、色、注記、曲線を保持します。',
            'release_syntax_title' => 'このバージョンの完全な構文リファレンス',
            'login' => 'ログイン',
            'register' => '登録',
            'name' => '名前',
            'email' => 'メール',
            'password' => 'パスワード',
            'create_account' => 'アカウント作成',
            'continue_with' => 'で続行',
            'not_configured' => '未設定',
            'oauth_hint' => '登録ユーザーは最近 20 件の統語樹を保存できます。',
            'workspace' => 'ワークスペース',
            'about' => '概要',
            'feedback' => 'フィードバック掲示板',
            'feedback_title' => 'フィードバック掲示板',
            'feedback_intro' => '問題報告、機能要望、管理者からの返信を公開します。登録ユーザーのみ投稿でき、メールアドレスは公開されません。',
            'feedback_open_page' => 'ページで開く',
            'feedback_name_optional' => '名前（任意）',
            'feedback_email_optional' => 'メール（任意）',
            'feedback_format' => '形式',
            'feedback_markdown' => 'Markdown',
            'feedback_html' => 'HTML',
            'feedback_message' => 'フィードバックまたは問題報告',
            'feedback_message_placeholder' => '見つけた問題、または追加してほしい機能をお書きください。',
            'feedback_attachment' => '画像添付',
            'feedback_attachment_hint' => '任意。JPG、PNG、GIF、WebP のみ。最大 5 MB。',
            'feedback_submit' => '送信',
            'feedback_close' => '閉じる',
            'feedback_success' => 'フィードバックを受け取りました。ありがとうございます。',
            'feedback_error_email' => '有効なメールアドレスを入力してください。',
            'feedback_error_message' => '10,000 文字以内で内容を入力してください。',
            'feedback_error_file_size' => '画像は 5 MB 以下にしてください。',
            'feedback_error_file_type' => 'JPG、PNG、GIF、WebP 画像をアップロードしてください。',
            'feedback_error_upload' => '画像をアップロードできませんでした。もう一度お試しください。',
            'feedback_public_board' => '公開フィードバック掲示板',
            'feedback_board_heading' => '問題、要望、公式回答',
            'feedback_login_notice' => '掲示板は誰でも閲覧できます。新規投稿にはログインまたは登録が必要です。',
            'feedback_new_topic' => '新しいフィードバックを投稿',
            'feedback_posting_as' => '{name} として公開投稿します',
            'feedback_first_post_notice' => '最初の投稿は公開前に管理者が確認します。承認後の投稿はすぐに公開できます。',
            'feedback_topics' => '公開メッセージ',
            'feedback_topic_count' => '{count} 件のメッセージを表示できます',
            'feedback_user' => '登録ユーザー',
            'feedback_pending' => '確認待ち',
            'feedback_pending_note' => '承認されるまでは、本人と管理者だけがこの投稿を閲覧できます。',
            'feedback_answered' => '回答済み',
            'feedback_admin_reply' => '管理者からの公式回答',
            'feedback_admin_edited' => '管理者が {time} に編集',
            'feedback_attachment_preview' => 'フィードバック添付画像',
            'feedback_empty_title' => '公開メッセージはまだありません',
            'feedback_empty_copy' => '最初に承認された投稿がここに表示されます。',
            'feedback_per_page' => '1ページあたり',
            'feedback_pagination' => 'フィードバックのページ',
            'feedback_previous' => '前へ',
            'feedback_next' => '次へ',
            'feedback_page_status' => '{pages} ページ中 {page} ページ',
            'feedback_success_pending' => '投稿を受け付けました。管理者の確認待ちです。',
            'feedback_success_published' => '投稿を公開しました。',
            'feedback_error_form' => 'フォームの有効期限が切れたか確認できませんでした。再読み込みしてお試しください。',
            'feedback_error_links' => '外部リンクは1件につき3個までです。',
            'feedback_error_duplicate' => '同じ内容を最近すでに送信しています。',
            'feedback_error_cooldown' => '次の投稿まで60秒以上お待ちください。',
            'feedback_error_daily' => '1日の投稿上限に達しました。明日もう一度お試しください。',
            'feedback_public_name_notice' => '掲示板に投稿すると、この名前が公開されます。ニックネームも使用できます。',
            'editor_bold' => '太字',
            'editor_italic' => '斜体',
            'editor_link' => 'リンク',
            'editor_quote' => '引用',
            'editor_code' => 'コード',
            'editor_list' => 'リスト',
            'about_title' => APP_BRAND . ' について',
            'about_maker' => '制作者',
            'about_version' => 'バージョン',
            'about_intro' => APP_BRAND . ' は、言語学の教育、分析、発表資料、論文草稿向けの軽量な統語樹生成ツールです。',
            'about_feature_title' => '主な機能',
            'about_feature_1' => '括弧表現をリアルタイムで読みやすい句構造木に変換します。',
            'about_feature_2' => '移動線、trace、三角形 roof、取り消し線、斜体、袋文字、下付き、上付き、ギリシャ文字に対応します。',
            'about_feature_3' => 'SVG、透過 PNG、白背景 PNG、編集可能な Forest LaTeX、手動配置を保つ視覚 TikZ を書き出せます。',
            'about_privacy_title' => 'アカウントと履歴',
            'about_privacy_copy' => '登録なしでワークスペースを利用できます。ログインしたユーザーは最近 20 件の生成履歴を保存できます。',
            'about_contact_title' => '連絡先',
            'about_contact_copy' => '使用中に問題を見つけた場合は、こちらまでご連絡ください。',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => 'このツールが役に立った場合は、ダブルショットのアメリカーノをご支援いただけます。',
            'about_alipay_button' => 'Alipay で支援',
            'about_alipay_qr_title' => 'Alipay QR コード',
            'about_alipay_qr_copy' => 'Alipay でこの QR コードを読み取ってください。',
            'dialog_close' => '閉じる',
            'admin' => '管理',
            'sign_out' => 'ログアウト',
            'bracket_expression' => '括弧表現',
            'undo' => '元に戻す',
            'redo' => 'やり直す',
            'input_hint' => '括弧表現を貼り付けるか入力してください。プレビューは即時更新されます。',
            'enter_expression' => '括弧表現を入力してください。',
            'found_stats' => '{nodes} 個のノードと {links} 本の移動リンクを検出しました。',
            'branch_style' => '枝の線種',
            'uniform_branch_angles' => '枝の角度を統一',
            'movement_style' => '移動線の線種',
            'show_movement' => '移動線を表示',
            'show_movement_one' => '移動線を表示（{label}）',
            'empty_movement_position' => '空位置（{index}）',
            'hide_branch' => '枝を隠す',
            'hidden_branches' => '非表示の枝',
            'restore_branch' => '復元',
            'restore_all_branches' => 'すべて復元',
            'free_drawing_tools' => '追加注記',
            'add_annotation' => '注記を追加',
            'add_segment_curve' => '曲線を追加',
            'curve_style' => '曲線の線種',
            'curve_weight' => '曲線の太さ',
            'regular' => '通常',
            'bold' => '太字',
            'delete_selected_extra' => '選択した注記/曲線を削除',
            'delete_extra_short' => '削除',
            'annotation_prompt' => '注記テキスト',
            'annotation_color' => '注記の色',
            'default_annotation_text' => '（注記）',
            'solid' => '実線',
            'dashed' => '破線',
            'load_sample' => 'サンプル',
            'white_png' => '白背景 PNG',
            'transparent_png' => '透過 PNG',
            'forest_latex' => 'Forest LaTeX',
            'tikz_latex' => '視覚 TikZ',
            'save_account' => 'アカウントに保存',
            'saving' => '保存中...',
            'saved' => '保存済み',
            'typesetting_code' => 'LaTeX コード',
            'syntax_reference_title' => 'よく使う構文',
            'syntax_example_col' => '構文例',
            'syntax_effect_col' => '表示結果',
            'syntax_effect_empty' => '枝あり、文字なし',
            'syntax_effect_silent' => '非表示、枝なし',
            'syntax_action_annotation' => '注記を追加',
            'syntax_action_curve' => '曲線を追加',
            'syntax_effect_curve' => '三点の弧線',
            'syntax_action_drag' => '要素をドラッグ',
            'syntax_effect_drag' => 'ラベル / 枝 / 三角形 / 移動線',
            'copy' => 'コピー',
            'copied' => 'コピー済み',
            'preview' => 'プレビュー',
            'preview_hint' => '_i は表示下付き、_z1/_z2 は非表示の移動用インデックス、=word= は取り消し線、*word* は斜体、@word@ は袋文字、alpha/beta/gamma/phi はギリシャ文字です。',
            'help' => '使い方',
            'help_title' => '使い方',
            'help_close' => '閉じる',
            'help_syntax' => '構文表',
            'help_examples' => '例',
            'help_notes' => 'ワークスペース',
            'signed_in' => 'ログイン中',
            'recent_records' => '最近 20 件',
            'no_saved' => '保存済みの木はありません。',
            'guest_mode' => 'ゲストモード',
            'guest_note' => 'すぐに木を生成・書き出しできます。最近 20 件の履歴保存にはログインが必要です。',
            'language' => '日本語',
        ],
        'ko' => [
            'syntax_tree_generator' => 'Merlin의 수형도 생성기',
            'nav_how' => '사용 방법',
            'nav_about' => '소개',
            'start_creating' => '시작하기',
            'continue_guest' => '가입 없이 사용',
            'hero_eyebrow' => APP_BRAND,
            'hero_title' => '괄호 표기를 깔끔한 수형도로 변환합니다',
            'hero_copy' => '구 구조 수형도, 이동 링크, 투명 PNG, SVG, Forest LaTeX를 한 화면에서 만들 수 있습니다.',
            'hero_note_1' => '무료 시작',
            'hero_note_2' => '계정 불필요',
            'hero_note_3' => '로그인 시 기록 저장',
            'trees_generated' => '생성된 수형도 {count}개',
            'latest_update_title' => '최신 업데이트',
            'latest_update_copy' => '버전 {version} · 업데이트 날짜 {date}',
            'latest_update_1' => '같은 노드 안의 줄바꿈에는 |를 사용합니다. 문자 대괄호는 \\[ 및 \\]로 입력합니다(예: T0|\\[+PST\\]).',
            'latest_update_2' => '[]는 빈 노드, [_i] 또는 [_z2]는 인덱스가 있는 빈 이동 위치를 만듭니다.',
            'latest_update_3' => '각 이동선의 실선/점선을 따로 선택할 수 있고 선택한 모든 가지를 숨겼다가 복원할 수 있습니다.',
            'latest_update_4' => '입력 실행 취소/다시 실행, 핀치 줌, 주석 색상, 전체 구문 안내를 추가했습니다.',
            'latest_update_5' => 'Forest는 편집 가능한 구조를, 시각 TikZ는 수동 배치, 숨긴 가지, 선 종류, 색상, 주석과 곡선을 보존합니다.',
            'release_syntax_title' => '이 버전의 전체 구문 안내',
            'login' => '로그인',
            'register' => '가입',
            'name' => '이름',
            'email' => '이메일',
            'password' => '비밀번호',
            'create_account' => '계정 만들기',
            'continue_with' => '로 계속',
            'not_configured' => '설정 안 됨',
            'oauth_hint' => '가입한 사용자는 최근 20개의 수형도를 저장할 수 있습니다.',
            'workspace' => '작업공간',
            'about' => '소개',
            'feedback' => '피드백 게시판',
            'feedback_title' => '피드백 게시판',
            'feedback_intro' => '문제 신고, 기능 요청, 관리자 답변을 공개합니다. 가입한 사용자만 글을 올릴 수 있으며 이메일 주소는 공개되지 않습니다.',
            'feedback_open_page' => '전체 페이지 열기',
            'feedback_name_optional' => '이름(선택)',
            'feedback_email_optional' => '이메일(선택)',
            'feedback_format' => '형식',
            'feedback_markdown' => 'Markdown',
            'feedback_html' => 'HTML',
            'feedback_message' => '피드백 또는 문제 신고',
            'feedback_message_placeholder' => '발견한 문제나 추가되었으면 하는 기능을 적어 주세요.',
            'feedback_attachment' => '이미지 첨부',
            'feedback_attachment_hint' => '선택 사항. JPG, PNG, GIF, WebP만 가능하며 최대 5 MB입니다.',
            'feedback_submit' => '피드백 보내기',
            'feedback_close' => '닫기',
            'feedback_success' => '피드백을 받았습니다. 감사합니다.',
            'feedback_error_email' => '올바른 이메일 주소를 입력해 주세요.',
            'feedback_error_message' => '10,000자 이내로 내용을 입력해 주세요.',
            'feedback_error_file_size' => '이미지는 5 MB 이하여야 합니다.',
            'feedback_error_file_type' => 'JPG, PNG, GIF, WebP 이미지를 업로드해 주세요.',
            'feedback_error_upload' => '이미지를 업로드하지 못했습니다. 다시 시도해 주세요.',
            'feedback_public_board' => '공개 피드백 게시판',
            'feedback_board_heading' => '문제, 요청 및 공식 답변',
            'feedback_login_notice' => '누구나 게시판을 읽을 수 있습니다. 새 글은 로그인하거나 가입한 뒤 작성할 수 있습니다.',
            'feedback_new_topic' => '새 피드백 작성',
            'feedback_posting_as' => '{name} 이름으로 공개 게시합니다',
            'feedback_first_post_notice' => '첫 글은 공개 전에 관리자가 검토합니다. 첫 글이 승인되면 이후 글은 바로 공개될 수 있습니다.',
            'feedback_topics' => '공개 글',
            'feedback_topic_count' => '현재 {count}개의 글을 볼 수 있습니다',
            'feedback_user' => '가입 사용자',
            'feedback_pending' => '검토 대기',
            'feedback_pending_note' => '승인 전에는 작성자와 관리자만 이 글을 볼 수 있습니다.',
            'feedback_answered' => '답변 완료',
            'feedback_admin_reply' => '관리자 공식 답변',
            'feedback_admin_edited' => '관리자가 {time}에 수정함',
            'feedback_attachment_preview' => '피드백 첨부 이미지',
            'feedback_empty_title' => '공개 글이 아직 없습니다',
            'feedback_empty_copy' => '승인된 첫 글이 여기에 표시됩니다.',
            'feedback_per_page' => '페이지당',
            'feedback_pagination' => '피드백 페이지',
            'feedback_previous' => '이전',
            'feedback_next' => '다음',
            'feedback_page_status' => '{pages}페이지 중 {page}페이지',
            'feedback_success_pending' => '글을 받았으며 관리자 검토를 기다리고 있습니다.',
            'feedback_success_published' => '글이 공개되었습니다.',
            'feedback_error_form' => '양식이 만료되었거나 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.',
            'feedback_error_links' => '한 글에는 외부 링크를 최대 3개까지 넣을 수 있습니다.',
            'feedback_error_duplicate' => '최근에 같은 내용을 이미 제출했습니다.',
            'feedback_error_cooldown' => '다시 게시하려면 최소 60초 기다려 주세요.',
            'feedback_error_daily' => '일일 게시 한도에 도달했습니다. 내일 다시 시도해 주세요.',
            'feedback_public_name_notice' => '게시판에 글을 올리면 이 이름이 공개됩니다. 별명을 사용할 수 있습니다.',
            'editor_bold' => '굵게',
            'editor_italic' => '기울임',
            'editor_link' => '링크',
            'editor_quote' => '인용',
            'editor_code' => '코드',
            'editor_list' => '목록',
            'about_title' => APP_BRAND . ' 소개',
            'about_maker' => '제작자',
            'about_version' => '버전',
            'about_intro' => APP_BRAND . '는 언어학 교육, 분석, 발표 자료, 논문 초안을 위한 가벼운 수형도 생성 도구입니다.',
            'about_feature_title' => '주요 기능',
            'about_feature_1' => '괄호 표현식을 읽기 쉬운 구 구조 수형도로 실시간 변환합니다.',
            'about_feature_2' => '이동 링크, trace, 삼각형 roof, 취소선, 이탤릭체, 윤곽 글자, 아래첨자, 위첨자, 그리스 문자를 지원합니다.',
            'about_feature_3' => 'SVG, 투명 PNG, 흰 배경 PNG, 편집 가능한 Forest LaTeX, 수동 배치를 보존하는 시각 TikZ를 내보낼 수 있습니다.',
            'about_privacy_title' => '계정과 기록',
            'about_privacy_copy' => '가입 없이 작업공간을 사용할 수 있습니다. 로그인한 사용자는 최근 20개 생성 기록을 저장할 수 있습니다.',
            'about_contact_title' => '연락처',
            'about_contact_copy' => '사용 중 문제가 있으면 연락해 주세요.',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => '이 도구가 도움이 되었다면 더블샷 아메리카노 한 잔을 사 주실 수 있습니다.',
            'about_alipay_button' => 'Alipay로 후원',
            'about_alipay_qr_title' => 'Alipay QR 코드',
            'about_alipay_qr_copy' => 'Alipay로 이 QR 코드를 스캔해 주세요.',
            'dialog_close' => '닫기',
            'admin' => '관리',
            'sign_out' => '로그아웃',
            'bracket_expression' => '괄호 표현식',
            'undo' => '실행 취소',
            'redo' => '다시 실행',
            'input_hint' => '괄호 표현식을 붙여넣거나 입력하세요. 미리보기가 즉시 갱신됩니다.',
            'enter_expression' => '괄호 표현식을 입력하세요.',
            'found_stats' => '{nodes}개 노드와 {links}개 이동 링크를 찾았습니다.',
            'branch_style' => '가지 선 스타일',
            'uniform_branch_angles' => '가지 각도 통일',
            'movement_style' => '이동 선 스타일',
            'show_movement' => '이동 링크 표시',
            'show_movement_one' => '이동 링크 표시({label})',
            'empty_movement_position' => '빈 위치({index})',
            'hide_branch' => '가지 숨기기',
            'hidden_branches' => '숨긴 가지',
            'restore_branch' => '복원',
            'restore_all_branches' => '모두 복원',
            'free_drawing_tools' => '추가 주석',
            'add_annotation' => '주석 추가',
            'add_segment_curve' => '곡선 추가',
            'curve_style' => '곡선 스타일',
            'curve_weight' => '곡선 굵기',
            'regular' => '보통',
            'bold' => '굵게',
            'delete_selected_extra' => '선택한 주석/곡선 삭제',
            'delete_extra_short' => '삭제',
            'annotation_prompt' => '주석 텍스트',
            'annotation_color' => '주석 색상',
            'default_annotation_text' => '(주석)',
            'solid' => '실선',
            'dashed' => '점선',
            'load_sample' => '예시 불러오기',
            'white_png' => '흰 배경 PNG',
            'transparent_png' => '투명 PNG',
            'forest_latex' => 'Forest LaTeX',
            'tikz_latex' => '시각 TikZ',
            'save_account' => '계정에 저장',
            'saving' => '저장 중...',
            'saved' => '저장됨',
            'typesetting_code' => 'LaTeX 코드',
            'syntax_reference_title' => '자주 쓰는 구문',
            'syntax_example_col' => '구문 예시',
            'syntax_effect_col' => '표시 결과',
            'syntax_effect_empty' => '가지는 있고 글자 없음',
            'syntax_effect_silent' => '숨김, 가지 없음',
            'syntax_action_annotation' => '주석 추가',
            'syntax_action_curve' => '곡선 추가',
            'syntax_effect_curve' => '세 점 호형 곡선',
            'syntax_action_drag' => '요소 드래그',
            'syntax_effect_drag' => 'label / 가지 / 삼각형 / 이동선',
            'copy' => '복사',
            'copied' => '복사됨',
            'preview' => '미리보기',
            'preview_hint' => '_i 는 보이는 아래첨자, _z1/_z2 는 숨김 이동 인덱스, =word= 는 취소선, *word* 는 이탤릭체, @word@ 는 윤곽 글자, alpha/beta/gamma/phi 는 그리스 문자입니다.',
            'help' => '사용 설명',
            'help_title' => '사용 설명',
            'help_close' => '닫기',
            'help_syntax' => '구문 표',
            'help_examples' => '예시',
            'help_notes' => '작업공간 안내',
            'signed_in' => '로그인됨',
            'recent_records' => '최근 20개 기록',
            'no_saved' => '저장된 트리가 없습니다.',
            'guest_mode' => '게스트 모드',
            'guest_note' => '지금 바로 트리를 생성하고 내보낼 수 있습니다. 최근 20개 기록 저장은 로그인한 사용자만 가능합니다.',
            'language' => '한국어',
        ],
    ];

    $lang = current_lang();
    return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
}

function page_url(string $action = 'home', ?string $lang = null): string
{
    $params = [];
    if ($action !== 'home') {
        $params['action'] = $action;
    }
    $params['lang'] = $lang ?? current_lang();
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

function render_language_picker(string $action): void
{
    $labels = enabled_language_options();
    ?>
    <label class="language-picker">
        <span class="visually-hidden">Language</span>
        <select onchange="window.location.href=this.value">
            <?php foreach ($labels as $code => $label): ?>
                <option value="<?= e(page_url($action, $code)) ?>" <?= current_lang() === $code ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

function help_content_data(): array
{
    $lang = current_lang();

    if ($lang === 'zh') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', '基本括号节点。第一个项目是节点标签，后面的项目是子节点。'],
            ['(...)', '(TP John (VP ...))', '圆括号也可以表示树结构，作用与方括号相同。'],
            ['A|B|C', 'T0|\\[+PST\\]|\\[+3SG\\]', '同一个节点内换行显示。方括号属于文字时必须写成 \\[ 和 \\]。'],
            ['_i, _j, _k', 'John_i', '显示斜体下标。相同下标会自动生成移位线。'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '隐藏下标。z1、z2 等不显示，但会分别参与不同移位线匹配。'],
            ['t_i / trace_i', 't_i', 'trace 或空位标签，可与上方同标成分连线。'],
            ['=word=', '=read=_k', '删除线。旧写法 -word- 仍然兼容。'],
            ['*word*', '*where*_i', '斜体。'],
            ['=*word*=', '=*read*=_k', '斜体加删除线。'],
            ['@word@', '@he will go *where*@_i', '空心字。内部也可以继续使用斜体。'],
            ['"text with spaces"', '"(head)" / "New York"', '双引号把空格、圆括号或方括号保留为同一个文字标签。'],
            ['v0 / X0', 'v0|read_k, C0', '0 显示为上标。v0 会显示为斜体 v 加上标 0。'],
            ['X1, X2', 'DP1', '末尾非零数字显示为下标。'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', '希腊字母。也支持 theta、lambda、omega 等。'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '三角形/roof 节点。下方文字默认居中显示。'],
            ['@empty', '[TP @empty T\']', '生成一个空 terminal：保留树枝，但不显示文字。'],
            ['[] / [_i] / [_z2]', '[TP [] [_i] [_z2]]', '直接创建空节点；带可见或隐藏索引的空节点可作为移位线终点。'],
            ['@silent', '[C @silent] / [C|@silent]', '完全隐藏空标签，也不画通向空标签的竖线。'],
            ['点击 label', '-', 'label 可以整体移动。'],
            ['点击线条 / 树枝 / 三角形 / 移位线', '-', '可以手动调整；选中树枝后会出现“隐藏树枝”按钮，之后可在控制栏恢复。'],
            ['额外标注工具', '-', '可以添加自由注释和三点弧形曲线；双击注释可编辑文字。'],
        ], ['语法', '示例', '效果'], [
            '右侧控制栏可单独显示、隐藏每条移位线，并分别选择实线或虚线。',
            '预览区可用按钮或触控板双指缩放到 50%–250%；树图超出视口时会出现滚动条。',
            'Forest LaTeX 用于继续编辑结构；视觉 TikZ 会保留手动移动、隐藏树枝、逐条线型和颜色。',
            '游客可以生成和导出树图；登录用户会保存最近 20 条生成记录。',
        ]];
    }

    if ($lang === 'es') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', 'Nodo básico con corchetes. El primer elemento es la etiqueta del nodo y los siguientes son sus hijos.'],
            ['(...)', '(TP John (VP ...))', 'Los paréntesis también pueden representar la estructura del árbol y equivalen a los corchetes.'],
            ['A|B|C', 'T0|\\[+PST\\]|\\[+3SG\\]', 'Etiqueta de nodo en varias líneas. Los corchetes literales deben escribirse como \\[ y \\].'],
            ['_i, _j, _k', 'John_i', 'Subíndice visible en cursiva. Los índices coincidentes crean líneas de movimiento.'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', 'Índices de movimiento ocultos. z1, z2, etc. no se muestran, pero cada uno genera sus propias líneas de movimiento.'],
            ['t_i / trace_i', 't_i', 'Etiquetas de huella para el movimiento.'],
            ['=word=', '=read=_k', 'Texto tachado. También se admite la forma anterior -word-.'],
            ['*word*', '*where*_i', 'Texto en cursiva.'],
            ['=*word*=', '=*read*=_k', 'Texto en cursiva y tachado.'],
            ['@word@', '@he will go *where*@_i', 'Texto con contorno. Puede combinarse con cursiva en el interior.'],
            ['"text with spaces"', '"(head)" / "New York"', 'Las comillas dobles conservan espacios, paréntesis o corchetes como una sola etiqueta literal.'],
            ['v0 / X0', 'v0|read_k, C0', 'Superíndice 0. v0 se muestra como una v en cursiva seguida de un 0 en superíndice.'],
            ['X1, X2', 'DP1', 'Los dígitos finales distintos de cero se convierten en subíndices.'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', 'Letras griegas. También se admiten theta, lambda, omega, etc.'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', 'Nodo triangular. El texto se centra debajo del triángulo de forma predeterminada.'],
            ['@empty', '[TP @empty T\']', 'Crea un terminal vacío: conserva la rama, pero no muestra texto.'],
            ['[] / [_i] / [_z2]', '[TP [] [_i] [_z2]]', 'Crea nodos vacíos; los índices visibles u ocultos permiten recibir una línea de movimiento.'],
            ['@silent', '[C @silent] / [C|@silent]', 'Oculta por completo la etiqueta vacía y no dibuja ninguna rama hacia ella.'],
            ['Haz clic en una etiqueta', '-', 'Las etiquetas se pueden mover como una unidad.'],
            ['Haz clic en una línea, rama, triángulo o línea de movimiento', '-', 'Se puede ajustar manualmente. Al seleccionar una rama aparece el botón para ocultarla; después puede restaurarse en los controles.'],
            ['Herramientas de dibujo adicional', '-', 'Añade anotaciones libres y curvas de arco de tres puntos. Haz doble clic en una nota para editarla.'],
        ], ['Sintaxis', 'Ejemplo', 'Efecto'], [
            'Cada línea de movimiento puede mostrarse u ocultarse y configurarse como continua o discontinua por separado.',
            'Usa los botones o el gesto de pellizco del panel táctil para ampliar la vista previa del 50 % al 250 %; aparecerán barras de desplazamiento cuando sea necesario.',
            'Forest LaTeX permite seguir editando la estructura; TikZ visual conserva posiciones manuales, ramas ocultas, estilos y colores.',
            'Los invitados pueden generar y exportar árboles. Los usuarios registrados pueden guardar los 20 registros generados más recientes.',
        ]];
    }

    if ($lang === 'ja') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', '基本的な括弧ノードです。最初の項目がノードラベルで、後続の項目が子ノードです。'],
            ['(...)', '(TP John (VP ...))', '丸括弧も角括弧と同じようにツリー構造を表せます。'],
            ['A|B|C', 'T0|\\[+PST\\]|\\[+3SG\\]', '同じノード内で複数行に分けて表示します。文字としての角括弧は \\[ と \\] で入力します。'],
            ['_i, _j, _k', 'John_i', '斜体の下付き文字を表示します。同じ下付き文字は移動線を自動生成します。'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '非表示の移動インデックスです。z1、z2 などは表示されませんが、別々の移動線照合に使われます。'],
            ['t_i / trace_i', 't_i', '移動に対応する trace または空所ラベルです。'],
            ['=word=', '=read=_k', '取り消し線です。旧形式 -word- も引き続き使えます。'],
            ['*word*', '*where*_i', '斜体です。'],
            ['=*word*=', '=*read*=_k', '斜体に取り消し線を重ねます。'],
            ['@word@', '@he will go *where*@_i', '袋文字です。内部で斜体も併用できます。'],
            ['"text with spaces"', '"(head)" / "New York"', 'ダブルクォート内の空白、丸括弧、角括弧を一つの文字ラベルとして保持します。'],
            ['v0 / X0', 'v0|read_k, C0', '0 を上付きで表示します。v0 は斜体 v と上付き 0 で表示されます。'],
            ['X1, X2', 'DP1', '末尾の 0 以外の数字を下付きで表示します。'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', 'ギリシャ文字を表示します。theta、lambda、omega などにも対応します。'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '三角形 / roof ノードです。下の文字は既定で中央揃えになります。'],
            ['@empty', '[TP @empty T\']', '空の terminal を生成します。枝は残し、文字は表示しません。'],
            ['[] / [_i] / [_z2]', '[TP [] [_i] [_z2]]', '空ノードを作成します。表示・非表示インデックス付き空ノードは移動線の終点にできます。'],
            ['@silent', '[C @silent] / [C|@silent]', '空ラベルを完全に非表示にし、そこへの縦線も描きません。'],
            ['ラベルをクリック', '-', 'ラベルを全体として移動できます。'],
            ['線 / 枝 / 三角形 / 移動線をクリック', '-', '手動で調整できます。枝を選ぶと非表示ボタンが現れ、コントロール欄から復元できます。'],
            ['追加注記ツール', '-', '自由注記と三点の弧形曲線を追加できます。注記はダブルクリックで編集できます。'],
        ], ['構文', '例', '効果'], [
            '移動線ごとに表示・非表示を切り替え、実線または破線を個別に選択できます。',
            'ボタンまたはトラックパッドのピンチ操作で 50% から 250% まで拡大縮小できます。必要に応じてスクロールバーが表示されます。',
            'Forest LaTeX は構造編集向け、視覚 TikZ は手動配置、非表示の枝、線種、色を保持します。',
            'ゲストでもツリーの生成と書き出しができます。ログインしたユーザーは最近 20 件の生成履歴を保存できます。',
        ]];
    }

    if ($lang === 'ko') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', '기본 괄호 노드입니다. 첫 항목은 노드 라벨이고, 뒤의 항목들은 자식 노드입니다.'],
            ['(...)', '(TP John (VP ...))', '둥근 괄호도 대괄호와 같은 트리 구조를 나타낼 수 있습니다.'],
            ['A|B|C', 'T0|\\[+PST\\]|\\[+3SG\\]', '한 노드 안에서 여러 줄로 표시합니다. 문자 대괄호는 \\[ 및 \\]로 입력합니다.'],
            ['_i, _j, _k', 'John_i', '보이는 이탤릭 아래첨자를 표시합니다. 같은 아래첨자는 이동선을 자동 생성합니다.'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '숨김 이동 인덱스입니다. z1, z2 등은 표시되지 않지만 서로 다른 이동선 매칭에 사용됩니다.'],
            ['t_i / trace_i', 't_i', '이동에 대응하는 trace 또는 빈자리 라벨입니다.'],
            ['=word=', '=read=_k', '취소선입니다. 기존 -word- 형식도 계속 사용할 수 있습니다.'],
            ['*word*', '*where*_i', '이탤릭체입니다.'],
            ['=*word*=', '=*read*=_k', '이탤릭체에 취소선을 적용합니다.'],
            ['@word@', '@he will go *where*@_i', '윤곽 글자입니다. 내부에서 이탤릭체도 함께 사용할 수 있습니다.'],
            ['"text with spaces"', '"(head)" / "New York"', '큰따옴표 안의 공백, 둥근 괄호, 대괄호를 하나의 문자 라벨로 유지합니다.'],
            ['v0 / X0', 'v0|read_k, C0', '0을 위첨자로 표시합니다. v0는 이탤릭 v와 위첨자 0으로 표시됩니다.'],
            ['X1, X2', 'DP1', '끝의 0이 아닌 숫자는 아래첨자로 표시합니다.'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', '그리스 문자를 표시합니다. theta, lambda, omega 등도 지원합니다.'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '삼각형 / roof 노드입니다. 아래 텍스트는 기본적으로 가운데 정렬됩니다.'],
            ['@empty', '[TP @empty T\']', '빈 terminal을 만듭니다. 가지는 남기고 글자는 표시하지 않습니다.'],
            ['[] / [_i] / [_z2]', '[TP [] [_i] [_z2]]', '빈 노드를 만듭니다. 보이는/숨김 인덱스가 있는 빈 노드는 이동선의 도착점이 될 수 있습니다.'],
            ['@silent', '[C @silent] / [C|@silent]', '빈 라벨을 완전히 숨기고 그쪽으로 가는 세로선도 그리지 않습니다.'],
            ['라벨 클릭', '-', '라벨을 통째로 이동할 수 있습니다.'],
            ['선 / 가지 / 삼각형 / 이동선 클릭', '-', '손으로 조정할 수 있습니다. 가지를 선택하면 숨기기 버튼이 나타나며 제어 영역에서 복원할 수 있습니다.'],
            ['추가 주석 도구', '-', '자유 주석과 세 점짜리 호형 곡선을 추가할 수 있습니다. 주석은 더블클릭으로 편집할 수 있습니다.'],
        ], ['구문', '예시', '효과'], [
            '각 이동선을 개별적으로 표시하거나 숨기고 실선 또는 점선을 선택할 수 있습니다.',
            '버튼 또는 트랙패드 핀치로 50%부터 250%까지 확대/축소할 수 있으며 필요하면 스크롤바가 나타납니다.',
            'Forest LaTeX는 구조 편집용이며 시각 TikZ는 수동 배치, 숨긴 가지, 선 종류와 색상을 보존합니다.',
            '게스트도 트리를 생성하고 내보낼 수 있습니다. 로그인한 사용자는 최근 20개의 생성 기록을 저장할 수 있습니다.',
        ]];
    }

    return [[
        ['[XP child child]', '[TP John [T\' T VP]]', 'Basic bracketed node. The first item is the node label; following items are children.'],
        ['(...)', '(TP John (VP ...))', 'Parentheses can also express tree structure and work like square brackets.'],
        ['A|B|C', 'T0|\\[+PST\\]|\\[+3SG\\]', 'Multi-line node label. Literal square brackets must be entered as \\[ and \\].'],
        ['_i, _j, _k', 'John_i', 'Visible italic subscript. Matching indices create movement links.'],
        ['_z1, _z2', 'thought_z1 ... is_z2+phi', 'Hidden movement indices. z1, z2, etc. are not shown, but each creates its own movement links.'],
        ['t_i / trace_i', 't_i', 'Trace labels for movement.'],
        ['=word=', '=read=_k', 'Strikethrough. The older -word- form is still accepted.'],
        ['*word*', '*where*_i', 'Italic text.'],
        ['=*word*=', '=*read*=_k', 'Italic text with strikethrough.'],
        ['@word@', '@he will go *where*@_i', 'Outline text. Can be combined with italic text inside.'],
        ['"text with spaces"', '"(head)" / "New York"', 'Double quotes keep spaces, parentheses, or square brackets together as one literal label.'],
        ['v0 / X0', 'v0|read_k, C0', 'Superscript 0. v0 displays italic v plus superscript 0.'],
        ['X1, X2', 'DP1', 'Trailing non-zero digits become subscripts.'],
        ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', 'Greek letters. Also supports theta, lambda, omega, etc.'],
        ['[^TP words]', '[^TP @he will go *where*@_i]', 'Triangle / roof node. Text is centered under the triangle by default.'],
        ['@empty', '[TP @empty T\']', 'Creates an empty terminal: keeps the branch, but shows no text.'],
        ['[] / [_i] / [_z2]', '[TP [] [_i] [_z2]]', 'Creates empty nodes; visible or hidden indexed positions can receive a movement link.'],
        ['@silent', '[C @silent] / [C|@silent]', 'Hides the empty label completely and draws no branch to it.'],
        ['Click label', '-', 'Labels can be moved as a whole.'],
        ['Click line / branch / triangle / movement link', '-', 'Adjust it by hand. Selecting a branch reveals Hide branch; restore it later from the controls.'],
        ['Extra drawing tools', '-', 'Add free annotations and three-point arc curves. Double-click notes to edit text.'],
    ], ['Syntax', 'Example', 'Effect'], [
        'Each movement link can be shown or hidden and set to solid or dashed independently.',
        'Use the buttons or a trackpad pinch gesture to zoom from 50% to 250%; scrollbars appear when needed.',
        'Forest LaTeX stays structurally editable; visual TikZ preserves manual positions, hidden branches, per-link styles, and colors.',
        'Guests can generate and export trees. Signed-in users can save the latest 20 generated records.',
    ]];
}

function render_help_sections(array $rows, array $heads, array $notes): void
{
    ?>
    <section class="help-section">
        <h3><?= e(t('help_syntax')) ?></h3>
        <div class="help-table-wrap">
            <table class="help-table">
                <thead>
                    <tr>
                        <?php foreach ($heads as $head): ?>
                            <th><?= e($head) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as [$syntax, $example, $effect]): ?>
                        <tr>
                            <td><code><?= e($syntax) ?></code></td>
                            <td><code><?= e($example) ?></code></td>
                            <td><?= e($effect) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="help-section">
        <h3><?= e(t('help_examples')) ?></h3>
        <pre class="help-code">[CP Which-book_i [C' C0|did [TP John_j [T' T0|\[+PST\] [vP =John=_j [v' v0|read_k [VP =read=_k t_i]]]]]]]</pre>
        <pre class="help-code">[CP PRN|Where_i [C' C0|is_z2+phi [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|thought_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]</pre>
    </section>
    <section class="help-section">
        <h3><?= e(t('help_notes')) ?></h3>
        <ul class="help-list">
            <?php foreach ($notes as $note): ?>
                <li><?= e($note) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}

function render_help_dialog(): void
{
    [$rows, $heads, $notes] = help_content_data();
    ?>
    <dialog id="helpDialog" class="help-dialog">
        <div class="help-dialog-inner">
            <div class="help-header">
                <div>
                    <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                    <h2><?= e(t('help_title')) ?></h2>
                </div>
                <button type="button" id="helpClose" class="small-button"><?= e(t('help_close')) ?></button>
            </div>
            <?php render_help_sections($rows, $heads, $notes); ?>
        </div>
    </dialog>
    <?php
}

function render_feedback_button(): void
{
    ?>
    <a class="button ghost feedback-open" href="<?= e(page_url('feedback')) ?>"><?= e(t('feedback')) ?></a>
    <?php
}

function render_feedback_form(): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    $editorButtons = [
        ['bold', t('editor_bold')],
        ['italic', t('editor_italic')],
        ['link', t('editor_link')],
        ['quote', t('editor_quote')],
        ['code', t('editor_code')],
        ['list', t('editor_list')],
    ];
    ?>
    <form class="feedback-form" method="post" action="<?= e(page_url('feedback_submit')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="feedback_form_token" value="<?= e(issue_feedback_form_token()) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
        <div class="form-honeypot" aria-hidden="true">
            <label>Website<input name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <p class="feedback-author-note"><?= e(strtr(t('feedback_posting_as'), ['{name}' => (string) $user['name']])) ?></p>
        <p class="muted feedback-first-post-note"><?= e(t('feedback_first_post_notice')) ?></p>

        <label class="field">
            <span><?= e(t('feedback_message')) ?></span>
            <div class="feedback-editor-toolbar" aria-label="<?= e(t('feedback_format')) ?>">
                <?php foreach ($editorButtons as [$command, $label]): ?>
                    <button type="button" class="small-button" data-feedback-insert="<?= e($command) ?>"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
            <textarea name="message" class="feedback-message" data-feedback-message rows="8" maxlength="10000" placeholder="<?= e(t('feedback_message_placeholder')) ?>" required></textarea>
        </label>

        <label class="field">
            <span><?= e(t('feedback_attachment')) ?></span>
            <input name="attachment" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-feedback-attachment data-size-error="<?= e(t('feedback_error_file_size')) ?>">
            <small class="muted"><?= e(t('feedback_attachment_hint')) ?></small>
        </label>

        <button type="submit" class="button primary"><?= e(t('feedback_submit')) ?></button>
    </form>
    <?php
}

function render_feedback_page(): void
{
    $user = current_user();
    $pageSize = feedback_public_page_size();
    $total = public_feedback_messages_count($user ? (int) $user['id'] : null);
    $totalPages = max(1, (int) ceil($total / $pageSize));
    $page = min(max(1, (int) ($_GET['feedback_page'] ?? 1)), $totalPages);
    $messages = public_feedback_messages($pageSize, ($page - 1) * $pageSize, $user ? (int) $user['id'] : null);
    page_header(t('feedback_title'));
    ?>
    <main class="app-shell feedback-bbs-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                <h1><?= e(t('feedback_title')) ?></h1>
            </div>
            <nav class="topnav" aria-label="Feedback navigation">
                <?php render_language_picker('feedback'); ?>
                <a href="<?= e(page_url('workspace')) ?>"><?= e(t('workspace')) ?></a>
                <?php if (current_user()): ?>
                    <form method="post" action="index.php?action=logout">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="button ghost"><?= e(t('sign_out')) ?></button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(page_url('login')) ?>"><?= e(t('login')) ?></a>
                <?php endif; ?>
            </nav>
        </section>

        <?php render_flash(); ?>

        <section class="feedback-bbs-intro">
            <div>
                <p class="eyebrow"><?= e(t('feedback_public_board')) ?></p>
                <h2><?= e(t('feedback_board_heading')) ?></h2>
                <p><?= e(t('feedback_intro')) ?></p>
            </div>
            <?php if (!$user): ?>
                <div class="feedback-login-card">
                    <p><?= e(t('feedback_login_notice')) ?></p>
                    <div class="feedback-login-actions">
                        <a class="button primary" href="<?= e(page_url('login')) ?>"><?= e(t('login')) ?></a>
                        <a class="button ghost" href="<?= e(page_url('register')) ?>"><?= e(t('register')) ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($user): ?>
            <section class="feedback-composer" aria-labelledby="feedbackComposerTitle">
                <h2 id="feedbackComposerTitle"><?= e(t('feedback_new_topic')) ?></h2>
                <?php render_feedback_form(); ?>
            </section>
        <?php endif; ?>

        <section class="feedback-board" aria-labelledby="feedbackBoardTitle">
            <div class="feedback-board-heading">
                <div>
                    <h2 id="feedbackBoardTitle"><?= e(t('feedback_topics')) ?></h2>
                    <p class="muted"><?= e(strtr(t('feedback_topic_count'), ['{count}' => number_format($total)])) ?></p>
                </div>
                <?php render_feedback_page_size_selector($pageSize); ?>
            </div>

            <?php if (!$messages): ?>
                <div class="feedback-empty" role="status">
                    <h3><?= e(t('feedback_empty_title')) ?></h3>
                    <p><?= e(t('feedback_empty_copy')) ?></p>
                </div>
            <?php endif; ?>

            <div class="feedback-thread-list">
                <?php foreach ($messages as $message): ?>
                    <article class="feedback-thread <?= $message['status'] === 'pending' ? 'is-pending' : '' ?>">
                        <header class="feedback-thread-header">
                            <div>
                                <h3><?= e($message['public_name'] ?: t('feedback_user')) ?></h3>
                                <time datetime="<?= e((string) $message['created_at']) ?>"><?= e(feedback_public_time((string) $message['created_at'])) ?></time>
                            </div>
                            <?php if ($message['status'] === 'pending'): ?>
                                <span class="feedback-status pending"><?= e(t('feedback_pending')) ?></span>
                            <?php elseif (!empty($message['admin_reply'])): ?>
                                <span class="feedback-status answered"><?= e(t('feedback_answered')) ?></span>
                            <?php endif; ?>
                        </header>

                        <?php if ($message['status'] === 'pending'): ?>
                            <p class="feedback-pending-note"><?= e(t('feedback_pending_note')) ?></p>
                        <?php endif; ?>

                        <div class="feedback-thread-content user-content">
                            <?= feedback_render_markdown((string) $message['message']) ?>
                        </div>

                        <?php $attachmentHref = feedback_public_attachment_href($message['attachment_path'] ?? null); ?>
                        <?php if ($attachmentHref): ?>
                            <a class="feedback-attachment-preview" href="<?= e($attachmentHref) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= e($attachmentHref) ?>" alt="<?= e(t('feedback_attachment_preview')) ?>" loading="lazy">
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($message['edited_at'])): ?>
                            <p class="feedback-edit-note"><?= e(strtr(t('feedback_admin_edited'), ['{time}' => feedback_public_time((string) $message['edited_at'])])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($message['admin_reply'])): ?>
                            <section class="feedback-official-reply" aria-label="<?= e(t('feedback_admin_reply')) ?>">
                                <div class="feedback-reply-heading">
                                    <strong><?= e(t('feedback_admin_reply')) ?></strong>
                                    <time datetime="<?= e((string) $message['admin_reply_updated_at']) ?>"><?= e(feedback_public_time((string) $message['admin_reply_updated_at'])) ?></time>
                                </div>
                                <div class="feedback-thread-content">
                                    <?= feedback_render_markdown((string) $message['admin_reply']) ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php render_feedback_pagination($page, $totalPages, $pageSize); ?>
        </section>
    </main>
    <?php
    page_footer();
}

function feedback_public_page_size(): int
{
    $size = (int) ($_GET['per_page'] ?? 20);
    return in_array($size, [20, 40, 100], true) ? $size : 20;
}

function feedback_public_page_url(int $page, int $pageSize): string
{
    return 'index.php?' . http_build_query([
        'action' => 'feedback',
        'lang' => current_lang(),
        'feedback_page' => max(1, $page),
        'per_page' => $pageSize,
    ]);
}

function render_feedback_page_size_selector(int $pageSize): void
{
    ?>
    <form method="get" action="index.php" class="page-size-form feedback-page-size-form">
        <input type="hidden" name="action" value="feedback">
        <input type="hidden" name="lang" value="<?= e(current_lang()) ?>">
        <label>
            <span><?= e(t('feedback_per_page')) ?></span>
            <select name="per_page" onchange="this.form.submit()">
                <?php foreach ([20, 40, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php
}

function render_feedback_pagination(int $page, int $totalPages, int $pageSize): void
{
    if ($totalPages <= 1) {
        return;
    }
    ?>
    <nav class="pagination feedback-pagination" aria-label="<?= e(t('feedback_pagination')) ?>">
        <?php if ($page > 1): ?>
            <a href="<?= e(feedback_public_page_url($page - 1, $pageSize)) ?>"><?= e(t('feedback_previous')) ?></a>
        <?php endif; ?>
        <span><?= e(strtr(t('feedback_page_status'), ['{page}' => (string) $page, '{pages}' => (string) $totalPages])) ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?= e(feedback_public_page_url($page + 1, $pageSize)) ?>"><?= e(t('feedback_next')) ?></a>
        <?php endif; ?>
    </nav>
    <?php
}

function feedback_public_attachment_href(mixed $path): ?string
{
    if (!is_string($path) || !preg_match('~^uploads/feedback/[a-zA-Z0-9._-]+$~', $path)) {
        return null;
    }
    return is_file(__DIR__ . '/' . $path) ? $path : null;
}

function feedback_public_time(string $value): string
{
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Shanghai'))
            ->format('Y-m-d H:i');
    } catch (Throwable) {
        return $value;
    }
}

function render_syntax_reference_panel(): void
{
    $rows = [
        ['John_i', 'John<sub>i</sub>'],
        ['John_z1', 'John'],
        ['=read=', '<span class="syntax-strike">read</span>'],
        ['*where*', '<em>where</em>'],
        ['=*read*=', '<span class="syntax-strike"><em>read</em></span>'],
        ['@John@', '<span class="syntax-hollow">John</span>'],
        ['v0', '<em>v</em><sup>0</sup>'],
        ['C0|did', 'C<sup>0</sup><br>did'],
        ['T0|\\[+PST\\]', 'T<sup>0</sup><br>[+PST]'],
        ['(...)', '<span class="syntax-effect-note">= [ ... ]</span>'],
        ['alpha, beta, phi', 'α, β, φ'],
        ['[^TP words]', '<span class="syntax-roof">△TP</span><br>words'],
        ['"text with spaces"', 'New York'],
        ['@empty', '<span class="syntax-empty-demo"><span class="syntax-empty-stem"></span><span class="syntax-effect-note">' . e(t('syntax_effect_empty')) . '</span></span>'],
        ['[] / [_i] / [_z2]', '<span class="syntax-empty-demo"><span class="syntax-empty-stem"></span><span class="syntax-effect-note">' . e(t('syntax_effect_empty')) . '</span></span>'],
        ['@silent', '<span class="syntax-effect-note">' . e(t('syntax_effect_silent')) . '</span>'],
        [t('syntax_action_annotation'), '<span class="syntax-effect-note">(note)</span>'],
        [t('syntax_action_curve'), '<span class="syntax-curve-demo" aria-hidden="true"><svg viewBox="0 0 60 34" focusable="false"><path d="M5 29 Q18 4 55 7"></path></svg></span><span class="syntax-effect-note">' . e(t('syntax_effect_curve')) . '</span>'],
        [t('syntax_action_drag'), '<span class="syntax-effect-note">' . e(t('syntax_effect_drag')) . '</span>'],
    ];
    ?>
    <section class="syntax-reference-panel" aria-label="<?= e(t('syntax_reference_title')) ?>">
        <div class="section-title">
            <h2><?= e(t('syntax_reference_title')) ?></h2>
        </div>
        <div class="syntax-reference-grid" role="table">
            <div class="syntax-reference-row syntax-reference-head" role="row">
                <div role="columnheader"><?= e(t('syntax_example_col')) ?></div>
                <div role="columnheader"><?= e(t('syntax_effect_col')) ?></div>
            </div>
            <?php foreach ($rows as [$example, $effect]): ?>
                <div class="syntax-reference-row" role="row">
                    <div role="cell"><code><?= e($example) ?></code></div>
                    <div class="syntax-effect" role="cell"><?= $effect ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function render_help_page(): void
{
    [$rows, $heads, $notes] = help_content_data();
    page_header(t('help_title'));
    ?>
    <main class="app-shell about-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                <h1><?= e(t('help_title')) ?></h1>
            </div>
            <nav class="topnav" aria-label="Help navigation">
                <?php render_language_picker('help'); ?>
                <a href="<?= e(page_url('workspace')) ?>"><?= e(t('workspace')) ?></a>
                <?php if (current_user()): ?>
                    <form method="post" action="index.php?action=logout">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="button ghost"><?= e(t('sign_out')) ?></button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(page_url('login')) ?>"><?= e(t('login')) ?></a>
                <?php endif; ?>
            </nav>
        </section>

        <section class="about-panel guide-panel">
            <div class="about-hero-block">
                <p class="eyebrow"><?= e(t('syntax_tree_generator')) ?></p>
                <h2><?= e(t('help_title')) ?></h2>
            </div>
            <?php render_help_sections($rows, $heads, $notes); ?>
            <div class="about-actions">
                <a class="button primary" href="<?= e(page_url('workspace')) ?>"><?= e(t('start_creating')) ?></a>
                <a class="button ghost" href="<?= e(page_url()) ?>"><?= e(APP_BRAND) ?></a>
            </div>
        </section>
    </main>
    <?php
    page_footer();
}

function render_landing(string $action): void
{
    $authAction = $action === 'register' ? 'register' : 'login';
    [$syntaxRows, $syntaxHeads] = help_content_data();
    page_header(t('syntax_tree_generator'));
    ?>
    <main class="landing-shell">
        <header class="landing-nav">
            <a class="brand-mark" href="<?= e(page_url()) ?>" aria-label="<?= e(APP_BRAND) ?>">
                <img class="brand-logo-image" src="assets/landing-logo.png" width="520" height="173" alt="<?= e(APP_BRAND) ?>">
            </a>
            <nav class="landing-links" aria-label="Primary navigation">
                <a href="<?= e(page_url('help')) ?>"><?= e(t('nav_how')) ?></a>
                <?php render_feedback_button(); ?>
            </nav>
            <div class="landing-actions">
                <?php render_language_picker($authAction); ?>
                <a class="button ink" href="<?= e(page_url('workspace')) ?>"><?= e(t('start_creating')) ?></a>
            </div>
        </header>

        <?php render_flash(); ?>

        <section class="landing-update" aria-label="<?= e(t('latest_update_title')) ?>">
            <div>
                <p class="eyebrow"><?= e(t('latest_update_title')) ?></p>
                <p><?= e(strtr(t('latest_update_copy'), [
                    '{version}' => SYNTREE_VERSION,
                    '{date}' => SYNTREE_UPDATED_AT,
                ])) ?></p>
            </div>
            <ul>
                <li><?= e(t('latest_update_1')) ?></li>
                <li><?= e(t('latest_update_2')) ?></li>
                <li><?= e(t('latest_update_3')) ?></li>
                <li><?= e(t('latest_update_4')) ?></li>
                <li><?= e(t('latest_update_5')) ?></li>
            </ul>
            <details class="landing-syntax-details">
                <summary><?= e(t('release_syntax_title')) ?></summary>
                <div class="release-syntax-table-wrap">
                    <table class="release-syntax-table">
                        <thead>
                            <tr>
                                <?php foreach ($syntaxHeads as $head): ?>
                                    <th><?= e($head) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($syntaxRows as [$syntax, $example, $effect]): ?>
                                <tr>
                                    <td><code><?= e($syntax) ?></code></td>
                                    <td><code><?= e($example) ?></code></td>
                                    <td><?= e($effect) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <section class="landing-hero">
            <div class="hero-copy">
                <p class="hero-kicker"><?= e(t('hero_eyebrow')) ?></p>
                <h1><?= e(t('hero_title')) ?></h1>
                <p class="hero-lede"><?= e(t('hero_copy')) ?></p>
                <div class="hero-buttons">
                    <a class="button ink large" href="<?= e(page_url('workspace')) ?>"><?= e(t('start_creating')) ?></a>
                    <a class="button outline large" href="<?= e(page_url('workspace')) ?>"><?= e(t('continue_guest')) ?></a>
                </div>
                <div class="hero-notes" aria-label="Highlights">
                    <span><?= e(t('hero_note_1')) ?></span>
                    <span><?= e(t('hero_note_2')) ?></span>
                    <span><?= e(t('hero_note_3')) ?></span>
                </div>
            </div>

            <div class="hero-side">
                <div class="feature-visual" aria-hidden="true">
                    <img src="assets/landing-intro.png" width="900" height="563" alt="">
                </div>
                <?php render_auth_panel($authAction, 'landing'); ?>
            </div>
        </section>

        <section id="how" class="landing-band">
            <div>
                <strong>1</strong>
                <span><?= e(t('bracket_expression')) ?></span>
            </div>
            <div>
                <strong>2</strong>
                <span><?= e(t('preview')) ?></span>
            </div>
            <div>
                <strong>3</strong>
                <span>SVG / PNG / LaTeX</span>
            </div>
        </section>

        <?php render_support_footer('landing'); ?>
    </main>
    <script src="landing.js?v=<?= (int) @filemtime(__DIR__ . '/landing.js') ?>"></script>
    <?php
    page_footer();
}

function render_support_footer(string $context): void
{
    $compact = $context === 'workspace';
    ?>
    <section class="landing-about<?= $compact ? ' workspace-support' : '' ?>" aria-label="<?= e(t('about_title')) ?>">
        <dl class="landing-about-meta">
            <div>
                <dt><?= e(t('about_maker')) ?></dt>
                <dd>Merlin X. D. Yang</dd>
            </div>
            <div>
                <dt><?= e(t('about_version')) ?></dt>
                <dd><?= e(SYNTREE_VERSION) ?></dd>
            </div>
        </dl>

        <div class="landing-coffee">
            <div>
                <h2><?= e(t('about_coffee_title')) ?></h2>
                <p><?= e(t('about_coffee_copy')) ?></p>
            </div>
            <?php if (current_lang() === 'zh'): ?>
                <button type="button" class="coffee-link coffee-button" data-alipay-open aria-haspopup="dialog" aria-controls="alipayDialog">
                    <?= e(t('about_alipay_button')) ?>
                </button>
            <?php else: ?>
                <a class="coffee-link" href="https://paypal.me/yxd76" target="_blank" rel="noopener noreferrer">PayPal</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (current_lang() === 'zh'): ?>
        <dialog id="alipayDialog" class="alipay-dialog" aria-labelledby="alipayDialogTitle" aria-describedby="alipayDialogCopy">
            <div class="alipay-dialog-header">
                <div>
                    <p class="eyebrow"><?= e(t('about_coffee_title')) ?></p>
                    <h2 id="alipayDialogTitle"><?= e(t('about_alipay_qr_title')) ?></h2>
                </div>
                <button type="button" class="alipay-dialog-close" data-alipay-close aria-label="<?= e(t('dialog_close')) ?>">&times;</button>
            </div>
            <p id="alipayDialogCopy"><?= e(t('about_alipay_qr_copy')) ?></p>
            <img src="assets/alipay-coffee.jpeg" width="780" height="1008" alt="<?= e(t('about_alipay_qr_title')) ?>">
        </dialog>
    <?php endif; ?>
    <?php
}

function render_workspace(): void
{
    $user = current_user();
    $records = $user ? recent_tree_records((int) $user['id']) : [];
    $generationCount = generation_count();
    page_header(t('syntax_tree_generator'));
    ?>
    <main class="app-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                <h1><?= e(t('syntax_tree_generator')) ?></h1>
            </div>
            <p id="generationCounter" class="generation-counter" data-count="<?= $generationCount ?>" role="status" aria-live="polite">
                <?= e(strtr(t('trees_generated'), ['{count}' => number_format($generationCount)])) ?>
            </p>
            <nav class="topnav" aria-label="Account navigation">
                <?php render_language_picker('workspace'); ?>
                <button type="button" id="helpOpen" class="button ghost"><?= e(t('help')) ?></button>
                <?php render_feedback_button(); ?>
                <?php if (!$user): ?>
                    <span class="status-pill"><?= e(t('guest_mode')) ?></span>
                <?php else: ?>
                    <span class="status-pill signed-user-pill" title="<?= e($user['email']) ?>">
                        <?= e(t('signed_in')) ?>: <?= e($user['name']) ?>
                    </span>
                <?php endif; ?>
                <a href="<?= e(page_url('workspace')) ?>"><?= e(t('workspace')) ?></a>
                <?php if ($user): ?>
                    <form method="post" action="index.php?action=logout">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="button ghost"><?= e(t('sign_out')) ?></button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(page_url('login')) ?>"><?= e(t('login')) ?></a>
                <?php endif; ?>
            </nav>
        </section>
        <?php render_help_dialog(); ?>

        <?php render_flash(); ?>

        <section class="workspace workspace-tool <?= $user ? '' : 'guest-workspace' ?>">
            <section class="input-panel" aria-label="Bracket expression input">
                <div class="input-heading-row">
                    <label for="sourceInput"><?= e(t('bracket_expression')) ?></label>
                    <div class="input-history-controls">
                        <button type="button" id="undoInput" class="small-button" disabled><?= e(t('undo')) ?></button>
                        <button type="button" id="redoInput" class="small-button" disabled><?= e(t('redo')) ?></button>
                    </div>
                </div>
                <textarea id="sourceInput" spellcheck="false"></textarea>
                <p class="muted"><?= e(t('input_hint')) ?></p>
            </section>

            <aside class="control-panel" aria-label="Syntax tree controls">
                <div id="parseNotice" class="notice neutral"><?= e(t('enter_expression')) ?></div>
                <div class="settings-grid">
                    <label class="setting-row">
                        <span><?= e(t('branch_style')) ?></span>
                        <select id="branchStyle">
                            <option value="solid"><?= e(t('solid')) ?></option>
                            <option value="dashed"><?= e(t('dashed')) ?></option>
                        </select>
                    </label>
                    <label class="setting-row checkbox-row">
                        <span><?= e(t('uniform_branch_angles')) ?></span>
                        <input id="uniformBranchAngles" type="checkbox">
                    </label>
                    <label class="setting-row">
                        <span><?= e(t('movement_style')) ?></span>
                        <select id="movementStyle">
                            <option value="solid"><?= e(t('solid')) ?></option>
                            <option value="dashed"><?= e(t('dashed')) ?></option>
                        </select>
                    </label>
                    <label class="setting-row checkbox-row">
                        <span><?= e(t('show_movement')) ?></span>
                        <input id="showMovement" type="checkbox" checked>
                    </label>
                    <div id="movementToggles" class="movement-toggle-list"></div>
                </div>

                <div class="free-drawing-tools">
                    <p class="free-drawing-title"><?= e(t('free_drawing_tools')) ?></p>
                    <label class="setting-row annotation-text-row">
                        <span><?= e(t('annotation_prompt')) ?></span>
                        <input id="annotationTextInput" type="text" value="<?= e(t('default_annotation_text')) ?>" disabled>
                    </label>
                    <div class="setting-row annotation-color-row">
                        <span><?= e(t('annotation_color')) ?></span>
                        <div id="annotationColorPalette" class="annotation-color-palette" aria-label="<?= e(t('annotation_color')) ?>"></div>
                    </div>
                    <button type="button" id="addAnnotation" disabled><?= e(t('add_annotation')) ?></button>
                    <button type="button" id="addSegmentCurve" disabled><?= e(t('add_segment_curve')) ?></button>
                    <label class="setting-row">
                        <span><?= e(t('curve_style')) ?></span>
                        <select id="freeCurveStyle" disabled>
                            <option value="solid"><?= e(t('solid')) ?></option>
                            <option value="dashed"><?= e(t('dashed')) ?></option>
                        </select>
                    </label>
                    <label class="setting-row">
                        <span><?= e(t('curve_weight')) ?></span>
                        <select id="freeCurveWeight" disabled>
                            <option value="regular"><?= e(t('regular')) ?></option>
                            <option value="bold"><?= e(t('bold')) ?></option>
                        </select>
                    </label>
                    <button type="button" id="deleteSelectedExtra" disabled><?= e(t('delete_selected_extra')) ?></button>
                </div>

                <section id="hiddenBranchesPanel" class="hidden-branches-panel" hidden>
                    <div class="hidden-branches-heading">
                        <p><?= e(t('hidden_branches')) ?></p>
                        <button type="button" id="restoreAllBranches" class="small-button"><?= e(t('restore_all_branches')) ?></button>
                    </div>
                    <div id="hiddenBranchesList" class="hidden-branches-list"></div>
                </section>

                <div class="button-grid">
                    <button type="button" id="loadSample"><?= e(t('load_sample')) ?></button>
                    <button type="button" id="downloadSvg" disabled>SVG</button>
                    <button type="button" id="downloadWhitePng" disabled><?= e(t('white_png')) ?></button>
                    <button type="button" id="downloadPng" disabled><?= e(t('transparent_png')) ?></button>
                    <button type="button" id="downloadForestLatex" disabled><?= e(t('forest_latex')) ?></button>
                    <button type="button" id="downloadTikzLatex" disabled><?= e(t('tikz_latex')) ?></button>
                    <?php if ($user): ?>
                        <button type="button" id="saveHistory" class="button primary" disabled><?= e(t('save_account')) ?></button>
                    <?php endif; ?>
                </div>

                <?php if ($user): ?>
                    <details class="history-card compact-history">
                        <summary>
                            <span><?= e(t('recent_records')) ?></span>
                            <small><?= count($records) ?>/20</small>
                        </summary>
                        <?php if (!$records): ?>
                            <p class="muted"><?= e(t('no_saved')) ?></p>
                        <?php else: ?>
                            <div class="history-list">
                                <?php foreach ($records as $record): ?>
                                    <button type="button" class="history-item" data-source="<?= e($record['source']) ?>">
                                        <span><?= e(mb_strimwidth($record['source'], 0, 80, '...')) ?></span>
                                        <small><?= e($record['created_at']) ?> · <?= (int) $record['node_count'] ?> nodes</small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>

                <?php render_syntax_reference_panel(); ?>
            </aside>

            <section class="preview-panel" aria-label="Syntax tree preview">
                <div class="preview-toolbar">
                    <div>
                        <h2><?= e(t('preview')) ?></h2>
                        <p><?= e(t('preview_hint')) ?></p>
                    </div>
                    <div class="zoom-controls" aria-label="Preview zoom controls">
                        <button type="button" id="zoomOut" class="icon-button" aria-label="Zoom out">-</button>
                        <button type="button" id="zoomReset" class="zoom-value">100%</button>
                        <button type="button" id="zoomIn" class="icon-button" aria-label="Zoom in">+</button>
                    </div>
                </div>
                <div id="canvasWrap" class="canvas-wrap">
                    <div class="empty-state"><?= e(t('enter_expression')) ?></div>
                </div>
            </section>

        </section>
        <?php render_support_footer('workspace'); ?>
    </main>
    <script>
        window.SYNTREE = {
            csrf: <?= json_encode(csrf_token()) ?>,
            loggedIn: <?= $user ? 'true' : 'false' ?>,
            saveUrl: 'index.php?action=save_history',
            countUrl: 'index.php?action=record_generation',
            labels: <?= json_encode([
                'enterExpression' => t('enter_expression'),
                'typesettingPlaceholder' => t('typesetting_code'),
                'copy' => t('copy'),
                'copied' => t('copied'),
                'saveAccount' => t('save_account'),
                'saving' => t('saving'),
                'saved' => t('saved'),
                'annotationPrompt' => t('annotation_prompt'),
                'annotationColor' => t('annotation_color'),
                'defaultAnnotationText' => t('default_annotation_text'),
                'deleteExtra' => t('delete_extra_short'),
                'showMovementOne' => t('show_movement_one'),
                'movementStyle' => t('movement_style'),
                'solid' => t('solid'),
                'dashed' => t('dashed'),
                'emptyMovementPosition' => t('empty_movement_position'),
                'hideBranch' => t('hide_branch'),
                'restoreBranch' => t('restore_branch'),
                'foundStats' => t('found_stats'),
                'generatedTrees' => t('trees_generated'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="app.js?v=<?= (int) @filemtime(__DIR__ . '/app.js') ?>"></script>
    <script src="landing.js?v=<?= (int) @filemtime(__DIR__ . '/landing.js') ?>"></script>
    <?php
    page_footer();
}

function render_auth_panel(string $action, string $variant = 'compact'): void
{
    $providers = ['google' => oauth_provider_config('google'), 'github' => oauth_provider_config('github')];
    ?>
    <section class="auth-card <?= e('auth-' . $variant) ?>">
        <div class="auth-tabs" role="tablist">
            <a class="<?= $action !== 'register' ? 'active' : '' ?>" href="<?= e(page_url('login')) ?>"><?= e(t('login')) ?></a>
            <a class="<?= $action === 'register' ? 'active' : '' ?>" href="<?= e(page_url('register')) ?>"><?= e(t('register')) ?></a>
        </div>

        <?php if ($action === 'register'): ?>
            <form class="stack-form" method="post" action="<?= e(page_url('register')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                <label><span><?= e(t('name')) ?></span><input name="name" autocomplete="name" required></label>
                <p class="muted public-name-notice"><?= e(t('feedback_public_name_notice')) ?></p>
                <label><span><?= e(t('email')) ?></span><input type="email" name="email" autocomplete="email" required></label>
                <label><span><?= e(t('password')) ?></span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                <button type="submit" class="button primary"><?= e(t('create_account')) ?></button>
            </form>
        <?php else: ?>
            <form class="stack-form" method="post" action="<?= e(page_url('login')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label><span><?= e(t('email')) ?></span><input type="email" name="email" autocomplete="email" required></label>
                <label><span><?= e(t('password')) ?></span><input type="password" name="password" autocomplete="current-password" required></label>
                <button type="submit" class="button primary"><?= e(t('login')) ?></button>
            </form>
        <?php endif; ?>

        <a class="guest-link" href="<?= e(page_url('workspace')) ?>"><?= e(t('continue_guest')) ?></a>

        <div class="oauth-grid">
            <?php foreach ($providers as $key => $provider): ?>
                <?php if ($provider && $provider['configured']): ?>
                    <a class="oauth-button" href="index.php?action=oauth_start&provider=<?= e($key) ?>"><?= e(t('continue_with')) ?> <?= e($provider['label']) ?></a>
                <?php else: ?>
                    <button class="oauth-button" type="button" disabled><?= e($provider['label'] ?? $key) ?> <?= e(t('not_configured')) ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p class="muted"><?= e(t('oauth_hint')) ?></p>
    </section>
    <?php
}

function page_header(string $title): void
{
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    ?>
    <!doctype html>
    <html lang="<?= e(current_lang()) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php if (active_action() === 'feedback'): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
        <title><?= e($title) ?> · <?= e(APP_BRAND) ?></title>
        <link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
    </head>
    <body>
    <?php
}

function page_footer(): void
{
    ?>
    <script src="feedback.js?v=<?= (int) @filemtime(__DIR__ . '/feedback.js') ?>"></script>
    </body>
    </html>
    <?php
}

function render_flash(): void
{
    foreach (consume_flash() as $message) {
        $type = $message['type'] === 'success' ? 'success' : 'error';
        echo '<div class="flash ' . e($type) . '">' . e($message['message']) . '</div>';
    }
}
