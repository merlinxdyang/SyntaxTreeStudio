<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

const SYNTREE_VERSION = '0.2.0';
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
    if ($action === 'admin_user') {
        handle_admin_user();
    }
}

if ($action === 'oauth_start') {
    oauth_start((string) ($_GET['provider'] ?? ''));
}
if ($action === 'oauth_callback') {
    oauth_callback((string) ($_GET['provider'] ?? ''));
}

if ($action === 'admin') {
    render_admin();
    exit;
}

if ($action === 'workspace') {
    render_workspace();
    exit;
}

if ($action === 'about') {
    render_about();
    exit;
}

if ($action === 'help') {
    render_help_page();
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

function handle_admin_user(): void
{
    $admin = require_admin();
    require_csrf();
    $mode = (string) ($_POST['mode'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);
    $target = $userId > 0 ? find_user_by_id($userId) : null;
    if (!$target) {
        flash('error', 'User not found.');
        redirect('index.php?action=admin');
    }

    if ($mode === 'toggle_active') {
        if ((int) $target['id'] === (int) $admin['id']) {
            flash('error', 'You cannot disable your own account.');
            redirect('index.php?action=admin');
        }
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && active_admin_count() <= 1) {
            flash('error', 'You cannot disable the last active admin.');
            redirect('index.php?action=admin');
        }
        db()->prepare('UPDATE users SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':id' => $userId]);
        flash('success', 'User status updated.');
        redirect('index.php?action=admin');
    }

    if ($mode === 'role') {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        if ($target['role'] === 'admin' && $role !== 'admin' && active_admin_count() <= 1) {
            flash('error', 'You cannot demote the last active admin.');
            redirect('index.php?action=admin');
        }
        db()->prepare('UPDATE users SET role = :role, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':role' => $role, ':id' => $userId]);
        flash('success', 'User role updated.');
        redirect('index.php?action=admin');
    }

    if ($mode === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('index.php?action=admin');
        }
        db()->prepare('UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
        flash('success', 'Password reset.');
        redirect('index.php?action=admin');
    }

    flash('error', 'Unknown admin action.');
    redirect('index.php?action=admin');
}

function active_admin_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}

function init_language(): void
{
    $allowed = ['en', 'zh', 'ja', 'ko'];
    $lang = $_GET['lang'] ?? null;
    if (is_string($lang) && in_array($lang, $allowed, true)) {
        $_SESSION['lang'] = $lang;
    }
    if (empty($_SESSION['lang'])) {
        $_SESSION['lang'] = 'en';
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
            'login' => 'Login',
            'register' => 'Register',
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'create_account' => 'Create account',
            'continue_with' => 'Continue with',
            'not_configured' => 'not configured',
            'oauth_hint' => 'Google and GitHub login are enabled after OAuth environment variables are configured.',
            'workspace' => 'Workspace',
            'about' => 'About',
            'about_title' => 'About ' . APP_BRAND,
            'about_maker' => 'Creator',
            'about_version' => 'Version',
            'about_intro' => APP_BRAND . ' is a lightweight syntax tree generator for linguistics teaching, analysis, and quick publication drafting.',
            'about_feature_title' => 'What it does',
            'about_feature_1' => 'Turns bracket expressions into readable phrase-structure trees in real time.',
            'about_feature_2' => 'Supports movement links, traces, triangle roofs, strikethrough, italics, outline text, subscripts, superscripts, and Greek letters.',
            'about_feature_3' => 'Exports SVG, transparent PNG, white-background PNG, and complete Forest LaTeX code.',
            'about_privacy_title' => 'Accounts and records',
            'about_privacy_copy' => 'You can use the workspace as a guest without saving anything. Signed-in users can keep the most recent 20 generated records for reuse.',
            'about_contact_title' => 'Contact',
            'about_contact_copy' => 'If you find any problem while using this tool, please contact me.',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => 'If this tool helps you, you can buy me a double-shot Americano.',
            'admin' => 'Admin',
            'sign_out' => 'Sign out',
            'bracket_expression' => 'Bracket expression',
            'input_hint' => 'Paste or type a bracket expression. The preview updates immediately.',
            'enter_expression' => 'Enter a bracket expression.',
            'branch_style' => 'Branch style',
            'movement_style' => 'Movement style',
            'show_movement' => 'Show movement links',
            'solid' => 'Solid',
            'dashed' => 'Dashed',
            'load_sample' => 'Load sample',
            'white_png' => 'White PNG',
            'transparent_png' => 'Transparent PNG',
            'latex' => 'LaTeX',
            'save_account' => 'Save to account',
            'typesetting_code' => 'LaTeX Code',
            'syntax_reference_title' => 'Syntax Reference',
            'syntax_example_col' => 'Syntax example',
            'syntax_effect_col' => 'Display effect',
            'copy' => 'Copy',
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
            'login' => '登入',
            'register' => '注册',
            'name' => '姓名',
            'email' => '电子邮件',
            'password' => '密码',
            'create_account' => '创建账户',
            'continue_with' => '通过登录',
            'not_configured' => '未配置',
            'oauth_hint' => '配置 OAuth 环境变量后，可启用 Google 和 GitHub 登录。',
            'workspace' => '工作台',
            'about' => '关于',
            'about_title' => '关于 ' . APP_BRAND,
            'about_maker' => '制作人',
            'about_version' => '版本号',
            'about_intro' => APP_BRAND . ' 是一个轻量级句法树/树形图生成工具，面向语言学教学、句法分析、课堂展示和论文写作草稿。',
            'about_feature_title' => '主要功能',
            'about_feature_1' => '把括号表达式实时转换为可读的短语结构树形图。',
            'about_feature_2' => '支持移位线、trace、三角形 roof、删除线、斜体、空心字、下标、上标和希腊字母。',
            'about_feature_3' => '支持导出 SVG、透明 PNG、白底 PNG，以及可以在 Overleaf 编译的完整 Forest LaTeX 代码。',
            'about_privacy_title' => '账户与记录',
            'about_privacy_copy' => '不注册也可以直接使用工作台，游客模式不会保存生成历史。登录用户可以保留最近 20 条生成记录，方便之后继续编辑或复用。',
            'about_contact_title' => '联系',
            'about_contact_copy' => '如果您在使用中发现任何问题，请跟我联系。',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => '如果您觉得这个工具对您有帮助，可以给我买一杯加浓美式。',
            'admin' => '后台',
            'sign_out' => '退出',
            'bracket_expression' => '括号表达式',
            'input_hint' => '粘贴或输入括号表达式，预览会立即更新。',
            'enter_expression' => '请输入括号表达式。',
            'branch_style' => '树枝线型',
            'movement_style' => '移位线型',
            'show_movement' => '显示移位线',
            'solid' => '实线',
            'dashed' => '虚线',
            'load_sample' => '载入示例',
            'white_png' => '白底 PNG',
            'transparent_png' => '透明 PNG',
            'latex' => 'LaTeX',
            'save_account' => '保存到账户',
            'typesetting_code' => 'LaTeX 代码',
            'syntax_reference_title' => '常见语法表',
            'syntax_example_col' => '语法示例',
            'syntax_effect_col' => '实际显示效果',
            'copy' => '复制',
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
            'login' => 'ログイン',
            'register' => '登録',
            'name' => '名前',
            'email' => 'メール',
            'password' => 'パスワード',
            'create_account' => 'アカウント作成',
            'continue_with' => 'で続行',
            'not_configured' => '未設定',
            'oauth_hint' => 'OAuth 環境変数を設定すると Google/GitHub ログインが使えます。',
            'workspace' => 'ワークスペース',
            'about' => '概要',
            'about_title' => APP_BRAND . ' について',
            'about_maker' => '制作者',
            'about_version' => 'バージョン',
            'about_intro' => APP_BRAND . ' は、言語学の教育、分析、発表資料、論文草稿向けの軽量な統語樹生成ツールです。',
            'about_feature_title' => '主な機能',
            'about_feature_1' => '括弧表現をリアルタイムで読みやすい句構造木に変換します。',
            'about_feature_2' => '移動線、trace、三角形 roof、取り消し線、斜体、袋文字、下付き、上付き、ギリシャ文字に対応します。',
            'about_feature_3' => 'SVG、透過 PNG、白背景 PNG、Overleaf で使える Forest LaTeX を書き出せます。',
            'about_privacy_title' => 'アカウントと履歴',
            'about_privacy_copy' => '登録なしでワークスペースを利用できます。ログインしたユーザーは最近 20 件の生成履歴を保存できます。',
            'about_contact_title' => '連絡先',
            'about_contact_copy' => '使用中に問題を見つけた場合は、こちらまでご連絡ください。',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => 'このツールが役に立った場合は、ダブルショットのアメリカーノをご支援いただけます。',
            'admin' => '管理',
            'sign_out' => 'ログアウト',
            'bracket_expression' => '括弧表現',
            'input_hint' => '括弧表現を貼り付けるか入力してください。プレビューは即時更新されます。',
            'enter_expression' => '括弧表現を入力してください。',
            'branch_style' => '枝の線種',
            'movement_style' => '移動線の線種',
            'show_movement' => '移動線を表示',
            'solid' => '実線',
            'dashed' => '破線',
            'load_sample' => 'サンプル',
            'white_png' => '白背景 PNG',
            'transparent_png' => '透過 PNG',
            'latex' => 'LaTeX',
            'save_account' => 'アカウントに保存',
            'typesetting_code' => 'LaTeX コード',
            'syntax_reference_title' => 'よく使う構文',
            'syntax_example_col' => '構文例',
            'syntax_effect_col' => '表示結果',
            'copy' => 'コピー',
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
            'login' => '로그인',
            'register' => '가입',
            'name' => '이름',
            'email' => '이메일',
            'password' => '비밀번호',
            'create_account' => '계정 만들기',
            'continue_with' => '로 계속',
            'not_configured' => '설정 안 됨',
            'oauth_hint' => 'OAuth 환경 변수를 설정하면 Google/GitHub 로그인을 사용할 수 있습니다.',
            'workspace' => '작업공간',
            'about' => '소개',
            'about_title' => APP_BRAND . ' 소개',
            'about_maker' => '제작자',
            'about_version' => '버전',
            'about_intro' => APP_BRAND . '는 언어학 교육, 분석, 발표 자료, 논문 초안을 위한 가벼운 수형도 생성 도구입니다.',
            'about_feature_title' => '주요 기능',
            'about_feature_1' => '괄호 표현식을 읽기 쉬운 구 구조 수형도로 실시간 변환합니다.',
            'about_feature_2' => '이동 링크, trace, 삼각형 roof, 취소선, 이탤릭체, 윤곽 글자, 아래첨자, 위첨자, 그리스 문자를 지원합니다.',
            'about_feature_3' => 'SVG, 투명 PNG, 흰 배경 PNG, Overleaf에서 컴파일 가능한 Forest LaTeX를 내보낼 수 있습니다.',
            'about_privacy_title' => '계정과 기록',
            'about_privacy_copy' => '가입 없이 작업공간을 사용할 수 있습니다. 로그인한 사용자는 최근 20개 생성 기록을 저장할 수 있습니다.',
            'about_contact_title' => '연락처',
            'about_contact_copy' => '사용 중 문제가 있으면 연락해 주세요.',
            'about_coffee_title' => 'Buy me a coffee',
            'about_coffee_copy' => '이 도구가 도움이 되었다면 더블샷 아메리카노 한 잔을 사 주실 수 있습니다.',
            'admin' => '관리',
            'sign_out' => '로그아웃',
            'bracket_expression' => '괄호 표현식',
            'input_hint' => '괄호 표현식을 붙여넣거나 입력하세요. 미리보기가 즉시 갱신됩니다.',
            'enter_expression' => '괄호 표현식을 입력하세요.',
            'branch_style' => '가지 선 스타일',
            'movement_style' => '이동 선 스타일',
            'show_movement' => '이동 링크 표시',
            'solid' => '실선',
            'dashed' => '점선',
            'load_sample' => '예시 불러오기',
            'white_png' => '흰 배경 PNG',
            'transparent_png' => '투명 PNG',
            'latex' => 'LaTeX',
            'save_account' => '계정에 저장',
            'typesetting_code' => 'LaTeX 코드',
            'syntax_reference_title' => '자주 쓰는 구문',
            'syntax_example_col' => '구문 예시',
            'syntax_effect_col' => '표시 결과',
            'copy' => '복사',
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
    $labels = ['en' => 'English', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어'];
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
            ['A|B|C', 'T0|[+PST]|[+3SG]', '同一个节点内换行显示。'],
            ['_i, _j, _k', 'John_i', '显示斜体下标。相同下标会自动生成移位线。'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '隐藏下标。z1、z2 等不显示，但会分别参与不同移位线匹配。'],
            ['t_i / trace_i', 't_i', 'trace 或空位标签，可与上方同标成分连线。'],
            ['=word=', '=read=_k', '删除线。旧写法 -word- 仍然兼容。'],
            ['*word*', '*where*_i', '斜体。'],
            ['=*word*=', '=*read*=_k', '斜体加删除线。'],
            ['@word@', '@he will go *where*@_i', '空心字。内部也可以继续使用斜体。'],
            ['v0 / X0', 'v0|read_k, C0', '0 显示为上标。v0 会显示为斜体 v 加上标 0。'],
            ['X1, X2', 'DP1', '末尾非零数字显示为下标。'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', '希腊字母。也支持 theta、lambda、omega 等。'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '三角形/roof 节点。下方文字从三角形左端开始对齐。'],
            ['点击树枝或移位线', '-', '显示可拖动端点。移位线有起点、终点和中间控制点。'],
        ], ['语法', '示例', '效果'], [
            '右侧控制栏会按移位线数量生成复选框，可以单独显示或隐藏某一条移位线。',
            '预览区可以缩放 50% 到 250%；树图超出视口时会出现上下和左右滚动条。',
            '可以导出 SVG、白底 PNG、透明 PNG 和可在 Overleaf 编译的完整 Forest LaTeX。',
            '游客可以生成和导出树图；登录用户会保存最近 20 条生成记录。',
        ]];
    }

    if ($lang === 'ja') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', '基本的な括弧ノードです。最初の項目がノードラベルで、後続の項目が子ノードです。'],
            ['A|B|C', 'T0|[+PST]|[+3SG]', '同じノード内で複数行に分けて表示します。'],
            ['_i, _j, _k', 'John_i', '斜体の下付き文字を表示します。同じ下付き文字は移動線を自動生成します。'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '非表示の移動インデックスです。z1、z2 などは表示されませんが、別々の移動線照合に使われます。'],
            ['t_i / trace_i', 't_i', '移動に対応する trace または空所ラベルです。'],
            ['=word=', '=read=_k', '取り消し線です。旧形式 -word- も引き続き使えます。'],
            ['*word*', '*where*_i', '斜体です。'],
            ['=*word*=', '=*read*=_k', '斜体に取り消し線を重ねます。'],
            ['@word@', '@he will go *where*@_i', '袋文字です。内部で斜体も併用できます。'],
            ['v0 / X0', 'v0|read_k, C0', '0 を上付きで表示します。v0 は斜体 v と上付き 0 で表示されます。'],
            ['X1, X2', 'DP1', '末尾の 0 以外の数字を下付きで表示します。'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', 'ギリシャ文字を表示します。theta、lambda、omega などにも対応します。'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '三角形 / roof ノードです。下の文字は三角形の左端から揃えて表示されます。'],
            ['枝または移動線をクリック', '-', 'ドラッグ可能な制御点を表示します。移動線には始点、終点、中間制御点があります。'],
        ], ['構文', '例', '効果'], [
            '右側のコントロール欄では、生成された移動線ごとにチェックボックスで表示・非表示を切り替えられます。',
            'プレビューは 50% から 250% まで拡大縮小できます。ツリーが表示領域を超えると、縦横のスクロールバーが表示されます。',
            'SVG、白背景 PNG、透過 PNG、Overleaf でコンパイル可能な完全な Forest LaTeX を書き出せます。',
            'ゲストでもツリーの生成と書き出しができます。ログインしたユーザーは最近 20 件の生成履歴を保存できます。',
        ]];
    }

    if ($lang === 'ko') {
        return [[
            ['[XP child child]', '[TP John [T\' T VP]]', '기본 괄호 노드입니다. 첫 항목은 노드 라벨이고, 뒤의 항목들은 자식 노드입니다.'],
            ['A|B|C', 'T0|[+PST]|[+3SG]', '한 노드 안에서 여러 줄로 표시합니다.'],
            ['_i, _j, _k', 'John_i', '보이는 이탤릭 아래첨자를 표시합니다. 같은 아래첨자는 이동선을 자동 생성합니다.'],
            ['_z1, _z2', 'thought_z1 ... is_z2+phi', '숨김 이동 인덱스입니다. z1, z2 등은 표시되지 않지만 서로 다른 이동선 매칭에 사용됩니다.'],
            ['t_i / trace_i', 't_i', '이동에 대응하는 trace 또는 빈자리 라벨입니다.'],
            ['=word=', '=read=_k', '취소선입니다. 기존 -word- 형식도 계속 사용할 수 있습니다.'],
            ['*word*', '*where*_i', '이탤릭체입니다.'],
            ['=*word*=', '=*read*=_k', '이탤릭체에 취소선을 적용합니다.'],
            ['@word@', '@he will go *where*@_i', '윤곽 글자입니다. 내부에서 이탤릭체도 함께 사용할 수 있습니다.'],
            ['v0 / X0', 'v0|read_k, C0', '0을 위첨자로 표시합니다. v0는 이탤릭 v와 위첨자 0으로 표시됩니다.'],
            ['X1, X2', 'DP1', '끝의 0이 아닌 숫자는 아래첨자로 표시합니다.'],
            ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', '그리스 문자를 표시합니다. theta, lambda, omega 등도 지원합니다.'],
            ['[^TP words]', '[^TP @he will go *where*@_i]', '삼각형 / roof 노드입니다. 아래 텍스트는 삼각형 왼쪽 끝에 맞춰 표시됩니다.'],
            ['가지 또는 이동선 클릭', '-', '드래그 가능한 조절점을 표시합니다. 이동선에는 시작점, 끝점, 중간 조절점이 있습니다.'],
        ], ['구문', '예시', '효과'], [
            '오른쪽 제어 영역에서 생성된 각 이동선을 체크박스로 표시하거나 숨길 수 있습니다.',
            '미리보기는 50%부터 250%까지 확대/축소할 수 있습니다. 트리가 표시 영역을 넘으면 가로와 세로 스크롤바가 나타납니다.',
            'SVG, 흰 배경 PNG, 투명 PNG, Overleaf에서 컴파일 가능한 완전한 Forest LaTeX를 내보낼 수 있습니다.',
            '게스트도 트리를 생성하고 내보낼 수 있습니다. 로그인한 사용자는 최근 20개의 생성 기록을 저장할 수 있습니다.',
        ]];
    }

    return [[
        ['[XP child child]', '[TP John [T\' T VP]]', 'Basic bracketed node. The first item is the node label; following items are children.'],
        ['A|B|C', 'T0|[+PST]|[+3SG]', 'Multi-line node label.'],
        ['_i, _j, _k', 'John_i', 'Visible italic subscript. Matching indices create movement links.'],
        ['_z1, _z2', 'thought_z1 ... is_z2+phi', 'Hidden movement indices. z1, z2, etc. are not shown, but each creates its own movement links.'],
        ['t_i / trace_i', 't_i', 'Trace labels for movement.'],
        ['=word=', '=read=_k', 'Strikethrough. The older -word- form is still accepted.'],
        ['*word*', '*where*_i', 'Italic text.'],
        ['=*word*=', '=*read*=_k', 'Italic text with strikethrough.'],
        ['@word@', '@he will go *where*@_i', 'Outline text. Can be combined with italic text inside.'],
        ['v0 / X0', 'v0|read_k, C0', 'Superscript 0. v0 displays italic v plus superscript 0.'],
        ['X1, X2', 'DP1', 'Trailing non-zero digits become subscripts.'],
        ['alpha, beta, gamma, phi', 'alpha_i, phi+thought_z1', 'Greek letters. Also supports theta, lambda, omega, etc.'],
        ['[^TP words]', '[^TP @he will go *where*@_i]', 'Triangle / roof node. Text starts at the left base of the triangle.'],
        ['Click branch / movement line', '-', 'Shows draggable handles for manual adjustment. Movement lines have start, end, and control handles.'],
    ], ['Syntax', 'Example', 'Effect'], [
        'Use the movement checkboxes to show or hide each generated movement link.',
        'Use the preview zoom controls to zoom from 50% to 250%; scrollbars appear when the tree exceeds the viewport.',
        'SVG, white PNG, transparent PNG, and complete Forest LaTeX export are available from the control panel.',
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
        <pre class="help-code">[CP Which-book_i [C' C0|did [TP John_j [T' T0|[+PST] [vP =John=_j [v' v0|read_k [VP =read=_k t_i]]]]]]]</pre>
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
        ['alpha, beta, phi', 'α, β, φ'],
        ['[^TP words]', '<span class="syntax-roof">△TP</span><br>words'],
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
                <a href="<?= e(page_url('about')) ?>"><?= e(t('about')) ?></a>
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
    page_header(t('syntax_tree_generator'));
    ?>
    <main class="landing-shell">
        <header class="landing-nav">
            <a class="brand-mark" href="<?= e(page_url()) ?>" aria-label="<?= e(APP_BRAND) ?>">
                <img class="brand-logo-image" src="assets/landing-logo.png" width="520" height="173" alt="<?= e(APP_BRAND) ?>">
            </a>
            <nav class="landing-links" aria-label="Primary navigation">
                <a href="<?= e(page_url('help')) ?>"><?= e(t('nav_how')) ?></a>
                <a href="<?= e(page_url('about')) ?>"><?= e(t('about')) ?></a>
            </nav>
            <div class="landing-actions">
                <?php render_language_picker($authAction); ?>
                <a class="button ink" href="<?= e(page_url('workspace')) ?>"><?= e(t('start_creating')) ?></a>
            </div>
        </header>

        <?php render_flash(); ?>

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
    </main>
    <?php
    page_footer();
}

function render_about(): void
{
    page_header(t('about_title'));
    ?>
    <main class="app-shell about-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                <h1><?= e(t('about_title')) ?></h1>
            </div>
            <nav class="topnav" aria-label="About navigation">
                <?php render_language_picker('about'); ?>
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

        <section class="about-panel">
            <div class="about-hero-block">
                <p class="eyebrow"><?= e(t('syntax_tree_generator')) ?></p>
                <h2><?= e(APP_BRAND) ?></h2>
                <p><?= e(t('about_intro')) ?></p>
            </div>

            <dl class="about-meta">
                <div>
                    <dt><?= e(t('about_maker')) ?></dt>
                    <dd>Merlin X. D. Yang</dd>
                </div>
                <div>
                    <dt><?= e(t('about_version')) ?></dt>
                    <dd><?= e(SYNTREE_VERSION) ?></dd>
                </div>
            </dl>

            <section class="about-copy">
                <h3><?= e(t('about_feature_title')) ?></h3>
                <ul>
                    <li><?= e(t('about_feature_1')) ?></li>
                    <li><?= e(t('about_feature_2')) ?></li>
                    <li><?= e(t('about_feature_3')) ?></li>
                </ul>
            </section>

            <section class="about-copy">
                <h3><?= e(t('about_privacy_title')) ?></h3>
                <p><?= e(t('about_privacy_copy')) ?></p>
            </section>

            <section class="about-copy">
                <h3><?= e(t('about_contact_title')) ?></h3>
                <p><?= e(t('about_contact_copy')) ?> <a class="text-link" href="mailto:xdyang@zjut.edu.cn">xdyang[AT]zjut.edu.cn</a></p>
            </section>

            <section class="about-copy coffee-section">
                <h3><?= e(t('about_coffee_title')) ?></h3>
                <p><?= e(t('about_coffee_copy')) ?></p>
                <?php if (current_lang() === 'zh'): ?>
                    <img class="coffee-qr" src="assets/alipay-coffee.jpeg" alt="Alipay QR code for Merlin X. D. Yang">
                <?php else: ?>
                    <a class="coffee-link" href="https://paypal.me/yxd76" target="_blank" rel="noopener noreferrer">paypal.me/yxd76</a>
                <?php endif; ?>
            </section>

            <div class="about-actions">
                <a class="button primary" href="<?= e(page_url('workspace')) ?>"><?= e(t('start_creating')) ?></a>
                <a class="button ghost" href="<?= e(page_url()) ?>"><?= e(APP_BRAND) ?></a>
            </div>
        </section>
    </main>
    <?php
    page_footer();
}

function render_workspace(): void
{
    $user = current_user();
    $records = $user ? recent_tree_records((int) $user['id']) : [];
    page_header(t('syntax_tree_generator'));
    ?>
    <main class="app-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?></p>
                <h1><?= e(t('syntax_tree_generator')) ?></h1>
            </div>
            <nav class="topnav" aria-label="Account navigation">
                <?php render_language_picker('workspace'); ?>
                <button type="button" id="helpOpen" class="button ghost"><?= e(t('help')) ?></button>
                <a href="<?= e(page_url('about')) ?>"><?= e(t('about')) ?></a>
                <?php if (!$user): ?>
                    <span class="status-pill"><?= e(t('guest_mode')) ?></span>
                <?php else: ?>
                    <span class="status-pill signed-user-pill" title="<?= e($user['email']) ?>">
                        <?= e(t('signed_in')) ?>: <?= e($user['name']) ?>
                    </span>
                <?php endif; ?>
                <a href="<?= e(page_url('workspace')) ?>"><?= e(t('workspace')) ?></a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <a href="index.php?action=admin"><?= e(t('admin')) ?></a>
                <?php endif; ?>
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
                <label class="field">
                    <span><?= e(t('bracket_expression')) ?></span>
                    <textarea id="sourceInput" spellcheck="false"></textarea>
                </label>
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

                <div class="button-grid">
                    <button type="button" id="loadSample"><?= e(t('load_sample')) ?></button>
                    <button type="button" id="downloadSvg" disabled>SVG</button>
                    <button type="button" id="downloadWhitePng" disabled><?= e(t('white_png')) ?></button>
                    <button type="button" id="downloadPng" disabled><?= e(t('transparent_png')) ?></button>
                    <button type="button" id="downloadLatex" disabled><?= e(t('latex')) ?></button>
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
    </main>
    <script>
        window.SYNTREE = {
            csrf: <?= json_encode(csrf_token()) ?>,
            loggedIn: <?= $user ? 'true' : 'false' ?>,
            saveUrl: 'index.php?action=save_history',
            labels: <?= json_encode([
                'enterExpression' => t('enter_expression'),
                'typesettingPlaceholder' => t('typesetting_code'),
                'copy' => t('copy'),
                'copied' => current_lang() === 'en' ? 'Copied' : (current_lang() === 'zh' ? '已复制' : (current_lang() === 'ja' ? 'コピー済み' : '복사됨')),
                'saveAccount' => t('save_account'),
                'saving' => current_lang() === 'en' ? 'Saving...' : (current_lang() === 'zh' ? '保存中...' : (current_lang() === 'ja' ? '保存中...' : '저장 중...')),
                'saved' => current_lang() === 'en' ? 'Saved' : (current_lang() === 'zh' ? '已保存' : (current_lang() === 'ja' ? '保存済み' : '저장됨')),
                'showMovementOne' => current_lang() === 'en'
                    ? 'Show movement link ({label})'
                    : (current_lang() === 'zh'
                        ? '显示移位线（{label}）'
                        : (current_lang() === 'ja'
                            ? '移動線を表示（{label}）'
                            : '이동 링크 표시({label})')),
                'foundStats' => current_lang() === 'en'
                    ? 'Found {nodes} nodes and {links} movement links.'
                    : (current_lang() === 'zh'
                        ? '找到 {nodes} 个节点和 {links} 条移位线。'
                        : (current_lang() === 'ja'
                            ? '{nodes} 個のノードと {links} 本の移動リンクを検出しました。'
                            : '{nodes}개 노드와 {links}개 이동 링크를 찾았습니다.')),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="app.js?v=<?= (int) @filemtime(__DIR__ . '/app.js') ?>"></script>
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
                <label><span><?= e(t('name')) ?></span><input name="name" autocomplete="name" required></label>
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

function render_admin(): void
{
    $admin = require_admin();
    $stats = [
        'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'active' => (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
        'records' => (int) db()->query('SELECT COUNT(*) FROM tree_records')->fetchColumn(),
        'logins' => (int) db()->query("SELECT COUNT(*) FROM login_audit WHERE status = 'success'")->fetchColumn(),
    ];
    $users = db()->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC, id DESC')->fetchAll();
    $audits = db()->query('SELECT email, provider, status, ip_address, created_at FROM login_audit ORDER BY created_at DESC, id DESC LIMIT 30')->fetchAll();

    page_header('Admin');
    ?>
    <main class="app-shell admin-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_BRAND) ?> Admin</p>
                <h1>Users and Access</h1>
            </div>
            <nav class="topnav" aria-label="Admin navigation">
                <a href="<?= e(page_url('workspace')) ?>"><?= e(t('workspace')) ?></a>
                <form method="post" action="index.php?action=logout">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="button ghost">Sign out</button>
                </form>
            </nav>
        </section>

        <?php render_flash(); ?>

        <section class="stat-grid">
            <div><span>Total users</span><strong><?= $stats['users'] ?></strong></div>
            <div><span>Active users</span><strong><?= $stats['active'] ?></strong></div>
            <div><span>Saved trees</span><strong><?= $stats['records'] ?></strong></div>
            <div><span>Successful logins</span><strong><?= $stats['logins'] ?></strong></div>
        </section>

        <section class="admin-panel">
            <h2>User Management</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td>
                                <form method="post" action="index.php?action=admin_user" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="role">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <select name="role" onchange="this.form.submit()">
                                        <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                        <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                    </select>
                                </form>
                            </td>
                            <td><?= (int) $row['is_active'] === 1 ? 'active' : 'disabled' ?></td>
                            <td class="action-cell">
                                <form method="post" action="index.php?action=admin_user">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button"><?= (int) $row['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                                </form>
                                <form method="post" action="index.php?action=admin_user" class="reset-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <input name="password" type="password" minlength="8" placeholder="New password" required>
                                    <button type="submit" class="small-button">Reset</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel">
            <h2>Recent Login Activity</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Email</th><th>Provider</th><th>Status</th><th>IP</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($audits as $row): ?>
                        <tr>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['provider']) ?></td>
                            <td><?= e($row['status']) ?></td>
                            <td><?= e($row['ip_address']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php
    page_footer();
}

function page_header(string $title): void
{
    ?>
    <!doctype html>
    <html lang="<?= e(current_lang()) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · <?= e(APP_BRAND) ?></title>
        <link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
    </head>
    <body>
    <?php
}

function page_footer(): void
{
    ?>
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
