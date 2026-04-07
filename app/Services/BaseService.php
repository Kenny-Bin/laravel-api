<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

abstract class BaseService
{
    protected LoggerInterface $log;

    public function __construct()
    {

    }

    /**
     * 트랜잭션 내에서 콜백 실행
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function executeInTransaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (\Exception $e) {
            // 파일 로그만 기록 (SQL 로깅은 Controller에서 처리)
            $this->log->error(get_class($this).'::executeInTransaction - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Exception을 로깅하고, SQL 에러인 경우 DB에도 기록
     *
     * 사용법: catch 블록에서 호출
     * catch (\Exception $e) {
     *     $this->logException($e, 'SomeService::someMethod');
     *     throw $e;
     * }
     *
     * @param  \Exception|\Throwable  $exception
     * @param  string  $context  에러 발생 위치 (예: 'MenuService::getAllMenus')
     */
    protected function logException($exception, string $context = ''): void
    {
        // SQL 에러인 경우 DB에 로깅
        SqlErrorLogService::logIfQueryException($exception);

        // 파일 로그에 기록
        $this->log->error($context ? "[$context] Exception occurred" : 'Exception occurred', [
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
