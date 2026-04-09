# Laravel Admin API Server

## 개요

Laravel 12 기반 관리자 시스템 백엔드 API 서버입니다.

## 요구사항

### 시스템
- PHP >= 8.2
- PostgreSQL >= 13
- Composer

### PHP Extensions
```bash
php -m | grep -E "pdo_pgsql|openssl|mbstring|tokenizer|json|bcmath"
```

필요한 확장:
- pdo_pgsql
- openssl
- mbstring
- tokenizer
- json
- bcmath

---

## 환경 설정

### 1. 설치

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 2. .env 설정

#### 데이터베이스 (PostgreSQL)
```env
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
DB_SCHEMA=public
```

#### JWT 인증
```env
SECRET_KEY=your-aes-encryption-key
JWT_SECRET=your-jwt-secret-key
```

#### OpenAI API
```env
OPENAI_API_KEY=sk-...
```

#### DeepL API
```env
DEEPL_API_KEY=your-deepl-key
```

#### AWS S3
```env
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=ap-northeast-2
AWS_BUCKET=your-bucket-name
```

#### 로그 설정
```env
LOG_CHANNEL=daily
LOG_LEVEL=debug
```

### 3. 서버 실행

```bash
php artisan serve
```

---

## 표준 API 응답 형식

### 성공 응답

```json
{
  "status": "ok",
  "data": {
    "key": "value"
  },
  "message": "Success message (optional)"
}
```

**HTTP Status Code**: `200`

### 실패 응답

```json
{
  "status": "fail",
  "data": {
    "code": "ERROR_CODE",
    "message": "Error description"
  },
  "source": "api"
}
```

**HTTP Status Code**: `400` (클라이언트 에러) 또는 `500` (서버 에러)

### 주요 에러 코드

| 에러 코드 | 설명 |
|-----------|------|
| `AUTH_FAILED` | 인증 실패 (로그인 실패, 잘못된 비밀번호) |
| `DATABASE_ERROR` | 데이터베이스 오류 |
| `API_SERVER_ERROR` | 일반 서버 오류 |
| `VALIDATION_ERROR` | 입력값 검증 실패 |

### 응답 예시

#### 로그인 성공
```json
{
  "status": "ok",
  "data": {
    "user_email": "admin@example.com",
    "user_name": "관리자",
    "adm_seq": 1,
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### 로그인 실패
```json
{
  "status": "fail",
  "data": {
    "code": "AUTH_FAILED",
    "message": ""
  },
  "source": "api"
}
```
