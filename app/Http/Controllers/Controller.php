<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    public function __construct()
    {

    }
    /**
     * API 성공 응답 반환
     *
     * @param  mixed  $data  응답 데이터
     * @param  string  $message  성공 메시지 (선택)
     * @param  int  $status  HTTP 상태 코드
     */
    protected function successResponse($data = [], string $message = '', int $status = 200): JsonResponse
    {
        return response()->json(api_success($data, $message), $status);
    }

    /**
     * API 에러 응답 반환
     *
     * @param  mixed  $data  에러 데이터 (선택)
     * @param  int  $status  HTTP 상태 코드
     */
    protected function errorResponse($data = null, int $status = 500): JsonResponse
    {
        $response = api_error($data);

        return response()->json($response, $status);
    }

    /**
     * Service 로직을 실행하고 에러를 자동으로 처리
     * - Database/ORM 에러: DATABASE_ERROR
     * - Exception message가 에러코드 형식(대문자+언더스코어): 해당 코드를 error_code로 반환
     * - 일반 Exception: API_SERVER_ERROR
     *
     * @param  callable  $callback  Service 호출 콜백 함수
     */
    protected function handleServiceCall(callable $callback): JsonResponse
    {
        try {

            $result = $callback();

            // 외부 API 서비스가 이미 완성된 응답을 반환한 경우
            // status가 'ok' 또는 'fail'이고 data 키가 있는 경우만 API 응답으로 간주
            // FrontApiService, OtaApiService, GdsApiService 등은 외부 API 응답을 그대로 전달
            if (is_array($result)
                && isset($result['status'])
                && ($result['status'] === 'ok' || $result['status'] === 'fail')
                && isset($result['data'])) {
                $statusCode = $result['status'] === 'fail' ? 400 : 200;

                return response()->json($result, $statusCode);
            }

            // CRITICAL: result가 배열이고 'code'와 'message'를 가지고 있으면 에러로 처리
            if (is_array($result) && isset($result['code']) && isset($result['message'])) {
                $this->log->error('Service returned error object instead of exception', $result);

                return response()->json([
                    'status' => 'fail',
                    'data' => [
                        'code' => $result['code'],
                    ],
                    'source' => 'api',
                ], 400);
            }

            return $this->successResponse($result);

        } catch (\Illuminate\Database\QueryException $e) {

            // SQL 에러 DB에 로깅
            \App\Services\SqlErrorLogService::log($e);

            // Database/ORM 에러
            $this->log->error('Database error: '.$e->getMessage());

            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'DATABASE_ERROR',
                ],
                'source' => 'api',
            ], 500);

        } catch (\Exception $e) {

            $message = $e->getMessage();

            // 예외 정보 로그
            $this->log->error('Service exception caught', [
                'message' => $message,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // JSON 형태로 전달된 에러인지 확인
            $decoded = json_decode($message, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status'])) {
                // 외부 API 서비스가 이미 표준 형식으로 반환한 경우 (FrontApiService, GdsApiService 등)
                $statusCode = $decoded['status'] === 'fail' ? 400 : 200;

                return response()->json($decoded, $statusCode);
            }

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['code'])) {

                $errorData = [
                    'code' => $decoded['code'],
                ];

                // 외부 API 에러 메시지가 있으면 포함
                if (isset($decoded['message']) && ! empty($decoded['message'])) {
                    $errorData['message'] = $decoded['message'];
                }

                if (isset($decoded['status_code'])) {
                    $errorData['status_code'] = $decoded['status_code'];
                }

                return response()->json([
                    'status' => 'fail',
                    'data' => $errorData,
                    'source' => 'api',
                ], 400);
            }

            // 일반 비즈니스 로직 에러
            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'API_SERVER_ERROR',
                    'message' => $message,
                ],
                'source' => 'api',
            ], 400);

        }
    }

    /**
     * Route parameter ID 검증 (공통 메소드)
     *
     * @param  int  $min  최소값 (기본: 1)
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRouteId(int $id, int $min = 1): void
    {
        if ($id < $min) {
            throw new \InvalidArgumentException("ID must be greater than or equal to {$min}");
        }
    }

    /**
     * Exception을 로깅하고, SQL 에러인 경우 DB에도 기록
     *
     * 사용법: catch 블록에서 호출
     * catch (\Exception $e) {
     *     $this->logException($e, 'SomeController::someMethod');
     *     // ... 에러 응답 반환
     * }
     *
     * @param  \Exception|\Throwable  $exception
     * @param  string  $context  에러 발생 위치 (예: 'MenuController::index')
     */
    protected function logException($exception, string $context = ''): void
    {
        // SQL 에러인 경우 DB에 로깅
        \App\Services\SqlErrorLogService::logIfQueryException($exception);

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
