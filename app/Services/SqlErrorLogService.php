<?php

namespace App\Services;

use App\Models\LogSqlerror;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * SQL 에러 로깅 서비스
 * QueryException 발생 시 log_sqlerror 테이블에 기록
 */
class SqlErrorLogService
{
    /**
     * SQL 에러를 DB에 저장
     *
     * @param  QueryException  $exception  SQL 에러 Exception
     * @param  string|null  $scriptUrl  에러 발생 URL (자동으로 현재 요청 URL 사용)
     * @param  string  $sqlMode  SQL 모드 (SELECT, INSERT, UPDATE, DELETE 등)
     */
    public static function log(QueryException $exception, ?string $scriptUrl = null, string $sqlMode = 'UNKNOWN'): void
    {
        try {
            // 현재 요청 URL 가져오기
            if ($scriptUrl === null && request()) {
                $scriptUrl = request()->fullUrl() ?? request()->path();
            }

            // SQL 쿼리 추출 (바인딩 포함)
            $sqlTxt = self::getSqlWithBindings($exception);

            // DB에 저장
            LogSqlerror::create([
                'sql_txt' => $sqlTxt,
                'create_ts' => now(),
                'script_url' => $scriptUrl ?? 'CLI',
                'sqlmode' => strtoupper(substr($sqlMode, 0, 10)),
                'sqlerror' => self::formatError($exception),
            ]);
        } catch (\Exception $e) {
            // SQL 에러 로그 저장 실패 시 파일 로그에만 기록
            Log::error('Failed to save SQL error log to database', [
                'error' => $e->getMessage(),
                'original_sql_error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * SQL 쿼리와 바인딩을 결합하여 실제 실행된 쿼리 문자열 생성
     */
    private static function getSqlWithBindings(QueryException $exception): string
    {
        $sql = $exception->getSql();
        $bindings = $exception->getBindings();

        // 바인딩이 없으면 그대로 반환
        if (empty($bindings)) {
            return $sql;
        }

        // ? 플레이스홀더를 실제 값으로 치환
        foreach ($bindings as $binding) {
            // NULL 처리
            if ($binding === null) {
                $value = 'NULL';
            }
            // Boolean 처리
            elseif (is_bool($binding)) {
                $value = $binding ? 'TRUE' : 'FALSE';
            }
            // 숫자 처리
            elseif (is_numeric($binding)) {
                $value = $binding;
            }
            // 문자열 처리 (작은따옴표로 감싸기, 작은따옴표 이스케이프)
            else {
                $value = "'".str_replace("'", "''", $binding)."'";
            }

            // 첫 번째 ? 를 값으로 치환
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    /**
     * 에러 메시지 포맷팅
     */
    private static function formatError(QueryException $exception): string
    {
        $errorInfo = $exception->errorInfo ?? [];

        return sprintf(
            "SQLSTATE[%s]: %s\nCode: %s\nMessage: %s\nFile: %s:%d",
            $errorInfo[0] ?? 'Unknown',
            $exception->getMessage(),
            $errorInfo[1] ?? 'Unknown',
            $errorInfo[2] ?? 'No additional info',
            $exception->getFile(),
            $exception->getLine()
        );
    }

    /**
     * SQL 모드 자동 감지 (쿼리 문자열에서 추출)
     */
    public static function detectSqlMode(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (strpos($sql, 'SELECT') === 0) {
            return 'SELECT';
        } elseif (strpos($sql, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (strpos($sql, 'DELETE') === 0) {
            return 'DELETE';
        } elseif (strpos($sql, 'CREATE') === 0) {
            return 'CREATE';
        } elseif (strpos($sql, 'DROP') === 0) {
            return 'DROP';
        } elseif (strpos($sql, 'ALTER') === 0) {
            return 'ALTER';
        } else {
            return 'UNKNOWN';
        }
    }

    /**
     * QueryException인지 확인하고 SQL 에러 로그 저장 (편의 메서드)
     *
     * @param  \Exception|\Throwable  $exception
     */
    public static function logIfQueryException($exception, ?string $scriptUrl = null): void
    {
        if ($exception instanceof QueryException) {
            $sqlMode = self::detectSqlMode($exception->getSql());
            self::log($exception, $scriptUrl, $sqlMode);
        }
    }
}
