# Merlin 의 Syntax Studio

[English](README.md) | [中文](README.zh-CN.md) | [日本語](README.ja.md)

Merlin 의 Syntax Studio 는 언어학 연구자, 통사론 강의자, 학생을 위한 무료 브라우저 기반 수형도 생성 도구입니다. 괄호 표기식을 읽어 깔끔한 통사 수형도로 변환하며, 이동선, 흔적/복사, 여러 줄 라벨, 그리스 문자, 삼각형/roof 표기, 논문과 수업 자료에 적합한 내보내기 형식을 지원합니다.

온라인 사용:

https://ailinguistics.cloud/mss

## 주요 기능

- 괄호 표기식을 입력하면 수형도를 즉시 미리보기.
- `_i`, `_j`, `_k` 등 보이는 아래첨자.
- `_z1`, `_z2` 등 숨김 이동 인덱스. 아래첨자는 표시하지 않고 이동선만 생성.
- `=word=` 로 취소선 표시.
- `*word*` 로 이탤릭 표시.
- `@word@` 로 윤곽 글자 표시.
- `alpha`, `beta`, `gamma`, `phi` 등 그리스 문자 단축 입력.
- `[^TP @he will go *where*@_i]` 같은 삼각형/roof 노드.
- 이동선별 표시/숨김, 색상 변경, 수동 조정.
- SVG, 흰 배경 PNG, 투명 PNG, 완전한 Forest LaTeX 코드 내보내기.
- 이메일, Google, GitHub 로그인.
- 로그인 사용자는 최근 20개 생성 기록 저장 가능.
- 가입 없이 사용하는 게스트 모드.
- 인터페이스 언어: 영어, 중국어, 일본어, 한국어.

## 예시

```text
[CP PRN|Where_i [C' C0|is_z2+phi|\[+EPP\]|\[+WH\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]
```

## 자주 쓰는 문법

| 문법 | 표시 |
|---|---|
| `John_i` | `John` 에 이탤릭 아래첨자 `i` |
| `John_z1` | `John`, 숨김 이동 인덱스 `z1` |
| `=read=` | `read` 에 취소선 |
| `*where*` | 이탤릭 `where` |
| `=*read*=` | 이탤릭 `read` 에 취소선 |
| `@John@` | 윤곽 글자 |
| `v0` | 이탤릭 `v` 에 위첨자 `0` |
| `C0\|did` | 여러 줄 노드 라벨 |
| `alpha`, `beta`, `gamma`, `phi` | 그리스 문자 |
| `[^TP words]` | 삼각형/roof 노드 |

## 로컬 실행

```bash
php -S 127.0.0.1:8082 -t syntree
```

열기:

```text
http://127.0.0.1:8082/index.php
```

첫 접속 시 SQLite 데이터베이스 `data/syntree.sqlite` 가 자동으로 생성됩니다.

기본 로컬 관리자 계정:

```text
Email: admin@syntree.local
Password: admin123456
```

로컬 개발 외의 환경에서 사용하기 전에 반드시 비밀번호를 변경하세요.

## OAuth 설정

Google Cloud Console 과 GitHub Developer Settings 에서 OAuth App 을 만듭니다.

로컬 개발용 callback URL:

```text
http://127.0.0.1:8082/index.php?action=oauth_callback&provider=google
http://127.0.0.1:8082/index.php?action=oauth_callback&provider=github
```

공개 배포용 callback URL:

```text
https://ailinguistics.cloud/mss/index.php?action=oauth_callback&provider=google
https://ailinguistics.cloud/mss/index.php?action=oauth_callback&provider=github
```

PHP 실행 전에 환경 변수를 설정합니다.

```bash
export SYNTREE_BASE_URL="https://ailinguistics.cloud/mss"
export SYNTREE_GOOGLE_CLIENT_ID="..."
export SYNTREE_GOOGLE_CLIENT_SECRET="..."
export SYNTREE_GITHUB_CLIENT_ID="..."
export SYNTREE_GITHUB_CLIENT_SECRET="..."
```

OAuth 제공자가 설정되지 않은 경우 해당 로그인 버튼은 비활성화됩니다.

## Buy Me a Coffee

이 도구가 수업, 연구, 글쓰기에 도움이 되었다면 커피 한 잔으로 지원할 수 있습니다.

https://paypal.me/yxd76

## 라이선스

MIT License.
