<?php

namespace App\Services\Contracts;

interface TranslationServiceInterface
{
    /**
     * ChatGPT를 사용한 번역
     *
     * @param  array  $languageCodes  번역 대상 언어 코드 배열
     * @param  string  $text  번역할 텍스트
     * @param  string  $orgLanguage  원본 언어 코드 (선택사항)
     * @return array 번역 결과 ['ORG_LANG_CODE' => '원본언어', 'KO' => '번역결과', ...]
     */
    public function translateWithChatGPT(array $languageCodes, string $text, string $orgLanguage = ''): array;

    /**
     * DeepL을 사용한 번역
     *
     * @param  array  $languageCodes  번역 대상 언어 코드 배열
     * @param  string  $text  번역할 텍스트
     * @param  string  $orgLanguage  원본 언어 코드 (선택사항)
     * @return array 번역 결과 ['ORG_LANG_CODE' => '원본언어', 'KO' => '번역결과', ...]
     */
    public function translateWithDeepL(array $languageCodes, string $text, string $orgLanguage = ''): array;

    /**
     * 번역 실행 (API 타입에 따라 자동 선택)
     *
     * @param  string  $type  번역 API 타입 ('ChatGPT' 또는 'DeepL')
     * @param  array  $languageCodes  번역 대상 언어 코드 배열
     * @param  string  $text  번역할 텍스트
     * @param  string  $orgLanguage  원본 언어 코드 (선택사항)
     * @return array 번역 결과
     */
    public function translate(string $type, array $languageCodes, string $text, string $orgLanguage = ''): array;
}
