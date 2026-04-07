<?php

if (!function_exists('api_success')) {
    /**
     * API 성공 응답 생성
     *
     * @param mixed $data 응답 데이터
     * @param string $message 성공 메시지
     * @return array
     */
    function api_success($data = [], string $message = ''): array
    {
        $response = [
            'status' => "ok",
            'data' => $data,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }
}

if (!function_exists('api_error')) {
    /**
     * API 에러 응답 생성
     *
     * @param string $message 에러 메시지
     * @param mixed $data 추가 데이터
     * @return array
     */
    function api_error($data = null): array
    {
        return [
            'status' => "fail",
            'data' => $data,
            'source' => 'api'
        ];
    }
}

if (!function_exists('throw_error_code')) {
    /**
     * 에러 코드를 포함한 Exception 던지기
     *
     * @param string $code 에러 코드 (예: 'RESERVATION_NOT_FOUND')
     * @throws \Exception
     */
    function throw_error_code(string $code): void
    {
        throw new \Exception(json_encode([
            'code' => $code
        ]));
    }
}
